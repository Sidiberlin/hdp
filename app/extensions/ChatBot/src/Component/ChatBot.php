<?php

namespace ChatBot\Component;

use ChatBot\Util\RoleLookup;
use Config;
use IContextSource;
use Message;
use MWStake\MediaWiki\Component\CommonUserInterface\Component\Literal;
use TemplateParser;

class ChatBot extends Literal {
	/** @var Config */
	private Config $config;

	/** @var RoleLookup */
	private RoleLookup $roleLookup;

	/**
	 * @param Config $config
	 * @param RoleLookup $roleLookup
	 */
	public function __construct( Config $config, RoleLookup $roleLookup ) {
		$this->config = $config;
		$this->roleLookup = $roleLookup;
		parent::__construct(
			'chatbot',
			$this->getTemplateHtml()
		);
	}

	/**
	 * @inheritDoc
	 */
	public function getRequiredRLModules(): array {
		return [ "ext.chatbot", "ext.chatbot.attention.seeker" ];
	}

	/**
	 *
	 * @inheritDoc
	 */
	public function shouldRender( IContextSource $context ): bool {
		if ( !$context->getUser()->isAllowed( 'read' ) ) {
			return false;
		}
		$scriptPath = $context->getConfig()->get( 'ScriptPath' );

		$context->getOutput()->addJsConfigVars( [
			"bmbfApiChatUrl" => $scriptPath . '/rest.php/bmbf/chat',
			"bmbfApiSessionUrl" => $scriptPath . '/rest.php/bmbf/session',
			"bmbfApiHistoryUrl" => $scriptPath . '/rest.php/bmbf/history',
			"bmbfDeepsetApiFeedbackUrl" => $scriptPath . '/rest.php/bmbf/feedback',
			"bmbfFeedbackAnswerProblemSelection" => $this->config->get( 'BmbfFeedbackAnswerProblemSelection' ),
			"bmbfFeedbackAnswerCauseSelection" => $this->config->get( 'BmbfFeedbackAnswerCauseSelection' ),
			"bmbfRoles" => [
				'maintainer' => $this->roleLookup->isMaintainer( $context->getUser() )
			]
		] );

		return true;
	}

	/**
	 * @return string
	 */
	private function getTemplateHtml(): string {
		$templateParser = new TemplateParser(
			dirname( dirname( __DIR__ ) ) . '/resources/templates'
		);

		return $templateParser->processTemplate( 'Chat', [
			'headline' => Message::newFromKey( 'chat-headline-text' )->text(),
			'input_placeholder' => Message::newFromKey( 'chat-input-placeholder' )->text(),
			'send_button' => Message::newFromKey( 'chat-send-button-title' )->text(),
			'export_button' => Message::newFromKey( 'chat-export-button-title' )->text(),
			'resize_button' => Message::newFromKey( 'chat-resize-button-title' )->text(),
			'maximize_button' => Message::newFromKey( 'chat-maximize-button-title' )->text(),
			'minimize_button' => Message::newFromKey( 'chat-minimize-button-title' )->text(),
			'close_button' => Message::newFromKey( 'chat-close-button-title' )->text(),
			'restore_session' => Message::newFromKey( 'chat-restore-session-text' )->text(),
			'chat_banner_message' => Message::newFromKey( 'chat-banner-label' )->parse(),
			'logo_link' => \Title::newFromText( 'Chatbot-FAQ' )->getLocalURL(),
			'dismiss_error_button' => Message::newFromKey( 'chat-dismiss-error-button-title' )->text(),
			'banner_help_button' => Message::newFromKey( 'chat-banner-help-button' )->text(),
			'banner_close_button' => Message::newFromKey( 'chat-banner-close-button' )->text(),
			'disclaimer_footer' => Message::newFromKey( 'chat-footer-disclaimer' )->parse(),
		] );
	}
}
