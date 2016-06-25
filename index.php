<?php

	define( 'PATH', dirname( __FILE__ ) );
	define( 'ASSETS_PATH', PATH . '/assets' );

	spl_autoload_register( function( $class ){
		require PATH . '/classes/' . $class .'.php';
	});

	$data = [
		'summoner' 	=> 'summonerName',
		'apiKey' 	=> 'your-API-key',
		'server' 	=> 'eune'
	];

	$api = new RiotApi( $data['apiKey'], new FSCache('cache/') );
	$api->setRegion( $data['server'] );

	// get summoner id
	$summonerId = $api->getSummonerId( $data['summoner'] );

	// get current tier
	$league = $api->getLeague( $summonerId, true );

	// get match history
	$matches = $api->getMatchHistory( $summonerId, [ 
		'rankedQueues' 	=> 'TEAM_BUILDER_DRAFT_RANKED_5x5',
		'beginTime' 	=> 1464782400000,
		'beginIndex' 	=> 0,
		'endIndex' 		=> 10
	], true );
