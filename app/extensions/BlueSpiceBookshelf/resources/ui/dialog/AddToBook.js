( function ( mw, $, bs ) {
	bs.util.registerNamespace( 'bs.bookshelf.ui.dialog' );

	require( './../widget/ChapterPanel.js' );

	bs.bookshelf.ui.dialog.AddToBook = function ( config ) {
		config.size = 'medium';
		bs.bookshelf.ui.dialog.AddToBook.parent.call( this, config );

		this.pageName = config.pagename.replace( '_', ' ' );
	};

	OO.inheritClass( bs.bookshelf.ui.dialog.AddToBook, OOJSPlus.ui.dialog.FormDialog );

	bs.bookshelf.ui.dialog.AddToBook.prototype.makeApi = function () {
		mw.loader.using( 'bluespice.bookshelf.api' ).done( () => {
			this.bookApi = new ext.bookshelf.api.Api();
		} );
	};

	bs.bookshelf.ui.dialog.AddToBook.prototype.getActions = function () {
		return [ 'cancel', 'add' ];
	};

	bs.bookshelf.ui.dialog.AddToBook.prototype.getTitle = function () {
		return mw.message( 'bs-bookshelf-add-to-book-label' ).text();
	};

	bs.bookshelf.ui.dialog.AddToBook.prototype.getSetupProcess = function ( data ) {
		this.makeApi();

		return bs.bookshelf.ui.dialog.AddToBook.parent.prototype.getSetupProcess.call( this, data ).next( () => {
			this.actions.setAbilities( { add: false } );
		} );
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
			change: 'onBookChange'
		} );

		this.bookPickerLayout = new OO.ui.FieldLayout( this.bookPicker, {
			label: mw.message( 'bs-bookshelf-add-to-book-label-book' ).text(),
			align: 'top'
		} );

		return [
			this.bookPickerLayout,
			new OO.ui.FieldLayout( this.chapterPicker, {
				label: mw.message( 'bs-bookshelf-add-to-book-label-chapter' ).text(),
				align: 'top'
			} )
		];
	};

	bs.bookshelf.ui.dialog.AddToBook.prototype.onBookChange = function ( value ) {
		this.selectedBook = null;
		this.bookPickerLayout.setWarnings( [] );
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
			this.trySetBook( value, this.bookPicker.getSelectedItemData() ).then( () => {
				if ( this.selectedBook && !this.selectedBook.id ) {
					this.bookPickerLayout.setWarnings( [ mw.message( 'bs-bookshelf-add-to-book-warning-new-book' ).text() ] );
				}
				if ( this.selectedBook && this.selectedBook.id ) {
					this.chapterPicker.setChapters( this.selectedBook.id );
					this.chapterPicker.setDisabled( false );
				} else {
					this.chapterPicker.setFirstChapter();
				}

				this.updateSize();
				this.updateSize();
				setTimeout( () => {
					if ( this.bookPicker.lookupMenu && this.bookPicker.lookupMenu.isVisible() ) {
						this.bookPicker.lookupMenu.position();
						this.bookPicker.lookupMenu.clip();
					}
				}, 1 );

				this.actions.setAbilities( { add: !!this.selectedBook } );
			} );
		} else {
			this.bookPicker.setIndicator( null );
			this.bookPicker.closeLookupMenu();
			this.chapterPicker.clear();
			this.actions.setAbilities( { add: false } );
		}
	};

	bs.bookshelf.ui.dialog.AddToBook.prototype.trySetBook = function ( value, data ) {
		const dfd = $.Deferred();

		if ( typeof data === 'object' && data !== null ) {
			this.selectedBook = {
				id: data.book_id,
				name: data.book_prefixedtext,
				meta: data.book_meta
			};
			dfd.resolve();
		} else {
			const bookTitle = mw.Title.makeTitle( mw.config.get( 'wgNamespaceIds' ).book, value );
			if ( bookTitle ) {
				this.bookApi.getBookInfo( bookTitle.getPrefixedText() ).done( ( info ) => {
					info.name = bookTitle.getPrefixedText();
					this.selectedBook = info;
					dfd.resolve();
				} ).fail( () => {
					this.selectedBook = {
						id: null,
						name: bookTitle.getPrefixedText(),
						meta: []
					};
					dfd.resolve();
				} );
			} else {
				dfd.resolve();
			}
		}
		return dfd.promise();
	};

	/**
	 * @param {string} action
	 * @return {OO.ui.Process|null} if not handling
	 */
	bs.bookshelf.ui.dialog.AddToBook.prototype.onAction = function ( action ) {
		if ( action === 'add' ) {
			const dfd = $.Deferred();
			if ( !this.selectedBook ) {
				return dfd.reject().promise();
			}

			const [ prev, toInsert ] = this.chapterPicker.getChapterToInsert();
			const data = {
				nodes: [ toInsert ],
				after: prev || null
			};

			data.metadata = this.selectedBook.meta;
			mw.loader.using( 'ext.menuEditor.api' ).done( () => {
				const api = new ext.menueditor.api.Api();
				const bookTitle = mw.util.rawurlencode( mw.util.rawurlencode( this.selectedBook.name ) );
				api.post( `append/${ bookTitle }`, data ).done( () => {
					mw.notify(
						mw.message( 'bs-bookshelf-add-to-book-added', this.bookPicker.getValue() ).text(),
						{ title: mw.message( 'bs-bookshelf-add-to-book-label' ).text() }
					);
					this.close( { action: action, book: this.selectedBook.name } );
					dfd.resolve();
				} ).fail( ( error ) => {
					dfd.reject( new OO.ui.Error( error ) );
				} );
			} );

			return dfd.promise();
		}

		return bs.bookshelf.ui.dialog.AddToBook.parent.prototype.onAction.call( this, action );
	};

}( mediaWiki, jQuery, blueSpice ) );
