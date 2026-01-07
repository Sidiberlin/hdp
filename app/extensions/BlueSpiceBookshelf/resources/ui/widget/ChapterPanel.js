bs.util.registerNamespace( 'ext.bookshelf.ui.widget' );

require( './ChapterInsertionWidget.js' );

ext.bookshelf.ui.widget.ChapterPanel = function ( config ) {
	config = config || {};
	ext.bookshelf.ui.widget.ChapterPanel.parent.call( this, config );
	this.$overlay = config.$overlay || true;
	this.chapters = [];
	this.currentVisibleChapters = [];
	this.initialize();
};

OO.inheritClass( ext.bookshelf.ui.widget.ChapterPanel, OO.ui.Widget );

ext.bookshelf.ui.widget.ChapterPanel.prototype.initialize = function () {
	this.chapterPicker = new OO.ui.DropdownWidget( {
		$overlay: this.$overlay || true,
		disabled: true
	} );
	this.chapterPicker.getMenu().connect( this, {
		select: 'updateInsertion'
	} );
	this.chapterPickerClear = new OO.ui.ButtonWidget( {
		icon: 'close',
		framed: false,
		label: mw.message( 'bs-bookshelf-chapter-insertion-clear-btn-label' ).text(),
		invisibleLabel: true
	} );
	this.chapterPickerClear.connect( this, {
		click: function () {
			this.chapterPicker.getMenu().unselectItem( this.chapterPicker.getMenu().findSelectedItem() );
		}
	} );
	this.chapterPickerClear.toggle( false );
	this.$element.append( new OO.ui.HorizontalLayout( {
		classes: [ 'bs-bookshelf-chapter-picker-layout' ],
		items: [
			this.chapterPicker,
			this.chapterPickerClear
		] } ).$element );

	this.$insertionDetails = $( '<div>' ).addClass( 'bs-bookshelf-chapter-insertion-details' );
	this.$element.append( this.$insertionDetails );
};

ext.bookshelf.ui.widget.ChapterPanel.prototype.setChapters = function ( bookId ) {
	new mw.Api().get( {
		action: 'bs-book-chapters-store',
		limit: 9999,
		filter: JSON.stringify( [ {
			value: bookId,
			property: 'chapter_book_id',
			operator: 'eq',
			type: 'string'
		} ] )
	} ).done( ( result ) => {
		this.chapters = [];
		this.chapterPicker.getMenu().removeItems( this.chapterPicker.getMenu().getItems() );
		if ( result.results.length > 0 ) {
			for ( const chapter of result.results ) {
				this.chapters.push(
					new OO.ui.MenuOptionWidget( {
						data: {
							id: chapter.chapter_id,
							number: chapter.chapter_number,
							text: chapter.chapter_name,
							namespace: chapter.chapter_namespace,
							title: chapter.chapter_title,
							type: chapter.chapter_type
						},
						label: chapter.chapter_number + ' ' + chapter.chapter_name
					} )
				);
			}
			this.chapterPicker.getMenu().addItems( this.chapters );
			this.chapterPicker.setDisabled( false );
		} else {
			this.clearChapterPicker();
		}

		this.initializeChapterWidgets();
		this.addAsLastChapter();
		this.emit( 'updateUI' );
	} );
};

ext.bookshelf.ui.widget.ChapterPanel.prototype.updateInsertion = function ( selectedChapter ) {
	if ( selectedChapter === null ) {
		this.addAsLastChapter();
		this.chapterPickerClear.toggle( false );
		this.emit( 'updateUI' );
		return;
	}
	let index = this.chapters.findIndex( ( obj ) => obj.data.id === selectedChapter.data.id );
	const insertChapterNumber = this.chapters[ index ].data.number;
	const insertNumbersSplit = insertChapterNumber.split( '.' );
	const insertNumbersLength = insertNumbersSplit.length;
	const insertChildLevel = insertNumbersLength + 1;
	index++;
	this.currentVisibleChapters = [];
	for ( index; index < this.chapters.length; index++ ) {
		const chapterNumber = this.chapters[ index ].data.number;
		const chapterSplit = chapterNumber.split( '.' );
		if ( insertChildLevel === chapterSplit.length ) {
			this.currentVisibleChapters.push( this.chapters[ index ] );
			continue;
		}
		if ( insertNumbersLength < chapterSplit.length ) {
			continue;
		}
		if ( insertNumbersLength >= chapterSplit.length ) {
			break;
		}
	}
	this.addAsLastSubChapter( insertChapterNumber );
	this.chapterPickerClear.toggle( true );
};

