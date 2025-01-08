<?php

namespace BlueSpice\Expiry\Data;

use MWStake\MediaWiki\Component\DataStore\DatabaseWriter;

class Writer extends DatabaseWriter {

	/**
	 *
	 * @return string[]
	 */
	protected function getIdentifierFields() {
		return [ Record::PAGE_ID ];
	}

	/**
	 *
	 * @return string
	 */
	protected function getTableName() {
		return Schema::TABLE_NAME;
	}

	/**
	 *
	 * @return Schema
	 */
	public function getSchema() {
		return new Schema;
	}

	/**
	 *
	 * @param Record $existingRecord
	 * @param Record $record
	 * @return array
	 */
	protected function makeUpdateFields( $existingRecord, $record ) {
		$return = [];
		foreach ( (array)$record->getData() as $fieldName => $mValue ) {
			$return[$fieldName] = $mValue;
		}
		return $return;
	}

}
