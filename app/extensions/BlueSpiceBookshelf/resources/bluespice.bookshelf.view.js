( function ( mw ) {
	const Vue = require( 'vue' );
	const BookTree = require( './vue/components/BookTree.vue' );

	function render() {
		const h = Vue.h;

		const treeData = mw.config.get( 'bsBookshelfTreeData' );
		const config = require( './bookViewConfig.json' );

		const treeTools = [];
		let isSelecable = false;
		const registeredTreeTools = config.tools;
		const offset = config.offset;
		mw.user.getRights().done( ( rights ) => {
			for ( let index = 0; index < registeredTreeTools.length; index++ ) {
				const tool = registeredTreeTools[ index ];
				if ( tool.permission !== '' && rights.indexOf( tool.permission ) < 0 ) {
					continue;
				}
				if ( tool.selectable === true ) {
					isSelecable = true;
				}
				treeTools.push( tool );
			}

			mw.loader.using( config.modules ).done( () => {
				const vm = Vue.createMwApp( {
					mounted: function () {
					},
					render: function () {
						return h( BookTree, {
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

}( mediaWiki ) );
