( function ( mw, $, bs, d, undefined ) {
	const Vue = require( 'vue' );
    const TreeApp = require( 'ext.vuejsplus-data-tree.vue' );

	function render() {
        var h = Vue.h;

		var treeData = mw.config.get( 'bsBookshelfTreeData' );
		var config = require( './bookViewConfig.json' );

		var treeTools = [];
		var isSelecable = false;
		var registeredTreeTools = config.tools;
		var offset = config.offset;
		mw.user.getRights().done( function ( rights ) {
			for ( var index = 0; index < registeredTreeTools.length; index++ ) {
				let tool = registeredTreeTools[index];
				if ( tool.permission !== '' && rights.indexOf( tool.permission ) <0 ) {
					continue;
				}
				if ( tool.selectable === true ) {
					isSelecable = true;
				}
				treeTools.push( tool );
			}

			mw.loader.using( config.modules ).done( function () {
				var vm = Vue.createMwApp( {
					mounted: function () {
					},
					render: function() {
						return h( TreeApp, {
							class: '',
							selectable: isSelecable,
							selected: false,
							expandable: true,
							expanded: true,
							nodes: treeData,
							toolbar: true,
							tools: treeTools,
							toolbarFloatingOffset: offset
						} );
					}
				} );

				vm.mount( '#bs-bookshelf-view' );
			} );
		} );
	}

	render();

} )( mediaWiki, jQuery, blueSpice, document );
