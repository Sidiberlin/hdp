<?php

namespace NSFileRepo;

class ClientConfig {

	/**
	 *
	 * @return array
	 */
	public static function makeConfigJson() {
		$config = new \NSFileRepo\Config();
		return [
			'egNSFileRepoSkipTalk' => $config->get( 'SkipTalk' ) ,
			'egNSFileRepoNamespaceBlacklist' => $config->get( 'NamespaceBlacklist' ),
			'egNSFileRepoNamespaceThreshold' => $config->get( 'NamespaceThreshold' )
		];
	}
}
