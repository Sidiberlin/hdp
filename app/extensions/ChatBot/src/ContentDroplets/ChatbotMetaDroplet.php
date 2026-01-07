<?php

namespace ChatBot\ContentDroplets;

use MediaWiki\Extension\ContentDroplets\Droplet\TemplateDroplet;
use MediaWiki\Message\Message;

class ChatbotMetaDroplet extends TemplateDroplet {

		/**
		 * Get target for the template
		 * @return string
		 */
	protected function getTarget(): string {
		return 'ChatbotMeta';
	}

	/**
	 * Template params
	 * @return array
	 */
	protected function getParams(): array {
		return [
			'meta' => ''
		];
	}

	/**
	 * @inheritDoc
	 */
	public function getName(): Message {
		return Message::newFromKey( 'chatbot-droplet-meta-name' );
	}

	/**
	 * @inheritDoc
	 */
	public function getDescription(): Message {
		return Message::newFromKey( 'chatbot-droplet-meta-description' );
	}

	/**
	 * @inheritDoc
	 */
	public function getIcon(): string {
		return 'droplet-chatbot';
	}

	/**
	 * @inheritDoc
	 */
	public function getRLModules(): array {
		return [ 'ext.chatbot.meta' ];
	}

	/**
	 * @return array
	 */
	public function getCategories(): array {
		return [ 'content' ];
	}
}
