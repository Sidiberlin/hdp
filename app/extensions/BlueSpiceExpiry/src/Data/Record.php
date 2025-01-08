<?php

namespace BlueSpice\Expiry\Data;

class Record extends \MWStake\MediaWiki\Component\DataStore\Record {
	public const ID = 'exp_id';
	public const PAGE_ID = 'exp_page_id';
	public const DATE = 'exp_date';
	public const COMMENT = 'exp_comment';
}
