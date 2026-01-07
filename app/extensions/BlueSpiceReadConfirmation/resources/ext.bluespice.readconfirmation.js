( function ( mw, $, bs ) {

	function buildMessage( curPageId ) {

		// TODO: Use Mustache template
		const html =
			'<div id="bs-readconfirmation-container">' +
				'<h3>{0}</h3>' +
				'<table><tr>' +
					'<td>' +
					'<div class="mw-ui-checkbox">' +
						'<input type="checkbox" aria-description="{3}" id="bs-rc-cb-ack"/>' +
						'<label for="bs-rc-cb-ack">{1}</label>' +
					'</div>' +
				'</td><td>' +
					'<button disabled id="bs-rc-btn-ack" class="cdx-button cdx-button--weight-primary cdx-button--action-progressive">{2}</button>' +
					'</td>' +
				'</tr><table>' +
			'</div>';

		const $message = $( html.format(
			mw.message( 'bs-readconfirmation-confirm-read-heading' ).text(),
			mw.message( 'bs-readconfirmation-confirm-read-checkbox-label' ).text(),
			mw.message( 'bs-readconfirmation-confirm-read-button-label' ).text(),
			mw.message( 'bs-readconfirmation-confirm-read-aria-description' ).text()
		) );

		const $button = $message.find( '#bs-rc-btn-ack' );
		$button.on( 'click', async () => {
			await mw.loader.using( 'mediawiki.api' );
			const api = new mw.Api();

			try {
				await api.postWithToken( 'csrf', {
					action: 'bs-readconfirmation-tasks',
					task: 'confirm',
					taskData: JSON.stringify( { pageId: curPageId } )
				} );
				bs.alerts.remove( 'bs-readconfirmation-info' );
			} catch ( error ) {
				bs.util.alert( 'bs-rc-confirm', {
					text: error.message
				} );
			}
		} );

		const $checkbox = $message.find( '#bs-rc-cb-ack' );
		$checkbox.on( 'change', function () {
			$button.prop( 'disabled', !$( this ).is( ':checked' ) );
			if ( $( this ).is( ':checked' ) ) {
				$button.css( 'visibility', 'visible' );
			} else {
				$button.css( 'visibility', 'hidden' );
			}
		} );

		bs.alerts.add(
			'bs-readconfirmation-info',
			$message,
			bs.alerts.TYPE_INFO
		);
	}

	bs.util.registerNamespace( 'bs.readconfirmation' );
	bs.readconfirmation.init = function () {
		if ( mw.config.get( 'wgAction', 'view' ) !== 'view' ) {
			return;
		}

		const curPageId = mw.config.get( 'wgArticleId' );
		if ( curPageId < 1 ) { // SpecialPages ...
			return;
		}
		const data = {
			pageId: curPageId,
			revId: mw.config.get( 'wgRevisionId' )
		};

		mw.hook( 'readconfirmation.check.request.before' ).fire( data );

		bs.api.tasks.execSilent( 'readconfirmation', 'check', data )
			.done( ( response ) => {
				if ( response.success && response.payload.userHasConfirmed === false ) {
					mw.loader.using( 'mediawiki.ui.checkbox' ).done( () => {
						buildMessage( curPageId );
					} );
				}
			} );
	};

	setTimeout( () => {
		$( bs.readconfirmation.init );
	}, 1000 );

}( mediaWiki, jQuery, blueSpice ) );
