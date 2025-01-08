( function ( mw ) {
	mw.hook( 'enhanced.versionhistory' ).add( ( gridCfg ) => {
		gridCfg.columns.author.valueParser = function ( value ) {
			const createUserWidget = function ( userName ) {
				return new OOJSPlus.ui.widget.UserWidget( {
					user_name: userName,
					showImage: true,
					showLink: true,
					showRawUsername: false
				} );
			};

			// Not 'collab-edit'
			if ( typeof value === 'string' ) {
				return createUserWidget( value );
			}

			const userWidgets = [];
			value.forEach( ( userName ) => {
				userWidgets.push( createUserWidget( userName ) );
			} );

			const userWidgetsContainer = document.createElement( 'div' );
			userWidgets.forEach( ( widget, index ) => {
				userWidgetsContainer.appendChild( widget.$element[ 0 ] );

				if ( index < userWidgets.length - 1 ) {
					userWidgetsContainer.appendChild( document.createTextNode( ' ' ) );
				}
			} );

			return new OO.ui.HtmlSnippet( userWidgetsContainer );
		};
	} );
}( mediaWiki ) );