ext.bookshelf.ui.widget.ChapterPanel.prototype.clearChapterPicker = function () {
	const selectedItem = this.chapterPicker.getMenu().findSelectedItem();
	if ( selectedItem ) {
		this.chapterPicker.getMenu().unselectItem( selectedItem );
	}
	this.chapterPicker.setDisabled( true );
};

ext.bookshelf.ui.widget.ChapterPanel.prototype.parseChapterNumber = function ( number ) {
	const parts = number.split( '.' );
	const lastIndex = parts.length - 1;
	parts[ lastIndex ] = ( parseInt( parts[ lastIndex ] ) + 1 ).toString();
	return parts.join( '.' );
};

ext.bookshelf.ui.widget.ChapterPanel.prototype.setFirstChapter = function () {
	this.clearChapterPicker();
	this.initializeChapterWidgets();

	this.updateChapterNavigation( {
		showPrevious: false,
		showNext: false,
		enableMoveUp: false,
		enableMoveDown: false,
		currentNumber: '1'
	} );
	this.emit( 'updateUI' );
};

ext.bookshelf.ui.widget.ChapterPanel.prototype.addAsLastChapter = function () {
	this.currentVisibleChapters = [];
	if ( this.chapters.length < 1 ) {
		this.updateChapterNavigation( {
			showPrevious: false,
			showNext: false,
			enableMoveUp: false,
			enableMoveDown: false,
			currentNumber: '1'
		} );
		return;
	}

	const number = this.chapters[ this.chapters.length - 1 ].data.number;
	const firstChapter = number.split( '.' )[ 0 ];
	for ( let i = 0; i < this.chapters.length; i++ ) {
		const chapterNumber = this.chapters[ i ].data.number;
		const chapterSplit = chapterNumber.split( '.' );
		if ( chapterSplit.length !== 1 ) {
			continue;
		}
		this.currentVisibleChapters.push( this.chapters[ i ] );
	}

	this.updateChapterNavigation( {
		showPrevious: true,
		showNext: false,
		enableMoveUp: true,
		enableMoveDown: false,
		currentNumber: this.parseChapterNumber( firstChapter ),
		previousLabel: this.currentVisibleChapters[ this.currentVisibleChapters.length - 1 ].data.text,
		previousNumber: firstChapter
	} );
};

ext.bookshelf.ui.widget.ChapterPanel.prototype.addAsLastSubChapter = function ( parentChapterNumber ) {
	if ( this.currentVisibleChapters.length < 1 ) {
		this.updateChapterNavigation( {
			showPrevious: false,
			showNext: false,
			enableMoveUp: false,
			enableMoveDown: false,
			currentNumber: parentChapterNumber + '.1'
		} );
		return;
	}

	const lastElementIndex = this.currentVisibleChapters.length - 1;
	const number = this.currentVisibleChapters[ lastElementIndex ].data.number;

	this.updateChapterNavigation( {
		showPrevious: true,
		showNext: false,
		enableMoveUp: true,
		enableMoveDown: false,
		currentNumber: this.parseChapterNumber( number ),
		previousLabel: this.currentVisibleChapters[ lastElementIndex ].data.text,
		previousNumber: number
	} );
};

