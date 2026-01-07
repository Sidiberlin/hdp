<?php

if( !defined( 'WIKI_FARMING' ) ) return;

wfLoadExtension( 'ContentTransfer' );
wfLoadExtension( 'MergeArticles' );

$GLOBALS['wgExtensionFunctions'][] = 'BlueSpice\\WikiFarm\\Setup::setupContentTransfer';