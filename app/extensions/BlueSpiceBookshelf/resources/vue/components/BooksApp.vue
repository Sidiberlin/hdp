<template>
	<div class="bs-books-search">
		<cdx-search-input
			v-model="searchInputValue"
			:clearable="true"
			:placeholder="searchPlaceholderLabel"
			:aria-label="searchPlaceholderLabel"
			@update:model-value="getSearchResults"
		></cdx-search-input>
	</div>
	<div class="bs-books-bookshelfs" v-if="hasData" >
		<bookshelf  v-for="bookshelf in bookshelfs"
			v-show="bookshelf.isVisible"
			v-bind:bookshelf="bookshelf.name"
			v-bind:books="bookshelf.books"
		></bookshelf>
	</div>
	<div class="bs-books-bookshelfs-empty" v-else>
		{{ emptyMsg }}
	</div>
	<div
		id="bs-books-aria-lve"
		aria-live="polite"
	>{{ ariaLiveInitial }}</div>
</template>

<script>
var Bookshelf = require( './Bookshelf.vue' );
var { CdxSearchInput } = require( '@wikimedia/codex' );

// @vue/component
module.exports = exports = {
	name: 'BooksApp',
	props: {
		items: {
			type: Array,
			default: []
		},
		searchableData: {
			type: Array,
			default: []
		},
		searchPlaceholderLabel: {
			type: String,
			default: ''
		},
		filter: {
			type: String,
			default: ''
		}
	},
	components: {
		'bookshelf': Bookshelf,
		CdxSearchInput: CdxSearchInput
	},
	data: function () {
		this.items.forEach( function( item ) {
			item.isVisible = true;
		} );

		var hasData = true;
		var emtyMsg = '';
		var initialBookshelfs = [];
		if ( this.items.length > 0 ) {
			// Initial bookselfs is in local scope. The bookshelfs value in return statement is global.
			// If we use bookshelfs here for createBookshelfs the search input would not change the visible items.
			initialBookshelfs = createBookshelfs( this.items );

			var initialSearchInputValue = '';
			if ( this.filter !== '' ) {
				initialBookshelfs = filter( initialBookshelfs, this.items, this.searchableData, this.filter );
				initialSearchInputValue = this.filter;
			}
		} else {
			hasData = false;
			emtyMsg = mw.message( 'bs-books-overview-page-bookshelf-empty' ).plain();
		}

		let visibleItems = this.items.filter( obj => { return obj.isVisible === true } );

		return {
			searchInput: [],
			bookshelfs: initialBookshelfs,
			hasData: hasData,
			emptyMsg: emtyMsg,
			searchInputValue: initialSearchInputValue,
			bookshelfs: initialBookshelfs,
			ariaLiveInitial: mw.message( 'bs-books-overview-page-aria-live-filtered-rows', visibleItems.length ).toString()
		};
	},
	methods: {
		getSearchResults: function( search ) {
			if ( !this.items ) {
				return;
			}

			this.bookshelfs = filter( this.bookshelfs, this.items, this.searchableData, search );

			let visibleItems = this.items.filter( obj => { return obj.isVisible === true } );
			updateAriaLiveSection( visibleItems.length );
		}
	}
};

function filter( data, items, searchableData, search ) {
	search = search.toLowerCase();
	let found = 1;

	let searchInput = [];
	if ( search !== '' && search !== false ) {
		if ( search.search( ' ' ) !== -1 ) {
			searchInput = search.split( ' ' );
		} else  {
			searchInput[0] = search;
		}

		for ( let x = 0; x < searchableData.length; x++ ) {
			for ( let y = 0; y < searchInput.length; y++ ) {
				if ( searchableData[x].search( searchInput[y] )  === -1 ) {
					found = 0;
				}
			}

			if ( found === 0 ) {
				items[x].isVisible = false;
				data = createBookshelfs( items );
			} else {
				items[x].isVisible = true;
				data = createBookshelfs( items );
			}

			found = 1;
		}
	} else {
		// All chars in search input had been deleted
		items.forEach( function( item ) {
			item.isVisible = true;
		} );
		data = createBookshelfs( items );
	}
	return data;
}

function createBookshelfs( items ) {
	// Find all bookshelf names
	var bookshelfNames = [];
	items.forEach( function( item ) {
		if ( item.hasOwnProperty( 'bookshelf' ) ) {
			bookshelfNames.push( item.bookshelf );
		}
	} );

	// Make bookshelf names unique in array bookshelfNames
	bookshelfNames = bookshelfNames.filter( ( value, index, array ) => array.indexOf( value ) === index );

	// Fill bookshelfs with books
	var bookshelfsInData = [];
	bookshelfNames.forEach(
		function( bookshelfName ) {
			var bookshelfBooks = items.filter(
				obj => {
					return obj.bookshelf === bookshelfName
				}
			)

			bookshelfsInData.push( {
				name: bookshelfName,
				books: bookshelfBooks
			} );
		},
		{
			items: items,
		}
	);

	// Sort booksheslf alphabetically
	bookshelfsInData.sort( function( bookshelfA, bookshelfB ) {
		return bookshelfA.name.toLowerCase().localeCompare( bookshelfB.name.toLowerCase() );
	} )

	// Move various books (books without bookshelf value) to the end of the list
	const variousBooksIndex = bookshelfsInData.findIndex( bookshelf => bookshelf.name == '' );
	if ( variousBooksIndex >= 0 ) {
		const variousBooks = bookshelfsInData[variousBooksIndex];
		variousBooks.name = mw.message( 'bs-books-overview-page-bookshelf-various-books' ).plain();
		bookshelfsInData.shift( ...bookshelfsInData.splice( 0, variousBooksIndex ) );
		bookshelfsInData.push( variousBooks );
	}

	// Show only bookshelfs with visible books
	bookshelfsInData.forEach( function( item ) {
		var isVisible = false;
		item.books.forEach( function( book ) {
			if ( book.isVisible === true ) {
				isVisible = true;
			}
		} );
		item.isVisible = isVisible;
	} );

	return bookshelfsInData;
}

function updateAriaLiveSection( count ) {
	text = mw.message( 'bs-books-overview-page-aria-live-filtered-rows', count ).toString();
	$( '#bs-books-aria-lve' ).html( text );
}
</script>

<style lang="css">
:root {
	--bs-books-overview-page-focus-visible-color: #3E5389;
	--bs-books-overview-page-book-new: #BD1D1D;
}
.bs-books-search {
	width: 50%;
	margin-left: 20px;
}
.bs-books-bookshelfs {
	margin-top: 20px;
}
.bs-books-bookshelfs-empty {
	padding: 20px;
}
#bs-books-aria-lve {
	height: 0;
	overflow: hidden;
}
@media ( max-width: 768px ) {
	.bs-books-search {
		width: 100%;
		margin-left: 0px;
	}
}
</style>