<?php

namespace AtMentions\Hook;

use AtMentions\Mention;
use AtMentions\MentionStore;
use MediaWiki\Hook\ParserFirstCallInitHook;
use MediaWiki\Html\Html;
use MediaWiki\Language\Language;
use MediaWiki\Linker\LinkRenderer;
use MediaWiki\Message\Message;
use MediaWiki\Parser\Parser as MWParser;
use MediaWiki\User\UserFactory;

class ProcessTags implements ParserFirstCallInitHook {

	/** @var LinkRenderer */
	private $linkRenderer;
	/** @var UserFactory */
	private $userFactory;
	/** @var MentionStore */
	private $mentionStore;

	/**
	 * @param UserFactory $userFactory
	 * @param MentionStore $mentionStore
	 * @param LinkRenderer $linkRenderer
	 */
	public function __construct( UserFactory $userFactory, MentionStore $mentionStore, LinkRenderer $linkRenderer ) {
		$this->userFactory = $userFactory;
		$this->mentionStore = $mentionStore;
		$this->linkRenderer = $linkRenderer;
	}

	/**
	 * @param MWParser $parser
	 *
	 * @return bool|void
	 */
	public function onParserFirstCallInit( $parser ): bool {
		$parser->setHook( 'mentionslist', [ $this, 'render' ] );
		return true;
	}

	/**
	 * @param string $content
	 * @param array $attributes
	 * @param MWParser $parser
	 *
	 * @return string
	 */
	public function render( $content, $attributes, $parser ) {
		$username = $attributes['user'];
		if ( $username ) {
			$user = $this->userFactory->newFromName( $username );
		}
		if ( !$user ) {
			$user = $parser->getUserIdentity();
		}
		if ( !$user->isRegistered() ) {
			return '';
		}
		$days = 90;
		if ( isset( $attributes['days' ] ) ) {
			$days = intval( $attributes['days'] );
		}
		if ( $user->getName() === $parser->getUserIdentity()->getName() ) {
			if ( $days !== -1 ) {
				$headerMsg = $this->msg( 'at-mentions-mentionslist-header-mine' )->params( $days );
			} else {
				$headerMsg = $this->msg( 'at-mentions-mentionslist-header-mine-all' );
			}
		} else {
			if ( $days !== -1 ) {
				$headerMsg = $this->msg( 'at-mentions-mentionslist-header' )->params( $days, $user );
			} else {
				$headerMsg = $this->msg( 'at-mentions-mentionslist-header-all' );
			}
		}

		$this->mentionStore->forUser( $user );
		if ( $days !== -1 ) {
			$this->mentionStore->since( new \DateInterval( "P{$days}D" ) );
		}
		$mentions = $this->mentionStore->query();

		$html = Html::rawElement( 'p', [], $headerMsg->parse() );
		$html .= Html::openElement( 'ul' );
		foreach ( $mentions as $mention ) {
			$html .= $this->getItem( $mention, $parser->getContentLanguage() );
		}
		return $html;
	}

	/**
	 * @param Mention $mention
	 * @param Language $language
	 *
	 * @return string
	 */
	public function getItem( Mention $mention, Language $language ): string {
		$title = $mention->getTitle();
		$link = $this->linkRenderer->makeLink( $title );
		$timestamp = $mention->getTimestamp();
		$timestamp = $language->userTimeAndDate( $timestamp, $mention->getUser() );

		$author = $mention->getActor();
		$html = Html::openElement( 'li', [ 'class' => 'mention-item' ] );
		$html .= Html::rawElement( 'span', [], $link . ' ' );
		$html .= Html::rawElement(
			'span', [],
			$this->msg( 'at-mentions-tag-item-actor' )->params( $author )->parse() . ' '
		);
		$html .= Html::rawElement(
			'span', [],
			$this->msg( 'at-mentions-tag-item-time' )->params( $timestamp )->parse()
		);
		$html .= Html::closeElement( 'li' );
		return $html;
	}

	/**
	 * @param string $key
	 *
	 * @return Message
	 */
	private function msg( $key ): Message {
		return Message::newFromKey( $key );
	}
}
