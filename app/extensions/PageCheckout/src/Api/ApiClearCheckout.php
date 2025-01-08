<?php

namespace MediaWiki\Extension\PageCheckout\Api;

use ApiBase;
use ApiMain;
use MediaWiki\Extension\PageCheckout\CheckoutManager;
use MediaWiki\Extension\PageCheckout\Entity\CheckoutEntity;
use Title;
use Wikimedia\ParamValidator\ParamValidator;

class ApiClearCheckout extends ApiBase {
	/** @var CheckoutManager */
	private $manager;

	/**
	 * @param ApiMain $mainModule
	 * @param string $moduleName
	 * @param CheckoutManager $manager
	 */
	public function __construct( ApiMain $mainModule, $moduleName, CheckoutManager $manager ) {
		parent::__construct( $mainModule, $moduleName );
		$this->manager = $manager;
	}

	public function execute() {
		$this->checkUserRightsAny( 'page-checkout-clear' );

		$params = $this->extractRequestParams();

		$title = Title::newFromText( $params['page_title'] );
		$checkout = $this->manager->getCheckoutEntity( $title );
		if ( !$checkout instanceof CheckoutEntity ) {
			$this->dieStatus( \Status::newFatal( 'pagecheckout-error-no-checkout' ) );
		}

		$res = $this->manager->clearCheckout( $title );

		$this->getResult()->addValue( null, $this->getModuleName(), [ 'success' => $res ] );
	}

	public function mustBePosted() {
		return true;
	}

	public function isWriteMode() {
		return true;
	}

	/**
	 * @return array[]
	 */
	public function getAllowedParams() {
		return [
			'page_title' => [
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => true
			]
		];
	}

	/**
	 * @return string
	 */
	public function needsToken() {
		return 'csrf';
	}

	/**
	 * @return string[]
	 */
	protected function getExamplesMessages() {
		return [
			'action=pagecheckout-clear&page_title=Main_Page'
			=> 'apihelp-pagecheckout-clear-example',
		];
	}
}
