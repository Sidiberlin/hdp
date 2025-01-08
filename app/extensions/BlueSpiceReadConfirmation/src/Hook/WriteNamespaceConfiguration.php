<?php

namespace BlueSpice\ReadConfirmation\Hook;

use BlueSpice\NamespaceManager\Hook\NamespaceManagerBeforePersistSettingsHook;

class WriteNamespaceConfiguration implements NamespaceManagerBeforePersistSettingsHook {

	/**
	 * @inheritDoc
	 */
	public function onNamespaceManagerBeforePersistSettings(
		array &$configuration, int $id, array $definition, array $mwGlobals
	): void {
		$globalValue = $mwGlobals['wgNamespacesWithEnabledReadConfirmation'] ?? [];
		if ( isset( $definition['read_confirmation'] ) && $definition['read_confirmation'] === true ) {
			$configuration['wgNamespacesWithEnabledReadConfirmation'][$id] = true;
		} elseif ( isset( $definition[ 'read_confirmation' ] ) && $definition['read_confirmation'] === false ) {
			return;
		}
		if ( isset( $globalValue[$id] ) && $globalValue[$id] === true ) {
			$configuration['wgNamespacesWithEnabledReadConfirmation'][$id] = true;
		}
	}
}
