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
use SMWDITime;

class ExpirationDate extends PropertyValueProvider {

	/**
	 *
	 * @return string
	 */
	public function getAliasMessageKey() {
		return "bs-expiry-smwpropertyvalueprovider-expirationdate-alias";
	}

	/**
	 *
	 * @return string
	 */
	public function getDescriptionMessageKey() {
		return "bs-expiry-smwpropertyvalueprovider-expirationdate-desc";
	}

	/**
	 *
	 * @return int
	 */
	public function getType() {
		return SMWDataItem::TYPE_TIME;
	}

	/**
	 *
	 * @return string
	 */
	public function getId() {
		return '_EXPIRATIONDATE';
	}

	/**
	 *
	 * @return string
	 */
	public function getLabel() {
		return "Expiry/Expiration date";
	}

	/**
	 * @param AppFactory $appFactory
	 * @param DIProperty $property
	 * @param SemanticData $semanticData
	 * @return null
	 */
	public function addAnnotation( $appFactory, $property, $semanticData ) {
		$record = $this->getFactory()->newFromTitle( $semanticData->getSubject()->getTitle() );
		if ( !$record || !$record->get( Record::DATE, null ) ) {
			return null;
		}
		$expires = DateTime::createFromFormat(
			'Y-m-d H:i:s',
			$record->get( Record::DATE )
		);
		if ( !$expires ) {
			return null;
		}
		// Page expires as soon as the day starts
		$expires->setTime( 0, 0 );
		$semanticData->addPropertyObjectValue(
			$property,
			SMWDITime::newFromTimestamp( $expires->format( 'YmdHis' ) )
		);
		return null;
	}

	/**
	 *
	 * @return Factory
	 */
	private function getFactory() {
		return MediaWikiServices::getInstance()->getService( 'BSExpiryFactory' );
	}
}
