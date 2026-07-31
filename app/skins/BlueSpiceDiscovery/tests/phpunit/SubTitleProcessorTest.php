<?php

namespace BlueSpice\Discovery\Tests;

use BlueSpice\Discovery\SubTitleProcessor;
use MediaWiki\MainConfigNames;
use MediaWikiIntegrationTestCase;

/**
 * @group Database
 */
class SubTitleProcessorTest extends MediaWikiIntegrationTestCase {

	/**
	 *
	 * @return void
	 */
	public function addDBDataOnce() {
		$this->insertPage( 'Dummy' );
		$this->insertPage( 'Dummy/ABC' );
		$this->insertPage( 'Dummy/DEF' );
		$this->insertPage( 'Dummy/DEF/Some_Subpage' );
		$this->insertPage( 'Dummy/Dumm/eee/f/gh' );
	}

	/**
	 *
	 * @param string $subtitle
	 * @param string $expectedRedirect
	 * @param string $expectedBacklink
	 * @param string $expectedSubText
	 * @param string $expectedSubpages
	 * @return void
	 * @dataProvider provideParseTestData
	 * @covers \BlueSpice\Discovery\SubTitleProcessor::parse
	 */
	public function testParse( $subtitle, $expectedRedirect, $expectedBacklink,
		$expectedSubText, $expectedSubpages ) {
		$this->overrideConfigValues( [
			MainConfigNames::ArticlePath => '/wiki/$1'
		] );

		$processor = new SubTitleProcessor();
		$processor->parse( $subtitle );

		$redirect = $processor->get( 'redirect' );
		$backlink = $processor->get( 'backlink' );
		$subText = $processor->get();
		$subPages = $processor->get( 'subpages' );

		$this->assertEquals( $expectedRedirect, $redirect );
		$this->assertEquals( $expectedBacklink, $backlink );
		$this->assertEquals( $expectedSubText, $subText );
		$this->assertEquals( $expectedSubpages, $subPages );
	}

	public static function provideParseTestData() {
		return [
			'redirect' => [
				// phpcs:ignore Generic.Files.LineLength.TooLong
				'<span class="mw-redirectedfrom">(Weitergeleitet von <a href="/wiki?title=Dummy/DEF;redirect=no" class="mw-redirect" title="Dummy/DEF" data-bs-title="Dummy/DEF">Dummy/DEF</a>)</span><br><span id="redirectsub">Weiterleitung</span>',
				// phpcs:ignore Generic.Files.LineLength.TooLong
				'<span class="mw-redirectedfrom">(Weitergeleitet von <a href="/wiki?title=Dummy/DEF;redirect=no" class="mw-redirect" title="Dummy/DEF" data-bs-title="Dummy/DEF">Dummy/DEF</a>)</span>',
				'',
				'',
				''
			],
			'backlink-and-redirect' => [
				// phpcs:ignore Generic.Files.LineLength.TooLong
				'<span class="mw-redirectedfrom">(Weitergeleitet von <a href="/wiki?title=Dummy/DEF; redirect=no" class="mw-redirect" title="Dummy/DEF" data-bs-title="Dummy/DEF">Dummy/DEF</a>)</span><br><span id="redirectsub">Weiterleitung</span>← <a href="/wiki/index.php/Dummy/ABC" title="Dummy/ABC" data-bs-title="Dummy/ABC">Dummy/ABC</a>',
				// phpcs:ignore Generic.Files.LineLength.TooLong
				'<span class="mw-redirectedfrom">(Weitergeleitet von <a href="/wiki?title=Dummy/DEF; redirect=no" class="mw-redirect" title="Dummy/DEF" data-bs-title="Dummy/DEF">Dummy/DEF</a>)</span>',
				'← <a href="/wiki/index.php/Dummy/ABC" title="Dummy/ABC" data-bs-title="Dummy/ABC">Dummy/ABC</a>',
				'',
				''
			],
			'subText' => [
				// phpcs:ignore Generic.Files.LineLength.TooLong
				'Von WikiSysop (Beobachtungsliste: Änderungen | normal bearbeiten | im Listenformat bearbeiten (Import/Export) | Beobachtungsliste leeren)',
				'',
				'',
				// phpcs:ignore Generic.Files.LineLength.TooLong
				'Von WikiSysop (Beobachtungsliste: Änderungen | normal bearbeiten | im Listenformat bearbeiten (Import/Export) | Beobachtungsliste leeren)',
				''
			],
			'subpages' => [
				// phpcs:ignore Generic.Files.LineLength.TooLong
				'<div class="subpages"><a href="/wiki/index.php/Dummy" title="Dummy" data-bs-title="Dummy">Dummy</a></div>',
				'',
				'',
				'',
				// phpcs:ignore Generic.Files.LineLength.TooLong
				'<div class="subpages"><a href="/wiki/index.php/Dummy" title="Dummy" data-bs-title="Dummy">Dummy</a></div>'
			]
		];
	}

}
