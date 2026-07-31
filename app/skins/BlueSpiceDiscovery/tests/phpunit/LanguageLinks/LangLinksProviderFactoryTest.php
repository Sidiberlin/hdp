<?php

namespace BlueSpice\Discovery\Tests\LanguageLinks;

use BlueSpice\Discovery\ILangLinksProvider;
use BlueSpice\Discovery\LangLinksProvider\Hardwired;
use BlueSpice\Discovery\LangLinksProvider\Interwiki;
use BlueSpice\Discovery\LangLinksProvider\Subpages;
use BlueSpice\Discovery\LangLinksProviderFactory;
use MediaWiki\Title\Title;
use MediaWikiIntegrationTestCase;

/**
 * @covers BlueSpice\Discovery\LangLinksProviderFactory
 */
class LangLinksProviderFactoryTest extends MediaWikiIntegrationTestCase {

	/**
	 * @covers BlueSpice\Discovery\LangLinksProviderFactory::create
	 */
	public function testCreate() {
		$title = Title::newFromText( 'Test Page' );
		$services = $this->getServiceContainer();
		$objectFactory = $services->get( 'MWStakeManifestObjectFactory' );
		$config = $services->getConfigFactory()->makeConfig( 'bsg' );

		$this->setMwGlobals( [
			'bsgDiscoveryLangLinksMode' => "hardwired"
		] );

		$langLinksProviderFactory = new LangLinksProviderFactory( $objectFactory, $config );

		$result = $langLinksProviderFactory->create( [], $title );

		$this->assertInstanceOf( ILangLinksProvider::class, $result );
		$this->assertInstanceOf( Hardwired::class, $result );

		$this->setMwGlobals( [
			'bsgDiscoveryLangLinksMode' => "subpages"
		] );

		$langLinksProviderFactory = new LangLinksProviderFactory( $objectFactory, $config );

		$result = $langLinksProviderFactory->create( [], $title );

		$this->assertInstanceOf( ILangLinksProvider::class, $result );
		$this->assertInstanceOf( Subpages::class, $result );

		$this->setMwGlobals( [
			'bsgDiscoveryLangLinksMode' => "interwiki"
		] );

		$langLinksProviderFactory = new LangLinksProviderFactory( $objectFactory, $config );

		$result = $langLinksProviderFactory->create( [], $title );

		$this->assertInstanceOf( ILangLinksProvider::class, $result );
		$this->assertInstanceOf( Interwiki::class, $result );
	}
}
