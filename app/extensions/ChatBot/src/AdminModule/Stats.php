<?php

namespace ChatBot\AdminModule;

use ChatBot\DeepsetApi\FeedbackApi;
use ChatBot\IAdminModule;
use Html;
use Message;
use OOUI\HorizontalLayout;
use OOUI\IconWidget;
use OOUI\LabelWidget;
use OOUI\MessageWidget;
use OOUI\PanelLayout;
use Throwable;

class Stats implements IAdminModule {

	/** @var FeedbackApi */
	protected $feedbackApi;

	/**
	 * @param FeedbackApi $feedbackApi
	 */
	public function __construct( FeedbackApi $feedbackApi ) {
		$this->feedbackApi = $feedbackApi;
	}

	/**
	 * @inheritDoc
	 */
	public function getLabel(): Message {
		return Message::newFromKey( 'chatbot-admin-module-stats-label' );
	}

	/**
	 * @inheritDoc
	 */
	public function getDescription(): ?Message {
		return null;
	}

	/**
	 * @inheritDoc
	 */
	public function getHtml(): string {
		$html = '';
		foreach ( [ 'feedback' ] as $type ) {
			$html .= $this->renderStatType( $type );
		}

		return $html;
	}

	/**
	 * @inheritDoc
	 */
	public function getRLModules(): array {
		return [ 'ext.chatbot.admin.feedbackstats' ];
	}

	/**
	 * @param string $key
	 * @param bool $withHeader
	 * @return string
	 */
	public function renderStatType( string $key, bool $withHeader = true ) {
		if ( $key === 'feedback' ) {
			try {
				$value = $this->feedbackApi->getStats();
			} catch ( Throwable $ex ) {
				return new MessageWidget( [
					'type' => 'error',
					'label' => Message::newFromKey( 'chatbot-admin-module-stats-error' )->text(),
				] );
			}

			$tiles[] = $this->tryRenderTile( 'accurate', $value['positive_feedback_count'] ?? null, 'feedback-stat' );
			$tiles[] = $this->tryRenderTile( 'neutral', $value['neutral_feedback_count'] ?? null, 'feedback-stat' );
			$tiles[] = $this->tryRenderTile( 'negative', $value['negative_feedback_count'] ?? null, 'feedback-stat' );
			$tiles[] = $this->tryRenderTile( 'total_queries', $value['total_queries'] ?? null, 'feedback-stat' );
		}

		$layout = new HorizontalLayout( [
			'items' => array_filter( $tiles ),
		] );

		if ( $withHeader ) {
			// chatbot-admin-module-stats-feedback
			$header = Html::element( 'h4', [], Message::newFromKey( "chatbot-admin-module-stats-$key" )->text() );
			return $header . $layout->toString();
		}
		return $layout->toString();
	}

	/**
	 * @param string $key
	 * @param mixed $value
	 * @param string $prefix
	 * @return PanelLayout|null
	 */
	private function tryRenderTile( string $key, mixed $value, string $prefix ): ?PanelLayout {
		if ( $value === null ) {
			$value = '-';
		}
		// chatbot-admin-module-stats-feedback-stat-accurate
		// chatbot-admin-module-stats-feedback-stat-neutral
		// chatbot-admin-module-stats-feedback-stat-negative
		// chatbot-admin-module-stats-feedback-stat-total_queries
		$label = new LabelWidget( [
			'label' => Message::newFromKey( "chatbot-admin-module-stats-$prefix-$key" )->text(),
		] );
		$headerItems = [ $label ];
		$helpMsg = Message::newFromKey( "chatbot-admin-module-stats-$prefix-$key-help" );
		if ( $helpMsg->exists() ) {
			$headerItems[] = ( new LabelWidget( [
				'classes' => [ 'chatbot-admin-help-msg' ],
				'label' => $helpMsg->text(),
			] ) )->toggle( false );

		}
		$headerLayout = new HorizontalLayout( [
			'classes' => [ 'chatbot-admin-stat-header-container' ],
			'items' => $headerItems,
		] );
		$icon = new IconWidget( [
			'icon' => $prefix . '-' . $key,
		] );
		$valueLayout = new HorizontalLayout( [
			'classes' => [ 'chatbot-admin-stat-value-container' ],
			'items' => [
				$icon,
				new LabelWidget( [
					'classes' => [ 'chatbot-admin-stat-value' ],
					'label' => $value,
				] )
			]
		] );

		return new PanelLayout( [
			'padded' => true,
			'expanded' => false,
			'classes' => [ 'chatbot-admin-stat-tile' ],
			'content' => [
				$headerLayout,
				$valueLayout,
			]
		] );
	}
}
