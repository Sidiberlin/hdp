<?php

namespace BlueSpice\Bookshelf\MetaData;

use BlueSpice\Bookshelf\IMetaDataDescription;
use Message;

class Version implements IMetaDataDescription {

	/**
	 * @inheritDoc
	 */
	public function getKey(): string {
		return 'version';
	}

	/**
	 * @inheritDoc
	 */
	public function getName(): Message {
		return Message::newFromKey( 'bs-bookshelfui-bookmetatag-version' );
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
		return 'bs.bookshelf.ui.pages.VersionMeta';
	}

}
