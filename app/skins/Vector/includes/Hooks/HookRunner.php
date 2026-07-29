<?php

namespace MediaWiki\Skins\Vector\Hooks;

use MediaWiki\HookContainer\HookContainer;

/**
 * HookRunner for Vector skin hooks.
 * Missing from the Vector skin distribution — created as a minimal stub
 * to prevent ResourceLoader startup crashes.
 */
class HookRunner {

	private HookContainer $hookContainer;

	public function __construct( HookContainer $hookContainer ) {
		$this->hookContainer = $hookContainer;
	}

	/**
	 * @param array &$config
	 */
	public function onVectorSearchResourceLoaderConfig( array &$config ): void {
		$this->hookContainer->run(
			'VectorSearchResourceLoaderConfig',
			[ &$config ]
		);
	}
}
