<?php

namespace BlueSpice\Discovery\Tests\Renderer;

use BlueSpice\Discovery\SkinSlotRenderer\MainPanelSkinSlotRenderer;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionException;

/**
 * @covers \BlueSpice\Discovery\SkinSlotRenderer\MainPanelSkinSlotRenderer
 */
class MainPanelSkinSlotRendererTest extends TestCase {
	/**
	 * @covers ::sortItems()
	 *
	 * @return void
	 * @throws ReflectionException
	 */
	public function testSortItems(): void {
		$reflectionClass = new ReflectionClass( MainPanelSkinSlotRenderer::class );
		$sortItems = $reflectionClass->getMethod( 'sortItems' );
		$sortItems->setAccessible( true );
		$mainPanelSkinSlotRenderer = $this->createMock( MainPanelSkinSlotRenderer::class );

		$items = [
			'bs-special-bookshelf' => [
				'position' => 30,
			],
			'special-default-sort' => [],
			'special-mainpage' => [
				'position' => 10,
			],
			'special-first' => [
				'position' => 0,
			],
			'special-allpages' => [
				'position' => 20,
			],
			'special-recentchanges' => [
				'position' => 80,
			],
		];
		$sortItems->invokeArgs( $mainPanelSkinSlotRenderer, [ &$items ] );

		$this->assertEquals(
			[
				'special-first' => [
					'position' => 0,
				],
				'special-mainpage' => [
					'position' => 10,
				],
				'special-allpages' => [
					'position' => 20,
				],
				'bs-special-bookshelf' => [
					'position' => 30,
				],
				'special-recentchanges' => [
					'position' => 80,
				],
				'special-default-sort' => [],
			],
			$items
		);
	}
}
