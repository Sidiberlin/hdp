<?php

namespace MediaWiki\Extension\ContentProvisioning\Data;

use MWStake\MediaWiki\Component\DataStore\FieldType;

class Schema extends \MWStake\MediaWiki\Component\DataStore\Schema {
	public function __construct() {
		parent::__construct( [
			Record::PAGE_PREFIXED_TEXT => [
				self::FILTERABLE => true,
				self::SORTABLE => true,
				self::TYPE => FieldType::STRING
			],
			Record::PAGE_LINK => [
				self::FILTERABLE => false,
				self::SORTABLE => false,
				self::TYPE => FieldType::STRING
			],
			Record::IN_SYNC => [
				self::FILTERABLE => true,
				self::SORTABLE => true,
				self::TYPE => FieldType::BOOLEAN
			]
		] );
	}
}
