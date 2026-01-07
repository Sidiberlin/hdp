( function ( mw, $, bs ) {
	bs.util.registerNamespace( 'bs.bookshelf.ui.dialog' );

	require( './../widget/ChapterPanel.js' );

	bs.bookshelf.ui.dialog.AddToBook = function ( config ) {
		config.size = 'medium';
		bs.bookshelf.ui.dialog.AddToBook.parent.call( this, config );

		this.pageName = config.pagename.replace( '_', ' ' );
	};

	OO.inheritClass( bs.bookshelf.ui.dialog.AddToBook, OOJSPlus.ui.dialog.FormDialog );

	bs.bookshelf.ui.dialog.AddToBook.prototype.getActions = function () {
		return [ 'cancel', 'add' ];
	};

	bs.bookshelf.ui.dialog.AddToBook.prototype.getTitle = function () {
		return mw.message( 'bs-bookshelf-add-to-book-label' ).text();
	};

	bs.bookshelf.ui.dialog.AddToBook.prototype.initialize = function () {
		bs.bookshelf.ui.dialog.AddToBook.parent.prototype.initialize.call( this );
		this.actions.setAbilities( { add: false } );
	};

	bs.bookshelf.ui.dialog.AddToBook.prototype.getFormItems = function () {
		this.bookPicker = new OOJSPlus.ui.widget.StoreDataInputWidget( {
			id: 'book-picker',
			queryAction: 'bs-bookshelf-store',
			labelField: 'book_displaytext',
			$overlay: this.$overlay
		} );
		this.chapterPicker = new ext.bookshelf.ui.widget.ChapterPanel( {
			id: 'chapter-picker',
			$overlay: this.$overlay,
			disabled: true
		} );

		this.bookPicker.connect( this, {
			change: function () {
				const value = this.bookPicker.getValue();
				if ( value.length > 0 ) {
					this.bookPicker.setIndicator( 'clear' );
					this.bookPicker.$indicator.attr( {
						tabindex: -1,
						role: 'button'
					} );
					this.bookPicker.$indicator.on( 'click', () => {
						this.bookPicker.setValue( '' );
						return false;
					} );
					this.chapterPicker.setDisabled( false );
					this.actions.setAbilities( { add: true } );
					const data = this.bookPicker.getSelectedItemData();
					if ( typeof data === 'object' && data !== null ) {
						this.chapterPicker.setChapters( data.book_id );
					} else {
						this.chapterPicker.setFirstChapter();
					}
				} else {
					this.bookPicker.setIndicator( null );
					this.bookPicker.closeLookupMenu();
					this.chapterPicker.clear();
				}
			}.bind( this )
		} );

		this.chapterPicker.connect( this, {
			updateUI: function () {
				this.updateSize();
				setTimeout( () => {
					this.bookPicker.closeLookupMenu();
				}, 100 );
			}
		} );

		return [
			new OO.ui.FieldLayout( this.bookPicker, {
				label: mw.message( 'bs-bookshelf-add-to-book-label-book' ).text(),
				align: 'top'
			} ),
			new OO.ui.FieldLayout( this.chapterPicker, {
				label: mw.message( 'bs-bookshelf-add-to-book-label-chapter' ).text(),
				align: 'top'
			} )
		];
	};

	/**
	 * @param {string} action
	 * @return {OO.ui.Process|null} if not handling
	 */
	bs.bookshelf.ui.dialog.AddToBook.prototype.onAction = function ( action ) {
		if ( action === 'add' ) {
			const dfd = $.Deferred();
			const selectedBook = this.bookPicker.getSelectedItemData();
			let bookName = '';
			if ( selectedBook === null ) {
				bookName = 'Book:' + this.bookPicker.getValue();
			} else {
				bookName = selectedBook.book_prefixedtext;
			}

			const chapters = this.chapterPicker.getChapters();
			const data = {
				nodes: chapters
			};

			mw.loader.using( 'bluespice.bookshelf.api' ).done( () => {
				const bookApi = new ext.bookshelf.api.Api();
				bookApi.getBookMetadata( bookName ).done( ( meta ) => {
					data.metadata = meta;
					mw.loader.using( 'ext.menuEditor.api' ).done( () => {
						const api = new ext.menueditor.api.Api();
						const bookTitle = mw.util.rawurlencode( mw.util.rawurlencode( bookName ) );

						api.post( bookTitle, data ).done( () => {
							mw.notify(
								mw.message( 'bs-bookshelf-add-to-book-added', this.bookPicker.getValue() ).text(),
								{ title: mw.message( 'bs-bookshelf-add-to-book-label' ).text() }
							);
							this.close( { action: action, book: bookName } );
							dfd.resolve();
						} ).fail( ( error ) => {
							dfd.reject( new OO.ui.Error( error ) );
						} );
					} );
				} );
			} );

			return dfd.promise();
		}

		return bs.bookshelf.ui.dialog.AddToBook.parent.prototype.onAction.call( this, action );
	};

}( mediaWiki, jQuery, blueSpice ) );
