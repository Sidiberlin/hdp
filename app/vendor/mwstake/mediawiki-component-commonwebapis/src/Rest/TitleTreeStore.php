<?php

namespace MWStake\MediaWiki\Component\CommonWebAPIs\Rest;

use MWStake\MediaWiki\Component\CommonWebAPIs\Data\TitleTreeStore\Store;
use MWStake\MediaWiki\Component\CommonWebAPIs\Data\TitleTreeStore\TitleTreeReaderParams;
use MWStake\MediaWiki\Component\DataStore\IStore;
use MWStake\MediaWiki\Component\DataStore\ReaderParams;
use Wikimedia\ParamValidator\ParamValidator;

class TitleTreeStore extends TitleQueryStore {

	/**
	 * @return IStore
	 */
	protected function getStore(): IStore {
		return new Store( $this->lb, $this->titleFactory, $this->language, $this->nsInfo, $this->pageProps );
	}

	/**
	 * @return ReaderParams
	 */
	protected function getReaderParams(): ReaderParams {
		return new TitleTreeReaderParams( [
			'query' => $this->getQuery(),
			'start' => $this->getOffset(),
			'limit' => $this->getLimit(),
			'filter' => $this->getFilter(),
			'sort' => $this->getSort(),
			'node' => $this->getValidatedParams()['node'] ?? '',
			'expand-paths' => $this->getValidatedParams()['expand-paths'] ?? [],
		] );
	}

	/**
	 * @return array[]
	 */
	protected function getStoreSpecificParams(): array {
		return [
			'node' => [
				static::PARAM_SOURCE => 'query',
				ParamValidator::PARAM_REQUIRED => false,
				ParamValidator::PARAM_TYPE => 'string',
			],
			'expand-paths' => [
				static::PARAM_SOURCE => 'query',
				ParamValidator::PARAM_REQUIRED => false,
				ParamValidator::PARAM_TYPE => 'string',
			],
		];
	}
}
