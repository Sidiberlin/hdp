<?php

namespace MediaWiki\Extension\NumberHeadings;

use MediaWiki\Config\Config;
use MediaWiki\HookContainer\HookContainer;
use MediaWiki\Title\NamespaceInfo;
use MediaWiki\Title\Title;

class ApplyHeadingNumbering {

	/**
	 * @param Config $config
	 * @param HookContainer $hookContainer
	 * @param NamespaceInfo $namespaceInfo
	 */
	public function __construct(
		private readonly Config $config,
		private readonly HookContainer $hookContainer,
		private readonly NamespaceInfo $namespaceInfo
	) {
	}

	/**
	 * @param Title $title
	 * @param string $html
	 * @param string $prefix = ''
	 *
	 * @return string
	 */
	public function apply( Title $title, string $html, string $prefix = '' ): string {
		$skip = false;
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

		return $headingNumbering->execute( $html, $prefix );
	}
}
