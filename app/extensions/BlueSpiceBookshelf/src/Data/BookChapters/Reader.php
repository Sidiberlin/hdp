<?php

namespace BlueSpice\Bookshelf\Data\BookChapters;

use MediaWiki\Config\Config;
use MediaWiki\Context\IContextSource;
use MediaWiki\MediaWikiServices;
use MWStake\MediaWiki\Component\DataStore\ReaderParams;
use WANObjectCache;
use Wikimedia\Rdbms\LoadBalancer;

class Reader extends \MWStake\MediaWiki\Component\DataStore\Reader {

	/**
	 * @var LoadBalancer
	 */
	private $loadBalancer = null;

	/** @var WANObjectCache */
	private $wanCache;

	/**
	 *
	 * @param IContextSource|null $context
	 * @param Config|null $config
	 * @param LoadBalancer|null $loadBalancer
	 * @param WANObjectCache|null $wanCache
	 */
	public function __construct(
		?IContextSource $context = null, ?Config $config = null,
		?LoadBalancer $loadBalancer = null, ?WANObjectCache $wanCache = null
	) {
		parent::__construct( $context, $config );

		$this->loadBalancer = $loadBalancer;
		if ( $this->loadBalancer === null ) {
			$this->loadBalancer = MediaWikiServices::getInstance()->getDBLoadBalancer();
		}
		$this->wanCache = $wanCache;
		if ( $this->wanCache === null ) {
			$this->wanCache = MediaWikiServices::getInstance()->getMainWANObjectCache();
		}
	}

	/**
	 *
	 * @param ReaderParams $params
	 * @return PrimaryDataProvider
	 */
	protected function makePrimaryDataProvider( $params ) {
		$db = $this->loadBalancer->getConnection( DB_REPLICA );
		return new PrimaryDataProvider( $db, $this->getSchema(), $this->wanCache );
	}

	/**
	 *
	 * @return null
	 */
	protected function makeSecondaryDataProvider() {
		return null;
	}

	/**
	 *
	 * @return Schema
	 */
	public function getSchema() {
		return new Schema();
	}

}
