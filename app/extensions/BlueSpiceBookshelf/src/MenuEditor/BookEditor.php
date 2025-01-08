<?php

namespace BlueSpice\Bookshelf\MenuEditor;

use BlueSpice\Bookshelf\BookSourceParser;
use MediaWiki\Extension\MenuEditor\EditPermissionProvider;
use MediaWiki\Extension\MenuEditor\Menu\GenericMenu;
use MediaWiki\Extension\MenuEditor\ParsableMenu;
use MediaWiki\Extension\MenuEditor\Parser\IMenuParser;
use MediaWiki\Revision\RevisionRecord;
use MWException;
use MWStake\MediaWiki\Component\Wikitext\ParserFactory;
use RequestContext;
use Title;
use TitleFactory;

class BookEditor extends GenericMenu implements ParsableMenu, EditPermissionProvider {

	/** @var TitleFactory */
	private $titleFactory = null;

	/** @var bool */
	private $apply = true;

	/**
	 * @param ParserFactory $parserFactory
	 * @param TitleFactory $titleFactory
	 */
	public function __construct( ParserFactory $parserFactory, TitleFactory $titleFactory ) {
		parent::__construct( $parserFactory );
		$this->titleFactory = $titleFactory;
	}

	/**
	 * @inheritDoc
	 */
	public function getRLModule(): string {
		return 'bluespice.bookshelf.menueditor.book';
	}

	/**
	 * @inheritDoc
	 */
	public function getJSClassname(): string {
		return 'ext.bookshelf.ui.data.tree.BookEditorTree';
	}

	/**
	 * @inheritDoc
	 */
	public function appliesToTitle( Title $title ): bool {
		// TODO: User books
		if ( MW_ENTRY_POINT === 'index' ) {
			$requestContext = RequestContext::getMain();
			$action = $requestContext->getRequest()->getVal( 'action' );
			if ( !$action ) {
				$this->apply = false;
			}
			if ( $action && $action === 'view' ) {
				$this->apply = false;
			}
		}
		if ( $title->getNamespace() !== 1504 ) {
			$this->apply = false;
		}

		return $this->apply;
	}

	/**
	 * @inheritDoc
	 */
	public function getKey(): string {
		return 'bookeditor';
	}

	/**
	 * @inheritDoc
	 */
	public function getEmptyContent(): array {
		return [];
	}

	/**
	 * @param Title $title
	 * @param RevisionRecord|null $revision
	 *
	 * @return IMenuParser
	 * @throws MWException
	 */
	public function getParser( Title $title, ?RevisionRecord $revision = null ): IMenuParser {
		if ( !$revision ) {
			$revision = $this->parserFactory->getRevisionForText( '', $title );
		}
		return new BookSourceParser(
			$revision, $this->getProcessors(), $this->titleFactory
		);
	}

	/**
	 * @return string[]
	 */
	public function getAllowedNodes(): array {
		return [ 'bs-bookshelf-chapter-wikilink-with-alias', 'bs-bookshelf-chapter-plain-text' ];
	}

	/**
	 * @inheritDoc
	 */
	public function getToolbarItems(): array {
		return [
			"metadata",
			"massAdd"
		];
	}

	/**
	 * @inheritDoc
	 */
	public function getEditRight(): string {
		return 'edit';
	}
}
