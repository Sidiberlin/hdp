<?php

namespace BlueSpice\Bookshelf\Data\BooksOverview;

use BlueSpice\Bookshelf\BookMetaLookup;
use BlueSpice\Bookshelf\ChapterLookup;
use MediaWiki\Config\Config;
use MediaWiki\Context\IContextSource;
use MediaWiki\HookContainer\HookContainer;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\Title\TitleFactory;
use MWStake\MediaWiki\Component\DataStore\IStore;
use MWStake\MediaWiki\Component\DataStore\NoWriterException;
use RepoGroup;
use WANObjectCache;
use Wikimedia\Rdbms\LoadBalancer;

class Store implements IStore {

	/** @var IContextSource */
	protected $context = null;

	/** @var Config */
	private $config = null;

	/** @var LoadBalancer */
	private $loadBalancer = null;

	/** @var TitleFactory */
	private $titleFactory = null;

	/** @var HookContainer */
	private $hookRunner = null;

	/** @var PermissionManager */
	private $permissionManager = null;

	/** @var RepoGroup */
	private $repoGroup = null;

	/** @var ChapterLookup */
	private $bookChapterLookup = null;

	/** @var BookMetaLookup */
	private $bookMetaLookup = null;

	/** @var WANObjectCache */
	private $wanCache;

	/**
	 * @param IContextSource $context
	 * @param Config $config
	 * @param LoadBalancer $loadBalancer
	 * @param ChapterLookup $bookChapterLookup
	 * @param BookMetaLookup $bookMetaLookup
	 * @param TitleFactory $titleFactory
	 * @param PermissionManager $permissionManager
	 * @param HookContainer $hookContainer
	 * @param RepoGroup $repoGroup
	 * @param WANObjectCache $wanCache
	 */
	public function __construct(
		IContextSource $context, Config $config, LoadBalancer $loadBalancer, ChapterLookup $bookChapterLookup,
		BookMetaLookup $bookMetaLookup, TitleFactory $titleFactory, PermissionManager $permissionManager,
		HookContainer $hookContainer, RepoGroup $repoGroup, WANObjectCache $wanCache
	) {
		$this->context = $context;
		$this->config = $config;
		$this->loadBalancer = $loadBalancer;
		$this->bookChapterLookup = $bookChapterLookup;
		$this->bookMetaLookup = $bookMetaLookup;
		$this->titleFactory = $titleFactory;
		$this->permissionManager = $permissionManager;
		$this->hookRunner = $hookContainer;
		$this->repoGroup = $repoGroup;
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
			$this->titleFactory,
			$this->permissionManager,
			$this->hookRunner,
			$this->repoGroup,
			$this->bookChapterLookup,
			$this->bookMetaLookup,
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
