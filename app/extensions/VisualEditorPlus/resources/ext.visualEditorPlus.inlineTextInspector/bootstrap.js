mw.hook( 've.activationComplete' ).add( function () {
	var target = ve.init.target;

	registerInlineTextInspectors( target );
} );

function registerInlineTextInspectors( target ) {
	if ( target.getSurface().getMode() === 'visual' ) {
		var $popupTarget = $( '<div>' );
		// eslint-disable-next-line no-jquery/no-global-selector
		$( '.ve-init-target' ).append( $popupTarget );
		var inspectorPopup = new ext.visualEditorPlus.ui.InlineTextInspector( {
			$overlay: true,
			$container: $popupTarget
		} );
		inspectorPopup.setTarget( target );
		$popupTarget.append( inspectorPopup.$element );

		for ( var key in ext.visualEditorPlus.registry.inlineTextInspectors.registry ) {
			var cls = ext.visualEditorPlus.registry.inlineTextInspectors.registry[ key ],
				instance = new cls( inspectorPopup, {} );
			if ( instance instanceof ext.visualEditorPlus.ui.InlineTextInspectorElement ) {
				inspectorPopup.addInspector( key, instance );
			}
		}
		inspectorPopup.appendInspectors();
	}
}
