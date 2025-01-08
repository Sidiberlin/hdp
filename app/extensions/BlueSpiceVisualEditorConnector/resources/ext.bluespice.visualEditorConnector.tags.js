( function( mw, $, bs ){
	var tagDefinitions = [];

	/**
	 *
	 * @param bs.vec.util.TagDefinition tagDefinition
	 * @returns undefined
	 */
	function registerTagDefinition( tagDefinition ) {
		tagDefinitions.push( tagDefinition );
	}

	/**
	 *
	 * @returns array of callbacks
	*/
	function getTagDefinitions() {
		return tagDefinitions;
	}

	bs.util.registerNamespace( 'bs.vec' );
	bs.vec.registerTagDefinition = registerTagDefinition;
	bs.vec.getTagDefinitions = getTagDefinitions;

	//Step 1: Load the tags classes
	mw.loader.using( [ 'ext.bluespice.visualEditorConnector.tags.classes', 'ext.visualEditor.mwcore' ] )
		.done( function() {
			//Keep in sync with `extension.json/ResourceModules/ext.bluespice.visualEditorConnector.tags.classes/scripts`
			var tagRegistry = new bs.vec.util.tag.Registry();

			//Step 2: Load all plugin modules that may want to register to
			//those classes
			var bsvecTagDefinitions = bs.vec.config.get( 'TagDefinitions' );
			if( bsvecTagDefinitions.length === 0 ) {
				return;
			}

			mw.loader.using( bsvecTagDefinitions ).done( function() {
				//Step 3: There is no step three
				tagRegistry.initialize();
			} );
		} );
})( mediaWiki, jQuery, blueSpice );
