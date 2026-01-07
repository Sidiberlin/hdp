<?php

namespace BlueSpice\Bookshelf\Data\BooksOverview;

use BlueSpice\Bookshelf\BookMetaLookup;
use BlueSpice\Bookshelf\ChapterLookup;
use MediaWiki\Config\Config;
use MediaWiki\Context\IContextSource;
use MediaWiki\Context\RequestContext;
use MediaWiki\HookContainer\HookContainer;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\Title\TitleFactory;
use MediaWiki\User\User;
use MWStake\MediaWiki\Component\DataStore\ReaderParams;
use RepoGroup;
use Wikimedia\Rdbms\LoadBalancer;

class Reader extends \MWStake\MediaWiki\Component\DataStore\Reader {

	/**
	 * @var LoadBalancer
	 */
	private $loadBalancer = null;

	/**
	 * @var TitleFactory
	 */
	private $titleFactory = null;

	/**
	 * @var PermissionManager
	 */
	private $permissionManager = null;

	/**
	 * @var HookContainer
	 */
	private $hookRunner = null;

	/**
	 * @var User
	 */
	private $user = null;

	/**
	 * @var RepoGroup
	 */
	private $repoGroup = null;

	/** @var ChapterLookup */
	private $bookChapterLookup = null;

	/** @var BookMetaLookup */
	private $bookMetaLookup = null;

	/**
	 *
	 * @param IContextSource $context
	 * @param Config $config
	 * @param LoadBalancer $loadBalancer
	 * @param TitleFactory $titleFactory
	 * @param PermissionManager $permissionManager
	 * @param HookContainer $hookRunner
	 * @param RepoGroup $repoGroup
	 * @param ChapterLookup $bookChapterLookup
	 * @param BookMetaLookup $bookMetaLookup
	 */
	public function __construct(
		IContextSource $context, Config $config, LoadBalancer $loadBalancer,
		TitleFactory $titleFactory, PermissionManager $permissionManager,
		HookContainer $hookRunner, RepoGroup $repoGroup, ChapterLookup $bookChapterLookup,
		BookMetaLookup $bookMetaLookup
	) {
		$context = RequestContext::getMain();
		$this->config = $config;
		parent::__construct( $context, $config );

		$this->loadBalancer = $loadBalancer;
		$this->titleFactory = $titleFactory;
		$this->permissionManager = $permissionManager;
		$this->hookRunner = $hookRunner;
		$this->user = $context->getUser();
		$this->repoGroup = $repoGroup;
		$this->bookChapterLookup = $bookChapterLookup;
		$this->bookMetaLookup = $bookMetaLookup;
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
		return new SecondaryDataProvider(
			$this->titleFactory, $this->permissionManager, $this->hookRunner,
			$this->user, $this->repoGroup, $this->config, $this->bookChapterLookup,
			$this->bookMetaLookup
		);
	}

	/**
	 *
	 * @return Schema
	 */
	public function getSchema() {
		return new Schema();
	}

}
