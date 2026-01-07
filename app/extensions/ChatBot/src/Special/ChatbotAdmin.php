<?php

namespace ChatBot\Special;

use ChatBot\AdminModuleFactory;
use ChatBot\IAdminModule;
use MediaWiki\Html\Html;
use MediaWiki\SpecialPage\SpecialPage;

// INACTIVE - NOT NEEDED
class ChatbotAdmin extends SpecialPage {

	/** @var AdminModuleFactory */
	protected $adminModuleFactory;

	/**
	 * @param AdminModuleFactory $adminModuleFactory
	 */
	public function __construct( AdminModuleFactory $adminModuleFactory ) {
		parent::__construct( 'ChatBotAdmin', 'wikiadmin', false );
		$this->adminModuleFactory = $adminModuleFactory;
	}

	/**
	 * @param string $subPage
	 * @return void
	 */
	public function execute( $subPage ) {
		parent::execute( $subPage );

		$this->getOutput()->enableOOUI();
		$rlModules = [];
		$html = '';
		foreach ( $this->adminModuleFactory->getModules() as $key => $module ) {
			$rlModules = array_merge( $rlModules, $module->getRLModules() );
			$html .= $this->renderModule( $module, $key );
		}

		$this->getOutput()->addModules( $rlModules );
		$this->getOutput()->addHTML( $html );
	}

	/**
	 * @param IAdminModule $module
	 * @param string $key
	 * @return string
	 */
	protected function renderModule( IAdminModule $module, string $key ): string {
		$html = Html::openElement(
			'div', [
				'class' => 'chatbot-admin-module', 'id' => 'chatbot-admin-module-' . $key
			] );
		$html .= Html::element( 'h3', [], $module->getLabel()->text() );
		$description = $module->getDescription();
		if ( $description ) {
			$html .= Html::element( 'p', [], $description->text() );
		}
		$html .= $module->getHtml();
		$html .= Html::closeElement( 'div' );

		return $html;
	}
}
