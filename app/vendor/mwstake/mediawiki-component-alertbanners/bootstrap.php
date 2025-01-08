<?php

if ( defined( 'MWSTAKE_MEDIAWIKI_COMPONENT_ALERTBANNERS_VERSION' ) ) {
	return;
}

define( 'MWSTAKE_MEDIAWIKI_COMPONENT_ALERTBANNERS_VERSION', '2.0.5' );

MWStake\MediaWiki\ComponentLoader\Bootstrapper::getInstance()
->register( 'alertbanners', static function () {
	$GLOBALS['mwsgAlertBannersProviderRegistry'] = [];

	$GLOBALS['mwsgAlertBannersProviderFactories'] = [
		'globalvars-config' => [
			'class' => "\\MWStake\\MediaWiki\\Component\\AlertBanners\\AlertProviderFactory\\GlobalVars",
			'services' => [ 'ObjectFactory' ]
		],
		'hook' => [
			'class' => "\\MWStake\\MediaWiki\\Component\\AlertBanners\\AlertProviderFactory\\Hook",
			'services' => [ 'ObjectFactory', 'HookContainer' ]
		]
	];

	$GLOBALS['wgHooks']['SiteNoticeAfter'][] =
		'MWStake\\MediaWiki\\Component\\AlertBanners\\Hook\\SiteNoticeAfter\\AddAlerts::callback';

	$GLOBALS['wgResourceModules']['mwstake.component.alertbanners'] = [
		'scripts' => [
			'resources/mwstake.components.alertbanners.js'
		],
		'localBasePath' => __DIR__
	];
} );
