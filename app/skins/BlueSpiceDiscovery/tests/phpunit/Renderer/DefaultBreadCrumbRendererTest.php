<?php

namespace BlueSpice\Discovery\Tests\Renderer;

use BlueSpice\Discovery\BreadcrumbDataProviderFactory;
use BlueSpice\Discovery\Renderer\DefaultBreadCrumbRenderer;
use MediaWiki\MainConfigNames;
use MediaWiki\MediaWikiServices;
use MediaWiki\Message\Message;
use MediaWiki\Title\Title;
use MediaWiki\User\User;
use MediaWikiIntegrationTestCase;
use MessageLocalizer;

/**
 * @group Database
 */
class DefaultBreadCrumbRendererTest extends MediaWikiIntegrationTestCase {

	/**
	 *
	 * @return void
	 */
	public function addDBDataOnce() {
		$this->insertPage( 'Main_Page' );
		$this->insertPage( 'Dummy' );
		$this->insertPage( 'Dummy/ABC' );
		$this->insertPage( 'Dummy/DEF' );
		$this->insertPage( 'Dummy/DEF/Some_Subpage' );
		$this->insertPage( 'Dummy/Dumm/eee/f/gh' );
	}

	/**
	 *
	 * @param Title $title
	 * @param array $webRequestValues
	 * @param array $expectedRootNode
	 * @param array $expectedLeafNode
	 * @param array $expectedLabels
	 * @return void
	 * @dataProvider provideGetParamsTestData
	 * @covers \BlueSpice\Discovery\Renderer\DefaultBreadCrumbRenderer::getParams
	 */
	public function testGetParams( $title, $webRequestValues, $expectedRootNodeUrl,
		$expectedLeafNodeUrl, $expectedLabels ) {
		$this->overrideConfigValues( [
			MainConfigNames::ArticlePath => '/wiki/$1'
		] );

		$mockUser = $this->createMock( User::class );
		$mockMessageLocalizer = $this->createMock( MessageLocalizer::class );
		$mockMessageLocalizer->method( 'msg' )->willReturnCallback( static function ( $messageKey ) {
			return Message::newFromKey( $messageKey );
		} );

		$specialPageFactory = $this->getServiceContainer()->getSpecialPageFactory();
		$titleFactory = $this->getServiceContainer()->getTitleFactory();
		$namespaceInfo = $this->getServiceContainer()->getNamespaceInfo();
		$objectFactory = $this->getServiceContainer()->getObjectFactory();

		$breadcrumbFactory = new BreadcrumbDataProviderFactory(
			$mockMessageLocalizer,
			$webRequestValues,
			$titleFactory,
			$namespaceInfo,
			$objectFactory
		);

		$renderer = new DefaultBreadCrumbRenderer( $title, $mockUser,
			$mockMessageLocalizer, $specialPageFactory, $namespaceInfo, $breadcrumbFactory );

		$params = $renderer->getParams();

		$actualRootNodeUrl = $params['rootNode']['href'];

		$actualLabels = [];
		foreach ( $params['labels'] as $label ) {
			if ( $label instanceof Message ) {
				$actualLabels[] = $label;
			} else {
				$actualLabels[] = $label['text'];
			}
		}

		$actualLabels = array_map( static function ( $actualLabelMsg ) {
			/** @var Message $actualLabelMsg */
			return $actualLabelMsg->getKey();
		}, $actualLabels );

		$this->assertEquals( $expectedRootNodeUrl, $actualRootNodeUrl );
		$this->assertEquals( $expectedLabels, $actualLabels );
	}

	public static function provideGetParamsTestData() {
		$specialPageFactory = MediaWikiServices::getInstance()->getSpecialPageFactory();
		$specialpages = $specialPageFactory->getTitleForAlias( 'Specialpages' );
		$specialpagesPath = '/wiki/' . $specialpages->getFullText();

		return [
			'main-namespace-view-mode' => [
				Title::newFromText( 'Dummy/ABC' ),
				[ '' ],
				'/wiki/Main_Page',
				'/wiki/Dummy/ABC',
				[]
			],
			'talk-namespace-history-mode' => [
				Title::newFromText( 'Talk:Dummy/ABC' ),
				[ 'action' => 'history' ],
				'/wiki/Main_Page',
				'/wiki/Dummy/ABC',
				[ 'bs-discovery-breadcrumb-label-talk', 'bs-discovery-breadcrumb-label-action-history' ]
			],
			'specialpage-with-title-in-path' => [
				Title::newFromText( 'Special:Move/Dummy/ABC' ),
				[ 'action' => 'history' ],
				'/wiki/Main_Page',
				'/wiki/Dummy/ABC',
				[ 'rawmessage', 'bs-discovery-breadcrumb-label-action-history' ]
			],
			'specialpage' => [
				Title::newFromText( 'Special:Allpages' ),
				[],
				$specialpagesPath,
				'/wiki/Special:Allpages',
				[]
			],
			'specialpage-linkliste-with-target' => [
				Title::newFromText( 'Special:Whatlinkshere' ),
				[ 'target' => 'Dummy/ABC' ],
				'/wiki/Main_Page',
				'/wiki/Dummy/ABC',
				[ 'rawmessage' ]
			],
			'specialpage-linkliste-with-target' => [
				Title::newFromText( 'Special:CiteThisPage' ),
				[ 'page' => 'Dummy/ABC' ],
				'/wiki/Main_Page',
				'/wiki/Dummy/ABC',
				[ 'rawmessage' ]
			]
		];
	}

}
