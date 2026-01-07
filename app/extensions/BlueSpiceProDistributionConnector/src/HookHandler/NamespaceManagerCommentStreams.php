<?php

//phpcs:disable MediaWiki.NamingConventions.LowerCamelFunctionsName.FunctionName

namespace BlueSpice\ProDistributionConnector\HookHandler;

use BlueSpice\NamespaceManager\Hook\NamespaceManagerBeforePersistSettingsHook;
use MediaWiki\Config\Config;
use MediaWiki\Message\Message;
use MediaWiki\Title\NamespaceInfo;

class NamespaceManagerCommentStreams implements NamespaceManagerBeforePersistSettingsHook {

	/**
	 *
	 * @var Config
	 */
	protected $config = null;

	/**
	 *
	 * @var NamespaceInfo
	 */
	protected $namespaceInfo = null;

	/**
	 * @param Config $config
	 * @param NamespaceInfo $namespaceInfo
	 */
	public function __construct( Config $config, NamespaceInfo $namespaceInfo ) {
		$this->config = $config;
		$this->namespaceInfo = $namespaceInfo;
	}

	/**
	 * @param array &$aMetaFields
	 *
	 * @return bool
	 */
	public function onNamespaceManager__getMetaFields( &$aMetaFields ) {
		$aMetaFields[] = [
			'name' => 'commentstreams',
			'type' => 'boolean',
			'label' => Message::newFromKey( 'bs-pro-distribution-config-comment-streams-enabled' )->plain(),
			'filter' => [
				'type' => 'boolean'
			],
		];
		return true;
	}

	/**
	 * @param array &$aResults
	 *
	 * @return bool
	 */
	public function onBSApiNamespaceStoreMakeData( &$aResults ) {
		$current = $this->getCurrentValue( $this->config->get( 'CommentStreamsAllowedNamespaces' ) );
		$iResults = count( $aResults );
		for ( $i = 0; $i < $iResults; $i++ ) {
			$aResults[ $i ][ 'commentstreams' ] = [
				'value' => in_array( $aResults[ $i ][ 'id' ], $current ),
				'disabled' => $i === NS_MEDIAWIKI
			];
		}
		return true;
	}

	/**
	 * @param array &$namespaceDefinitions
	 * @param int &$ns
	 * @param array $additionalSettings
	 * @param bool $useInternalDefaults
	 *
	 * @return bool
	 */
	public function onNamespaceManager__editNamespace(
		&$namespaceDefinitions, &$ns, $additionalSettings, $useInternalDefaults = false
	) {
		if ( !$useInternalDefaults && isset( $additionalSettings['commentstreams'] ) ) {
			$namespaceDefinitions[$ns][ 'commentstreams' ] = $additionalSettings['commentstreams'];
		} else {
			$namespaceDefinitions[$ns][ 'commentstreams' ] = false;
		}
		return true;
	}

	/**
	 * @inheritDoc
	 */
	public function onNamespaceManagerBeforePersistSettings(
		array &$configuration, int $id, array $definition, array $mwGlobals
	): void {
		$configuration['wgCommentStreamsAllowedNamespaces'] = $configuration['wgCommentStreamsAllowedNamespaces'] ?? [];
		$enabledNamespaces = $this->getCurrentValue( $mwGlobals['wgCommentStreamsAllowedNamespaces'] );
		$currentlyActivated = in_array( $id, $enabledNamespaces );

		$explicitlyDeactivated = false;
		if ( isset( $definition['commentstreams'] ) && $definition['commentstreams'] === false ) {
			$explicitlyDeactivated = true;
		}

		$explicitlyActivated = false;
		if ( isset( $definition['commentstreams'] ) && $definition['commentstreams'] === true ) {
			$explicitlyActivated = true;
		}

		if ( ( $currentlyActivated && !$explicitlyDeactivated ) || $explicitlyActivated ) {
			$configuration['wgCommentStreamsAllowedNamespaces'][] = $id;
		}
	}

	/**
	 * @param array|null $raw
	 * @return array
	 */
	private function getCurrentValue( $raw ): array {
		$current = is_array( $raw ) ? $raw : [];
		if ( $raw === null ) {
			$current = $this->namespaceInfo->getContentNamespaces();
		}
		return $current;
	}
}
