<?php

namespace BlueSpice\Bookshelf\Panel;

use MediaWiki\Context\IContextSource;
use MediaWiki\Html\Html;
use MediaWiki\Html\TemplateParser;
use MWStake\MediaWiki\Component\CommonUserInterface\Component\Literal;

class AsyncBookNavigationTreeContainer extends Literal {

	/** @var string */
	private $activeBook;

	/**
	 * @param string $activeBook
	 */
	public function __construct( $activeBook ) {
		parent::__construct( 'async-subpage-tree', '' );
		$this->activeBook = $activeBook;
	}

	public function getId(): string {
		return 'async-subpage-tree';
	}

	public function getHtml(): string {
		$templateParser = new TemplateParser( dirname( __DIR__, 2 ) . '/resources/templates/skeleton' );

		$skeleton = $templateParser->processTemplate(
			'booknav',
			[]
		);

		$html = Html::openElement( 'div', [
			'id' => 'book-panel-tree',
			'class' => 'book-panel-tree'
		] );
		$html .= Html::openElement( 'div', [
			'id' => 'book-tree-skeleton'
		] );
		$html .= $skeleton;
		$html .= Html::closeElement( 'div' );
		$html .= Html::closeElement( 'div' );

		return $html;
	}

	/**
	 *
	 * @inheritDoc
	 */
	public function shouldRender( IContextSource $context ): bool {
		return true;
	}

	/**
	 * @return string[]
	 */
	public function getRequiredRLStyles(): array {
		return [];
	}

	/**
	 * @return array
	 */
	public function getRequiredRLModules(): array {
		return [ 'ext.bluespice.async.navigation' ];
	}
}
