<?php

class RiotAPI {

	private $cache,
		$region,
		$key,
		$responseCode,
		$errorCodes = [
			0   => 'NO_RESPONSE',
			400 => 'BAD_REQUEST',
			401 => 'UNAUTHORIZED',
			403 => 'ACCESS_DENIED',
			404 => 'NOT_FOUND',
			429 => 'RATE_LIMIT_EXCEEDED',
			500 => 'SERVER_ERROR',
			503 => 'UNAVAILABLE'
		];

	public function __construct( $key, $cache )
	{

		$this->shortLimitQueue = new SplQueue();
		$this->longLimitQueue = new SplQueue();

		$this->key = $key;
		$this->cache = $cache;

	}

	public function setRegion( $region )
	{
		$this->region = $region;
	}

	public function getMatch( $id, $params = [] )
	{
		return $this->request( 'v2.2', '/match/' . $id, $params );
	}

	public function getMatchHistory( $id, $params = [] )
	{
		return $this->request( 'v2.2', '/matchlist/by-summoner/'. $id, $params );
	}

	public function getGame( $id )
	{
		return $this->request( 'v1.3', '/game/by-summoner/'. $id .'/recent' );
	}

	public function getLeague( $id, $self = false )
	{
		$result = $this->request( 'v2.5', '/league/by-summoner/'. $id . ( $self ? '/entry' : '' ) )[ $id ][0];

		if ( $self )
			$result = [
				'tier' 		=> $result['tier'],
				'division' 	=> $result['entries'][0]['division'],
				'points' 	=> $result['entries'][0]['leaguePoints']
			];

		return $result;
	}

	public function getStats( $id, $option = 'summary' )
	{
		return $this->request( 'v1.3', '/stats/by-summoner/'. $id .'/'. $option );
	}
	
	public function getSummonerId( $name )
	{
		$name = strtolower( str_replace( ' ', '', $name ) );
		return $this->getSummonerByName( $name )[ $name ]['id'];
	}		

	public function getSummoner ( $id, $option = null ) 
	{
		
		$call = '/summoner/'. $id;
		switch ($option) 
		{
			case 'masteries':
				$call .= '/masteries';
				break;
			case 'runes':
				$call .= '/runes';
				break;
			case 'name':
				$call .= '/name';
				break;
			default:
				break;
		}

		return $this->request( 'v1.4', $call );

	}

	public function getSummonerByName( $name )
	{
		return $this->request( 'v1.4', '/summoner/by-name/'. rawurlencode( $name ) );
	}

	public function getTeam( $id )
	{
		return $this->request( 'v2.3', '/team/by-summoner/'. $id );
	}

	private function updateLimitQueue( $queue, $interval, $callLimit  )
	{
		
		while( !$queue->isEmpty() )
		{
			
			$timeSinceOldest = time() - $queue->bottom();

			if( $timeSinceOldest > $interval )

				$queue->dequeue();

			elseif ($queue->count() >= $callLimit ) 
			{
				if ( $timeSinceOldest < $interval ) 
					sleep( $interval - $timeSinceOldest );
			}
			else
				break;
		}

		$queue->enqueue( time() );

	}

	/**
	 * Fetch missing assets
	 * $type [items, summoner_spells, champions]
	 */
	public static function assets( $type, $id )
	{

		$local 	= sprintf( '%s/%s/%d.png', ASSETS_PATH, $type, $id ); 
		$remote = sprintf( 'https://members.elo-boost.net/images/%s/%d.png', $type, $id );

		if ( file_exists( $local ) )
			return readfile( $local );
		else
		{

			if ( !getimagesize( $remote ) )
				return base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABAQMAAAAl21bKAAAAA1BMVEUAAACnej3aAAAAAXRSTlMAQObYZgAAAApJREFUCNdjYAAAAAIAAeIhvDMAAAAASUVORK5CYII=');

			$file = file_get_contents( $remote );

			$handle = fopen( $local, 'w' );
			fwrite( $handle, $file );
			fclose( $handle );

			return $file;
		}

	}

	private function request( $version, $path, $params = [] )
	{

		$params['api_key'] = $this->key;

		$url = sprintf( 'https://%s.api.pvp.net/api/lol/%s/%s', $this->region, $this->region, $version ) . $path . '?' . http_build_query( $params );

		$result = $this->cache->get( $url );

		// echo $path;
		// echo "\r\n";
		// print_r( $result );
		// echo "\r\n";

		if( $result['timestamp'] )
		{


			// is request for matchlist
			if ( strpos( $path, 'matchlist' ) !== false )
			{

				$params['beginTime'] = $result['timestamp'] * 1000;				
				// $this->request( $version, $path, $params );

			}

			$result = $result['data'];

		}
		else
		{

			// limit 500 reqs every 10 minutes
			$this->updateLimitQueue( $this->longLimitQueue, 600, 500 );
			// limit 10 reqs every 10 seconds
			$this->updateLimitQueue( $this->shortLimitQueue, 10, 10 );

			// init request
			$ch = curl_init( $url );
			curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1 );
			curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, false );
			curl_setopt( $ch, CURLOPT_SSL_VERIFYHOST, false );			
			$result = json_decode( curl_exec( $ch ), true );
			$this->code = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
			curl_close( $ch );

			if( $this->code == 200 )
			{

				if ( strpos( $path, 'matchlist' ) !== false )
				{
					$matches = [];

					foreach ( $result['matches'] as $match )
					{
						$game = $this->getMatch( $match['matchId'] );
							
						foreach ( $game['participants'] as $participant )
							if ( $participant['championId'] == $match['champion'] )
								$player = $participant;

						$matches[] = [
							'id' 		=> $match['matchId'],
							'champion' 	=> $match['champion'],
							'duration' 	=> $game['matchDuration'],
							'items' 	=> [
								$player['stats']['item0'], 
								$player['stats']['item1'], 
								$player['stats']['item2'],
								$player['stats']['item3'],
								$player['stats']['item4'],
								$player['stats']['item5'],
								$player['stats']['item6']
							],
							'kills' 	=> $player['stats']['kills'],
							'deaths' 	=> $player['stats']['deaths'],
							'assists' 	=> $player['stats']['assists'],
							'farm' 		=> $player['stats']['minionsKilled'],
							'spells' 	=> [ 
								$player['spell1Id'],
								$player['spell2Id']
							],
							'lane' 		=> $match['lane'],
							'timestamp' => round( $match['timestamp'] / 1000 ),
							'won' 		=> $player['stats']['winner']
						];
					}

					$result = $matches;
				}

				$this->cache->put( $url, $result, ( strpos( $path, 'matchlist' ) !== false ) ? 0 : 1800 );
			}
			else
				return [ 'error' => $this->errorCodes[ $this->code ] ];

		}

		return $result;

	}

}
