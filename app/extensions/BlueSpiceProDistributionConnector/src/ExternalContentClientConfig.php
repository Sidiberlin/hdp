<?php

namespace BlueSpice\ProDistributionConnector;

class ExternalContentClientConfig {

	/**
	 * Provides regexes to match pasted URLs for external content URLs
	 *
	 * @return array
	 */
	public static function getSupportedDomainsForPaste(): array {
		if ( defined( 'MW_QUIBBLE_CI' ) ) {
			return [ 'whitelist' => [], 'bitbucket' => [] ];
		}

		$whitelist = $GLOBALS['wgExternalContentDomainWhitelist'];
		if ( empty( $whitelist ) ) {
			$whitelist = [
				'gitlab.com',
				'github.com',
				'bitbucket.org',
			];
		}
		$whitelist = array_map( static function ( $domain ) {
			$domain = preg_quote( $domain );
			return "^(^https?:\/\/(www\.)?|)$domain(?:\/|$)";
		}, $whitelist );

		return [
			// General regexes to determine if the pasted URL is to be converted to an external content PF
			'whitelist' => $whitelist,
			// Dedicated regex(es) for matching bit bucket URLs (for `#bitbucket` PF)
			'bitbucket' => [ '^(^https?:\/\/(www\.)?|)bitbucket\.org(?:\/|$)' ]
		];
	}
}
