ext.notifyme.ui.widget.NotificationItemWidget = function ( data, cfg ) {
	cfg = cfg || {};

	ext.notifyme.ui.widget.NotificationItemWidget.parent.call( this, cfg );

	this.$content = $( '<div>' ).addClass( 'notifications-ui-widget-NotificationItemWidget-notification-content' );

	if ( data.isInGroup ) {
		this.$content.addClass( 'notifications-ui-widget-NotificationItemWidget-notification-content-in-group' );
	}

	const timestampWidget = this.getTimestampWidget( data.timestamp ),

	 $message = this.composeMessage( data.message, data.agent, data.icon );

	this.markAsReadButton = this.getMarkAsReadButton( data.unread );
	this.markAsReadButton.connect( this, {
		click: 'markAsRead'
	} );

	// Compose header (contains icon, message, timestamp and "mark as read" circle)
	let $header = $( '<div>' )
			.addClass( 'notifications-ui-widget-NotificationItemWidget-header' )
			.append( $message, this.markAsReadButton.$element ),

	 $secondaryLinks = this.getSecondaryLinks( data.links ),

	 $timestamp = new OO.ui.LabelWidget( {
			label: new OO.ui.HtmlSnippet( timestampWidget.$element ),
			classes: [ 'notifications-ui-widget-NotificationItemWidget-content-message-timestamp' ]
		} ).$element,

		// Compose body (contains secondary links)
	 $body = $( '<div>' )
			.addClass( 'notifications-ui-widget-NotificationItemWidget-body' )
			.append( $secondaryLinks )
			.append( $timestamp );

	// If any of "child" classes need to modify body somehow
	// Example: ext.notifyme.ui.widget.NotificationGroupWidget
	$body = this.alterBody( $body );

	this.$content.append(
		$header,
		$body
	);

	this.$element.append( this.$content );

	this.$element.addClass( 'notifications-ui-widget-NotificationItemWidget' );
	this.$element.attr( 'data-id', data.id );
	this.$element.attr( 'data-unread', data.unread );
};

OO.inheritClass( ext.notifyme.ui.widget.NotificationItemWidget, OO.ui.Widget );

ext.notifyme.ui.widget.NotificationItemWidget.prototype.getMarkAsReadButton = function ( unread ) {
	// Mark as read
	const markAsReadButton = new ext.notifyme.ui.widget.ToggleReadCircleButtonWidget( {
		framed: false,
		classes: [ 'notifications-ui-widget-NotificationItemWidget-markAsReadButton' ],
		markAsRead: unread === true
	} );

	return markAsReadButton;
};

ext.notifyme.ui.widget.NotificationItemWidget.prototype.getTimestampWidget = function ( timestamp ) {
	// Timestamp
	const itemMoment = moment.utc( timestamp );
	itemMoment.local();

	const timestampWidget = new OO.ui.LabelWidget( {
		classes: [ 'notifications-ui-widget-NotificationItemWidget-timestamp' ],
		// Get the time 'fromNow' without the suffix 'ago' and add that suffix with translated message
		label: mw.message( 'notifyme-notification-center-timestamp-ago', itemMoment.fromNow( true ) ).text()
	} );

	return timestampWidget;
};

ext.notifyme.ui.widget.NotificationItemWidget.prototype.composeMessage = function (
	message,
	agent,
	icon
) {
	const $message = $( '<div>' ).addClass( 'notifications-ui-widget-NotificationItemWidget-content-message' ),
		messageFirstLine = new OO.ui.HorizontalLayout( {
			classes: [ 'notifications-ui-widget-NotificationItemWidget-content-message-first-line' ]
		} );

	if ( agent ) {
		const userWidget = new OOJSPlus.ui.widget.UserWidget( {
			user_name: agent.username,
			showImage: true,
			showLink: true,
			showRawUsername: false
		} );

		userWidget.$element.addClass( 'notifications-ui-widget-NotificationItemWidget-user-widget' );
		messageFirstLine.$element.append( userWidget.$element );
	}

	messageFirstLine.$element.append(
		$( '<div>' )
			.addClass( 'notifications-ui-widget-NotificationItemWidget-content-message-header' )
			.html( message.main )
	);
	$message.append( messageFirstLine.$element );
	if ( message.secondary ) {
		$message.append(
			new OO.ui.LabelWidget( {
				label: new OO.ui.HtmlSnippet( message.secondary ),
				classes: [ 'notifications-ui-widget-NotificationItemWidget-content-message-secondary' ]
			} ).$element
		);

	}

	var $iconWrapper = this.getIconWrapper( icon );
	$message.prepend( $iconWrapper );

	return $message;
};

ext.notifyme.ui.widget.NotificationItemWidget.prototype.getIconWrapper = function ( icon ) {
	const iconWidget = new OO.ui.IconWidget( {
			icon: icon
		} ),
	 $iconWrapper = $( '<div>' )
			.addClass( 'notifications-ui-widget-NotificationItemWidget-icon-wrapper' )
			.append( iconWidget.$element );

	return $iconWrapper;
};

ext.notifyme.ui.widget.NotificationItemWidget.prototype.getSecondaryLinks = function ( links ) {
	const $secondaryLinks = $( '<div>' ).addClass( 'notifications-ui-widget-NotificationItemWidget-secondary-links-wrapper' );

	for ( let i = 0; i < links.length; i++ ) {
		const $linkWrapper = $( '<div>' ).addClass( 'notifications-ui-widget-NotificationItemWidget-secondary-link-wrapper' ),

		 icon = new OO.ui.IconWidget( {
				icon: 'next'
			} ),

		 $link = $( '<a>' )
				.addClass( 'notifications-ui-widget-NotificationItemWidget-secondary-link' )
				.attr( 'href', links[ i ].url )
				.html( links[ i ].label );

		$link.on( 'click', () => {
			this.markAsRead();
		} );

		$linkWrapper.append( icon.$element, $link );

		$secondaryLinks.append( $linkWrapper );
	}

	return $secondaryLinks;
};

ext.notifyme.ui.widget.NotificationItemWidget.prototype.alterBody = function ( $body ) {
	return $body;
};

ext.notifyme.ui.widget.NotificationItemWidget.prototype.markAsRead = function () {
	const notificationToMark = {},

	 id = this.$element.attr( 'data-id' ),
	 isUnread = this.$element.attr( 'data-unread' ) === 'true';

	notificationToMark[ id ] = isUnread;

	ext.notifyme.setReadStatus( notificationToMark ).done( ( response ) => {
		const notifications = response;
		for ( const id in notifications ) {
			const isProcessed = notifications[ id ];
			if ( !isProcessed ) {
				console.error( 'Failed to change read status of notification: ' + id );
			}
		}

		this.emit( 'itemMarked' );
	} );
};
