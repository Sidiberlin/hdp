<?php

use Wikimedia\ParamValidator\ParamValidator;

class ApiBookshelfMassAddPageStore extends BSApiExtJSStoreBase {

	/**
	 *
	 * @param string $sQuery
	 * @return array
	 */
	protected function makeData( $sQuery = '' ) {
		$params = $this->extractRequestParams();

		$root = $params[ 'root' ];
		$type = $params[ 'type' ];

		$massAppPageProvider = BlueSpice\Bookshelf\MassAdd\PageProvider::getInstance();
		$massAppPageProvider->setType( $type );
		$massAppPageProvider->setRoot( $root );

		$pages = $massAppPageProvider->getData();

		return $pages;
	}

	/**
	 *
	 * @return array
	 */
	public function getAllowedParams() {
		return array_merge(
			parent::getAllowedParams(),
			[
				'root' => [
					ParamValidator::PARAM_TYPE => 'string',
					ParamValidator::PARAM_REQUIRED => true
				],
				'type' => [
					ParamValidator::PARAM_TYPE => 'string',
					ParamValidator::PARAM_REQUIRED => true
				],
				'limit' => [
					ParamValidator::PARAM_TYPE => 'integer',
					ParamValidator::PARAM_REQUIRED => false,
					ParamValidator::PARAM_DEFAULT => 9999
				]
			]
		);
	}

	/**
	 *
	 * @return array
	 */
	public function getParamDescription() {
		return array_merge(
			parent::getParamDescription(),
			[
				'root' => 'Root value based on which to return pages',
				'type' => 'Type of source for mass add',
				'limit' => 'Number of results to return'
			]
		);
	}
}
