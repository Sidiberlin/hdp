<?php

namespace BlueSpice\Social\Blog\Hook\BSUsageTrackerRegisterCollectors;

use BlueSpice\Social\Blog\Entity\Blog;
use BlueSpice\Social\Data\Entity\Store;
use BS\UsageTracker\Hook\BSUsageTrackerRegisterCollectors;
use MWStake\MediaWiki\Component\DataStore\FieldType;
use MWStake\MediaWiki\Component\DataStore\Filter\StringValue;
use MWStake\MediaWiki\Component\DataStore\ReaderParams;

class NoOfBlogEntries extends BSUsageTrackerRegisterCollectors {

	protected function doProcess() {
		$store = new Store;
		$res = $store->getReader( $this->getContext() )
			->read(	new ReaderParams( $this->getParams() ) );

		$noOfBlogEntries = count( $res->getRecords() );

		$this->collectorConfig['no-of-blog-entries'] = [
			'class' => 'Basic',
			'config' => [
				'identifier' => 'no-of-blog-entries',
				'internalDesc' => 'Number of Blog Entries in Special:Blog',
				'count' => $noOfBlogEntries
			]
		];
	}

	/**
	 * @return array
	 */
	protected function getParams(): array {
		return [
			ReaderParams::PARAM_LIMIT => ReaderParams::LIMIT_INFINITE,
			ReaderParams::PARAM_FILTER => $this->getFilter()
		];
	}

	/**
	 * @return array
	 */
	protected function getFilter(): array {
		return [ [
			StringValue::KEY_PROPERTY => Blog::ATTR_TYPE,
			StringValue::KEY_TYPE => FieldType::STRING,
			StringValue::KEY_COMPARISON => StringValue::COMPARISON_EQUALS,
			StringValue::KEY_VALUE => Blog::TYPE,
		] ];
	}
}
