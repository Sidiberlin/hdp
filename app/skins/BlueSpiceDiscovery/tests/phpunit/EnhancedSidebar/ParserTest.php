<?php

namespace BlueSpice\Discovery\Tests\EnhancedSidebar;

use BlueSpice\Discovery\EnhancedSidebar\Node\ExternalLinkNode;
use BlueSpice\Discovery\EnhancedSidebar\NodeProcessor\EnhancedSidebarNodeProcessor;
use BlueSpice\Discovery\EnhancedSidebar\NodeProcessor\ExternalLinkProcessor;
use BlueSpice\Discovery\EnhancedSidebar\Parser;
use MediaWiki\Revision\MutableRevisionRecord;
use MediaWiki\Title\Title;
use MWStake\MediaWiki\Lib\Nodes\INodeSource;
use PHPUnit\Framework\TestCase;

/**
 * @covers \BlueSpice\Discovery\EnhancedSidebar\Parser
 */
class ParserTest extends TestCase {
	/**
	 * @covers \BlueSpice\Discovery\EnhancedSidebar\Parser::addNodesFromData
	 * @return void
	 */
	public function testMutation() {
		$data = [
			[
				'type' => 'enhanced-sidebar-external-link',
				'href' => 'https://www.mediawiki.org',
				'text' => 'MediaWiki',
				'hidden' => '',
				'level' => 1
			],
			[
				'type' => 'enhanced-sidebar-external-link',
				'href' => 'https://www.dummy.org',
				'text' => 'Dummy',
				'hidden' => '',
				'classes' => [ 'dummy' ],
				'level' => 2
			],
			[
				'type' => 'enhanced-sidebar-external-link',
				'href' => 'https://www.foo.org',
				'text' => 'Foo',
				'hidden' => '',
				'level' => 2
			],
			[
				'type' => 'enhanced-sidebar-external-link',
				'href' => 'https://www.bar.org',
				'text' => 'Bar',
				'hidden' => '',
				'level' => 3
			],
			[
				'type' => 'enhanced-sidebar-external-link',
				'href' => 'https://www.baz.org',
				'text' => 'Baz',
				'hidden' => '',
				'level' => 1
			]
		];

		$title = Title::newFromText( 'testTitle' );
		$revision = new MutableRevisionRecord( $title );

		$objectCache = $this->createMock( 'Wikimedia\ObjectCache\WANObjectCache' );
		$parser = new Parser( $revision, [ $this->getProcessor() ], $objectCache );
		$parser->addNodesFromData( $data );
		$mutated = $parser->getMutatedData();
		$expected = [
			[
				'type' => 'enhanced-sidebar-external-link',
				'text' => 'MediaWiki',
				'hidden' => '',
				'classes' => [],
				'icon-cls' => '',
				'href' => 'https://www.mediawiki.org',
				'children' => [
					[
						'type' => 'enhanced-sidebar-external-link',
						'text' => 'Dummy',
						'hidden' => '',
						'classes' => [ 'dummy' ],
						'icon-cls' => '',
						'href' => 'https://www.dummy.org',
					],
					[
						'type' => 'enhanced-sidebar-external-link',
						'text' => 'Foo',
						'hidden' => '',
						'classes' => [],
						'icon-cls' => '',
						'href' => 'https://www.foo.org',
						'children' => [
							[
								'type' => 'enhanced-sidebar-external-link',
								'text' => 'Bar',
								'hidden' => '',
								'classes' => [],
								'icon-cls' => '',
								'href' => 'https://www.bar.org',
							],
						]
					],
				]
			],
			[
				'type' => 'enhanced-sidebar-external-link',
				'text' => 'Baz',
				'hidden' => '',
				'classes' => [],
				'icon-cls' => '',
				'href' => 'https://www.baz.org',
			]
		];

		$this->assertSame( $expected, json_decode( $mutated, 1 ), 'Mutated data is not as expected' );
	}

	private function getProcessor(): EnhancedSidebarNodeProcessor {
		$externalLinkProcessorMock = $this->createMock( ExternalLinkProcessor::class );
		$externalLinkProcessorMock
			->method( 'supportsNodeType' )
			->willReturnCallback( static function ( $type ) {
				return $type === 'enhanced-sidebar-external-link';
			} );
		$externalLinkProcessorMock->method( 'getRawNode' )
			->willReturnCallback( static function ( INodeSource $nodeSource ) {
				$data = $nodeSource->getData();
				return new ExternalLinkNode( $data );
			} );

		return $externalLinkProcessorMock;
	}
}
