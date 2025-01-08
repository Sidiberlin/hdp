( function ( mw, bs, $ ) {
	bs.bookshelf.storageLocation.WikiPage = function( cfg ) {};

	OO.initClass( bs.bookshelf.storageLocation.WikiPage );

	bs.bookshelf.storageLocation.WikiPage.prototype.getSaveAction = function( title, content ) {
		return new BS.action.APIEditPage( {
			pageTitle: title,
			pageContent: content
		} );
	};

	bs.bookshelf.storageLocation.WikiPage.prototype.appendText = function( record, content, modifyTag, pageId ) {
		var api = new mw.Api(),
			dfd = $.Deferred();

		pageId = pageId || record.get( 'page_id' );

		api.postWithToken( 'csrf', {
			action: 'edit',
			pageid: pageId,
			appendtext: content
		} ).done( function( response ){
			this.updateBookshelfTag( mw.config.get( 'wgPageName' ), response.edit.title ).done(
				function() {
					dfd.resolve();
				}
			).fail( function() {
				dfd.reject();
			} );
		}.bind( this ) ).fail( function() {
			dfd.reject();
		} );

		return dfd.promise();
	};

	bs.bookshelf.storageLocation.WikiPage.prototype.updateBookshelfTag = function( page, bookTitle ) {
		var api = new mw.Api(),
			dfd = $.Deferred();

		api.postWithToken( 'csrf', {
			action: 'parse',
			prop: 'wikitext',
			page: page
		} ).done( function( response ){
			if ( !response.hasOwnProperty( 'parse' ) || !response.parse.hasOwnProperty( 'wikitext' ) ) {
				dfd.reject();
				return;
			}
			var content = response.parse.wikitext['*'];
			content = content.replace( /<(bs:)?bookshelf.*?(src|book)=\"(.*?)\".*?\/>/gi, function( fullmatch, group ) {
				return '';
			} );
			content = '<bs:bookshelf src="' + bookTitle + '" />' + "\n" + content.trim();
			api.postWithToken( 'csrf', {
				action: 'edit',
				title: page,
				text: content
			} ).done( function( response ) {
				if ( response.edit.result === 'Success' ) {
					dfd.resolve();
				} else {
					dfd.fail();
				}
			} ).fail( function() {
				dfd.reject();
			} );
		} ).fail( function() {
			dfd.reject();
		} );

		return dfd.promise();
	};

	bs.bookshelf.storageLocation.WikiPage.prototype.delete = function( record ) {
		var pageId = record.get('page_id'),
			dfd = $.Deferred();

		var api = new mw.Api();
		api.postWithToken( 'csrf', {
			action: 'bs-bookshelf-manage',
			task: 'deleteBook',
			taskData: JSON.stringify( {
				'book_page_id': pageId
			} )
		} )
		.fail( function( protocol, response ) {
			dfd.reject( response.exception );
		} )
		.done( function( response, xhr ){
			dfd.resolve( response.success, response );
		} );

		return dfd.promise();
	};

	bs.bookshelf.storageLocation.WikiPage.prototype.getBookPageText = function( data ) {
		var api = new mw.Api(),
			dfd = $.Deferred();

		api.get( {
			action: 'query',
			pageids: data.page_id,
			prop: 'revisions',
			rvprop: 'content',
			indexpageids : ''
		} ).done(function( resp, jqXHR ){
			var pageId = resp.query.pageids[0];
			dfd.resolve( resp.query.pages[pageId].revisions[0]['*'] );
		} );

		return dfd.promise();
	};

	bs.bookshelf.storageLocation.WikiPage.prototype.isTitleBased = function() {
		return true;
	};
} ) ( mediaWiki, blueSpice, jQuery );
