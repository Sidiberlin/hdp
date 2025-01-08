<?php

namespace BlueSpice\Expiry\SMWConnector\PropertyValueProvider;

use BlueSpice\Expiry\Data\Record;
use BlueSpice\Expiry\Factory;
use BlueSpice\SMWConnector\PropertyValueProvider;
use DateTime;
use MediaWiki\MediaWikiServices;
use SESP\AppFactory;
use SMW\DIProperty;
use SMW\SemanticData;
use SMWDataItem;
use SMWDIBoolean;

class IsExpired extends PropertyValueProvider {

	/**
	 *
	 * @return string
	 */
	public function getAliasMessageKey() {
		return "bs-expiry-smwpropertyvalueprovider-isexpired-alias";
	}

	/**
	 *
	 * @return string
	 */
	public function getDescriptionMessageKey() {
		return "bs-expiry-smwpropertyvalueprovider-isexpired-desc";
	}

	/**
	 *
	 * @return int
	 */
	public function getType() {
		return SMWDataItem::TYPE_BOOLEAN;
	}

	/**
	 *
	 * @return string
	 */
	public function getId() {
		return '_ISEXPIRED';
	}

	/**
	 *
	 * @return string
	 */
	public function getLabel() {
		return "Expiry/Is Expired";
	}

	/**
	 * @param AppFactory $appFactory
	 * @param DIProperty $property
	 * @param SemanticData $semanticData
	 */
	public function addAnnotation( $appFactory, $property, $semanticData ) {
		$record = $this->getFactory()->newFromTitle( $semanticData->getSubject()->getTitle() );
		if ( !$record || !$record->get( Record::DATE, null ) ) {
			return;
		}
		$expires = DateTime::createFromFormat(
			'Y-m-d H:i:s',
			$record->get( Record::DATE )
		);
		if ( !$expires ) {
			return;
		}
		$expires->setTime( 0, 0 );
		$now = new DateTime( 'now' );
		$semanticData->addPropertyObjectValue(
			$property,
			new SMWDIBoolean( $expires < $now )
		);
	}

	/**
	 *
	 * @return Factory
	 */
	private function getFactory() {
		return MediaWikiServices::getInstance()->getService( 'BSExpiryFactory' );
	}
}
