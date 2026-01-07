<?php

namespace ChatBot;

use MediaWiki\Message\Message;

interface IAdminModule {
	/**
	 * @return Message
	 */
	public function getLabel(): Message;

	/**
	 * @return Message|null
	 */
	public function getDescription(): ?Message;

	/**
	 * @return string
	 */
	public function getHtml(): string;

	/**
	 * @return array
	 */
	public function getRLModules(): array;
}
