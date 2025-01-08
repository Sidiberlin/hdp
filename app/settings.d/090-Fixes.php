<?php

define( 'DB_SLAVE', DB_REPLICA );

//ERM23110 - 'jquery.ui.*'-RL-Module-Shim
$GLOBALS['wgHooks']['ResourceLoaderRegisterModules'][] = function( ResourceLoader $resourceLoader ) {
	//HINT: https://github.com/SemanticMediaWiki/SemanticResultFormats/pull/621/files
	$missingRLModules = [
		'jquery.ui.core',
		'jquery.ui.core.styles',
		'jquery.ui.accordion',
		'jquery.ui.autocomplete',
		'jquery.ui.button',
		'jquery.ui.datepicker',
		'jquery.ui.dialog',
		'jquery.ui.draggable',
		'jquery.ui.droppable',
		'jquery.ui.menu',
		'jquery.ui.mouse',
		'jquery.ui.position',
		'jquery.ui.progressbar',
		'jquery.ui.resizable',
		'jquery.ui.selectable',
		'jquery.ui.slider',
		'jquery.ui.sortable',
		'jquery.ui.spinner',
		'jquery.ui.tabs',
		'jquery.ui.tooltip',
		'jquery.ui.widget'
	];
	foreach( $missingRLModules as $missingRLModule ) {
		if ( $resourceLoader->getModule( $missingRLModule ) === null ) {
			$resourceLoader->register( $missingRLModule, [
				'dependencies' => [
					'jquery.ui'
				]
			] );
		}
	}

	return true;
};

// ERM23160
$GLOBALS['smwgChangePropagationProtection'] = false;

// ERM21013
$GLOBALS['wgPageImagesLeadSectionOnly'] = false;
