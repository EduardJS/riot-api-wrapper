<?php

	define( 'PATH', dirname( __FILE__ ) );
	header( 'Content-Type: application/json' );

	spl_autoload_register( function( $class ){
		require PATH . '/classes/' . $class .'.php';
	});

	$data = [
		'summoner' => 'yourSummonerName',
		'server'   => 'eune' // https://developer.riotgames.com/api/methods for implementation availability
	];

	$api = new RiotApi( 'YOUR-API-KEY' ); // `new FileSystemCache('cache/')` as second param for using the caching system
	$api->setRegion( 'eune' );

	$summoner = $api->getSummonerByName( $data['summoner'] )[ $data['summoner'] ];
	$history  = $api->getMatchHistory( $summoner['id'] );
