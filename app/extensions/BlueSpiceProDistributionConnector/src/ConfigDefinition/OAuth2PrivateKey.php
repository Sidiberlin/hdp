<?php

namespace BlueSpice\ProDistributionConnector\ConfigDefinition;

class OAuth2PrivateKey extends OAuth2PublicKey {

	/**
	 * @return string
	 */
	public function getLabelMessageKey() {
		return "bs-pro-distribution-config-outh-private-key";
	}

	/**
	 * @return string
	 */
	public function getGlobalName() {
		return 'wgOAuth2PrivateKey';
	}
}
