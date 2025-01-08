<?php

namespace MediaWiki\Extension\ContentProvisioning\Data;

class Record extends \MWStake\MediaWiki\Component\DataStore\Record {
	public const PAGE_PREFIXED_TEXT = 'page_prefixed_text';
	public const PAGE_LINK = 'page_link';
	public const IN_SYNC = 'in_sync';
	public const CLASSES = 'classes';
}
