<?php

namespace MediaWiki\Extension\NSFileRepo\HookHandler;

use MediaWiki\Config\Config;
use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\NSFileRepo\NamespaceList;
use MWStake\MediaWiki\Component\CommonWebAPIs\Hook\MWStakeCommonWebAPIsQueryStoreResultHook;
use MWStake\MediaWiki\Component\CommonWebAPIs\Rest\FileQueryStore;
use MWStake\MediaWiki\Component\DataStore\ResultSet;

class AddNamespaceToFilelist implements MWStakeCommonWebAPIsQueryStoreResultHook {

	/** @var Config */
	private $config;

	/** @var RequestContext */
	private $context;

	/**
	 *
	 * @param Config $config
	 */
	public function __construct( Config $config ) {
		$this->config = $config;
		$this->context = RequestContext::getMain();
	}

	/**
	 * @inheritDoc
	 */
	public function onMWStakeCommonWebAPIsQueryStoreResult( $store, &$result ) {
		if ( !( $store instanceof FileQueryStore ) ) {
			return;
		}
		$data = $result->getRecords();
		$namespaceList = new NamespaceList(
			$this->context->getUser(),
			$this->config,
			$this->context->getLanguage()
		);
		$namespaces = $namespaceList->getReadable();
		$mainNamespace = $namespaces[0];
		foreach ( $data as $record ) {
			$prefixed = $record->get( 'prefixed' );
			$prefixed = str_replace( ' ', '_', $prefixed );

			if ( !str_contains( $prefixed, ':' ) ) {
				$record->set( 'namespace_text', $mainNamespace->getDisplayName() );
				$record->set( 'namespace', $mainNamespace->getId() );
				continue;
			}
			$titleParts = explode( ':', $prefixed, 2 );
			$nsPart = $titleParts[0];
			$namespaceChanged = false;
			foreach ( $namespaces as $nsId => $namespace ) {
				if ( $namespace->getDisplayName() !== $nsPart ) {
					continue;
				}
				$record->set( 'namespace_text', $namespace->getDisplayName() );
				$record->set( 'namespace', $namespace->getId() );
				$namespaceChanged = true;
				break;
			}
			// if title contains ':' but no valid namespace change namespace to main namespace
			if ( !$namespaceChanged ) {
				$record->set( 'namespace_text', $mainNamespace->getDisplayName() );
				$record->set( 'namespace', $mainNamespace->getId() );
			}
		}

		$result = new ResultSet( $data, $result->getTotal() );
	}
}
