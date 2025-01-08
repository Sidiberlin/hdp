<?php

namespace BlueSpice\Discovery;

interface ITemplateDataProvider {

	/**
	 *
	 * @param string $group
	 * @param string $id
	 * @return void
	 */
	public function register( $group, $id ): void;

	/**
	 *
	 * @param string $group
	 * @param string $id
	 * @return void
	 */
	public function unregister( $group, $id ): void;
}
