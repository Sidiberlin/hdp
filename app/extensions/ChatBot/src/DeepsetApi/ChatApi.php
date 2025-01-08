<?php

namespace ChatBot\DeepsetApi;

use ChatBot\Util\RoleLookup;
use Config;
use Exception;
use GuzzleHttp\Psr7\Utils;
use MediaWiki\Http\HttpRequestFactory;
use RequestContext;

class ChatApi extends Connector {

	/** @var RoleLookup */
	private RoleLookup $roleLookup;

	/** @var int */
	private int $outputBufferLength;

	/**
	 * @param Config $config
	 * @param HttpRequestFactory $httpRequestFactory
	 * @param RoleLookup $roleLookup
	 */
	public function __construct( Config $config, HttpRequestFactory $httpRequestFactory, RoleLookup $roleLookup ) {
		parent::__construct( $config, $httpRequestFactory );
		$this->roleLookup = $roleLookup;
		$this->outputBufferLength = (int)ini_get( 'output_buffering' );
	}

	/**
	 * @return void
	 *
	 * Available Paths:
	 * "rag" = standardsuche
	 * "followup_short" = kurze antwort
	 * "followup_elaborate" = mehr informationen
	 * "followup_bulletpoints" = antwort in bulletpoints
	 * "followup_onlytext" = antwort in fließtext
	 * "followup_citations" = antwort mit hilfe von direkten Zitaten
	 *
	 * @throws Exception
	 */
	public function request( string $query, string $sessionId, ?string $followUpType = null ): void {
		ignore_user_abort( true ); // Stops PHP from checking for user disconnect
		connection_aborted(); // Checks if user has disconnected or not
		header( 'Content-Type: text/event-stream' );
		header( 'Cache-Control: no-cache' );
		header( 'Connection: keep-alive' );

		$followUpType = !empty( $followUpType ) ? $followUpType : 'rag';
		$body = [
			'search_session_id' => $sessionId,
			'query' => $query,
			'include_result' => true,
			'params' => [
				'ConditionalRouter' => [
					'path' => $followUpType,
				],
			]
		];

		$filter = $this->getFilter();
		if ( $filter ) {
			$body['filters'] = $filter;
		}

		$options = [
			'body' => json_encode( $body ),
			'stream' => true,
		];

		$response = $this->stream( "$this->apiUrl/chat-stream", $options );

		$body = $response->getBody();
		while ( !$body->eof() ) {
			$dataString = Utils::readline( $body );

			// Fill up the string to the buffer length
			if ( strlen( $dataString ) < $this->outputBufferLength ) {
				$dataString .= str_repeat( ' ', $this->outputBufferLength - strlen( $dataString ) );
			}

			echo $dataString . "\n";
			ob_flush();
			flush();
		}
	}


	/**
	 * Filter based on namespace and group permissions
	 *
	 * @return array|null
	 */
	private function getFilter(): ?array {
		$user = RequestContext::getMain()->getUser();

		if ( $this->roleLookup->isSysop( $user ) || $this->roleLookup->isMaintainer( $user ) ) {
			return null;
		}

		$filter = [ 'operator' => "AND" ];
		$conditions = [];
		$conditionTemplate = [
			'field' => "meta.namespace",
			'operator' => "!=",
			'value' => null
		];

		if ( !$this->roleLookup->isBMBF( $user ) ) {
			$conditionTemplate['value'] = NS_BMBF;
			$conditions[] = $conditionTemplate;

			$conditionTemplate['value'] = NS_BMBF_TALK;
			$conditions[] = $conditionTemplate;
		}

		if ( !$this->roleLookup->isProjectSponsor( $user ) ) {
			$conditionTemplate['value'] = NS_PT;
			$conditions[] = $conditionTemplate;

			$conditionTemplate['value'] = NS_PT_TALK;
			$conditions[] = $conditionTemplate;
		}

		$filter['conditions'] = $conditions;

		return $filter;
	}
}
