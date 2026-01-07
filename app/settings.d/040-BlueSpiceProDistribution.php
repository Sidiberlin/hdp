<?php

// Additional settings that only apply to PRO etc.

// Especially in SSO environments, it is expected that mails are send without additonal
// authentication. Mail addresses are usually set by the SSO provider.
$GLOBALS['wgEmailAuthentication'] = false;

wfLoadExtension( 'AdhocTranslation' );

wfLoadExtension( 'AIEditingAssistant' );
$GLOBALS[ 'wgAIEditingAssistantActiveProvider' ] = 'open-ai';

wfLoadExtension( 'AtMentions' );
wfLoadExtension( 'Checklists' );

wfLoadExtension( 'CodeMirror' );
$GLOBALS[ 'wgDefaultUserOptions' ][ 'usecodemirror' ] = 1;
$GLOBALS[ 'wgCodeMirrorEnableBracketMatching' ] = true;
$GLOBALS[ 'wgCodeMirrorAccessibilityColors' ] = true;
$GLOBALS[ 'wgCodeMirrorLineNumberingNamespaces' ] = [ NS_TEMPLATE ];

wfLoadExtension( 'CognitiveProcessDesigner' );
$GLOBALS['wgVisualEditorAvailableNamespaces'][1530 /* NS_PROCESS */] = true;
// NS_PROCESS
$GLOBALS['wgContentNamespaces'][] = 1530;

wfLoadExtension( 'CollabPads' );
$GLOBALS[ 'wgCollabPadsBackendServiceURL' ] = $GLOBALS[ 'wgServer' ] . ( trim(  getenv( 'WIKI_BASE_PATH' ) ?: '/' ) ) . '_collabpads/';

wfLoadExtension( 'CommentStreams' );
$GLOBALS[ 'wgCommentStreamsTimeFormat' ] = null;
$GLOBALS[ 'bsgPermissionConfig' ][ 'cs-comment' ] = [
	'type' => 'namespace',
	'roles' => [ 'reader' ]
];
$GLOBALS[ 'bsgPermissionConfig' ][ 'cs-moderator-edit' ] = [
	'type' => 'namespace',
	'roles' => [ 'admin' ]
];
$GLOBALS[ 'bsgPermissionConfig' ][ 'cs-moderator-delete' ] = [
	'type' => 'namespace',
	'roles' => [ 'admin' ]
];
$GLOBALS[ 'wgCommentStreamsStoreModel' ] = 'talk-page';
$GLOBALS[ 'wgCommentStreamsNotifier' ] = 'notify-me';

wfLoadExtension( 'ContainerFilter' );
wfLoadExtension( 'ContentProvisioning' );
wfLoadExtension( 'ContentStabilization' );

wfLoadExtension( 'CreateUserPage' );
$GLOBALS[ 'wgCreateUserPage_PageContent' ] = '{{Userpage standard content}}';
$GLOBALS[ 'wgCreateUserPage_OnLogin' ] = false;

wfLoadExtension( 'DataTransfer' );
$GLOBALS[ 'wgGroupPermissions' ][ 'user' ][ 'datatransferimport' ] = true;
$GLOBALS[ 'bsgPermissionConfig' ][ 'datatransferimport' ] = [
	'type' => 'global',
	'roles' => [ 'editor' ]
];
$GLOBALS[ 'wgDataTransferViewXMLParseFields' ] = true;

wfLoadExtension( 'DateTimeTools' );

wfLoadExtension( 'DrawioEditor' );
$GLOBALS[ 'wgDrawioEditorImageType' ] = 'svg';

wfLoadExtension( 'EventBus' );
wfLoadExtension( 'ExternalData' );
wfLoadExtension( 'Forms' );
wfLoadExtension( 'HeaderFooter' );
wfLoadExtension( 'HeaderTabs' );
wfLoadExtension( 'ImportOfficeFiles' );
wfLoadExtension( 'LDAPProvider' );
wfLoadExtension( 'LDAPAuthentication2' );
wfLoadExtension( 'Lingo' );
wfLoadExtension( 'Maps' );
wfLoadExtension( 'MultimediaViewer' );

