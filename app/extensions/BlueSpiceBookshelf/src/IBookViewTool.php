<?php

namespace BlueSpice\Bookshelf;

interface IBookViewTool {

	public const TYPE_BUTTON = 'button';
	public const SLOT_LEFT = 'left';
	public const SLOT_RIGHT = 'right';

	/**
	 * Return one of the TYPE_ constants
	 * @return string
	 */
	public function getType(): string;

	/**
	 * @return string
	 */
	public function getLabelMsgKey(): string;

	/**
	 * @return string
	 */
	public function getCallback(): string;

	/**
	 * @return array
	 */
	public function getClasses(): array;

	/**
	 * @return string
	 */
	public function getSlot(): string;

	/**
	 * @return int
	 */
	public function getPosition(): int;

	/**
	 * @return array
	 */
	public function getRLModules(): array;

	/**
	 * @return string
	 */
	public function getRequiredPermission(): string;

	/**
	 * @return bool
	 */
	public function requireSelectableTree(): bool;
}
