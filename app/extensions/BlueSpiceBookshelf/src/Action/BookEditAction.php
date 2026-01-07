<?php

namespace BlueSpice\Bookshelf\Action;

use BlueSpice\Bookshelf\BookEditData;
use EditAction;
use MediaWiki\Html\Html;
use MediaWiki\Message\Message;
use MediaWiki\Title\Title;

class BookEditAction extends EditAction {
	/** @var string|bool */
	protected $bookTitle = false;

	/**
	 * @return string
	 */
	public function getName() {
		return 'edit';
	}

	/**
	 * @return void
	 */
	public function show() {
		$this->useTransactionalTimeLimit();

		$out = $this->getOutput();
		$out->setRobotPolicy( 'noindex,nofollow' );
		$out->addBacklinkSubtitle( $this->getTitle() );

		$bookEditData = BookEditData::newFromTitleAndRequest(
			$this->getTitle(), $this->getRequest()
		);

		$out->setPageTitle(
			Message::newFromKey(
				'bs-bookshelf-edit-title'
			)->params( $bookEditData->getBookTitle() )
		);

		$returnTo = $this->getRequest()->getText( 'returnto', false );
		$returnToTitle = Title::newFromText( $returnTo );
		if ( $returnTo && $returnToTitle instanceof Title ) {
			$out->addBacklinkSubtitle( $returnToTitle );
		}

		$data = $bookEditData->getBookData();
		$out->addJsConfigVars( 'bsBookshelfData', $data );

		$out->addModules( "ext.bluespice.bookshelf.book-editor" );
		$out->addHTML(
			Html::element( 'div', [
				'id' => 'bs-bookshelf-book-editor-container',
				'style' => '',
				'class' => ''
			] )
		);
	}
}
