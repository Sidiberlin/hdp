<?php

namespace MediaWiki\Extension\NSFileRepo\HookHandler;

use Config;
use MWStake\MediaWiki\Component\CommonWebAPIs\Hook\MWStakeCommonWebAPIsQueryStoreResultHook;
use MWStake\MediaWiki\Component\CommonWebAPIs\Rest\FileQueryStore;
use MWStake\MediaWiki\Component\DataStore\ResultSet;
use RequestContext;

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
		$namespaceList = new \NSFileRepo\NamespaceList(
			$this->context->getUser(),
			$this->config,
			$this->context->getLanguage()
		);
		$namespaces = $namespaceList->getReadable();
		foreach ( $data as $record ) {
			$prefixed = $record->get( 'prefixed' );

			if ( !str_contains( $prefixed, ':' ) ) {
				$record->set( 'namespace_text', $namespaces[0]->getDisplayName() );
				$record->set( 'namespace', $namespaces[0]->getId() );
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
				$record->set( 'namespace_text', $namespaces[0]->getDisplayName() );
				$record->set( 'namespace', $namespaces[0]->getId() );
			}
		}

		$result = new ResultSet( $data, $result->getTotal() );
	}
}
