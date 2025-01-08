<?php

namespace NSFileRepo;

use MediaWiki\MediaWikiServices;

class NamespaceList {

	/**
	 *
	 * @var \User
	 */
	protected $user = null;

	/**
	 *
	 * @var \Config
	 */
	protected $config = null;

	/**
	 *
	 * @var \Language
	 */
	protected $lang = null;

	/**
	 *
	 * @param \User $user
	 * @param \Config $config
	 * @param \Language $lang
	 */
	public function __construct( \User $user, \Config $config, \Language $lang ) {
		$this->user = $user;
		$this->config = new \MultiConfig([
			$config,
			new \HashConfig( [
				Config::CONFIG_SKIP_TALK => true,
				Config::CONFIG_THRESHOLD => 0,
				Config::CONFIG_BLACKLIST => []
			] )
		]);
		$this->lang = $lang;
	}

	/**
	 * @return MWNamespace[] With namespace id as an index
	 */
	public function getReadable() {
		return $this->getNamespacesByPermission( 'read' );
	}

	/**
	 * @return MWNamespace[] With namespace id as an index
	 */
	public function getEditable() {
		return $this->getNamespacesByPermission( 'edit' );
	}

	protected function getNamespacesByPermission( $permission ) {
		$availableNamespaces = $this->lang->getNamespaces();

		$namespaces = [];
		$namespaceInfo = MediaWikiServices::getInstance()->getNamespaceInfo();
		foreach( $availableNamespaces as $nsId => $nsText ) {

			if( $this->skip( $nsId, $permission ) ) {
				continue;
			}

			if( $nsId === NS_MAIN ) {
				$nsText = wfMessage('nsfilerepo-nsmain')->plain();
			}

			$canonicalName = $namespaceInfo->getCanonicalName( $nsId );
			$namespaces[$nsId] = new MWNamespace( $nsId, $canonicalName , $nsText );
		}

		return $namespaces;
	}

	protected function skip( $nsId, $permission = '' ) {

		if( $nsId < $this->config->get( Config::CONFIG_THRESHOLD ) && $nsId !== NS_MAIN ) {
			return true;
		}

		if( in_array( $nsId, $this->config->get( Config::CONFIG_BLACKLIST ) ) ) {
			return true;
		}

		$services = MediaWikiServices::getInstance();
		$namespaceInfo = $services->getNamespaceInfo();
		if( $this->config->get( Config::CONFIG_SKIP_TALK )
				&& $namespaceInfo->isTalk( $nsId ) ) {
			return true;
		}

		if( !empty( $permission ) ) {
			$title = \Title::makeTitle( $nsId, 'Dummy' );
			return !$services->getPermissionManager()
				->userCan( $permission, $this->user, $title );
		}

		return false;
	}

}
