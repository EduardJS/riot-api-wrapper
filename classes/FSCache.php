<?php
class FSCache {

	private $dir;

	public function __construct( $dir )
	{
		$this->dir = trim( $dir, '/\\') . '/';

		if ( !file_exists( $this->dir ) )
			mkdir( $this->dir, 0777, true );
	}

	public function has( $key )
	{
		return file_exists( $this->getPath( $key ) );
	}

	public function get( $key )
	{
		return $this->load( $key );
	}

	public function put( $key, $data )
	{
		file_put_contents( $this->getPath( $key ), json_encode( $data ) );
	}

	private function load( $key )
	{
		return json_decode( file_get_contents( $this->getPath( $key ) ) );
	}

	private function getPath( $key )
	{
		return $this->dir . md5( $key );
	}

}