ext.bookshelf.ui.widget.ChapterPanel.prototype.moveUpChapter = function () {
	const currentNumber = this.insertionChapterWidget.getNumber();
	const previousLabel = this.previousChapterWidget.getLabel();
	const previousNumber = this.previousChapterWidget.getNumber();
	const previousIndex = this.currentVisibleChapters.findIndex( ( obj ) => obj.data.number === previousNumber );

	const enableMoveDown = true;
	if ( previousIndex === 0 ) {
		this.updateChapterNavigation( {
			showPrevious: false,
			showNext: true,
			enableMoveUp: false,
			enableMoveDown: enableMoveDown,
			currentNumber: previousNumber,
			nextLabel: previousLabel,
			nextNumber: currentNumber
		} );
		return;
	}

	const newPreviousChapter = this.currentVisibleChapters[ previousIndex - 1 ];

	this.updateChapterNavigation( {
		showPrevious: true,
		showNext: true,
		enableMoveUp: true,
		enableMoveDown: enableMoveDown,
		currentNumber: previousNumber,
		previousLabel: newPreviousChapter.data.text,
		previousNumber: newPreviousChapter.data.number,
		nextLabel: previousLabel,
		nextNumber: currentNumber
	} );
};

ext.bookshelf.ui.widget.ChapterPanel.prototype.moveDownChapter = function () {
	const currentNumber = this.insertionChapterWidget.getNumber();
	const nextLabel = this.nextChapterWidget.getLabel();
	const nextNumber = this.nextChapterWidget.getNumber();
	const currentIndex = this.currentVisibleChapters.findIndex( ( obj ) => obj.data.number === currentNumber );
	const enableMoveUp = true;

	if ( currentIndex === this.currentVisibleChapters.length - 1 ) {
		this.updateChapterNavigation( {
			showPrevious: true,
			showNext: false,
			enableMoveUp: enableMoveUp,
			enableMoveDown: false,
			currentNumber: nextNumber,
			previousLabel: nextLabel,
			previousNumber: currentNumber
		} );
		return;
	}

	const newNextChapter = this.currentVisibleChapters[ currentIndex + 1 ];

	this.updateChapterNavigation( {
		showPrevious: true,
		showNext: true,
		enableMoveUp: enableMoveUp,
		enableMoveDown: true,
		currentNumber: nextNumber,
		previousLabel: nextLabel,
		previousNumber: currentNumber,
		nextLabel: newNextChapter.data.text,
		nextNumber: this.parseChapterNumber( newNextChapter.data.number )
	} );
};

ext.bookshelf.ui.widget.ChapterPanel.prototype.updateChapterNavigation = function ( params ) {
	this.previousChapterWidget.toggle( params.showPrevious );
	this.nextChapterWidget.toggle( params.showNext );
	this.insertionChapterWidget.toggleMoveUpButton( params.enableMoveUp );
	this.insertionChapterWidget.toggleMoveDownButton( params.enableMoveDown );

	this.insertionChapterWidget.updateNumber( params.currentNumber );

	if ( params.previousLabel ) {
		this.previousChapterWidget.updateLabel( params.previousLabel );
		this.previousChapterWidget.updateNumber( params.previousNumber );
	}

	if ( params.nextLabel ) {
		this.nextChapterWidget.updateLabel( params.nextLabel );
		this.nextChapterWidget.updateNumber( params.nextNumber );
	}
	this.emit( 'updateUI' );
};

ext.bookshelf.ui.widget.ChapterPanel.prototype.initializeChapterWidgets = function () {
	if ( !this.previousChapterWidget ) {
		this.previousChapterWidget = new ext.bookshelf.ui.widget.ChapterInsertionWidget();
		this.$insertionDetails.append( this.previousChapterWidget.$element );
	}

	if ( !this.insertionChapterWidget ) {
		this.insertionChapterWidget = new ext.bookshelf.ui.widget.ChapterInsertionWidget( {
			label: mw.config.get( 'wgPageName' ),
			isEditAllowed: true
		} );
		this.insertionChapterWidget.connect( this, {
			moveUp: 'moveUpChapter',
			moveDown: 'moveDownChapter'
		} );
		this.$insertionDetails.append( this.insertionChapterWidget.$element );
	}
	this.insertionChapterWidget.toggle( true );

	if ( !this.nextChapterWidget ) {
		this.nextChapterWidget = new ext.bookshelf.ui.widget.ChapterInsertionWidget();
		this.$insertionDetails.append( this.nextChapterWidget.$element );
	}
};

