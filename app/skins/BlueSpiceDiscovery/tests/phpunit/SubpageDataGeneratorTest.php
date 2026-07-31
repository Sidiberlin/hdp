<?php

namespace BlueSpice\Discovery\Tests;

use BlueSpice\Discovery\SubpageDataGenerator;
use MediaWiki\MainConfigNames;
use MediaWiki\Message\Message;
use MediaWiki\Title\Title;
use MediaWikiIntegrationTestCase;

/**
 * @group Database
 */
class SubpageDataGeneratorTest extends MediaWikiIntegrationTestCase {

	/**
	 * @return void
	 */
	public function addDBData() {
		$this->overrideConfigValues( [
			MainConfigNames::NamespacesWithSubpages => [ NS_MAIN => true ]
		] );

		$this->insertPage( 'Root' );
		$this->insertPage( 'Root/1A' );
		$this->insertPage( 'Root/1A/2A' );
		$this->insertPage( 'Root/1A/2A/3A' );

		$this->insertPage( 'Root/1B' );
		$this->insertPage( 'Root/1B/2B' );

		$this->insertPage( 'TreeMissing' );
		$this->insertPage( 'TreeMissing/1C/2C' );
	}

	/**
	 * @covers BlueSpice\Discovery\SubpageDataGenerator::generate
	 * @dataProvider provideSubpageData
	 */
	public function testGenerate(
		array $rawExpectedItems, int $maxDepth, ?string $activeTitleText, ?string $treeRootTitleText
	) {
		$subpageDataGenerator = new SubpageDataGenerator();
		$title = Title::newFromText( 'Root/1A' );

		if ( $activeTitleText ) {
			$activeTitle = Title::newFromText( $activeTitleText );
			$subpageDataGenerator->setActiveTitle( $activeTitle );
		}
		if ( $treeRootTitleText ) {
			$treeRootTitle = Title::newFromText( $treeRootTitleText );
			$subpageDataGenerator->setTreeRootTitle( $treeRootTitle );
		}

		$expected = [];
		foreach ( $rawExpectedItems as $itemConfig ) {
			$expected[] = $this->buildItem( ...$itemConfig );
		}

		$actualSubpageData = $subpageDataGenerator->generate( $title, $maxDepth );
		$this->assertEquals( $expected, $actualSubpageData );
	}

	/**
	 * @return array
	 */
	protected function provideSubpageData(): array {
		return [
			'Tree from root title' => [
				// $expected
				[
					[
						'Root/1A',
						'1A',
						[ 'active' ],
						[
							[
								'Root/1A/2A',
								'2A',
								[],
								[
									[
										'Root/1A/2A/3A',
										'3A',
										[],
										[]
									]
								]
							]
						]
					],
					[
						'Root/1B',
						'1B',
						[],
						[
							[
								'Root/1B/2B',
								'2B',
								[],
								[]
							]
						]
					]
				],
				// $maxDepth
				6,
				// $activeTitle
				'Root/1A',
				// $treeRootTitle
				null
			],
			'Tree from root title, maxDepth 2' => [
				// $expected
				[
					[
						'Root/1A',
						'1A',
						[ 'active' ],
						[
							[
								'Root/1A/2A',
								'2A',
								[],
								[]
							]
						]
					],
					[
						'Root/1B',
						'1B',
						[],
						[
							[
								'Root/1B/2B',
								'2B',
								[],
								[]
							]
						]
					]
				],
				// $maxDepth
				2,
				// $activeTitle
				'Root/1A',
				// $treeRootTitle
				null
			],
			'Tree from specific title' => [
				// $expected
				[
					[
						'Root/1A/2A',
						'2A',
						[],
						[
							[
								'Root/1A/2A/3A',
								'3A',
								[],
								[]
							]
						]
					]
				],
				// $maxDepth
				6,
				// $activeTitle
				null,
				// $treeRootTitle
				'Root/1A'
			],
			'Tree with missing/new page' => [
				// $expected
				[
					[
						'TreeMissing/1C',
						'1C',
						[ 'new' ],
						[
							[
								'TreeMissing/1C/2C',
								'2C',
								[ 'active' ],
								[]
							]
						]
					]
				],
				// $maxDepth
				6,
				// $activeTitle
				'TreeMissing/1C/2C',
				// $treeRootTitle
				'TreeMissing'
			]
		];
	}

	/**
	 * @param string $name
	 * @param string $text
	 * @param array $classes
	 * @param array $items
	 * @return array
	 */
	private function buildItem( string $name, string $text, array $classes = [], array $rawItems = [] ): array {
		$title = Title::newFromText( $name );

		$fullId = md5( $title->getFullText() );
		$id = substr( $fullId, 0, 6 );

		$item = [
			'id' => $id,
			'name' => $name,
			'text' => $text,
			'href' => $title->getLocalURL(),
		];

		if ( !$title->exists() ) {
			$item['title'] = Message::newFromKey(
				'bs-discovery-page-does-not-exist-title',
				$title->getPrefixedText()
			)->text();
		}

		if ( $classes ) {
			$item['classes'] = $classes;
		}

		if ( $rawItems ) {
			$items = [];
			foreach ( $rawItems as $itemConfig ) {
				$items[] = $this->buildItem( ...$itemConfig );
			}
			$item['items'] = $items;
		}

		return $item;
	}
}
