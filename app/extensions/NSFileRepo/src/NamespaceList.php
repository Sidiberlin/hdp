<?php

namespace MediaWiki\Extension\NSFileRepo;

use MediaWiki\Config\Config as MediaWikiConfig;
use MediaWiki\Config\HashConfig;
use MediaWiki\Config\MultiConfig;
use MediaWiki\Language\Language;
use MediaWiki\MediaWikiServices;
use MediaWiki\Title\Title;
use MediaWiki\User\User;

class NamespaceList {

	/**
	 *
	 * @var User
	 */
	protected $user = null;

	/**
	 *
	 * @var MediaWikiConfig
	 */
	protected $config = null;

	/**
	 *
	 * @var Language
	 */
	protected $lang = null;

	/**
	 *
	 * @param User $user
	 * @param MediaWikiConfig $config
	 * @param Language $lang
	 */
	public function __construct( User $user, MediaWikiConfig $config, Language $lang ) {
		$this->user = $user;
		$this->config = new MultiConfig( [
			$config,
			new HashConfig( [
				Config::CONFIG_SKIP_TALK => true,
				Config::CONFIG_THRESHOLD => 0,
				Config::CONFIG_BLACKLIST => []
			] )
		] );
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

	/**
	 * @param string $permission
	 * @return array
	 */
	protected function getNamespacesByPermission( string $permission ) {
		$availableNamespaces = $this->lang->getNamespaces();

		$namespaces = [];
		$namespaceInfo = MediaWikiServices::getInstance()->getNamespaceInfo();
		foreach ( $availableNamespaces as $nsId => $nsText ) {

			if ( $this->skip( $nsId, $permission ) ) {
				continue;
			}

			if ( $nsId === NS_MAIN ) {
				$nsText = wfMessage( 'nsfilerepo-nsmain' )->plain();
			}

			$canonicalName = $namespaceInfo->getCanonicalName( $nsId );
			$namespaces[$nsId] = new MWNamespace( $nsId, $canonicalName, $nsText );
		}

		return $namespaces;
	}

	/**
	 * @param int $nsId
	 * @param string $permission
	 * @return bool
	 */
	protected function skip( $nsId, string $permission = '' ) {
		if ( $nsId < $this->config->get( Config::CONFIG_THRESHOLD ) && $nsId !== NS_MAIN ) {
			return true;
		}

		if ( in_array( $nsId, $this->config->get( Config::CONFIG_BLACKLIST ) ) ) {
			return true;
		}

		$services = MediaWikiServices::getInstance();
		$namespaceInfo = $services->getNamespaceInfo();
		if ( $this->config->get( Config::CONFIG_SKIP_TALK )
				&& $namespaceInfo->isTalk( $nsId ) ) {
			return true;
		}

		if ( !empty( $permission ) ) {
			$title = Title::makeTitle( $nsId, 'Dummy' );
			return !$services->getPermissionManager()
				->userCan( $permission, $this->user, $title );
		}

		return false;
	}

}
