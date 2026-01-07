<?php

namespace BlueSpice\Bookshelf;

use MediaWiki\Message\Message;

interface IMetaDataDescription {

	/**
	 * @return string
	 */
	public function getKey(): string;

	/**
	 * @return Message
	 */
	public function getName(): Message;

	/**
	 * @return array
	 */
	public function getRLModules(): array;

	/**
	 * @return string
	 */
	public function getJSClassname(): string;

}
