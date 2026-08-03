<?php

namespace BlueSpice\Bookshelf\Data\BookChapters;

use MediaWiki\Config\Config;
use MediaWiki\Context\IContextSource;
use MWStake\MediaWiki\Component\DataStore\IStore;
use MWStake\MediaWiki\Component\DataStore\NoWriterException;
use WANObjectCache;
use Wikimedia\Rdbms\LoadBalancer;

class Store implements IStore {

	/**
	 * @var IContextSource
	 */
	protected $context = null;

	/**
	 * @var Config
	 */
	private $config = null;

	/**
	 * @var LoadBalancer
	 */
	private $loadBalancer = null;

	/** @var WANObjectCache */
	private $wanCache;

	/**
	 * @param IContextSource $context
	 * @param Config $config
	 * @param LoadBalancer $loadBalancer
	 * @param WANObjectCache $wanCache
	 */
	public function __construct(
		IContextSource $context, Config $config, LoadBalancer $loadBalancer, WANObjectCache $wanCache
	) {
		$this->context = $context;
		$this->config = $config;
		$this->loadBalancer = $loadBalancer;
		$this->wanCache = $wanCache;
	}

	/**
	 *
	 * @return Reader
	 */
	public function getReader() {
		return new Reader(
			$this->context,
			$this->config,
			$this->loadBalancer,
			$this->wanCache
		);
	}

	/**
	 *
	 * @throws NoWriterException
	 */
	public function getWriter() {
		throw new NoWriterException();
	}
}
