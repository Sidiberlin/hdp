<?php

namespace MediaWiki\Extension\NumberHeadings\HookHandler;

use MediaWiki\Config\Config;
use MediaWiki\Content\Content;
use MediaWiki\Content\TextContent;
use MediaWiki\Extension\NumberHeadings\ApplyHeadingNumbering;
use MediaWiki\HookContainer\HookContainer;
use MediaWiki\Parser\ParserOutput;
use MediaWiki\Parser\Parsoid\PageBundleParserOutputConverter;
use MediaWiki\Title\NamespaceInfo;
use MediaWiki\Title\Title;

class AddHeadingNumbering {

	public const ALREADY_PROCESSED = 'numberheading-already-processed';

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
	 * @param Content $content
	 * @param Title $title
	 * @param ParserOutput &$output
	 * @return void
	 */
	public function onContentAlterParserOutput( Content $content, Title $title, ParserOutput &$output ) {
		if ( !$this->config->get( 'NumberHeadingsEnable' ) ) {
			return true;
		}
		if ( !( $content instanceof TextContent ) ) {
			return true;
		}
		// We skip execution of our code if content is parsoid content. This is the case in visual edit mode.
		// See ERM45110
		if ( $output->getExtensionData( PageBundleParserOutputConverter::PARSOID_PAGE_BUNDLE_KEY ) !== null ) {
			return true;
		}
		if ( $output->getExtensionData( self::ALREADY_PROCESSED ) !== null ) {
			return true;
		}
		if ( !$output->hasText() ) {
			return true;
		}
		// Intentionally using deprecated `getText`/`setText` here, as new `DefaultOutputPipelineFactory`
		// is marked as "unstable".
		// https://github.com/wikimedia/mediawiki/blob/1.43.5/includes/OutputTransform/DefaultOutputPipelineFactory.php#L27
		// We can not use `getRawText` as it does not provide the required markup.
		$text = $output->getText();

		$applyHeadingNumbering = new ApplyHeadingNumbering(
			$this->config, $this->hookContainer, $this->namespaceInfo
		);

		$output->setText( $applyHeadingNumbering->apply( $title, $text ) );
		$output->setExtensionData( self::ALREADY_PROCESSED, true );

		return true;
	}
}
