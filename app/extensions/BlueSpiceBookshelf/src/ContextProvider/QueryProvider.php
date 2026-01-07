<?php

namespace BlueSpice\Bookshelf\ContextProvider;

use BlueSpice\Bookshelf\IBookContextProvider;
use MediaWiki\Request\WebRequest;
use MediaWiki\Session\Session;
use MediaWiki\Session\SessionManager;
use MediaWiki\Title\Title;
use MediaWiki\Title\TitleFactory;

class QueryProvider implements IBookContextProvider {

	/** @var TitleFactory */
	protected $titleFactory = null;

	/** @var Session */
	protected $session = null;

	/** @var WebRequest */
	private $request = null;

	/** @var string */
	protected $param = '';

	/**
	 * @param TitleFactory $titleFactory
	 */
	public function __construct( TitleFactory $titleFactory ) {
		$this->titleFactory = $titleFactory;
		$this->session = SessionManager::getGlobalSession();
		$this->request = $this->session->getRequest();
		$this->param = $this->request->getText( 'book', '' );
	}

	/**
	 * @return bool
	 */
	public function isResponsible(): bool {
		if ( $this->param !== '' ) {
			return true;
		}
		return false;
	}

	/**
	 * @return Title|null
	 */
	public function getActiveBook(): ?Title {
		if ( $this->param === '' ) {
			return null;
		}

		$book = $this->titleFactory->newFromText( $this->param );
		if ( $book && $book->exists() ) {
			$this->session->set( 'book', $book->getPrefixedText() );
			return $book;
		}

		return null;
	}

	/**
	 * @return int
	 */
	public function getPriority(): int {
		return 100;
	}

}
