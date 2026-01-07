<?php

namespace ChatBot\Rest;

use ChatBot\Model\ChatMessage;
use ChatBot\Model\ChatMessageFactory;
use DateTime;
use DOMDocument;
use DOMDocumentFragment;
use DOMException;
use Exception;
use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\PDFCreator\Factory\ExportSpecificationFactory;
use MediaWiki\Extension\PDFCreator\PDFCreator;
use MediaWiki\Extension\PDFCreator\Utility\ExportContext;
use MediaWiki\Html\Html;
use MediaWiki\Message\Message;
use MediaWiki\Rest\Response;
use MediaWiki\Rest\SimpleHandler;
use TitleFactory;
use Wikimedia\ParamValidator\ParamValidator;

class CreateChatPdf extends SimpleHandler {

	/**
	 * @param ChatMessageFactory $chatMessageFactory
	 * @param TitleFactory $titleFactory
	 * @param PDFCreator $pdfCreator
	 * @param ExportSpecificationFactory $specificationFactory
	 */
	public function __construct(
		private readonly ChatMessageFactory $chatMessageFactory,
		private readonly TitleFactory $titleFactory,
		private readonly PDFCreator $pdfCreator,
		private readonly ExportSpecificationFactory $specificationFactory
	) {
	}

	/**
	 * @return Response
	 * @throws DOMException
	 * @throws Exception
	 */
	public function execute() {
		$history = $this->getValidatedBody()['history'];
		$filename = $this->getValidatedParams()['filename'];

		$chatMessages = $this->chatMessageFactory->makeMessages( $history );
		$dom = $this->createDomDocument( $chatMessages );

		$result = $this->pdfCreator->create(
			$this->specificationFactory->createNewSpec( [
				'module' => 'chatbot-pdf-export',
				'target' => 'download',
				'params' => [
					'dom' => $dom,
					'template' => 'StandardPDF'
				],
			] ),
			new ExportContext(
				RequestContext::getMain()->getUser(), $this->titleFactory->newFromText( $filename )
			)
		);

		$exportResult = $result->getResult();
		$exportData = $exportResult->getData();
		$pdfData = $exportData['data'];

		$response = $this->getResponseFactory()->create();
		$response->setHeader( 'Content-Type', 'application/pdf' );
		$response->setHeader( 'Content-Disposition', 'attachment; filename=' . $filename );
		$response->setHeader( 'Content-Length', strlen( $pdfData ) );
		$response->setHeader( 'X-Filename', $filename );
		$response->getBody()->write( $pdfData );

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

		$wrapper = $doc->createElement( 'div', '' );

		$headline = $doc->createElement( 'p' );
		$headline->textContent = Message::newFromKey( 'chat-pdf-title' )->text();
		$wrapper->appendChild( $headline );

		$dataTable = $this->getDataTableHtml( $doc );
		$wrapper->appendChild( $dataTable );

		$chatheading = $doc->createElement( 'p' );
		$chatheading->textContent = 'Vollständiger Chatverlauf';
		$wrapper->appendChild( $chatheading );

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
		$wrapper->appendChild( $content );

		$banner = $this->getBannerHtml( $doc );
		$wrapper->appendChild( $banner );

		$body->appendChild( $wrapper );

		return $doc;
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
	 * @inheritDoc
	 */
	public function getBodyParamSettings(): array {
		return [
			'history' => [
				ParamValidator::PARAM_TYPE => 'array',
				ParamValidator::PARAM_REQUIRED => true,
				ParamValidator::PARAM_DEFAULT => ''
			]
		];
	}

	/**
	 *
	 * @param DomDocument $doc
	 *
	 * @return DOMDocumentFragment
	 */
	private function getBannerHtml( $doc ): DOMDocumentFragment {
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
