var Vue = require( 'vue' ); // eslint-disable-line no-var
var Tiles = require( './Tiles.vue' ); // eslint-disable-line no-var
var $allRLModules = []; // eslint-disable-line no-var

function getList() {
	const dfd = $.Deferred();
	$.ajax( {
		url: mw.util.wikiScript( 'rest' ) + '/unifiedtaskoverview/list',
		contentType: 'application/json',
		dataType: 'json'
	} ).done( ( response ) => {
		dfd.resolve( response );
	} ).fail( ( jgXHR, type, status ) => {
		if ( type === 'error' ) {
			dfd.reject( {
				error: jgXHR.responseJSON || jgXHR.responseText
			} );
		}
		dfd.reject( { type: type, status: status } );
	} );
	return dfd.promise();
}

function loadData( response ) {
	loadRLModules( response );
	const itemData = sortItems( response );
	setVisibility( itemData );
	return itemData;
}

function sortItems( data ) {
	data = data.sort( ( a, b ) => a.sortkey - b.sortkey );
	return data;
}

// get RLModules from data
function loadRLModules( data ) {
	for ( let x = 0; x < data.length; x++ ) {
		data[ x ].RLmodules.forEach( ( item ) => {
			if ( $allRLModules.length === 0 ) {
				$allRLModules.push( item );
			} else {
				$allRLModules.forEach( ( module ) => {
					if ( module !== item ) {
						$allRLModules.push( item );
					}
				} );
			}
		} );
	}
	mw.loader.using( $allRLModules );
}

function setDataForSearch( items ) {
	const dataSearch = [];
	items.forEach( ( item ) => {
		dataSearch.push( item.type.toLowerCase() + ' ' +
			item.header.toLowerCase() + ' ' + item.subheader.toLowerCase() + ' ' +
			item.body.toLowerCase() );
	} );
	return dataSearch;
}

function setVisibility( items ) {
	items.forEach( ( item ) => {
		item.isVisible = true;
	} );
}

function render() {
	const deferred = $.Deferred();
	const dfdList = getList();
	dfdList.done( ( response ) => {
		const h = Vue.h;
		const vm = Vue.createMwApp( {
			mounted: function () {
				deferred.resolve( this.$el );
			},
			render: function () {
				let listItems = [];
				let searchElts = [];
				if ( response.length > 0 ) {
					listItems = loadData( response );
					searchElts = setDataForSearch( listItems );
				}

				return h( Tiles, {
					items: listItems,
					searchElements: searchElts,
					noTaskDesc: listItems.length == 0, // eslint-disable-line eqeqeq
					searchPlaceholderLabel: mw.message( 'unifiedtaskoverview-search-placeholder' ).plain()
				} );
			}
		} );
		vm.mount( '#unifiedTaskOverview-tiles' );
		return deferred;
	} );
}

render();
