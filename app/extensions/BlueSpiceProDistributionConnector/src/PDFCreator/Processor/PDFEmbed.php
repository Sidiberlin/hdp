<?php

namespace BlueSpice\ProDistributionConnector\PDFCreator\Processor;

use BlueSpice\ProDistributionConnector\PDFCreator\Utility\PDFEmbedFinder;
use MediaWiki\Config\Config;
use MediaWiki\Extension\PDFCreator\IProcessor;
use MediaWiki\Extension\PDFCreator\Utility\ExportContext;
use MediaWiki\Title\TitleFactory;
use RepoGroup;

class PDFEmbed implements IProcessor {

	/** @var TitleFactory */
	private $titleFactory;

	/** @var Config */
	private $config;

	/** @var RepoGroup */
	private $repoGroup;

	/**
	 * @param TitleFactory $titleFactory
	 * @param Config $config
	 * @param RepoGroup $repoGroup
	 */
	public function __construct(
		TitleFactory $titleFactory, Config $config, RepoGroup $repoGroup ) {
		$this->titleFactory = $titleFactory;
		$this->config = $config;
		$this->repoGroup = $repoGroup;
	}

	/**
	 * @inheritDoc
	 */
	public function execute(
		array &$pages, array &$images, array &$attachments,
		ExportContext $context, string $module = '', $params = []
	): void {
		if ( empty( $pages ) ) {
			return;
		}

		$attachmentFinder = new PDFEmbedFinder(
			$this->titleFactory, $this->config, $this->repoGroup
		);
		$results = $attachmentFinder->execute( $pages, $attachments );

		foreach ( $results as $result ) {
			$filename = $result->getFilename();
			$attachments[$filename] = $result->getAbsolutePath();
		}
	}

	/**
	 * @inheritDoc
	 */
	public function getPosition(): int {
		return 70;
	}

}
