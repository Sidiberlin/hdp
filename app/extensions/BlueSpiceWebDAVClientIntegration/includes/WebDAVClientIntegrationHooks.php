<?php

use MediaWiki\MediaWikiServices;

class WebDAVClientIntegrationHooks {
	/**
	 * @param OutputPage $out
	 * @param Skin $skin
	 * @return boolean
	 */
	public static function onBeforePageDisplay($out, $skin) {

		$out->addModules('ext.bluespice.webDAVClientIntegration');

		self::getConfig();
		$out->addJsConfigVars( 'bsWebDAVConfig', self::$aConfig );

		return true;
	}

	protected static $aConfig = [];
	protected static $aAttributes = [];
	protected static $oFile = null;
	protected static $oTitle = null;
	protected static $sClientApp = '';
	protected static $sClientAppText = '';
	protected static $sAppProtocol = '';

	protected static function getFile() {
		$repoGroup = MediaWikiServices::getInstance()->getRepoGroup();
		$oFile = $repoGroup->getLocalRepo()->newFile( self::$oTitle );
		if( $oFile instanceof File === false ){
			return false;
		}
		self::$oFile = $oFile;
		return true;
	}

	protected static function getConfig() {
		$services = MediaWikiServices::getInstance();
		$config = $services->getConfigFactory()->makeConfig( 'bsg' );

		if( !empty( self::$aConfig ) ) {
			return;
		}

		$aConfig = array(
			'clientapps' => $config->get( 'WebDAVCIClientApps' )
		);

		##Hacky, in order not to break implementations already in place
		$oOut = RequestContext::getMain()->getOutput();
		$oSkin = RequestContext::getMain()->getSkin();
		$hookContainer = $services->getHookContainer();
		$hookContainer->run( 'BSWebDAVClientIntegrationMakeConfig', array( $oOut, $oSkin, &$aConfig ) );

		self::$aConfig = $aConfig;
	}

	protected static function getAttributes() {
		$oUrlProvider = MediaWikiServices::getInstance()->getService( 'WebDAVUrlProvider' );

		$aAttributes = [];
		$aAttributes[ 'webdav-url' ] = $oUrlProvider->getURL( self::$oTitle );
		$aAttributes[ 'webdav-ext' ] = strtolower( self::$oFile->getExtension() );

		self::$aAttributes = $aAttributes;
	}

	protected static function getClientApp() {
		$sFileExtension = self::$aAttributes[ 'webdav-ext' ];

		foreach( self::$aConfig[ 'clientapps' ] as $sAppKey => $aApp ) {
			$aAppExtensions = [];
			if( is_array( $aApp ) && array_key_exists( 'extensions', $aApp ) ) {
				$aAppExtensions = $aApp['extensions'];
			}

			if( !empty( $aAppExtensions ) && in_array( $sFileExtension, $aAppExtensions ) ) {
				self::$sClientApp = $sAppKey;
				self::$sClientAppText = wfMessage( $sAppKey )->plain();
				if( array_key_exists( 'protocol', $aApp ) ) {
					self::$sAppProtocol = $aApp[ 'protocol' ];
				}
				return;
			}
			self::$sClientApp = 'bs-webdav-ci-generic';
			self::$sClientAppText = wfMessage( 'bs-webdav-ci-generic' )->plain();
		}
	}

	/**
	 * @param array $aItems
	 * @param Title $oTitle
	 * @return boolean
	 */
	public static function onBsContextMenuGetItems( &$aItems, $oTitle ) {
		if( !$oTitle instanceof Title || !$oTitle->isKnown() ) {
			return true;
		}

		if( in_array( $oTitle->getNamespace(), [ NS_FILE, NS_MEDIA ] ) === false ) {
			return true;
		}

		self::$oTitle = $oTitle;
		if( !self::getFile() ) {
			return true;
		}
		self::getAttributes();

		self::getConfig();

		self::getClientApp();

		$aWDItems = [];
		$aWDItems[ 'bs-webdav-show-history' ] = array(
			'text' => wfMessage( 'bs-webdav-ci-show-history', self::$sClientAppText )->plain(),
			'href' => '',
			'id' => 'bs-webdav-show-history',
			'iconCls' => 'bs-icon-history'
		);

		$aWDItems[ 'bs-webdav-edit-file' ] = array(
			'text' => wfMessage( 'bs-webdav-ci-edit-with', self::$sClientAppText )->plain(),
			'href' => self::$aAttributes[ 'webdav-url' ],
			'id' => 'bs-webdav-edit-file',
			'iconCls' => self::$aConfig[ 'clientapps' ][ self::$sClientApp ]['icon']
		);
		$aItems = array_merge( $aWDItems, $aItems );

		return true;
	}
}
