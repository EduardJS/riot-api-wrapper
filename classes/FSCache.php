<?php

class FSCache {

	private $dir;

	public function __construct( $dir )
	{
		$this->dir = trim( $dir, '/\\') . '/';

		if ( !file_exists( $this->dir ) )
			mkdir( $this->dir, 0777, true );
	}

	public function get( $key )
	{

		if ( !file_exists( $this->getPath( $key ) ) )
			return [];

		$entry = json_decode( file_get_contents( $this->getPath( $key ) ), true );
		
		if ( $entry === null ) 
			return [];

		if ( $entry['ttl'] != 0 && time() >= ( $entry['timestamp'] + $entry['ttl'] )  )
			return [];

		return $entry;

	}

	public function put( $key, $data, $ttl )
	{
		file_put_contents( $this->getPath( $key ), json_encode( [ 'path' => $key, 'timestamp' => time(), 'ttl' => $ttl, 'data' => $data ] ) );
	}

	private function getPath( $key )
	{
		return $this->dir . md5( $key );
	}

}
