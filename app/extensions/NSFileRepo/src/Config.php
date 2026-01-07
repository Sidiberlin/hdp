<?php

namespace MediaWiki\Extension\NSFileRepo;

use MediaWiki\Config\GlobalVarConfig;

class Config extends GlobalVarConfig {
	public const CONFIG_SKIP_TALK = 'SkipTalk';
	public const CONFIG_THRESHOLD = 'NamespaceThreshold';
	public const CONFIG_BLACKLIST = 'NamespaceBlacklist';

	/**
	 * @inheritDoc
	 */
	public function __construct() {
		parent::__construct( 'egNSFileRepo' );
	}
}
