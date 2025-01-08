<?php

namespace ChatBot\Rest;

use BlueSpice\UEModulePDF\PDFServletHookRunner;
use BsPDFServlet;
use ChatBot\Model\ChatMessage;
use ChatBot\Model\ChatMessageFactory;
use Config;
use ConfigFactory;
use DateTime;
use DOMDocument;
use DOMDocumentFragment;
use DOMException;
use Html;
use MediaWiki\Rest\Response;
use MediaWiki\Rest\SimpleHandler;
use MediaWiki\Rest\Validator\JsonBodyValidator;
use Message;
use MWException;
use TitleFactory;
use Wikimedia\ParamValidator\ParamValidator;

class CreateChatPdf extends SimpleHandler {
	/** @var ChatMessageFactory */
	private ChatMessageFactory $chatMessageFactory;
	/** @var Config */
	private Config $config;
	/** @var TitleFactory */
	private TitleFactory $titleFactory;

	/**
	 * @param ChatMessageFactory $chatMessageFactory
	 * @param ConfigFactory $configFactory
	 * @param TitleFactory $titleFactory
	 */
	public function __construct(
		ChatMessageFactory $chatMessageFactory,
		ConfigFactory $configFactory,
		TitleFactory $titleFactory
	) {
		$this->chatMessageFactory = $chatMessageFactory;
		$this->config = $configFactory->makeConfig( 'bsg' );
		$this->titleFactory = $titleFactory;
	}

	/**
	 * @return Response
	 * @throws MWException
	 * @throws DOMException
	 */
	public function execute() {
		$history = $this->getValidatedBody()['history'];
		$chatMessages = $this->chatMessageFactory->makeMessages( $history );
		$filename = $this->getValidatedParams()['filename'];

		$doc = $this->createDomDocument( $chatMessages );
		$pdfByteArray = $this->createPdfByteArray( $doc, $filename );

		$response = $this->getResponseFactory()->create();
		$response->setHeader( 'Content-Type', 'application/pdf' );
		$response->setHeader( 'Content-Disposition', 'attachment; filename=' . $filename );
		$response->getBody()->write( $pdfByteArray );

		return $response;
	}

	/**
	 * @param ChatMessage[] $chatMessages
	 *
	 * @return DOMDocument
	 * @throws DOMException
	 */
	private function createDomDocument( array $chatMessages ): DOMDocument {
		$doc = new \DOMDocument();
		$html = $doc->createElement( 'html' );
		$doc->appendChild( $html );

		$head = $doc->createElement( 'head' );
		$html->appendChild( $head );

		$title = $doc->createElement( 'title', 'Export aus HdP-Chatbot' );
		$head->appendChild( $title );

		$body = $doc->createElement( 'body' );
		$html->appendChild( $body );

		$headline = $doc->createElement( 'p' );
		$headline->textContent = Message::newFromKey( 'chat-pdf-title' )->text();
		$body->appendChild( $headline );

		$dataTable = $this->getDataTableHtml( $doc );
		$body->appendChild( $dataTable );

		$chatheading = $doc->createElement( 'p' );
		$chatheading->textContent = 'Vollständiger Chatverlauf';
		$body->appendChild( $chatheading );

		$content = $doc->createDocumentFragment();
		$chatTable = Html::openElement( 'table', [
			'style' => 'border-collapse: collapse;'
		] );
		foreach ( $chatMessages as $message ) {
			$queryRow = Html::openElement( 'tr', [] );
			$queryRow .= Html::element( 'td', [
				'style' => 'border: 1px solid black; padding: 2px;'
			], $message->getQuery() );
			$queryRow .= Html::closeElement( 'tr' );
			$chatTable .= $queryRow;

			$answerRow = Html::openElement( 'tr', [] );
			$answerRow .= Html::openElement( 'td', [
				'style' => 'border: 1px solid black; padding: 2px;'
			] );

			$answerHtml = Html::openElement( 'div', [] );
			$answerHtml .= $message->getAnswer();
			$answerHtml .= Html::closeElement( 'div' );
			$answerRow .= $answerHtml;

			$references = $message->getReferences();
			if ( count( $references ) > 0 ) {
				foreach ( $references as $ref ) {
					$title = $this->titleFactory->newFromText( $ref['meta']['prefixed_title'] );

					$refHtml = Html::openElement( 'p', [
						'style' => 'font-size: 10pt'
					] );
					$refNumber = Html::element( 'span', [], '[' . $ref['docRefId'] . '] - ' );
					$refHtml .= $refNumber;

					$refText = Html::element( 'a', [
						'href' => $title->getFullURL()
					], $title->getText() );
					$refHtml .= $refText;
					$refHtml .= Html::closeElement( 'p' );

					$answerRow .= $refHtml;
				}
			}

			$date = Html::element( 'small', [], $message->getDate() );
			$answerRow .= $date;
			$answerRow .= Html::closeElement( 'td' );
			$answerRow .= Html::closeElement( 'tr' );

			$chatTable .= $answerRow;
		}
		$chatTable .= Html::closeElement( 'table' );
		$content->appendXML( $chatTable );
		$body->appendChild( $content );

		$banner = $this->getBannerHtml( $doc );
		$body->appendChild( $banner );

		return $doc;
	}

