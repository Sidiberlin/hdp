<?php

namespace MediaWiki\Extension\ImportOfficeFiles;

interface IResult {

	/**
	 * false = step not done yet
	 * true = step finished
	 *
	 * @return bool
	 */
	public function getStatus(): bool;

	/**
	 * @return array
	 */
	public function getData(): array;
}
