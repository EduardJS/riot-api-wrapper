<?php

class RiotApi {


	private $apiVersion = [
				'1.1' => 'https://{region}.api.pvp.net/api/lol/{region}/v1.1/',
				'1.2' => 'https://{region}.api.pvp.net/api/lol/{region}/v1.2/',
				'1.3' => 'https://{region}.api.pvp.net/api/lol/{region}/v1.3/',
				'1.4' => 'https://{region}.api.pvp.net/api/lol/{region}/v1.4/',
				'2.1' => 'https://{region}.api.pvp.net/api/lol/{region}/v2.1/',
				'2.2' => 'https://{region}.api.pvp.net/api/lol/{region}/v2.2/',
				'2.3' => "https://{region}.api.pvp.net/api/lol/{region}/v2.3/",
				'2.4' => "https://{region}.api.pvp.net/api/lol/{region}/v2.4/",
				'2.5' => "https://{region}.api.pvp.net/api/lol/{region}/v2.5/"
			],
			$cache,
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

	public function __construct( $key, $decode = true, CacheInterface $cache = null)
	{

		$this->shortLimitQueue = new SplQueue();
		$this->longLimitQueue = new SplQueue();

		$this->key = $key;
		$this->decode = $decode;
		$this->cache = $cache;

	}

	public function setRegion( $region )
	{
		$this->region = $region;
	}

	public function getMatch( $id, $params = [] )
	{
		$call = $this->apiVersion['2.2']  . 'match/' . $id;
		return $this->request( $call, $params );
	}

	public function getMatchHistory( $id, $params = [] )
	{
		return $this->request( $this->apiVersion['2.2']  . 'matchlist/by-summoner/' . $id, $params );
	}

	public function getGame( $id )
	{
		return $this->request( $this->apiVersion['1.3'] . 'game/by-summoner/' . $id . '/recent' );
	}

	public function getLeague( $id )
	{
		return $this->request( $this->apiVersion['2.5'] . 'league/by-summoner/' . $id ) ;
	}

	public function getStats( $id, $option = 'summary' )
	{
		return $this->request( $this->apiVersion['1.3'] . 'stats/by-summoner/' . $id . '/' . $option );
	}
	
	public function getSummonerId( $name )
	{

		$name = strtolower( $name );
		$summoner = $this->getSummonerByName( $name );

		if ( $this->decode )
		{
			$summoner = json_decode( $summoner, true );
			return $summoner[$name]['id'];
		}
		else
			return $summoner[$name]['id'];

	}		

	public function getSummoner ( $id, $option = null ) 
	{
		
		$call = 'summoner/' . $id;
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

		return $this->request( $this->apiVersion['1.4'] . $call );

	}

	public function getSummonerByName( $name )
	{
		return $this->request( $this->apiVersion['1.4'] . 'summoner/by-name/' . rawurlencode( $name ) );
	}

	public function getTeam( $id )
	{
		return $this->request( $this->apiVersion['2.3'] . 'team/by-summoner/' . $id );
	}

	private function updateLimitQueue( $queue, $interval, $callLimit  ){
		
		while( !$queue->isEmpty() )
		{
			
			$timeSinceOldest = time() - $queue->bottom();

			if( $timeSinceOldest > $interval )

				$queue->dequeue();
			
			elseif ($queue->count() >= $callLimit ) 
			{
				if ($timeSinceOldest < $interval) 
				{
					echo( "sleeping for".($interval - $timeSinceOldest + 1)." seconds\n" );
					sleep($interval - $timeSinceOldest);
				}
			}
			else
				break;
		}

		$queue->enqueue( time() );

	}

	private function request( $call, $params = [] )
	{

		$params['api_key'] = $this->key;

		$url = str_replace( '{region}', $this->region, $call ) . '?' . http_build_query( $params );

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
