<?php

namespace BlueSpice\Bookshelf\Data\BookChapters;

use MediaWiki\Config\Config;
use MediaWiki\Context\IContextSource;
use MediaWiki\MediaWikiServices;
use MWStake\MediaWiki\Component\DataStore\ReaderParams;
use Wikimedia\Rdbms\LoadBalancer;

class Reader extends \MWStake\MediaWiki\Component\DataStore\Reader {

	/**
	 * @var LoadBalancer
	 */
	private $loadBalancer = null;

	/**
	 *
	 * @param IContextSource|null $context
	 * @param Config|null $config
	 * @param LoadBalancer|null $loadBalancer
	 */
	public function __construct(
		?IContextSource $context = null, ?Config $config = null, ?LoadBalancer $loadBalancer = null
	) {
		parent::__construct( $context, $config );

		$this->loadBalancer = $loadBalancer;
		if ( $this->loadBalancer === null ) {
			$this->loadBalancer = MediaWikiServices::getInstance()->getDBLoadBalancer();
		}
	}

	/**
	 *
	 * @param ReaderParams $params
	 * @return PrimaryDataProvider
	 */
	protected function makePrimaryDataProvider( $params ) {
		$db = $this->loadBalancer->getConnection( DB_REPLICA );
		return new PrimaryDataProvider( $db );
	}

	/**
	 *
	 * @return SecondaryDataProvider
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
