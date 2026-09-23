<?php

namespace SMW\MediaWiki\Specials;

use MediaWiki\MediaWikiServices;
use MediaWiki\Skin\SkinComponentUtils;
use MediaWiki\SpecialPage\SpecialPage;
use SMW\Exporter\Escaper;

/**
 * Resolve (redirect) pretty URIs (or "short URIs") to the equivalent full MediaWiki
 * representation.
 *
 * @license GPL-2.0-or-later
 * @since 1.0
 *
 * @author Denny Vrandecic
 */
class SpecialURIResolver extends SpecialPage {

	/**
	 * @see SpecialPage::__construct
	 */
	public function __construct() {
		parent::__construct( 'URIResolver', '', false );
	}

	/**
	 * @see SpecialPage::execute
	 *
	 * @param string $query string
	 */
	public function execute( $query ) {
		$out = $this->getOutput();

		// #2344, It is believed that when no HTTP_ACCEPT is available then a
		// request came from a "defect" mobile device without a correct accept
		// header
		if ( !isset( $_SERVER['HTTP_ACCEPT'] ) ) {
			$_SERVER['HTTP_ACCEPT'] = '';
		}

		if ( $query === null || trim( $query ) === '' ) {
			if ( stristr( $_SERVER['HTTP_ACCEPT'], 'RDF' ) ) {
				$out->redirect( SpecialPage::getTitleFor( 'ExportRDF' )->getFullURL( [ 'stats' => '1' ] ), '303' );
			} else {
				$this->setHeaders();
				$out->addHTML(
					'<p>' .
						$this->msg( 'smw_uri_doc', 'https://www.w3.org/2001/tag/issues.html#httpRange-14' )->parse() .
					'</p>'
				);
			}
		} else {
			$query = Escaper::decodeUri( $query );
			$query = str_replace( '_', '%20', $query );
			$query = urldecode( $query );
			$title = MediaWikiServices::getInstance()->getTitleFactory()->newFromText( $query );

			// In case the title doesn't exist throw an error page
			if ( $title === null ) {
				$out->showErrorPage( 'badtitle', 'badtitletext' );
			} elseif ( stristr( $_SERVER['HTTP_ACCEPT'], 'RDF' ) ) {
				$out->redirect(
					SkinComponentUtils::makeSpecialUrl( 'ExportRDF', [ 'xmlmime' => 'rdf' ] )
				);
			} else {
				$targetUrl = $title->getFullURL();

				// HDP: backport of upstream SMW 7.2.0 (CVE-2026-77609). $title is
				// resolved from a user-controlled subpage (an interwiki prefix can
				// make it point off-host, and a resolved authority can even carry
				// user:pass@host credentials), so the target must be validated
				// against the current host before redirecting rather than trusted
				// because it came from Title::getFullURL().
				if ( $this->isLocalRedirectTarget( $targetUrl ) ) {
					$out->redirect( $targetUrl, '303' );
				} else {
					$out->showErrorPage( 'badtitle', 'badtitletext' );
				}
			}
		}
	}

	/**
	 * HDP: backport of upstream SMW 7.2.0 (CVE-2026-77609).
	 *
	 * Whether a resolved redirect target is safe to hand to the browser: it
	 * must resolve to the current host. Validated at the sink (the final URL),
	 * not the input path, so an authority-like path cannot smuggle a foreign
	 * host — including one embedding user:pass@ credentials — past
	 * normalisation.
	 *
	 * @since 7.2.0
	 */
	private function isLocalRedirectTarget( $url ) {
		$urlUtils = MediaWikiServices::getInstance()->getUrlUtils();

		$targetBits = $urlUtils->parse( (string)$urlUtils->expand( $url, PROTO_CURRENT ) );
		$serverBits = $urlUtils->parse( (string)$urlUtils->expand( '/', PROTO_CURRENT ) );

		if ( $targetBits === null || $serverBits === null ) {
			return false;
		}

		$targetHost = $targetBits['host'] ?? '';
		$serverHost = $serverBits['host'] ?? '';

		// Host comparison only; port is intentionally out of scope.
		return $targetHost !== '' && $serverHost !== '' && strcasecmp( $targetHost, $serverHost ) === 0;
	}

	/**
	 * @see SpecialPage::getGroupName
	 */
	protected function getGroupName() {
		return 'smw_group';
	}

}
