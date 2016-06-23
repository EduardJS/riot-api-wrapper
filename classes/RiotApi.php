<?php

class RiotApi {

	private $cache,
			$region,
			$key,
			$responseCode,
			$errorCodes;

	public function __construct( $key, CacheInterface $cache = null)
	{

		$this->shortLimitQueue = new SplQueue();
		$this->longLimitQueue = new SplQueue();

		$this->key = $key;
		$this->cache = $cache;

		$this->errorCodes = [
			0   => 'NO_RESPONSE',
			400 => 'BAD_REQUEST',
			401 => 'UNAUTHORIZED',
			403 => 'ACCESS_DENIED',
			404 => 'NOT_FOUND',
			429 => 'RATE_LIMIT_EXCEEDED',
			500 => 'SERVER_ERROR',
			503 => 'UNAVAILABLE'
		];

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

	public function getLeague( $id )
	{
		return $this->request( 'v2.5', '/league/by-summoner/'. $id ) ;
	}

	public function getStats( $id, $option = 'summary' )
	{
		return $this->request( 'v1.3', '/stats/by-summoner/'. $id .'/'. $option );
	}
	
	public function getSummonerId( $name )
	{
		$name = strtolower( str_replace( ' ', '', $name ) );
		$summoner = $this->getSummonerByName( $name );
		$summoner = json_decode( $summoner, true );

		return $summoner[ $name ]['id'];
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

	private function updateLimitQueue( $queue, $interval, $callLimit  ){
		
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

	private function request( $version, $path, $params = [] )
	{

		$params['api_key'] = $this->key;
		$url = sprintf( 'https://%s.api.pvp.net/api/lol/%s/%s', $this->region, $this->region, $version ) . $path . '?' . http_build_query( $params );

		if( $this->cache !== null && $this->cache->has( $url ) )
			$result = $this->cache->get( $url );
		else {

			$this->updateLimitQueue( $this->longLimitQueue, 600, 500 );
			$this->updateLimitQueue( $this->shortLimitQueue, 10, 10 );


			$ch = curl_init( $url );
			curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1 );
			curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, false );
			curl_setopt( $ch, CURLOPT_SSL_VERIFYHOST, false );			
			$result = curl_exec( $ch );
			$this->code = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
			curl_close( $ch );

			if( $this->code == 200 ) {

				if( $this->cache !== null )
					$this->cache->put( $url, $result, 600 );
				
			} else
				return [ 'error': $this->errorCodes[ $this->code ] ];
		}

		$this->decode && $result = json_decode( $result, true );

		return $result;
	}

}
