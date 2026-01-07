<?php

namespace BlueSpice\Bookshelf\ContextProvider;

use MediaWiki\Title\TitleFactory;

class SessionProvider extends QueryProvider {

	/**
	 * @param TitleFactory $titleFactory
	 */
	public function __construct( TitleFactory $titleFactory ) {
		parent::__construct( $titleFactory );
		$this->param = $this->session->get( 'book', '' );
	}

	/**
	 * @return int
	 */
	public function getPriority(): int {
		return 50;
	}

}
