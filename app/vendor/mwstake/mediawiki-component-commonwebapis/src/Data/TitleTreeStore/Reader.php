<?php

namespace MWStake\MediaWiki\Component\CommonWebAPIs\Data\TitleTreeStore;

use MWStake\MediaWiki\Component\DataStore\ReaderParams;
use Wikimedia\Rdbms\ILoadBalancer;

class Reader extends \MWStake\MediaWiki\Component\CommonWebAPIs\Data\TitleQueryStore\Reader {
	/** @var ILoadBalancer */
	protected $lb;
	/** @var \TitleFactory */
	protected $titleFactory;
	/** @var \Language */
	protected $language;
	/** @var \NamespaceInfo */
	protected $nsInfo;

	/**
	 * @return UserSchema
	 */
	public function getSchema() {
		return new TitleTreeSchema();
	}

	/**
	 * @param ReaderParams $params
	 *
	 * @return PrimaryDataProvider
	 */
	public function makePrimaryDataProvider( $params ) {
		return new PrimaryDataProvider(
			$this->lb->getConnection( DB_REPLICA ), $this->getSchema(), $this->language, $this->nsInfo
		);
	}

	/**
	 * @return SecondaryDataProvider
	 */
	public function makeSecondaryDataProvider() {
		return new SecondaryDataProvider( $this->titleFactory, $this->language, $this->pageProps );
	}
}