wfLoadExtension( 'NSFileRepo' );
$GLOBALS[ 'egNSFileRepoNamespaceThreshold' ] = 3000;
$GLOBALS[ 'wgUploadPath' ] = $GLOBALS[ 'wgScriptPath' ] . '/nsfr_img_auth.php';

wfLoadExtension( 'NumberHeadings' );

wfLoadExtension( 'OAuth' );
// `$GLOBALS[ 'wgOAuth2PrivateKey' ]` and `$GLOBALS[ 'wgOAuth2PublicKey' ]` are being set through Special:ConfigManager
$GLOBALS[ 'bsgPermissionConfig' ][ 'mwoauthproposeconsumer' ] = [
	'type' => 'global',
	'roles' => [ 'reader' ]
];
$GLOBALS[ 'bsgPermissionConfig' ][ 'mwoauthupdateownconsumer' ] = [
	'type' => 'global',
	'roles' => [ 'reader' ]
];
$GLOBALS[ 'bsgPermissionConfig' ][ 'mwoauthmanageconsumer' ] = [
	'type' => 'global',
	'roles' => [ 'reader' ]
];
$GLOBALS[ 'bsgPermissionConfig' ][ 'mwoauthsuppress' ] = [
	'type' => 'global',
	'roles' => [ 'reader' ]
];
$GLOBALS[ 'bsgPermissionConfig' ][ 'mwoauthviewsuppressed' ] = [
	'type' => 'global',
	'roles' => [ 'reader' ]
];
$GLOBALS[ 'bsgPermissionConfig' ][ 'mwoauthviewprivate' ] = [
	'type' => 'global',
	'roles' => [ 'reader' ]
];
$GLOBALS[ 'bsgPermissionConfig' ][ 'mwoauthmanagemygrants' ] = [
	'type' => 'global',
	'roles' => [ 'reader' ]
];

wfLoadExtension( 'OATHAuth' );
$GLOBALS['wgGroupPermissions']['user']['oathauth-enable'] = true;
$GLOBALS['bsgPermissionConfig']['oathauth-enable'] = [
	'type' => 'global',
	'roles' => [ 'reader' ]
];

wfLoadExtension( 'OpenIDConnect' );
$GLOBALS['wgOpenIDConnect_MigrateUsersByEmail'] = true;

wfLoadExtension( 'OpenLayers' );
wfLoadExtension( 'PageCheckout' );

wfLoadExtension( 'PageForms' );
$GLOBALS[ 'wgPageFormsMaxLocalAutocompleteValues' ] = 300;
$GLOBALS[ 'bsgPermissionConfig' ][ 'multipageedit' ] = [
	'type' => 'global',
	'roles' => [ 'editor' ]
];
$GLOBALS[ 'bsgPermissionConfig' ][ 'editrestrictedfields' ] = [
	'type' => 'global',
	'roles' => [ 'admin' ]
];

wfLoadExtension( 'PageImages' );
// ERM21013
$GLOBALS['wgPageImagesLeadSectionOnly'] = false;
$GLOBALS['wgPageImagesNamespaces'] = range(0, 5000);

wfLoadExtension( 'PDFembed' );
$GLOBALS['bsgPermissionConfig']['embed_pdf'] = [
	'type' => 'global',
	'roles' => [ 'reader' ]
];

wfLoadExtension( 'PluggableAuth' );
$GLOBALS[ 'wgPluggableAuth_EnableLocalLogin' ] = true;
$GLOBALS[ 'wgPluggableAuth_EnableFastLogout' ] = true;
$GLOBALS[ 'wgExtensionFunctions' ][] = static function() {
	// Only enable local properties if PluggableAuth is _not_ used
	$GLOBALS[ 'wgPluggableAuth_EnableLocalProperties' ]
		= empty( $GLOBALS[ 'wgPluggableAuth_Config' ] );
};

wfLoadExtension( 'Popups' );
// ERM18546: Parse whole page for preview, not only section = 0
$GLOBALS[ 'wgPopupsTextExtractsIntroOnly' ] = false;
$GLOBALS[ 'wgPopupsOptInDefaultState' ] = '1';

