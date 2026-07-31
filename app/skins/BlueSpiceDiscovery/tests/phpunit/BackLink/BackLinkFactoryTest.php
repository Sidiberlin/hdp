<?php

namespace BlueSpice\Discovery\Tests\BackLink;

use BlueSpice\Discovery\BackLinkProvider\DiffBackLinkProvider;
use BlueSpice\Discovery\BackLinkProvider\PagesBackLinkProvider;
use BlueSpice\Discovery\BackLinkProviderFactory;
use MediaWiki\Context\DerivativeContext;
use MediaWiki\Context\RequestContext;
use MediaWiki\Request\FauxRequest;
use MediaWiki\Title\Title;
use MediaWikiIntegrationTestCase;
use WikiPage;

class BackLinkFactoryTest extends MediaWikiIntegrationTestCase {

	/**
	 * @param string $pageName
	 * @param array $urlParams
	 * @dataProvider provideTestData
	 * @covers BlueSpice\Discovery\BackLinkProviderFactory::getProvider
	 */
	public function testGetProvider( $pageName, $urlParams, $instanceName ) {
		$request = new FauxRequest();
		$request->setVal( $urlParams[0], $urlParams[1] );
		$context = new DerivativeContext( RequestContext::getMain() );
		$context->setRequest( $request );
		$context->setWikiPage( $this->getPage( $pageName ) );

		$objectfactory = $this->getServiceContainer()->getObjectFactory();
		$factory = new BackLinkProviderFactory( $objectfactory );
		$provider = $factory->getProvider( $context );

		if ( !$instanceName ) {
			$this->assertNull( $provider );
		} else {
			$this->assertInstanceOf( $instanceName, $provider );
		}
	}

	private function getPage( $pageName ): WikiPage {
		$title = Title::newFromText( $pageName );
		return $this->getServiceContainer()->getWikiPageFactory()->newFromTitle( $title );
	}

	public static function provideTestData() {
		return [
			'no-backlink' => [
				'Testpage/Subpage',
				[ '', '' ],
				null
			],
			'pages-backlink' => [
				'Testpage',
				[ 'backTo', 'Special:AllPages' ],
				PagesBackLinkProvider::class
			],
			'diff-backlink' => [
				'Testpage',
				[ 'diff', '123' ],
				DiffBackLinkProvider::class
			]
		];
	}
}
