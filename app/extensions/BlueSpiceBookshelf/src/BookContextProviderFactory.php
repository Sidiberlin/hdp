<?php

namespace BlueSpice\Bookshelf;

use BlueSpice\Bookshelf\ContextProvider\DefaultProvider;
use Title;
use TitleFactory;
use Wikimedia\ObjectFactory\ObjectFactory;

class BookContextProviderFactory {

	/** @var ObjectFactory */
	private $objectFactory = null;

	/** @var BookLookup */
	private $bookLookup = null;

	/** @var TitleFactory */
	private $titleFactory = null;

	/**
	 * @param ObjectFactory $objectFactory
	 * @param BookLookup $bookLookup
	 * @param TitleFactory $titleFactory
	 */
	public function __construct( ObjectFactory $objectFactory, BookLookup $bookLookup, TitleFactory $titleFactory ) {
		$this->objectFactory = $objectFactory;
		$this->bookLookup = $bookLookup;
		$this->titleFactory = $titleFactory;
	}

	/**
	 * @param Title $title
	 * @return IBookContextProvider
	 */
	public function getProvider( Title $title ): IBookContextProvider {
		$books = $this->bookLookup->getBooksForPage( $title );
		$specs = $this->getSpecs();

		if ( !$title->isSpecial( 'Userlogin' ) ) {
			$responsibleProvider = $this->createProvider( $specs['forced'], null );
			if ( $responsibleProvider !== null ) {
				return $responsibleProvider;
			}
			$responsibleProvider = $this->createProvider( $specs['query'], $books );
			if ( $responsibleProvider !== null ) {
				return $responsibleProvider;
			}

			$responsibleProvider = $this->createProvider( $specs['session'], $books );
			if ( $responsibleProvider !== null ) {
				return $responsibleProvider;
			}
		}

		// If no other provider is responsible use the default provider.
		// This provider will return the first book available.
		return new DefaultProvider( $books, $this->titleFactory );
	}

	/**
	 * @param array $spec
	 * @param array|null $books
	 * @return IBookContextProvider|null
	 */
	private function createProvider( array $spec, ?array $books ): ?IBookContextProvider {
		/** @var IBookContextProvider */
		$provider = $this->objectFactory->createObject( $spec );
		$activeBook = $provider->getActiveBook();
		if ( $provider->isResponsible() && $activeBook ) {
			if ( $books === null ) {
				// This is for cases when book page itself is being handled
				return $provider;
			}
			$dbKey = $activeBook->getPrefixedDBkey();
			if ( isset( $books[$dbKey] ) ) {
				return $provider;
			}
		}

		return null;
	}

	/**
	 * @return array
	 */
	private function getSpecs(): array {
		return [
			'query' => [
				'class' => 'BlueSpice\\Bookshelf\\ContextProvider\\QueryProvider',
				'services' => [ 'TitleFactory' ]
			],
			'session' => [
				'class' => 'BlueSpice\\Bookshelf\\ContextProvider\\SessionProvider',
				'services' => [ 'TitleFactory' ]
			],
			'forced' => [
				'class' => 'BlueSpice\\Bookshelf\\ContextProvider\\ForcedProvider',
				'services' => [ 'TitleFactory' ]
			],
		];
	}

}
