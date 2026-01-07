<?php

use MediaWiki\CommentStore\CommentStoreComment;
use MediaWiki\Content\CssContent;
use MediaWiki\Content\JsonContent;
use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\PDFCreator\MediaWiki\Content\PDFCreatorTemplate;
use MediaWiki\Maintenance\LoggedUpdateMaintenance;
use MediaWiki\MediaWikiServices;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\User\User;

require_once dirname( dirname( dirname( __DIR__ ) ) ) . '/maintenance/Maintenance.php';

class AddBookPDFTemplates extends LoggedUpdateMaintenance {

	/**
	 * @return string
	 */
	protected function getUpdateKey() {
		return 'bs-bookshelf-default-bookpdf-creation';
	}

	/**
	 * @return bool|void
	 * @throws MWException
	 */
	protected function doDBUpdates() {
		if ( !ExtensionRegistry::getInstance()->isLoaded( 'PDFCreator' ) ) {
			return $this->output( "No PDFCreator extension available\n" );
		}
		$this->output( "Adding default book pdf templates...\n" );

		$baseDir = __DIR__ . '/../data/PDFTemplates';
		$templates = [
			'pdfcreator_template_intro' => [
				'file' => $baseDir . '/Intro.html',
			],
			'pdfcreator_template_body' => [
				'file' => $baseDir . '/Body.html',
			],
			'pdfcreator_template_header' => [
				'file' => $baseDir . '/Header.html',
			],
			'pdfcreator_template_footer' => [
				'file' => $baseDir . '/Footer.html',
			],
			'pdfcreator_template_outro' => [
				'file' => $baseDir . '/Outro.html',
			],
			'pdfcreator_template_styles' => [
				'file' => $baseDir . '/Styles.css',
			],
			'pdfcreator_template_options' => [
				'file' => $baseDir . '/Options.json',
			]
		];

		$services = MediaWikiServices::getInstance();
		$titleFactory = $services->getTitleFactory();
		$wikiPageFactory = $services->getWikiPageFactory();

		$title = $titleFactory->newFromText( 'PDFCreator/StandardBookPDF', NS_MEDIAWIKI );
		$wikiPage = $wikiPageFactory->newFromTitle( $title );
		$updater = $wikiPage->newPageUpdater( $this->getMaintenanceUser() );
		$updater->setContent( 'main', new PDFCreatorTemplate( '' ) );
		foreach ( $templates as $slotKey => $template ) {
			$content = file_get_contents( $template['file'] );
			if ( $slotKey === 'pdfcreator_template_styles' ) {
				$content = new CssContent( $content );
			} elseif ( $slotKey === 'pdfcreator_template_options' ) {
				$content = new JsonContent( $content );
			} else {
				$content = new PDFCreatorTemplate( $content );
			}
			$updater->setContent( $slotKey, $content );
		}
		$rev = $updater->saveRevision(
			CommentStoreComment::newUnsavedComment( 'Default pdf template content' )
		);
		if ( $rev instanceof RevisionRecord ) {
			$this->output( "done\n" );
		} else {
			$statusFormatter = MediaWikiServices::getInstance()->getFormatterFactory()->getStatusFormatter(
				RequestContext::getMain()
			);
			$this->output( "failed. {$statusFormatter->getMessage( $updater->getStatus() )->text()}\n" );
		}
		return true;
	}

	/**
	 * @return User
	 */
	private function getMaintenanceUser(): User {
		return User::newSystemUser( 'MediaWiki default', [ 'steal' => true ] );
	}
}

$maintClass = AddBookPDFTemplates::class;
require_once RUN_MAINTENANCE_IF_MAIN;
