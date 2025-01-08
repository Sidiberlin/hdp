$( function () {
	const $form = $( '#mw-prefs-form' ),
		$container = $form.find( '.notifications-subscriptions' ),
		$input = $form.find( 'input.ext-notifyme-subscriptions-hidden' ),

		 manager = new ext.notifyme.ui.panel.SubscriptionManager( {
			data: JSON.parse( $input.val() ),
			channelConfigurationRegistry: ext.notifyme.channelConfigurationRegistry,
			buckets: $container.data( 'buckets' ),
			channelLabels: $container.data( 'channel-labels' )
		} );
	manager.connect( this, {
		change: function ( value ) {
			$input.val( JSON.stringify( value ) );
			// Trigger change event so that the form is marked as dirty
			const event = new Event( 'change' );
			document.dispatchEvent( event );
		}
	} );
	$container.find( '.oo-ui-progressBarWidget' ).remove();
	$container.append( manager.$element );
} );
