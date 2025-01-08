<?php

namespace ChatBot\HookHandler;

use ChatBot\AdminModule\Stats;
use ChatBot\AdminModuleFactory;
use MediaWiki\Hook\ParserFirstCallInitHook;
use MWException;
use OOUI\MessageWidget;
use OutputPage;
use Parser;

class AddTags implements ParserFirstCallInitHook {

	/** @var AdminModuleFactory */
	private $adminModuleFactory;

	/**
	 * @param AdminModuleFactory $adminModuleFactory
	 */
	public function __construct( AdminModuleFactory $adminModuleFactory ) {
		$this->adminModuleFactory = $adminModuleFactory;
	}

	/**
	 * @param Parser $parser
	 * @return void
	 * @throws MWException
	 */
	public function onParserFirstCallInit( $parser ) {
		$parser->setHook( 'chatbotstats', [ $this, 'renderChatBotStats' ] );
	}

	/**
	 * @param string $input
	 * @param array $args
	 * @param Parser $parser
	 * @param \PPFrame $frame
	 * @return MessageWidget|string
	 */
	public function renderChatBotStats( $input, array $args, $parser, $frame ) {
		$type = $args['type'] ?? 'default';
		$module = $this->adminModuleFactory->getModule( 'stats' );
		if ( !$module instanceof Stats ) {
			return '';
		}
		OutputPage::setupOOUI();
		$rl = $module->getRLModules();
		$parser->getOutput()->addModules( $rl );
		if ( $type === 'default' ) {
			return $module->getHtml();
		}
		return $module->renderStatType( $type, false );
	}
}
