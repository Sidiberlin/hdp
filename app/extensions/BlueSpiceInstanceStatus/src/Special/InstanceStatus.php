<?php

namespace BlueSpice\InstanceStatus\Special;

use BlueSpice\InstanceStatus\IStatusProvider;
use ExtensionRegistry;
use Html;
use MediaWiki\Logger\LoggerFactory;
use Message;
use MWException;
use SpecialPage;
use Wikimedia\ObjectFactory\ObjectFactory;

class InstanceStatus extends SpecialPage {

	/**
	 *
	 * @var ObjectFactory
	 */
	protected $objectFactory = null;

	/**
	 * @param ObjectFactory $objectFactory
	 */
	public function __construct( ObjectFactory $objectFactory ) {
		parent::__construct( 'InstanceStatus', 'wikiadmin' );
		$this->objectFactory = $objectFactory;
	}

	/**
	 * @inheritDoc
	 */
	public function execute( $page ) {
		parent::execute( $page );
		$html = Html::openElement( 'div', [
			'id' => 'bs-instance-status-overview'
		] );
		$html .= Html::element(
			'h3',
			[ 'id' => 'bs-instancestatus-overview-loader' ],
			Message::newFromKey( 'bs-instancestatus-overview-loader' )->text()
		);
		$html .= Html::closeElement( 'div' );
		$this->getOutput()->addHTML( $html );
		$this->addStatusData();
		$this->getOutput()->addModules( [ "ext.blueSpiceInstanceStatus.special" ] );
		return true;
	}

	/**
	 * @return void
	 */
	private function addStatusData() {
		$logger = LoggerFactory::getInstance( 'BlueSpiceInstanceStatus' );
		$attribute = ExtensionRegistry::getInstance()->getAttribute( 'BlueSpiceInstanceStatusInstanceStatusProvider' );
		$statusInfo = [];
		foreach ( $attribute as $key => $spec ) {
			$instance = $this->objectFactory->createObject( $spec );
			if ( !$instance instanceof IStatusProvider ) {
				throw new MWException( "Invalid factory spec for InstanceStatusProvider $key" );
			}

			$statusInfo[$key] = [
				'label' => $instance->getLabel(),
				'value' => $instance->getValue(),
				'icon' => $instance->getIcon(),
				'priority' => $instance->getPriority()
			];
		}

		uasort( $statusInfo, static function ( $a, $b ) {
			if ( $a['priority'] === $b['priority'] ) {
				return 0;
			}
			return ( $a['priority'] < $b['priority'] ) ? -1 : 1;
		} );

		$this->getOutput()->addJsConfigVars( 'bsInstanceStatus_StatusData', $statusInfo );
	}
}
