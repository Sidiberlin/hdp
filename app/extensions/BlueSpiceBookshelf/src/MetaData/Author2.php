<?php

namespace BlueSpice\Bookshelf\MetaData;

use BlueSpice\Bookshelf\IMetaDataDescription;
use Message;

class Author2 implements IMetaDataDescription {

	/**
	 * @inheritDoc
	 */
	public function getKey(): string {
		return 'author2';
	}

	/**
	 * @inheritDoc
	 */
	public function getName(): Message {
		return Message::newFromKey( 'bs-bookshelfui-bookmetatag-author2' );
	}

	/**
	 * @inheritDoc
	 */
	public function getRLModules(): array {
		return [ 'bluespice.bookshelf.metadata.pages' ];
	}

	/**
	 * @inheritDoc
	 */
	public function getJSClassname(): string {
		return 'bs.bookshelf.ui.pages.Author2Meta';
	}

}
