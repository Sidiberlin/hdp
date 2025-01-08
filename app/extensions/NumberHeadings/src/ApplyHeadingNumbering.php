<?php

namespace MediaWiki\Extension\NumberHeadings;

use Config;
use MediaWiki\HookContainer\HookContainer;
use NamespaceInfo;
use OutputPage;

class ApplyHeadingNumbering {

	/** @var OutputPage */
	private $out;

	/** @var Config */
	private $config;

	/** @var HookContainer */
	private $hookContainer;

	/** @var NamespaceInfo */
	private $namespaceInfo;

	/**
	 * @param OutputPage $out
	 * @param Config $config
	 * @param HookContainer $hookContainer
	 * @param NamespaceInfo $namespaceInfo
	 */
	public function __construct(
		OutputPage $out, Config $config, HookContainer $hookContainer, NamespaceInfo $namespaceInfo
	) {
		$this->config = $config;
		$this->out = $out;
		$this->hookContainer = $hookContainer;
		$this->namespaceInfo = $namespaceInfo;
	}

	/**
	 * @param string $html
	 * @param string $prefix = ''
	 * @return string
	 */
	public function apply( string $html, string $prefix = '' ) {
		$skip = false;

		$title = $this->out->getTitle();
		$namespace = $title->getNamespace();

		// Skip numbering if namespace is not a content namespace
		$contentNamespaces = $this->namespaceInfo->getContentNamespaces();
		if ( !in_array( $namespace, $contentNamespaces ) ) {
			return $html;
		}

		// Skip numbering if namespace is on exclude list
		$skipNamespaces = $this->config->get( 'NumberHeadingsExcludeNamespaces' );
		if ( in_array( $namespace, $skipNamespaces ) ) {
			return $html;
		}

		$this->hookContainer->run(
			'NumberHeadingsBeforeApply',
			[ &$skip, &$prefix, $title, $html ]
		);

		if ( $skip ) {
			return $html;
		}

		$headingNumbering = new NumberHeadings();
		$html = $headingNumbering->execute( $html, $prefix );

		return $html;
	}
}
