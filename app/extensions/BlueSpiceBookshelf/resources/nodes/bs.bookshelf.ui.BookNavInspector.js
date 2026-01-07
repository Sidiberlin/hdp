( function ( mw, $, d, bs ) {
	bs.util.registerNamespace( 'bs.bookshelf.ui' );
	bs.bookshelf.ui.BookNavInspector = function BsBookshelfUiBookNavInspector( config ) {
		// Parent constructor
		bs.bookshelf.ui.BookNavInspector.super.call( this, ve.extendObject( { padded: true }, config ) );
	};

	/* Inheritance */

	OO.inheritClass( bs.bookshelf.ui.BookNavInspector, ve.ui.MWLiveExtensionInspector );

	/* Static properties */

	bs.bookshelf.ui.BookNavInspector.static.name = 'booknavInspector';

	bs.bookshelf.ui.BookNavInspector.static.title = OO.ui.deferMsg(
		'bs-bookshelf-booknav-title'
	);

	bs.bookshelf.ui.BookNavInspector.static.modelClasses = [ bs.bookshelf.dm.BookNavNode ];

	bs.bookshelf.ui.BookNavInspector.static.dir = 'ltr';

	// This tag does not have any content
	bs.bookshelf.ui.BookNavInspector.static.allowedEmpty = true;
	bs.bookshelf.ui.BookNavInspector.static.selfCloseEmptyBody = true;

	/* Methods */

	/**
	 * @inheritdoc
	 */
	bs.bookshelf.ui.BookNavInspector.prototype.initialize = function () {
		// Parent method
		bs.bookshelf.ui.BookNavInspector.super.prototype.initialize.call( this );
		this.input.$element.remove();
		// Index layout
		this.indexLayout = new OO.ui.PanelLayout( {
			scrollable: false,
			expanded: false
		} );

		this.bookInput = new mw.widgets.TitleInputWidget( {
			required: true,
			namespace: 1504,
			$overlay: true
		} );
		this.chapterInput = new OO.ui.TextInputWidget();

		this.bookLayout = new OO.ui.FieldLayout( this.bookInput, {
			align: 'left',
			label: ve.msg( 'bs-bookshelf-booknav-book-label' ),
			help: ve.msg( 'bs-bookshelf-booknav-book-help' )
		} );
		this.chapterLayout = new OO.ui.FieldLayout( this.chapterInput, {
			align: 'left',
			label: ve.msg( 'bs-bookshelf-booknav-chapter-label' ),
			help: ve.msg( 'bs-bookshelf-booknav-chapter-help' )
		} );

		this.indexLayout.$element.append(
			this.bookLayout.$element,
			this.chapterLayout.$element,
			this.generatedContentsError.$element
		);
		this.form.$element.append(
			this.indexLayout.$element
		);
	};

	/**
	 * @inheritdoc
	 */
	bs.bookshelf.ui.BookNavInspector.prototype.getSetupProcess = function ( data ) {
		return bs.bookshelf.ui.BookNavInspector.super.prototype.getSetupProcess.call( this, data )
			.next( function () {
				const attributes = this.selectedNode.getAttribute( 'mw' ).attrs;

				if ( attributes.book ) {
					this.bookInput.setValue( attributes.book );
				}
				if ( attributes.chapter ) {
					this.chapterInput.setValue( attributes.chapter );
				}

				this.bookInput.on( 'change', this.onChangeHandler );
				this.chapterInput.on( 'change', this.onChangeHandler );

				// Get this out of here
				this.actions.setAbilities( { done: true } );
			}, this );
	};

	bs.bookshelf.ui.BookNavInspector.prototype.updateMwData = function ( mwData ) {
		// Parent method
		bs.bookshelf.ui.BookNavInspector.super.prototype.updateMwData.call( this, mwData );

		if ( this.bookInput.getValue() ) {
			mwData.attrs.book = this.bookInput.getValue();
		} else {
			delete ( mwData.attrs.book );
		}

		if ( this.chapterInput.getValue() ) {
			mwData.attrs.chapter = this.chapterInput.getValue();
		} else {
			delete ( mwData.attrs.chapter );
		}
	};

	/**
	 * @inheritdoc
	 */
	bs.bookshelf.ui.BookNavInspector.prototype.formatGeneratedContentsError = function ( $element ) {
		return $element.text().trim();
	};

	/**
	 * Append the error to the current tab panel.
	 */
	bs.bookshelf.ui.BookNavInspector.prototype.onTabPanelSet = function () {
		this.indexLayout.getCurrentTabPanel().$element.append( this.generatedContentsError.$element );
	};

	/* Registration */

	ve.ui.windowFactory.register( bs.bookshelf.ui.BookNavInspector );

}( mediaWiki, jQuery, document, blueSpice ) );
