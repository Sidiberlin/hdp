<?php

namespace BlueSpice\Discovery\Tests\LanguageLinks;

use BlueSpice\Discovery\LangLinksProvider\Hardwired;
use MediaWiki\Title\Title;
use MediaWikiIntegrationTestCase;

/**
 * @covers BlueSpice\Discovery\LangLinksProvider\Hardwired
 */
class HardwiredLangLinksTest extends MediaWikiIntegrationTestCase {

	/**
	 * @covers BlueSpice\Discovery\LangLinksProvider\Hardwired::getLangLinks
	 */
	public function testGetLangLinks() {
		$this->setMwGlobals( [
			'bsgDiscoveryHardWiredLangLinks' => [
				"de" => "/de/wiki/$1",
				"en" => "/wiki/en/$1",
				"fr" => "/wiki/$1/fr"
			]
		] );

		$services = $this->getServiceContainer();
		$languageNameUtils = $services->getLanguageNameUtils();
		$configFactory = $services->getConfigFactory();

		$hardwired = new Hardwired( $languageNameUtils, $configFactory );

		$title = Title::newFromText( 'Test Page' );

		$expected = [
			[
				'href' => '/de/wiki/Test_Page',
				'text' => 'Deutsch',
				'title' => 'Test Page' . ' – ' . 'Deutsch',
				'class' => 'interlanguage-link interwiki-' . 'de',
				'link-class' => 'interlanguage-link-target',
				'lang' => 'de',
				'hreflang' => 'de'
			],
			[
				'href' => '/wiki/en/Test_Page',
				'text' => 'English',
				'title' => 'Test Page' . ' – ' . 'English',
				'class' => 'interlanguage-link interwiki-' . 'en',
				'link-class' => 'interlanguage-link-target',
				'lang' => 'en',
				'hreflang' => 'en'
			],
			[
				'href' => '/wiki/Test_Page/fr',
				'text' => 'français',
				'title' => 'Test Page' . ' – ' . 'français',
				'class' => 'interlanguage-link interwiki-' . 'fr',
				'link-class' => 'interlanguage-link-target',
				'lang' => 'fr',
				'hreflang' => 'fr'
			]
		];

		$result = $hardwired->getLangLinks( [], $title );

		$this->assertIsArray( $result );
		$this->assertCount( 3, $result );
		$this->assertEquals( $expected, $result );
	}
}
