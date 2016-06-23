<?php
	
	// remove these two in production enviroments
	ini_set( 'display_errors', 'On' );
	error_reporting( E_ALL );

	define( 'PATH', dirname( __FILE__ ) );
	header( 'Content-Type: application/json' );

	spl_autoload_register( function( $class ){
		require PATH . '/classes/' . $class .'.php';
	});

	$data = [
		'summoner' => 'summonerName',
		'server'   => 'eune'
	];

	$api = new RiotApi( 'your-api-key', new FileSystemCache('cache/') );
	$api->setRegion( $data['server'] );

	// get summoner id
	$summonerId = $api->getSummonerId( $data['summoner'] );

	// get match history
	$history = $api->getMatchHistory( $summonerId, [ 
		'rankedQueues' 	=> 'TEAM_BUILDER_DRAFT_RANKED_5x5', // get ranked games only
		'beginTime' 	=> 1464782400000 // June 1st 2016
	]);
