ext.notifyme.ui.widget.PaginationWidget = function ( cfg ) {
	cfg = cfg || {};

	// Parent constructor
	ext.notifyme.ui.widget.PaginationWidget.super.call( this, cfg );

	this.itemPerPage = cfg.itemPerPage || 50;
	this.page = cfg.page || 1;

	this.total = -1;

	// Pagination elements
	this.labelWidget = new OO.ui.LabelWidget( {
		classes: [ 'notifications-ui-widget-PaginationWidget-label' ]
	} );

	this.previousWidget = new OO.ui.ButtonWidget( {
		icon: 'previous',
		label: mw.msg( 'notifyme-notification-center-pagination-aria-previous' ),
		invisibleLabel: true
	} );

	this.nextWidget = new OO.ui.ButtonWidget( {
		icon: 'next',
		label: mw.msg( 'notifyme-notification-center-pagination-aria-next' ),
		invisibleLabel: true
	} );

	this.labelWidget.setLabel( '1 - ' + this.itemPerPage );
	this.$element
		.addClass( 'notifications-ui-widget-PaginationWidget' )
		.append(
			$( '<div>' )
				.addClass( 'notifications-ui-widget-PaginationWidget-row' )
				.append(
					this.previousWidget.$element,
					this.labelWidget.$element,
					this.nextWidget.$element
				)
		);

	this.previousWidget.connect( this, { click: 'onPreviousChoose' } );
	this.nextWidget.connect( this, { click: 'onNextChoose' } );
};

OO.inheritClass( ext.notifyme.ui.widget.PaginationWidget, OO.ui.Widget );

/**
 * Respond to "previous" button click event
 *
 * @fires change
 */
ext.notifyme.ui.widget.PaginationWidget.prototype.onPreviousChoose = function () {
	this.emit( 'change', 'prev' );
};

/**
 * Respond to "next" button click event
 *
 * @fires change
 */
ext.notifyme.ui.widget.PaginationWidget.prototype.onNextChoose = function () {
	this.emit( 'change', 'next' );
};

ext.notifyme.ui.widget.PaginationWidget.prototype.reset = function () {
	this.page = 1;

	this.updateLabel();
};

ext.notifyme.ui.widget.PaginationWidget.prototype.nextPage = function () {
	this.page++;

	this.updateLabel();
};

ext.notifyme.ui.widget.PaginationWidget.prototype.prevPage = function () {
	if ( this.page > 1 ) {
		this.page--;

		this.updateLabel();
	}
};

ext.notifyme.ui.widget.PaginationWidget.prototype.hasPrevPage = function () {
	return this.total > 0 && this.page > 1;
};

ext.notifyme.ui.widget.PaginationWidget.prototype.hasNextPage = function () {
	return this.total > ( this.page * this.itemPerPage );
};

ext.notifyme.ui.widget.PaginationWidget.prototype.updateWidget = function ( total ) {
	this.total = total;

	const hasPrevPage = this.hasPrevPage(),
	 hasNextPage = this.hasNextPage();

	this.previousWidget.setDisabled( this.isDisabled() || !hasPrevPage );
	this.nextWidget.setDisabled( this.isDisabled() || !hasNextPage );

	// Update label text and visibility
	this.updateLabel();
	this.labelWidget.toggle( !this.isDisabled() );
};

ext.notifyme.ui.widget.PaginationWidget.prototype.updateLabel = function () {
	let label,
		lastItem = this.page * this.itemPerPage,
		firstItem = ( ( this.page - 1 ) * this.itemPerPage ) + 1;

	if ( lastItem > this.total ) {
		lastItem = this.total;
	}

	label = firstItem + ' - ' + lastItem;

	this.labelWidget.setLabel( label );
};
