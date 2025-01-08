<?php

namespace ChatBot\Model;

use DateTime;
use MediaWiki\MediaWikiServices;
use Message;

class ChatMessage {
	/**
	 * @var array
	 */
	private array $query;
	/**
	 * @var string
	 */
	private string $answer;
	/**
	 * @var array
	 */
	private array $references;
	/**
	 * @var string
	 */
	private string $session_id;
	/**
	 * @var DateTime
	 */
	private DateTime $date;

	/**
	 * @param string $query
	 * @param string $answer
	 * @param array $references
	 * @param string $session_id
	 * @param string $time
	 *
	 * @throws \Exception
	 */
	private function __construct(
		array $query,
		string $answer,
		array $references,
		string $session_id,
		string $time
	) {
		$this->query = $query;
		$this->answer = $answer;
		$this->references = $references;
		$this->session_id = $session_id;
		$this->date = new DateTime( $time );
	}

	/**
	 * @param array $message
	 *
	 * @return ChatMessage
	 * @throws \Exception
	 */
	public static function fromMessageJson( array $message ): ChatMessage {
		return new ChatMessage(
			$message['query'], $message['answer'], $message['references'],
			$message['session_id'] ?? '', $message['time'],
		);
	}

	/**
	 * @return string
	 */
	public function getQuery(): string {
		$query = $this->query['query'];
		if ( $this->getFollowUpType() ) {
			$query .= ' ' .
				Message::newFromKey( 'chatbot-feedback-mail-body-followup', $this->getFollowUpType() )->parse();
		}
		return $query;
	}

	/**
	 * @return string
	 */
	public function getType(): string {
		return $this->query['type'];
	}

	/**
	 * @return string|null
	 */
	private function getFollowUpType(): ?string {
		if ( $this->getType() === 'followUp' ) {
			return $this->query['followUpType'];
		}
		return null;
	}

	/**
	 * @return string
	 */
	public function getAnswer(): string {
		$urlUtils = MediaWikiServices::getInstance()->getUrlUtils();
		$dom = new \DOMDocument();
		$dom->loadHTML( '<html><head><meta charset=\"UTF-8\"></head><body>' . $this->answer . '</body></html>' );
		$links = $dom->getElementsByTagName( 'a' );
		foreach ( $links as $link ) {
			$link->setAttribute( 'href', $urlUtils->expand( $link->getAttribute( 'href' ) ) );
		}
		return $dom->saveHTML( $dom->getElementsByTagName( 'body' )->item( 0 ) );
	}

	/**
	 * @return array
	 */
	public function getReferences(): array {
		return $this->references;
	}

	/**
	 * @return string
	 */
	public function getSessionId(): string {
		return $this->session_id;
	}

	/**
	 * @return string
	 */
	public function getDate(): string {
		return $this->date->format( 'Y-m-d H:i:s' );
	}
}
