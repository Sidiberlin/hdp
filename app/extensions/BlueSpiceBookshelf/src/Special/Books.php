<?php

namespace BlueSpice\Bookshelf\Special;

use MediaWiki\Html\TemplateParser;
use MediaWiki\SpecialPage\SpecialPage;

class Books extends SpecialPage {

	/** @var TemplateParser */
	private $templateParser;

	/**
	 *
	 */
	public function __construct() {
		parent::__construct( 'Books', 'bookshelf-viewspecialpage' );

		$this->templateParser = new TemplateParser(
			dirname( __DIR__, 2 ) . '/resources/templates'
		);
	}

	/**
	 *
	 * @param string $subPage
	 * @return void
	 */
	public function execute( $subPage ) {
		$this->setHeaders();

		$out = $this->getOutput();
		$out->addModules( "ext.bluespice.books.special.vue" );
		$out->addModuleStyles( "ext.bluespice.books.special.styles" );
		$out->setPageTitle( $this->msg( 'books' )->text() );

		$html = $this->templateParser->processTemplate(
			'books.vue',
			[
				'loading-text' => $this->msg( 'bs-books-overview-page-loading-text' )->text()
			]
		);

		$out->addHTML( $html );
	}
}
