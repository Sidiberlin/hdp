bs.util.registerNamespace( 'ext.bookshelf.ui.widget' );

ext.bookshelf.ui.widget.ChapterInsertionWidget = function ( config ) {
	config = config || {};
	ext.bookshelf.ui.widget.ChapterInsertionWidget.parent.call( this, config );
	this.number = config.number;
	this.label = config.label;
	this.isEditAllowed = config.isEditAllowed || false;
	this.initialize();
};

OO.inheritClass( ext.bookshelf.ui.widget.ChapterInsertionWidget, OO.ui.Widget );

ext.bookshelf.ui.widget.ChapterInsertionWidget.prototype.initialize = function () {
	const elements = [];
	this.chapterNumberLabel = new OO.ui.LabelWidget( {
		label: this.number,
		classes: [ 'bs-bookshelf-chapter-insertion-number' ]
	} );
	elements.push( this.chapterNumberLabel );
	if ( this.isEditAllowed ) {
		this.chapterText = new OO.ui.TextInputWidget( {
			value: this.label,
			classes: [ 'bs-bookshelf-chapter-insertion-add-label' ]
		} );
		this.moveUpButton = new OO.ui.ButtonWidget( {
			icon: 'collapse',
			label: mw.message( 'bs-bookshelf-chapter-insertion-btn-move-up' ).text(),
			framed: false,
			invisibleLabel: true,
			classes: [ 'bs-bookshelf-chapter-insertion-move-btn' ]
		} );
		this.moveDownButton = new OO.ui.ButtonWidget( {
			icon: 'expand',
			label: mw.message( 'bs-bookshelf-chapter-insertion-btn-move-down' ).text(),
			framed: false,
			invisibleLabel: true,
			classes: [ 'bs-bookshelf-chapter-insertion-move-btn' ]
		} );

		this.moveUpButton.connect( this, {
			click: function () {
				this.emit( 'moveUp' );
			}
		} );
		this.moveDownButton.connect( this, {
			click: function () {
				this.emit( 'moveDown' );
			}
		} );
		elements.push( this.chapterText );
		elements.push( this.moveUpButton );
		elements.push( this.moveDownButton );
		this.$element.addClass( 'bs-bookshelf-chapter-insertion-editable' );
	} else {
		this.chapterText = new OO.ui.LabelWidget( {
			label: this.label
		} );
		elements.push( this.chapterText );
	}
	const layout = new OO.ui.HorizontalLayout( {
		items: elements
	} );
	this.$element.append( layout.$element );
};

ext.bookshelf.ui.widget.ChapterInsertionWidget.prototype.updateNumber = function ( number ) {
	this.chapterNumberLabel.setLabel( number );
};

ext.bookshelf.ui.widget.ChapterInsertionWidget.prototype.updateLabel = function ( label ) {
	if ( this.isEditAllowed ) {
		this.chapterText.setValue( label );
		return;
	}
	this.chapterText.setLabel( label );
};

ext.bookshelf.ui.widget.ChapterInsertionWidget.prototype.getNumber = function () {
	return this.chapterNumberLabel.getLabel();
};

ext.bookshelf.ui.widget.ChapterInsertionWidget.prototype.getLabel = function () {
	if ( this.isEditAllowed ) {
		return this.chapterText.getValue();
	}
	return this.chapterText.getLabel();
};

ext.bookshelf.ui.widget.ChapterInsertionWidget.prototype.toggleMoveUpButton = function ( visible ) {
	if ( !this.isEditAllowed ) {
		return;
	}
	this.moveUpButton.toggle( visible );
};

ext.bookshelf.ui.widget.ChapterInsertionWidget.prototype.toggleMoveDownButton = function ( visible ) {
	if ( !this.isEditAllowed ) {
		return;
	}
	this.moveDownButton.toggle( visible );
};
