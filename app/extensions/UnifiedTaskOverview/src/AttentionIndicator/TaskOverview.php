<?php

namespace MediaWiki\Extension\UnifiedTaskOverview\AttentionIndicator;

use BlueSpice\Discovery\AttentionIndicator\Collection;
use BlueSpice\Discovery\AttentionIndicatorFactory;
use BlueSpice\Discovery\IAttentionIndicator;
use Config;
use MediaWiki\MediaWikiServices;
use MWStake\MediaWiki\Component\ManifestRegistry\IRegistry;
use User;

class TaskOverview extends Collection {

	/**
	 * @var IRegistry
	 */
	protected $registry = null;

	/**
	 * @param string $key
	 * @param Config $config
	 * @param User $user
	 * @param AttentionIndicatorFactory $attentionIndicatorFactory
	 * @param IRegistry $registry
	 */
	public function __construct( string $key, Config $config, User $user,
		AttentionIndicatorFactory $attentionIndicatorFactory, IRegistry $registry ) {
		parent::__construct( $key, $config, $user, $attentionIndicatorFactory );
		$this->registry = $registry;
	}

	/**
	 * @param string $key
	 * @param Config $config
	 * @param User $user
	 * @param MediaWikiServices $services
	 * @param AttentionIndicatorFactory|null $attentionIndicatorFactory
	 * @param IRegistry|null $registry
	 * @return IAttentionIndicator
	 */
	public static function factory( string $key, Config $config, User $user,
		MediaWikiServices $services, AttentionIndicatorFactory $attentionIndicatorFactory = null,
		IRegistry $registry = null ) {
		if ( !$attentionIndicatorFactory ) {
			$attentionIndicatorFactory = $services->getService( 'BSAttentionIndicatorFactory' );
		}
		if ( !$registry ) {
			$registry = $services->getService( 'MWStakeManifestRegistryFactory' )
				->get( 'UnifiedTaskOverviewAttentionIndicatorCollectionRegistry' );
		}
		return new static( $key, $config, $user, $attentionIndicatorFactory, $registry );
	}

	/**
	 * @return string[]
	 */
	protected function getSubIndicatorKeys(): array {
		return $this->registry->getAllValues();
	}

}
