$( function() {
	$( '.chatbot-admin-help-msg' ).each( function() {
		var $this = $( this ),
			text = $this.text();
		if ( text ) {
			var btn = new OO.ui.PopupButtonWidget( {
				icon: 'info',
				label: mw.msg( 'chatbot-admin-help-msg' ),
				invisibleLabel: true,
				framed: false,
				classes: [ 'chatbot-admin-help-msg' ],
				popup: {
					padded: true,
					$content: $( '<div>' ).text( text )
				}
			} );
			$this.replaceWith( btn.$element );
		}
	} );
} );