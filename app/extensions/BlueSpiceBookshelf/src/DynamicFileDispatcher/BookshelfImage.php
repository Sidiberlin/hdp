<?php

namespace BlueSpice\Bookshelf\DynamicFileDispatcher;

use BlueSpice\DynamicFileDispatcher\ArticlePreviewImage;
use MediaWiki\MediaWikiServices;
use Title;

class BookshelfImage extends ArticlePreviewImage {

	/**
	 * TODO: remove when in base class
	 */
	public const MODULE_NAME = 'articlepreviewimage';

	/**
	 * @var \PageHierarchyProvider
	 */
	protected $pageHierarchyProvider = null;

	/**
	 *
	 * @return StaticCoverImage|ImageExternal
	 */
	public function getFile() {
		$title = Title::newFromText( $this->params[static::TITLETEXT] );
		$this->pageHierarchyProvider = \PageHierarchyProvider::getInstanceFor(
			$title->getPrefixedText()
		);
		$meta = $this->pageHierarchyProvider->getBookMeta();

		$coverpage = '';
		if ( isset( $meta['bookshelfimage'] ) ) {
			$coverpage = $meta['bookshelfimage'];
		}

		if ( empty( $coverpage ) ) {
			return new StaticCoverImage( $this );
		}

		$services = MediaWikiServices::getInstance();

		$file = $services->getRepoGroup()->findFile( $coverpage );
		if ( $file instanceof \File ) {
			// TODO: Add "transformable" RepoFile to BSF
			return new ImageExternal(
				$this,
				$file->createThumb(
					$this->params[static::WIDTH]
				),
				$this->getContext()->getUser()
			);
		}

		$urlUtils = $services->getUrlUtils();
		$parsedUrl = $urlUtils->parse( $coverpage );
		if ( $parsedUrl !== false ) {
			return new ImageExternal(
				$this,
				$coverpage,
				$this->getContext()->getUser()
			);
		}

		return new StaticCoverImage( $this );
	}
}
