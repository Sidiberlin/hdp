<?php

namespace MediaWiki\Extension\NumberHeadings\HookHandler;

use Config;
use MediaWiki\Extension\NumberHeadings\ApplyHeadingNumbering;
use MediaWiki\HookContainer\HookContainer;
use NamespaceInfo;
use OutputPage;

class AddHeadingNumbering {

	/** @var Config */
	private $config;

	/** @var HookContainer */
	private $hookContainer;

	/** @var NamespaceInfo */
	private $namespaceInfo;

	/**
	 * @param Config $config
	 * @param HookContainer $hookContainer
	 * @param NamespaceInfo $namespaceInfo
	 */
	public function __construct(
		Config $config, HookContainer $hookContainer, NamespaceInfo $namespaceInfo
	) {
		$this->config = $config;
		$this->hookContainer = $hookContainer;
		$this->namespaceInfo = $namespaceInfo;
	}

	/**
	 * @param OutputPage $out
	 * @param string &$text
	 * @return bool
	 */
	public function onOutputPageBeforeHTML( OutputPage $out, &$text ) {
		if ( !$this->config->get( 'NumberHeadingsEnable' ) ) {
			return true;
		}
		$applyHeadingNumbering = new ApplyHeadingNumbering(
			$out, $this->config, $this->hookContainer, $this->namespaceInfo
		);
		$text = $applyHeadingNumbering->apply( $text );

		return true;
	}
}
