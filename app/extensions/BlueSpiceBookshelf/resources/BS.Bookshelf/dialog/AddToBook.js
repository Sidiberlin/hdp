( function( mw, $, bs ) {
	bs.util.registerNamespace( 'bs.bookshelf.dialog' );

	bs.bookshelf.dialog.AddToBook = function( config ) {
		config.size = 'medium';
		bs.bookshelf.dialog.AddToBook.parent.call( this, config );

		this.pageName = config.pagename.replace( '_', ' ' );
	};

	OO.inheritClass( bs.bookshelf.dialog.AddToBook, OOJSPlus.ui.dialog.FormDialog );

	bs.bookshelf.dialog.AddToBook.prototype.getActions = function() {
		return [ 'cancel', 'add' ];
	};

	bs.bookshelf.dialog.AddToBook.prototype.getTitle = function() {
		return mw.message( 'bs-bookshelf-add-to-book-label' ).plain();
	};

	bs.bookshelf.dialog.AddToBook.prototype.initialize = function() {
		bs.bookshelf.dialog.AddToBook.parent.prototype.initialize.call( this );
		this.actions.setAbilities( { add: false } );
	};

	bs.bookshelf.dialog.AddToBook.prototype.getFormItems = function() {
		this.bookPicker = new OOJSPlus.ui.widget.StoreDataInputWidget( {
			id: 'book-picker',
			queryAction: 'bs-bookshelf-store',
			labelField: 'book_displaytext',
			groupBy: 'book_type',
			groupLabelCallback: function( label, data ) {
				var location = bs.bookshelf.storageLocationRegistry.lookup( label );
				return location.getLabel();
			},
			$overlay: this.$overlay
		} );
		this.bookPicker.connect( this, {
			change: function() {
				var data = this.bookPicker.getSelectedItemData();
				if ( typeof data === 'object' && data !== null ) {
					this.actions.setAbilities( { add: true } );
				} else {
					this.actions.setAbilities( { add: false } );
				}
			}.bind( this )
		} );
		this.alias = new OO.ui.TextInputWidget( {
			value: this.pageName,
			id: 'page-alias'
		} );

		return [
			new OO.ui.FieldLayout( this.bookPicker, {
				label: mw.message( 'bs-bookshelf-add-to-book-label-book' ).plain(),
				align: 'top'
			} ),
			new OO.ui.FieldLayout( this.alias, {
				label: mw.message( 'bs-bookshelf-add-to-book-label-alias' ).plain(),
				align: 'top'
			} )
		];
	};

	/**
	 *
	 * @param string action
	 * @returns OO.ui.Process|null if not handling
	 */
	bs.bookshelf.dialog.AddToBook.prototype.onAction = function( action ) {
		if ( action === 'add' ) {
			var dfd = $.Deferred();
			var selectedBook = this.bookPicker.getSelectedItemData();
			if ( selectedBook === null ) {
				// Sanity
				dfd.reject( new OO.ui.Error() );
			}
			var alias = this.alias.getValue(),
				messageTitle = mw.message( 'bs-bookshelf-add-to-book-label' ).plain();

			var wikiText = "\n* [[{0}|{1}]]".format(
				this.pageName,
				alias
			);

			mw.loader.using( 'mediawiki.api' ).done( function () {
				const mwApi = new mw.Api();
				mwApi.postWithToken( 'csrf', {
					action: 'edit',
					title: selectedBook.book_prefixedtext,
					appendtext: wikiText,
					summary: mw.message( 'bs-bookshelf-add-to-book-summary', this.pageName ).text()
				} ).fail( function ( error ) {
					dfd.reject( new OO.ui.Error( error ) );
				} )
					.done( function () {
						mw.notify(
							mw.message( 'bs-bookshelf-add-to-book-added', selectedBook.book_displaytext ).plain(),
							{ title: messageTitle }
						);
						this.close( { action: action } );
						dfd.resolve();
				}.bind( this ) );
			}.bind( this ) );

			return dfd.promise();
		}

		return bs.bookshelf.dialog.AddToBook.parent.prototype.onAction.call( this, action );
	};

} ) ( mediaWiki, jQuery, blueSpice );