ext.bookshelf.ui.widget.ChapterPanel.prototype.getChapters = function () {
	let index = -1;

	if ( this.previousChapterWidget.isVisible() ) {
		const chapterindex = this.chapters.findIndex( ( obj ) => obj.data.number === this.previousChapterWidget.getNumber() );
		const chapterLevel = this.calculateLevel( this.chapters[ chapterindex ].data.number );
		index = chapterindex;
		for ( let i = chapterindex + 1; i < this.chapters.length; i++ ) {
			const chapterData = this.chapters[ i ].data;
			if ( this.calculateLevel( chapterData.number ) > chapterLevel ) {
				index++;
				continue;
			}
			break;
		}
	}
	if ( this.chapterPicker.getMenu().findSelectedItem() && index < 0 ) {
		const id = this.chapterPicker.getMenu().findSelectedItem().data.id;
		index = this.chapters.findIndex( ( obj ) => obj.data.id === id );
	}

	if ( index < 0 ) {
		return [ {
			type: 'bs-bookshelf-chapter-wikilink-with-alias',
			label: this.insertionChapterWidget.getLabel(),
			level: '1',
			target: mw.config.get( 'wgPageName' )
		} ];
	}
	index++;

	const structuredChapters = [];
	for ( const i in this.chapters ) {
		const chapterData = this.chapters[ i ].data;
		if ( parseInt( i ) === index ) {
			structuredChapters.push( {
				type: 'bs-bookshelf-chapter-wikilink-with-alias',
				label: this.insertionChapterWidget.getLabel(),
				level: this.calculateLevel( this.insertionChapterWidget.getNumber() ),
				target: mw.config.get( 'wgPageName' )
			} );
		}
		if ( chapterData.type !== 'wikilink-with-alias' ) {
			structuredChapters.push( {
				type: 'bs-bookshelf-chapter-plain-text',
				text: chapterData.text,
				level: this.calculateLevel( chapterData.number )
			} );
			continue;
		}
		const title = mw.Title.newFromText( chapterData.title, chapterData.namespace );
		structuredChapters.push( {
			type: 'bs-bookshelf-chapter-wikilink-with-alias',
			label: chapterData.text,
			level: this.calculateLevel( chapterData.number ),
			target: title.getPrefixedText()
		} );
	}
	if ( index >= this.chapters.length ) {
		structuredChapters.push( {
			type: 'bs-bookshelf-chapter-wikilink-with-alias',
			label: this.insertionChapterWidget.getLabel(),
			level: this.calculateLevel( this.insertionChapterWidget.getNumber() ),
			target: mw.config.get( 'wgPageName' )
		} );
	}
	return structuredChapters;
};

ext.bookshelf.ui.widget.ChapterPanel.prototype.calculateLevel = function ( number ) {
	const parts = number.split( '.' );
	return parts.length;
};

ext.bookshelf.ui.widget.ChapterPanel.prototype.clear = function () {
	this.chapterPicker.setDisabled( true );
	if ( this.chapterPicker.getMenu().findSelectedItem() ) {
		this.chapterPicker.getMenu().unselectItem( this.chapterPicker.getMenu().findSelectedItem() );
	}
	if ( this.previousChapterWidget ) {
		this.previousChapterWidget.toggle( false );
	}
	if ( this.nextChapterWidget ) {
		this.nextChapterWidget.toggle( false );
	}
	if ( this.insertionChapterWidget ) {
		this.insertionChapterWidget.toggle( false );
	}
	this.emit( 'updateUI' );
};

ext.bookshelf.ui.widget.ChapterPanel.prototype.setDisabled = function ( state ) {
	ext.bookshelf.ui.widget.ChapterPanel.parent.prototype.setDisabled.call( this, state );
	if ( this.chapterPicker ) {
		this.chapterPicker.setDisabled( state );
	}
};
