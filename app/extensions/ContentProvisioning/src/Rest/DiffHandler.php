<?php

namespace MediaWiki\Extension\ContentProvisioning\Rest;

use MediaWiki\Content\TextContent;
use MediaWiki\Message\Message;
use MediaWiki\Page\WikiPageFactory;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\Rest\Handler;
use MediaWiki\Title\TitleFactory;
use MWStake\MediaWiki\Component\ContentProvisioner\ManifestListProvider\StaticManifestProvider;
use Wikimedia\Diff\Diff;
use Wikimedia\Diff\TableDiffFormatter;
use Wikimedia\ParamValidator\ParamValidator;

class DiffHandler extends Handler {
	use UnmaskPageTitleTrait;

	/**
	 * @var TitleFactory
	 */
	private $titleFactory;

	/**
	 * @var WikiPageFactory
	 */
	private $wikiPageFactory;

	/**
	 * @inheritDoc
	 */
	public function getParamSettings() {
		return [
			'pagePrefixedText' => [
				static::PARAM_SOURCE => 'path',
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => true
			]
		];
	}

	/**
	 * @param TitleFactory $titleFactory
	 * @param WikiPageFactory $wikiPageFactory
	 */
	public function __construct( TitleFactory $titleFactory, WikiPageFactory $wikiPageFactory ) {
		$this->titleFactory = $titleFactory;
		$this->wikiPageFactory = $wikiPageFactory;
	}

	/**
	 * @inheritDoc
	 */
	public function execute() {
		$error = '';

		$params = $this->getValidatedParams();

		$prefixedText = $this->unmaskPageTitle( $params['pagePrefixedText'] );

		$wikiPageContent = $this->getWikiPageContent( $prefixedText );
		$provisionContent = $this->getProvisionContent( $prefixedText );

		$diff = new Diff(
			explode( "\n", $wikiPageContent ),
			explode( "\n", $provisionContent )
		);

		if ( $diff->isEmpty() === false ) {
			$diffFormatter = new TableDiffFormatter();

			$diffHtml = '<table class="diff">';
			$diffHtml .= <<<HERE
<colgroup>
<col class="diff-marker">
<col class="diff-content">
<col class="diff-marker">
<col class="diff-content">
</colgroup>
HERE;
			$diffHtml .= $diffFormatter->format( $diff );
			$diffHtml .= '</table>';

			$diffHtml = $this->localiseLineNumbers( $diffHtml );
		} else {
			$error = Message::newFromKey( 'contentprovisioning-diff-no-change' )->text();
		}

		if ( $error ) {
			return $this->getResponseFactory()->createJson( [
				'success' => false,
				'error' => $error
			] );
		}

		return $this->getResponseFactory()->createJson( [
			'success' => true,
			'diffHtml' => $diffHtml
		] );
	}

	/**
	 * @param string $prefixedText
	 * @return string
	 */
	private function getWikiPageContent( string $prefixedText ): string {
		$title = $this->titleFactory->newFromText( $prefixedText );

		$wikiPage = $this->wikiPageFactory->newFromTitle( $title );

		/** @var TextContent $content */
		$content = $wikiPage->getContent();
		if ( !$content instanceof TextContent ) {
			return '';
		}

		return $content->getText();
	}

	/**
	 * @param string $prefixedText
	 * @return string
	 */
	private function getProvisionContent( string $prefixedText ): string {
		$enabledExtensions = array_keys( ExtensionRegistry::getInstance()->getAllThings() );
		$manifestListProvider = new StaticManifestProvider( $enabledExtensions, $GLOBALS['IP'] );

		$wikiPageManifests = $manifestListProvider->provideManifests( 'DefaultContentProvisioner' );

		foreach ( $wikiPageManifests as $absoluteManifestPath ) {
			$pagesList = json_decode( file_get_contents( $absoluteManifestPath ), true );

			if ( !isset( $pagesList[$prefixedText] ) ) {
				// There is no information about specified page in that manifest, let's look into next one
				continue;
			}

			$contentPath = $pagesList[$prefixedText]['content_path'];
			$absoluteContentPath = dirname( $absoluteManifestPath ) . $contentPath;

			return file_get_contents( $absoluteContentPath );
		}

		// In case if provision content was not found in any of manifests
		// Really should not happen actually...
		return '';
	}

	/**
	 * Replace line numbers with the text in the user's language.
	 *
	 * Almost complete copy of {@link \DifferenceEngine::localiseLineNumbers()}.
	 * But here we do not need all that huge class, so just copied specific method.
	 *
	 * @param string $text
	 *
	 * @return string
	 */
	private function localiseLineNumbers( $text ) {
		return preg_replace_callback(
			'/<!--LINE (\d+)-->/',
			static function ( array $matches ) {
				return Message::newFromKey( 'lineno' )->numParams( $matches[1] )->escaped();
			},
			$text
		);
	}
}
