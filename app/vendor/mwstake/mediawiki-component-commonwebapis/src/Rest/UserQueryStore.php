<?php

namespace MWStake\MediaWiki\Component\CommonWebAPIs\Rest;

use GlobalVarConfig;
use MediaWiki\HookContainer\HookContainer;
use MediaWiki\Linker\LinkRenderer;
use MediaWiki\User\UserFactory;
use MWStake\MediaWiki\Component\CommonWebAPIs\Data\UserQueryStore\Store;
use MWStake\MediaWiki\Component\DataStore\IStore;
use Wikimedia\Rdbms\ILoadBalancer;

class UserQueryStore extends QueryStore {
	/** @var Store */
	private $store;

	/**
	 * @param HookContainer $hookContainer
	 * @param ILoadBalancer $lb
	 * @param UserFactory $userFactory
	 * @param LinkRenderer $linkRenderer
	 * @param \TitleFactory $titleFactory
	 * @param GlobalVarConfig $mwsgConfig
	 */
	public function __construct(
		HookContainer $hookContainer, ILoadBalancer $lb, UserFactory $userFactory,
		LinkRenderer $linkRenderer, \TitleFactory $titleFactory, GlobalVarConfig $mwsgConfig
	) {
		parent::__construct( $hookContainer );
		$this->store = new Store( $lb, $userFactory, $linkRenderer, $titleFactory, $mwsgConfig );
	}

	/**
	 * @return IStore
	 */
	protected function getStore(): IStore {
		return $this->store;
	}
}
