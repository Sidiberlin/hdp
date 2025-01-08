<?php

namespace MediaWiki\Extension\NotifyMe\Channel;

use Config;
use Exception;
use MailAddress;
use MediaWiki\Extension\NotifyMe\Channel\Email\DigestCreator;
use MediaWiki\Extension\NotifyMe\Channel\Email\MailContentProvider;
use MediaWiki\Extension\NotifyMe\NotificationSerializer;
use MediaWiki\Extension\NotifyMe\Storage\NotificationStore;
use MediaWiki\Extension\NotifyMe\SubscriptionConfigurator;
use MediaWiki\MainConfigNames;
use MediaWiki\User\UserIdentity;
use Message;
use MWException;
use MWStake\MediaWiki\Component\Events\Delivery\IExternalChannel;
use MWStake\MediaWiki\Component\Events\INotificationEvent;
use MWStake\MediaWiki\Component\Events\ITitleEvent;
use MWStake\MediaWiki\Component\Events\Notification;
use MWStake\MediaWiki\Component\Events\PriorityEvent;
use Psr\Log\LoggerInterface;
use User;
use UserMailer;

class EmailChannel implements IExternalChannel {
	/** @var NotificationStore */
	private $store;
	/** @var NotificationSerializer */
	private $serializer;
	/**
	 * @var SubscriptionConfigurator
	 */
	private $subscriptionConfigurator;
	/**
	 * @var LoggerInterface
	 */
	private $logger;
	/**
	 * @var MailContentProvider
	 */
	private $mailContentProvider;

	/**
	 * @var Config
	 */
	private $config;

	/**
	 * @param NotificationStore $store
	 * @param NotificationSerializer $serializer
	 * @param MailContentProvider $mailContentProvider
	 * @param SubscriptionConfigurator $configurator
	 * @param LoggerInterface $logger
	 * @param Config $mainConfig
	 */
	public function __construct(
		 NotificationStore $store, NotificationSerializer $serializer, MailContentProvider $mailContentProvider,
		 SubscriptionConfigurator $configurator, LoggerInterface $logger, Config $mainConfig
	) {
		$this->store = $store;
		$this->serializer = $serializer;
		$this->subscriptionConfigurator = $configurator;
		$this->logger = $logger;
		$this->mailContentProvider = $mailContentProvider;
		$this->config = $mainConfig;
	}

	/**
	 * @return Message
	 */
	public function getLabel(): Message {
		return Message::newFromKey( 'notifyme-channel-email' );
	}

	/**
	 * @param Notification $notification
	 *
	 * @return bool
	 * @throws Exception
	 */
	public function deliver( Notification $notification ): bool {
		$user = $notification->getTargetUser();
		if ( !$this->canReceiveMail( $user ) ) {
			throw new MWException( 'User ' . $user->getName() . ' cannot receive mail' );
		}
		if (
			!$this->userWantsImmediateEmail( $user ) &&
			!( $notification->getEvent() instanceof PriorityEvent )
		) {
			return false;
		}

		$mail = $this->getSingleMail( $notification, $user );
		$this->send( $mail, $user );
		return true;
	}

	/**
	 * @param User $user
	 * @param array $notifications
	 * @param string $digestType
	 *
	 * @return void|null
	 * @throws MWException
	 */
	public function digest( User $user, array $notifications, string $digestType ) {
		if ( !$this->canReceiveMail( $user ) ) {
			$this->logger->error( 'User {name} cannot receive mail', [
				'name' => $user->getName()
			] );
			return;
		}
		$digestCreator = new DigestCreator( $this->serializer, $this->mailContentProvider, $this->config );
		$mail = $digestCreator->createDigest( $user, $notifications, $digestType );
		if ( !$mail ) {
			return;
		}

		$this->send( $mail, $user );

		/** @var Notification $notification */
		foreach ( $notifications as $notification ) {
			// Mark all notifications as delivered
			$notification->getStatus()->markAsCompleted();
			$this->store->persist( $notification );
		}
	}

	/**
	 * @param INotificationEvent $event
	 * @param UserIdentity $user
	 *
	 * @return bool
	 */
	public function shouldSkip( INotificationEvent $event, UserIdentity $user ): bool {
		return !$this->isEnabled( $user );
	}

	/**
	 * @return string
	 */
	public function getKey(): string {
		return 'email';
	}

	/**
	 * @return string[]
	 */
	public function getDefaultConfiguration(): array {
		return [
			'enabled' => true,
			'frequency' => 'daily',
		];
	}

	/**
	 * @param User $user
	 *
	 * @return bool
	 */
	private function canReceiveMail( User $user ) {
		return $user->getEmail() && $user->isEmailConfirmed();
	}

	/**
	 * @param Notification $notification
	 * @param User $user
	 *
	 * @return array
	 * @throws Exception
	 */
	protected function getSingleMail( Notification $notification, User $user ) {
		return [
			'subject' => $this->generateSubject( $notification->getEvent() ),
			'body' => $this->mailContentProvider->getFinalEmailHtml(
				'single',
				$this->serializer->serializeForOutput( $notification, $user ),
				$user
			),
			'options' => [
				'contentType' => 'text/html;charset=UTF-8'
			],
		];
	}

	/**
	 * @param User $user
	 *
	 * @return bool
	 */
	private function userWantsImmediateEmail( User $user ) {
		return $this->getFrequencyPreference( $user ) === 'instant';
	}

	/**
	 * @param UserIdentity $user
	 *
	 * @return string
	 */
	public function getFrequencyPreference( UserIdentity $user ) {
		$channelConf = $this->subscriptionConfigurator->getChannelConfiguration( $user, $this );
		return $channelConf['frequency'] ?? 'instant';
	}

	/**
	 * Is channel enabled for user
	 * @param UserIdentity $user
	 *
	 * @return bool
	 */
	private function isEnabled( UserIdentity $user ): bool {
		$channelConf = $this->subscriptionConfigurator->getChannelConfiguration( $user, $this );
		return $channelConf['enabled'] ?? false;
	}

	/**
	 * @param array $mail
	 * @param User $user
	 *
	 * @return void
	 * @throws MWException
	 */
	private function send( array $mail, User $user ) {
		$from = new MailAddress(
			$this->config->get( MainConfigNames::PasswordSender ),
			Message::newFromKey( 'emailsender' )->inContentLanguage()->text()
		);
		$sendTo = MailAddress::newFromUser( $user );
		$status = UserMailer::send( $sendTo, $from, $mail['subject'], $mail['body'], $mail['options'] );
		if ( !$status->isOK() ) {
			throw new Exception( $status->getWikiText() );
		}
	}

	/**
	 * @param INotificationEvent $event
	 *
	 * @return string
	 */
	private function generateSubject( INotificationEvent $event ): string {
		$typeMessage = $event->getKeyMessage()->text();

		if ( $event instanceof ITitleEvent ) {
			return $typeMessage . ' - ' . $event->getTitle()->getPrefixedText();
		}
		return $typeMessage;
	}

	/**
	 * @inheritDoc
	 */
	public function onNotificationPersisted( Notification $notification, bool $created ): void {
		// No-op
	}

	/**
	 * @inheritDoc
	 */
	public function onNotificationOutputSerialized( Notification $notification, array &$data ): void {
		// No-op
	}
}
