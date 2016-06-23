<?php

class RiotApi {


	const API_URL_1_1 = 'https://{region}.api.pvp.net/api/lol/{region}/v1.1/';
	const API_URL_1_2 = 'https://{region}.api.pvp.net/api/lol/{region}/v1.2/';
	const API_URL_1_3 = 'https://{region}.api.pvp.net/api/lol/{region}/v1.3/';
	const API_URL_1_4 = 'https://{region}.api.pvp.net/api/lol/{region}/v1.4/';
	const API_URL_2_1 = 'https://{region}.api.pvp.net/api/lol/{region}/v2.1/';
	const API_URL_2_2 = 'https://{region}.api.pvp.net/api/lol/{region}/v2.2/';
	const API_URL_2_3 = "https://{region}.api.pvp.net/api/lol/{region}/v2.3/";
	const API_URL_2_4 = "https://{region}.api.pvp.net/api/lol/{region}/v2.4/";
	const API_URL_2_5 = "https://{region}.api.pvp.net/api/lol/{region}/v2.5/";

	const LONG_LIMIT_INTERVAL = 600;
	const RATE_LIMIT_LONG = 500;

	const SHORT_LIMIT_INTERVAL = 10;
	const RATE_LIMIT_SHORT = 10;

	const CACHE_LIFETIME_MINUTES = 60;

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

	public function __construct( $key, $decode = true, CacheInterface $cache = null)
	{

		$this->shortLimitQueue = new SplQueue();
		$this->longLimitQueue = new SplQueue();

		$this->key = $key;
		$this->decode = $decode;
		$this->cache = $cache;

	}

	public function getMatch($matchId, $timeLine=false) {
		$call = self::API_URL_2_2  . 'match/' . $matchId . ($timeLine ? '?includeTimeline=true' : '');
		return $this->request($call, $timeLine);
	}

	public function getMatchHistory( $id )
	{
		return $this->request( self::API_URL_2_2  . 'matchlist/by-summoner/' . $id );
	}

	public function getGame( $id )
	{

		return $this->request( self::API_URL_1_3 . 'game/by-summoner/' . $id . '/recent' );
	}

	public function getLeague( $id, $entry = null )
	{
		return $this->request( self::API_URL_2_5 . 'league/by-summoner/' . $id . '/' . $entry) ;
	}

	public function getStats( $id, $option = 'summary' )
	{
		return $this->request( self::API_URL_1_3 . 'stats/by-summoner/' . $id . '/' . $option );
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

		return $this->request( self::API_URL_1_4 . $call );

	}

	public function getSummonerByName( $name )
	{
		return $this->request( self::API_URL_1_4 . 'summoner/by-name/' . rawurlencode( $name ) );
	}

	public function getTeam( $id )
	{
		return $this->request( self::API_URL_2_3 . 'team/by-summoner/' . $id );
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

	private function request( $call, $otherQueries = false, $static = false ) {

		$url = $this->format_url( $call, $otherQueries );

		if( $this->cache !== null && $this->cache->has( $url ) )
			$result = $this->cache->get( $url );

		else {

			if ( !$static ) {
				$this->updateLimitQueue($this->longLimitQueue, self::LONG_LIMIT_INTERVAL, self::RATE_LIMIT_LONG);
				$this->updateLimitQueue($this->shortLimitQueue, self::SHORT_LIMIT_INTERVAL, self::RATE_LIMIT_SHORT);
			}

			$ch = curl_init( $url);
			curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, false);
			curl_setopt( $ch, CURLOPT_SSL_VERIFYHOST, false);			
			$result = curl_exec( $ch);
			$this->responseCode = curl_getinfo( $ch, CURLINFO_HTTP_CODE);
			curl_close( $ch);

			if( $this->responseCode == 200 ) {

				if( $this->cache !== null )
					$this->cache->put( $url, $result, self::CACHE_LIFETIME_MINUTES * 60 );
				
			} else
				throw new Exception( $this->errorCodes[ $this->responseCode ] );
		}

		$this->decode && $result = json_decode( $result, true );

		return $result;
	}

	private function format_url( $call, $otherQueries = false ){
		return str_replace( '{region}', $this->region, $call ) . ( $otherQueries ? '&' : '?' ) . 'api_key=' . $this->key;
	}

	public function setRegion($region) {
		$this->region = $region;
	}
}
