<?php

namespace MergeArticles\PageFilter;

use MediaWiki\Context\IContextSource;
use MediaWiki\Title\Title;
use MergeArticles\IPageFilter;

class Term implements IPageFilter {
	/** @var IContextSource */
	protected $context;

	/**
	 * @param IContextSource $context
	 * @return static
	 */
	public static function factory( IContextSource $context ) {
		return new static( $context );
	}

	/**
	 * @param IContextSource $context
	 */
	public function __construct( IContextSource $context ) {
		$this->context = $context;
	}

	/**
	 * @inheritDoc
	 */
	public function getId() {
		return 'term';
	}

	/**
	 * @inheritDoc
	 */
	public function getDisplayName() {
		return $this->context->msg( 'mergearticles-term-filter-label' )->text();
	}

	/**
	 * @inheritDoc
	 */
	public function getFilterableData( Title $title ) {
		// Data already present by default
		return [];
	}

	/**
	 * @inheritDoc
	 */
	public function getRLModule() {
		return 'ext.mergearticles.filters';
	}

	/**
	 * @inheritDoc
	 */
	public function getWidgetClass() {
		return 'mergeArticles.widget.TermFilter';
	}

	/**
	 * @inheritDoc
	 */
	public function getWidgetData() {
		return [];
	}

	/**
	 * @inheritDoc
	 */
	public function getPriority() {
		return 10;
	}
}
