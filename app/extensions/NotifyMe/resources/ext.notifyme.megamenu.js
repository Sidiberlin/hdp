$( () => {
	window.megaMenuOpened = false;

	function updateUnreadNotificationsIndicator( unreadNotifications ) {
		// If there are unread notifications - add corresponding CSS indicator
		// Also add number of unread notifications there - up to "99+"
		if ( unreadNotifications > 0 ) {
			$megaMenuButton.addClass( 'attention-indicator' );

			if ( unreadNotifications < 100 ) {
				$megaMenuButton.attr( 'data-unread-count', unreadNotifications );
			} else {
				$megaMenuButton.attr( 'data-unread-count', '99+' );
			}

			const unreadNotificationsAriaText = mw.message(
				'notifyme-navbar-button-aria-label-unread-notifications',
				unreadNotifications
			).text();

			$megaMenuButton.attr( 'aria-label', unreadNotificationsAriaText );
		} else {
			$megaMenuButton.removeClass( 'attention-indicator' );

			// If there are no unread notifications - no need to announce that
			$megaMenuButton.attr( 'aria-label', '' );

			$megaMenuButton.attr( 'data-unread-count', 0 );
		}
	}

	function markNotificationsAsRead( notificationsToMark ) {
		if ( notificationsToMark.length === 0 ) {
			return;
		}

		// Convert array [ 'a', 'b'] to { 'a': true, 'b': true }
		const notifications = {};
		notificationsToMark.forEach( ( id ) => {
			notifications[ id ] = true;
		} );

		ext.notifyme.setReadStatus( notifications ).done( ( response ) => {
			const notifications = response;
			for ( const id in notifications ) {
				const isProcessed = notifications[ id ];
				if ( !isProcessed ) {
					console.error( 'Failed to change read status of notification: ' + id );
				}
			}
		} );
	}

	function renderNotificationCenterLink() {
		const $notificationCenterLinkWrapper = $( '<div>' ).addClass( 'notification-center-link' ),

		 linkText = mw.message( 'notifyme-notifications-mega-menu-all-notifications-label' ).text(),
		 href = new mw.Title( 'NotificationCenter', -1 ).getUrl(),

		 $notificationCenterLink = $( '<a>' ).text( linkText ).attr( 'href', href );

		$notificationCenterLinkWrapper.append( $notificationCenterLink );

		return $notificationCenterLinkWrapper;
	}

	function renderNoNotifications() {
		const $noNotificationsWrapper = $( '<div>' ).addClass( 'notifications-no-notifications-wrapper' ),

		 noNotificationsText = mw.message( 'notifyme-notifications-mega-menu-no-notifications' ).text(),

		 $noNotificationsLabel = $( '<p>' ).text( noNotificationsText ),

		 $notificationCenterLink = renderNotificationCenterLink();

		$noNotificationsWrapper.append( [
			$( '<div>' ).addClass( 'notifications-no-notifications-image' ),
			$noNotificationsLabel,
			$notificationCenterLink
		] );

		return $noNotificationsWrapper;
	}

	function renderNotificationItem( notification ) {
		const $notificationItem = $( '<div>' ).addClass( 'notification-item' ),

		 $unreadCircleWrapper = $( '<div>' ).addClass( 'notification-unread-circle-wrapper' ),
		 $unreadCircle = $( '<div>' ).addClass( 'notification-unread-circle' );

		$unreadCircleWrapper.append( $unreadCircle );

		const hasAgent = !notification.agent_is_bot && notification.entity_type === 'single_notification',
		 agent = hasAgent ? notification.agent.display_name : '',
		 $message = $( '<div>' ).addClass( 'notification-message' );
		$message.html( agent ? agent + ' ' + notification.message.main : notification.message.main );

		// Timestamp
		// We want to use extra-short timestamp strings; we change the locale
		// to our echo-defined one and use that instead of the normal moment locale
		const itemMoment = moment.utc( notification.timestamp );
		itemMoment.locale( 'en' );
		itemMoment.local();

		const $timestamp = $( '<div>' ).addClass( 'notification-timestamp' );
		$timestamp.text( itemMoment.fromNow( true ) + ' ago' );

		$notificationItem.append( [
			$unreadCircleWrapper,
			$message,
			$timestamp
		] );

		return $notificationItem;
	}

	var $megaMenuButton = $( '.notifications-megamenu-btn' ),
		$megamenu = $( '#notifications-mm' ),

	 unreadNotifications = $megamenu.find( 'input[name=unreadNotificationsCount]' ).val() || 0;

	updateUnreadNotificationsIndicator( unreadNotifications );

	// Implicitly mark as "read" all unread notifications
	// when user opens mega menu
	// Request is sent only the first time when notifications menu is opened
	$megaMenuButton.click( () => {
		// We do not need to do anything if user just closed menu
		if ( window.megaMenuOpened ) {
			window.megaMenuOpened = false;

			return;
		}

		window.megaMenuOpened = true;

		const $notificationsMenuCard = $megamenu.find( '#notifications-card' );

		// Remove notifications which could probably left after previous opening
		$notificationsMenuCard.find( '.notifications-list-wrapper' ).remove();
		$notificationsMenuCard.find( '.notifications-no-notifications-wrapper' ).remove();

		// Request and display notifications
		// We are doing that on demand with API in JS to not produce extra load when page is loading
		const params = {
			group: true,
			limit: 10,
			filter: [
				{
					property: 'status',
					value: 'pending',
					operator: 'eq',
					type: 'string'
				}
			],
			sort: [ {
				property: 'timestamp',
				direction: 'desc'
			} ]
		};

		ext.notifyme.retrieve( params ).done( ( response ) => {
			// All shown notifications will be implicitly marked as read after retrieving
			let notificationsToMark = [],

			 results = response.results || [];

			if ( results.length === 0 ) {
				$notificationsMenuCard.append( renderNoNotifications() );

				return;
			}

			let $notificationsListWrapper = $( '<div>' ).addClass( 'notifications-list-wrapper' ),

			 $notificationsList = $( '<div>' ).addClass( 'notifications-list' ),

			 colsCount = 1;
			if ( results.length > 5 ) {
				colsCount = 2;
			}

			for ( let i = 0; i < colsCount; i++ ) {
				const classes = [ 'notifications-col' ];
				if ( colsCount === 1 ) {
					classes.push( 'notifications-single-col' );
				}

				const $notificationsCol = $( '<div>' ).addClass( classes );

				for ( let j = 0; j < 5; j++ ) {
					if ( ( i * 5 + j ) > results.length - 1 ) {
						break;
					}

					const notification = results[ i * 5 + j ],

						// Save notification ID to mark it as read
					 ids = notification.entity_type === 'single_notification' ?
							[ notification.id ] :
							notification.notifications.map( ( n ) => n.id );
					notificationsToMark = notificationsToMark.concat( ids );

					$notificationsCol.append( renderNotificationItem( notification ) );
				}

				$notificationsList.append( $notificationsCol );
			}

			$notificationsListWrapper.append( [
				$notificationsList,
				renderNotificationCenterLink()
			] );

			$notificationsMenuCard.append( $notificationsListWrapper );
			markNotificationsAsRead( notificationsToMark );

			// Update "unread notifications" indicator
			unreadNotifications -= results.length;

			updateUnreadNotificationsIndicator( unreadNotifications );
		} );
	} );
} );