wfLoadExtension( 'PreToClip' );
wfLoadExtension( 'ReplaceText' );

wfLoadExtension( 'RevisionSlider' );
$GLOBALS['wgVisualEditorEnableDiffPage'] = true;
$GLOBALS['wgVisualEditorEnableDiffPageBetaFeature'] = true;

wfLoadExtension( 'Scribunto' );
$GLOBALS[ 'wgScribuntoDefaultEngine' ] = 'luastandalone';

wfLoadExtension( 'SectionAnchors' );
wfLoadExtension( 'SemanticCompoundQueries' );
wfLoadExtension( 'SemanticExtraSpecialProperties' );
$GLOBALS[ 'sespgUseFixedTables' ] = true;
$GLOBALS[ 'sespgExcludeBotEdits' ] = true;
$GLOBALS[ 'sespgEnabledPropertyList' ] = [
	'_EUSER', '_CUSER', '_REVID', '_PAGEID', '_VIEWS', '_NREV', '_TNREV',
	'_SUBP', '_USERREG', '_USEREDITCNT', '_EXIFDATA'
];
wfLoadExtension( 'SemanticMediaWiki' );
enableSemantics( 'localhost' );
// ERM23160
$GLOBALS[ 'smwgChangePropagationProtection' ] = false;
$GLOBALS[ 'smwgPageSpecialProperties' ] = array_merge(
	$GLOBALS[ 'smwgPageSpecialProperties' ],
	[ '_CDAT', '_LEDT', '_NEWP', '_MIME', '_MEDIA' ]
);
$GLOBALS[ 'smwgEnabledEditPageHelp' ] = false;
$GLOBALS[ 'smwgQMaxSize' ] = 100;
$GLOBALS[ 'maxRecursionDepth' ] = 4;

$GLOBALS[ 'smwgConfigFileDir' ] = "$IP/extensions/BlueSpiceFoundation/data";
if ( defined( 'BSROOTDIR' ) ) {
	$GLOBALS[ 'smwgConfigFileDir' ] = BSROOTDIR . "/data";
}
if( defined( 'BSDATADIR' ) ) {
	$GLOBALS[ 'smwgConfigFileDir' ] = BSDATADIR;
}

if(
	defined( 'FARMER_CALLED_INSTANCE_VAULT' )
) {
	$GLOBALS[ 'smwgConfigFileDir' ] = FARMER_CALLED_INSTANCE_VAULT . "/extensions/BlueSpiceFoundation/data";
}

$GLOBALS[ 'wgFooterIcons' ][ 'poweredby' ] += [
	'semanticmediawiki' => [
		'src' => $GLOBALS['wgScriptPath'] . '/extensions/BlueSpiceDistributionConnector/resources/images/footer/SemanticMediaWiki.png',
		'url' => 'https://www.semantic-mediawiki.org/wiki/Semantic_MediaWiki',
		'alt' => 'Powered by Semantic MediaWiki',
		'height' => '27',
		'width' => '149'
	]
];

wfLoadExtension( 'SemanticResultFormats' );
wfLoadExtension( 'SemanticScribunto' );
wfLoadExtension( 'SimpleTasks' );

wfLoadExtension( 'SimpleBlogPage' );
$GLOBALS['bsgPermissionConfig']['createblogpost'] = [
	'type' => 'global',
	'roles' => [ 'editor' ]
];

wfLoadExtension( 'SimpleSAMLphp' );
$GLOBALS['wgSimpleSAMLphp_EnableSingleLogout'] = true;

wfLoadExtension( 'SubPageList' );
wfLoadExtension( 'TabberNeue' );
wfLoadExtension( 'TableTools' );
wfLoadExtension( 'TextExtracts' );
wfLoadExtension( 'UnifiedTaskOverview' );
wfLoadExtension( 'VEForAll' );
wfLoadExtension( 'WebAuthn' );
wfLoadExtension( 'Widgets' );
$GLOBALS[ 'wgWidgetsCompileDir' ] = $GLOBALS[ 'wgCacheDirectory' ];
wfLoadExtension( 'Workflows');
