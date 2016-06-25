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
	$league = [
		'tier' 		=> $league['tier'],
		'division' 	=> $league['entries'][0]['division'],
		'points' 	=> $league['entries'][0]['leaguePoints']
	];
	

	// get match history
	$history = $api->getMatchHistory( $summonerId, [ 
		'rankedQueues' 	=> 'TEAM_BUILDER_DRAFT_RANKED_5x5',
		'beginTime' 	=> ( time() - ( 86400 * 7 ) ) * 1000, // last 7 days
		'beginIndex' 	=> 0,
		'endIndex' 	=> 10 // last 10 games
	]);
