<?php

namespace BlueSpice\Bookshelf\ContextProvider;

use MediaWiki\Title\TitleFactory;

class ForcedProvider extends SessionProvider {

	/**
	 * @param TitleFactory $titleFactory
	 */
	public function __construct( TitleFactory $titleFactory ) {
		parent::__construct( $titleFactory );
		$forcedBook = $this->session->get( 'forced_book', false );
		if ( $forcedBook ) {
			// In some contexts (eg. export), we want to force the active book for all titles included
			$this->param = $forcedBook;
		}
	}

	/**
	 * @return bool
	 */
	public function isResponsible(): bool {
		return $this->session->get( 'forced_book', false );
	}

	/**
	 * @return int
	 */
	public function getPriority(): int {
		return 50;
	}

}
