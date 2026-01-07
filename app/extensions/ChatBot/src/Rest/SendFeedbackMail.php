<?php

namespace ChatBot\Rest;

use ChatBot\Model\ChatMessageFactory;
use Config;
use Exception;
use MailAddress;
use MediaWiki\Html\Html;
use MediaWiki\Message\Message;
use MediaWiki\Rest\SimpleHandler;
use TitleFactory;
use UserMailer;
use Wikimedia\ParamValidator\ParamValidator;

class SendFeedbackMail extends SimpleHandler {

	/** @var Config */
	private $config;

	/** @var TitleFactory */
	private $titleFactory;

	/** @var ChatMessageFactory */
	private $chatMessageFactory;

	/**
	 * @param Config $config
	 * @param TitleFactory $titleFactory
	 * @param ChatMessageFactory $chatMessageFactory
	 */
	public function __construct( Config $config, TitleFactory $titleFactory, ChatMessageFactory $chatMessageFactory ) {
		$this->config = $config;
		$this->titleFactory = $titleFactory;
		$this->chatMessageFactory = $chatMessageFactory;
	}

	/**
	 * @inheritDoc
	 */
	public function run() {
		$body = $this->getValidatedBody()['feedback'];

		$mails = [ new MailAddress( $this->config->get( 'BMBFFeedbackMail' ) ) ];
		$email = new MailAddress( $this->config->get( 'PasswordSender' ) );
		$mailBody = $this->getMailBody( $body );
		$status = UserMailer::send(
			$mails,
			$email,
			"[HdP-Chatbot] " . implode( ', ', $body['issue'] ),
			// Issue with PEAR/mime when sending array body
			$mailBody['html'],
			[
				'contentType' => 'text/html;charset=UTF-8',
				'headers' => [
					'Content-type' => 'text/html;charset=UTF-8'
				]
			]
		);
		if ( $status->isOK() ) {
			return $this->getResponseFactory()->create();
		}

		return $this->getResponseFactory()->createHttpError( 404, [ $status->getMessage()->plain() ] );
	}

	/**
	 * @return array[]
	 */
	public function getParamSettings() {
		return [
			'id' => [
				static::PARAM_SOURCE => 'path',
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
			'feedback' => [
				ParamValidator::PARAM_TYPE => 'array',
				ParamValidator::PARAM_REQUIRED => true,
				ParamValidator::PARAM_DEFAULT => ''
			],
		];
	}

	/**
	 * @param array $data
	 *
	 * @return array
	 * @throws Exception
	 */
	private function getMailBody( array $data ): array {
		$mailBody = Html::openElement( 'div' );

		$mailBody .= Html::element( 'h1', [], Message::newFromKey( 'chatbot-feedback-last' )->plain() );
		$mailBody .= $this->makeQuestionHtml( $data['lastAnswer'] );

		$mailBody .= Html::element( 'h2', [], Message::newFromKey( 'chatbot-feedback-mail-body-score' )->plain() );

		$score = Message::newFromKey( 'chatbot-feedback-mail-body-score-value-inaccurate' )->plain();
		if ( $data['score'] === 'FAIRLY_ACCURATE' ) {
			$score = Message::newFromKey( 'chatbot-feedback-mail-body-score-value-fairly-accurate' )->plain();
		}
		$mailBody .= Html::rawElement( 'span', [], $score );

		$mailBody .= Html::element( 'h2', [], Message::newFromKey( 'chatbot-feedback-mail-body-problems' )->plain() );
		$mailBody .= Html::element( 'span', [], implode( ', ', $data['issue'] ) );

		$mailBody .= Html::element( 'h2', [], Message::newFromKey( 'chatbot-feedback-mail-body-cause' )->plain() );
		$mailBody .= Html::element( 'span', [], $data['cause'] );

		$mailBody .= Html::element( 'h2', [], Message::newFromKey( 'chatbot-feedback-mail-body-desc' )->plain() );
		$mailBody .= Html::element( 'span', [], $data['comment'] );

		$mailBody .= Html::element( 'h2', [], Message::newFromKey( 'chat-popup-feedback-problem-mail' )->plain() );
		$mailBody .= Html::element( 'span', [], $data['mail'] );

		if ( $data['contextAnswers'] ) {
			$mailBody .= Html::element( 'h1', [], Message::newFromKey( 'chatbot-feedback-mail-context' )->plain() );
			$mailBody .= Html::openElement( 'div', [] );
			foreach ( $data['contextAnswers'] as $contextAnswer ) {
				$mailBody .= $this->makeQuestionHtml( $contextAnswer );
			}
			$mailBody .= Html::closeElement( 'div' );
		}

		$mailBody .= Html::closeElement( 'div' );

		return [
			'text' => strip_tags( $mailBody ),
			'html' => "<html><body>$mailBody</body></html>"
		];
	}

	/**
	 * @param array $data
	 * @return string
	 */
	private function getQueryMessage( array $data ): string {
		$query = $data['query'];
		if ( $data['followUpType'] ) {
			$query = $query . ' ' .
				Message::newFromKey( 'chatbot-feedback-mail-body-followup', $data['followUpType'] )->parse();
		}

		return $query;
	}

	/**
	 * @param array $data
	 *
	 * @return string
	 * @throws Exception
	 */
	private function makeQuestionHtml( array $data ): string {
		$html = Html::element( 'h3', [], Message::newFromKey( 'chatbot-feedback-mail-body-question' )->plain() );
		$html .= Html::element( 'span', [], $this->getQueryMessage( $data ) );

		$html .= Html::element( 'h3', [], Message::newFromKey( 'chatbot-feedback-mail-body-answer' )->plain() );
		$html .= Html::openElement( 'div', [] );

		$message = $this->chatMessageFactory->makeMessage( [
			'query' => [ 'query' => $data['query'], 'type' => 'normal' ],
			'answer' => $data['answer'],
			'references' => $data['references'],
			'time' => ''
		] );
		$html .= $message->getAnswer();
		$html .= Html::closeElement( 'div' );

		$referencesList = Html::openElement( 'ul' );
		foreach ( $data['references' ] as $ref ) {
			$title = $this->titleFactory->newFromText( $ref[ 'meta' ]['prefixed_title'] );
			$listItem = Html::openElement( 'li' );
			$listItem .= Html::element( 'span', [], '[' . $ref['docRefId'] . '] - ' );
			$listItem .= Html::element( 'a', [
				'href' => $title->getFullURL()
			], $title->getText() );
			$listItem .= Html::closeElement( 'li' );
			$referencesList .= $listItem;
		}
		$referencesList .= Html::closeElement( 'ul' );

		$html .= Html::element( 'h3', [], Message::newFromKey( 'chatbot-feedback-mail-body-references' )->plain() );
		$html .= Html::rawElement( 'div', [], $referencesList );

		return $html;
	}

}