	/**
	 * @param DOMDocument $doc
	 * @param string $filename
	 *
	 * @return string
	 * @throws MWException
	 */
	private function createPdfByteArray( DOMDocument $doc, string $filename ): string {
		$hookContainer = $this->getHookContainer();
		$hookRunner = new PDFServletHookRunner( $hookContainer );

		if ( !$this->config->has( 'UEModulePDFPdfServiceURL' ) ) {
			throw new MWException( 'UEModulePDFPdfServiceURL is not set' );
		}

		$params = [
			'format' => 'pdf',
			'module' => 'pdf',
			'title' => 'Chat',
			'display-title' => 'Chat',
			'soap-service-url' => $this->config->get( 'UEModulePDFPdfServiceURL' ),
			'document-token' => md5( $filename ),
		];
		$backend = new BsPDFServlet( $params, $hookRunner );

		return $backend->createPDF( $doc );
	}

	/**
	 * @return array[]
	 */
	public function getParamSettings() {
		return [
			'filename' => [
				static::PARAM_SOURCE => 'query',
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => true,
			]
		];
	}

	/**
	 * @param string $contentType
	 *
	 * @return JsonBodyValidator
	 */
	public function getBodyValidator( $contentType ) {
		if ( $contentType !== 'application/json' ) {
			return null;
		}

		return new JsonBodyValidator( [
			'history' => [
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => true,
				ParamValidator::PARAM_DEFAULT => ''
			],
		] );
	}

	/**
	 *
	 * @param DomDocument $doc
	 *
	 * @return DOMDocumentFragment
	 */
	private function getBannerHtml( $doc ) {
		// To have wikitext parsable its necessary to create a fragment here - ERM37507
		$fragment = $doc->createDocumentFragment();
		$bannerDiv = Html::openElement( 'div', [
			'style' => 'margin: 20px 0;'
		] );
		$bannerDiv .= Message::newFromKey( 'chat-pdf-banner-text' )->parse();
		$bannerDiv .= Html::closeElement( 'div' );
		$fragment->appendXML( $bannerDiv );

		return $fragment;
	}

	/**
	 *
	 * @param DomDocument $doc
	 *
	 * @return DOMDocumentFragment
	 */
	private function getDataTableHtml( $doc ) {
		$mainPageTitle = $this->titleFactory->newMainPage();
		$fragment = $doc->createDocumentFragment();
		$table = Html::openElement( 'table', [
			'style' => 'border-collapse: collapse; width: 100%; margin: 20px 0;'
		] );
		$table .= Html::openElement( 'tr', [] );
		$table .= Html::element( 'td', [
			'style' => 'border: 1px solid black; padding: 2px;'
		], 'Abfragedatum' );
		$date = new DateTime( 'now' );
		$table .= Html::element( 'td', [
			'style' => 'border: 1px solid black; padding: 2px;'
		], $date->format( 'd.m.Y' ) );
		$table .= Html::closeElement( 'tr' );

		$table .= Html::openElement( 'tr', [
			'style' => 'width: 100%;'
		] );
		$table .= Html::element( 'td', [
			'style' => 'border: 1px solid black; padding: 2px;'
		], 'Programmname' );
		$table .= Html::element( 'td', [
			'style' => 'border: 1px solid black; padding: 2px;'
		], 'Handbuch der Projektförderung (HdP)' );
		$table .= Html::closeElement( 'tr' );

		$table .= Html::openElement( 'tr', [
			'style' => 'width: 100%;'
		] );
		$table .= Html::element( 'td', [
			'style' => 'border: 1px solid black; padding: 2px;'
		], 'Homepage' );
		$table .= Html::element( 'td', [
			'style' => 'border: 1px solid black; padding: 2px;'
		], $mainPageTitle->getFullURL() );
		$table .= Html::closeElement( 'tr' );

		$table .= Html::openElement( 'tr', [
			'style' => 'width: 100%;'
		] );
		$table .= Html::element( 'td', [
			'style' => 'border: 1px solid black; padding: 2px;'
		], 'Technologieeinsatz' );
		$table .= Html::element( 'td', [
			'style' => 'border: 1px solid black; padding: 2px;'
		], 'KI im HdP, Chatbot' );
		$table .= Html::closeElement( 'tr' );
		$table .= Html::closeElement( 'table' );
		$fragment->appendXML( $table );

		return $fragment;
	}

	public function needsReadAccess() {
		return false;
	}
}
