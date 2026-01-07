<?php

namespace BlueSpice\MatomoConnector;

use Exception;
use MediaWiki\Config\ConfigFactory;
use MediaWiki\Http\HttpRequestFactory;
use MediaWiki\Json\FormatJson;
use ObjectCacheFactory;

class CustomDimensions {

	private ?string $baseUrl;
	private ?string $siteId;
	private ?string $authToken;
	private array $defaultOptions;
	private array $requiredCustomDimensions;

	private ObjectCacheFactory $objectCacheFactory;
	private HttpRequestFactory $httpRequestFactory;

	public function __construct(
		ConfigFactory $configFactory, ObjectCacheFactory $objectCacheFactory, HttpRequestFactory $httpRequestFactory
	) {
		$config = $configFactory->makeConfig( 'bsg' );
		$baseUrl = $config->get( 'MatomoConnectorBaseUrl' ) ?? '';
		$this->baseUrl = rtrim( $baseUrl, '/' );
		$this->siteId = $config->get( 'MatomoConnectorSiteID' );
		$this->authToken = $config->get( 'MatomoConnectorAuthToken' );
		$this->defaultOptions = $config->get( 'HttpRequestDefaultOptions' ) ?? [];
		$this->requiredCustomDimensions = $config->get( 'MatomoConnectorCustomDimensionIDMap' );
		$this->objectCacheFactory = $objectCacheFactory;
		$this->httpRequestFactory = $httpRequestFactory;
	}

	/**
	 * Fetches custom dimension IDs from the Matomo HTTP API
	 *
	 * @return array<string,int> e.g. [ 'page_namespace' => 1, 'page_categories' => 2, 'user_groups' => 3 ]
	 */
	public function getCustomDimensionIDs(): array {
		$url = "{$this->baseUrl}/index.php";
		$params = [
			'module' => 'API',
			'method' => 'CustomDimensions.getConfiguredCustomDimensions',
			'idSite' => $this->siteId,
			'format' => 'json',
			'token_auth' => $this->authToken
		];

		$options = array_merge(
			$this->defaultOptions,
			[ 'postData' => $params ]
		);

		$objectCache = $this->objectCacheFactory->getLocalServerInstance( CACHE_DB );
		$cacheKey = md5( FormatJson::encode( $options ) );
		$fname = __METHOD__;

		return $objectCache->getWithSetCallback(
			$objectCache->makeKey( 'bluespicematomoconnector-getdimensionids', $cacheKey ),
			$objectCache::TTL_DAY,
			function () use ( $url, $options, $fname ) {
				try {
					$response = $this->httpRequestFactory->post(
						$url,
						$options,
						$fname
					);
					$data = FormatJson::decode( $response, true );

					$mapped = [];
					foreach ( $data as $dimension ) {
						if ( isset( $dimension['name'] ) && isset( $dimension['idcustomdimension'] ) ) {
							$mapped[$dimension['name']] = (int)$dimension['idcustomdimension'];
						}
					}

					return $mapped;
				} catch ( Exception $e ) {
					return [];
				}
			}
		);
	}

	/**
	 * Ensures custom dimensions exist in Matomo, creating them if needed.
	 * Does nothing if bsgMatomoConnectorAuthToken is not set.
	 */
	public function checkAndCreateCustomDimensions(): void {
		if ( !$this->authToken ) {
			return;
		}

		$existingDimensions = $this->getCustomDimensionIDs();

		foreach ( $this->requiredCustomDimensions as $name => $id ) {
			if ( !isset( $existingDimensions[$name] ) ) {
				$this->configureNewCustomDimension( $name );
			}
		}
	}

	/**
	 * @param string $name
	 * @param string $scope 'visit' or 'action'
	 * @return int|null The ID of the configured dimension, or null on failure.
	 */
	public function configureNewCustomDimension( string $name, string $scope = 'visit' ): ?int {
		$url = "{$this->baseUrl}/index.php";
		$params = [
			'module' => 'API',
			'method' => 'CustomDimensions.configureNewCustomDimension',
			'format' => 'json',
			'token_auth' => $this->authToken,
			'idSite' => $this->siteId,
			'name' => $name,
			'scope' => $scope,
			'active' => 1,
		];

		$options = array_merge(
			$this->defaultOptions,
			[ 'postData' => $params ]
		);

		try {
			$response = $this->httpRequestFactory->post(
				$url,
				$options,
				__METHOD__
			);
			$data = FormatJson::decode( $response, true );

			if ( is_numeric( $data ) ) {
				return (int)$data;
			} else {
				return null;
			}
		} catch ( Exception $e ) {
			return null;
		}
	}
}
