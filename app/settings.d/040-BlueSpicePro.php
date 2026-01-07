<?php

unset( $GLOBALS['bsgOverridePermissionManagerAllowedPresets'] );

wfLoadExtension( 'BlueSpiceBookshelf' );
$GLOBALS['wgPDFCreatorCoverbackground'] = MW_INSTALL_PATH . '/extensions/BlueSpiceBookshelf/data/common/images/bs-cover.png';
wfLoadExtension( 'BlueSpiceCategoryCheck' );
wfLoadExtension( 'BlueSpiceCategoryManager' );
wfLoadExtension( 'BlueSpiceExpiry' );
wfLoadExtension( 'BlueSpiceExportTables' );
wfLoadExtension( 'BlueSpiceFilterableTables' );
wfLoadExtension( 'BlueSpiceMatomoConnector' );
wfLoadExtension( 'BlueSpiceNSFileRepoConnector' );
wfLoadExtension( 'BlueSpicePageFormsConnector' );
wfLoadExtension( 'BlueSpicePlayer' );
wfLoadExtension( 'BlueSpicePrivacy' );
wfLoadExtension( 'BlueSpiceProDistributionConnector' );
wfLoadExtension( 'BlueSpiceRating' );
wfLoadExtension( 'BlueSpiceReadConfirmation' );
wfLoadExtension( 'BlueSpiceReminder' );
wfLoadExtension( 'BlueSpiceSignHere' );
wfLoadExtension( 'BlueSpiceSMWConnector' );
wfLoadExtension( 'BlueSpiceUEModuleTable2Excel' );
wfLoadExtension( 'BlueSpiceWikiExplorer' );
