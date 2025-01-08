<?php

namespace ChatBot\Rest;

use ChatBot\Model\ChatMessage;
use ChatBot\Model\ChatMessageFactory;
use DateTime;
use MediaWiki\Rest\Response;
use MediaWiki\Rest\SimpleHandler;
use MediaWiki\Rest\Validator\JsonBodyValidator;
use Message;
use PhpOffice\PhpSpreadsheet\Style\Font;
use PhpOffice\PhpWord\Element\Section;
use PhpOffice\PhpWord\Exception\Exception;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Settings;
use PhpOffice\PhpWord\SimpleType\TblWidth;
use TitleFactory;
use Wikimedia\ParamValidator\ParamValidator;

class CreateChatOdf extends SimpleHandler {
	/** @var ChatMessageFactory */
	private ChatMessageFactory $chatMessageFactory;

	/** @var TitleFactory */
	private TitleFactory $titleFactory;

	/** @var array */
	private $tableStyle = [
		// Top padding
		'cellMargin' => 2000,
		// 100% width of the page (100 x 50 in twips)
		'width' => 100 * 50,
		'unit' => TblWidth::PERCENT,
		'alignment' => \PhpOffice\PhpWord\SimpleType\JcTable::CENTER,
	];

	/** @var array */
	private $cellStyle = [
		'borderSize' => 6,
		'borderColor' => '747474',
		'valign' => 'center',
		// 50% of the table width (50 x 50 in twips)
		'width' => 50 * 50,
		'unit' => TblWidth::PERCENT,
	];

	/**
	 * @param ChatMessageFactory $chatMessageFactory
	 * @param TitleFactory $titleFactory
	 */
	public function __construct(
		ChatMessageFactory $chatMessageFactory,
		TitleFactory $titleFactory
	) {
		$this->chatMessageFactory = $chatMessageFactory;
		$this->titleFactory = $titleFactory;
	}

	/**
	 * @return Response
	 * @throws Exception
	 */
	public function execute() {
		$history = $this->getValidatedBody()['history'];
		$chatMessages = $this->chatMessageFactory->makeMessages( $history );
		$filename = $this->getValidatedParams()['filename'];

		$dir = __DIR__ . '/../../../../cache';
		Settings::setTempDir( $dir );

		$doc = $this->createWordDocument( $chatMessages );
		$objWriter = IOFactory::createWriter( $doc, 'Word2007' );

		$temp_file = tempnam( $dir . '/' . $filename, 'PHPWord' );
		$objWriter->save( $temp_file, 'Word2007' );

		$response = $this->getResponseFactory()->create();
		$response->setHeader(
			'Content-Type', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
		);
		$response->setHeader( 'Content-Disposition', 'attachment; filename="' . $filename . '"' );
		$response->setHeader( "Content-Description", "File Transfer" );
		$response->setHeader( 'Content-Transfer-Encoding', 'binary' );
		$response->setHeader( 'Expires', ' 0' );
		$response->setHeader( 'Cache-Control', ' must-revalidate, post-check=0, pre-check=0' );
		$response->setHeader( 'Pragma', ' public' );
		$response->setHeader( 'Content-Length', filesize( $temp_file ) );

		$response->getBody()->write( file_get_contents( $temp_file ) );

		return $response;
	}

	/**
	 * @param ChatMessage[] $chatMessages
	 *
	 * @return PhpWord
	 */
	private function createWordDocument( array $chatMessages ): PhpWord {
		$phpWord = new PhpWord();
		$phpWord->setDefaultParagraphStyle(
			[
				'alignment' => \PhpOffice\PhpWord\SimpleType\Jc::BOTH,
				'spaceAfter' => \PhpOffice\PhpWord\Shared\Converter::pointToTwip( 12 ),
				'spacing' => 80,
			]
		);

		$section = $phpWord->addSection();
		$section->addTitle( Message::newFromKey( 'chat-pdf-title' )->text() );
		$section->addTextBreak( 1 );
		$section = $this->getDataTable( $section );
		$section->addTextBreak( 1 );
		$section->addText( 'Vollständiger Chatverlauf:' );
		$section->addTextBreak( 1 );

		$table = $section->addTable( 'FullWidthStyle', $this->tableStyle );
		foreach ( $chatMessages as $message ) {
			$table->addRow();
			$queryCell = $table->addCell( null, $this->cellStyle );
			$queryCell->addText( $message->getQuery() );

			$table->addRow();
			$answerCell = $table->addCell( null, $this->cellStyle );
			\PhpOffice\PhpWord\Shared\Html::addHtml( $answerCell, $message->getAnswer(), false, false );

			$references = $message->getReferences();
			if ( count( $references ) > 0 ) {
				foreach ( $references as $ref ) {
					$title = $this->titleFactory->newFromText( $ref['meta']['prefixed_title'] );
					$refText = '[' . $ref['docRefId'] . '] - ' . $title->getText();

					$listItem = $answerCell->addListItemRun();
					$listItem->addLink(
						$title->getFullURL(),
						$refText,
						[
							'color' => '0000FF',
							'underline' => Font::UNDERLINE_SINGLE
						]
					);
				}
			}
			$answerCell->addText( $message->getDate() );
		}

		$section->addText( Message::newFromKey( 'chat-pdf-banner-text' )->text() );
		return $phpWord;
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
	 * @param Section $section
	 * @return Section
	 */
	private function getDataTable( $section ) {
		$table = $section->addTable( 'FullWidthTable', $this->tableStyle );
		$table->addRow();
		$date = new DateTime( 'now' );
		$table->addCell( null, $this->cellStyle )->addText( "Abfragedatum" );
		$table->addCell( null, $this->cellStyle )->addText( $date->format( 'd.m.Y' ) );

		$table->addRow();
		$table->addCell( null, $this->cellStyle )->addText( "Bearbeitername, -vorname" );
		$table->addCell( null, $this->cellStyle );

		$table->addRow();
		$table->addCell( null, $this->cellStyle )->addText( "Ort" );
		$table->addCell( null, $this->cellStyle );

		$table->addRow();
		$table->addCell( null, $this->cellStyle )->addText( "Programmname" );
		$table->addCell( null, $this->cellStyle )->addText( 'Handbuch der Projektförderung (HdP)' );

		$mainPageTitle = $this->titleFactory->newMainPage();
		$table->addRow();
		$table->addCell( null, $this->cellStyle )->addText( "Homepage" );
		$table->addCell( null, $this->cellStyle )->addText( $mainPageTitle->getFullURL() );

		$table->addRow();
		$table->addCell( null, $this->cellStyle )->addText( "Technologieeinsatz" );
		$table->addCell( null, $this->cellStyle )->addText( 'KI im HdP, Chatbot' );

		return $section;
	}

	public function needsReadAccess() {
		return false;
	}
}
