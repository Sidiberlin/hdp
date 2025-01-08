window.bs = window.bs || {};
window.bs.skin = bs.skin || {};
bs.skin.dialog = bs.skin.dialog || {};
bs.skin.dialog.DetailsDialog = function( cfg ) {
	cfg = cfg || {};
	this.links = cfg.links || [];
	bs.skin.dialog.DetailsDialog.super.call( this, cfg );
};
OO.inheritClass( bs.skin.dialog.DetailsDialog, OO.ui.ProcessDialog );
bs.skin.dialog.DetailsDialog.static.name = 'Skin.dialog.DetailsDialog';
bs.skin.dialog.DetailsDialog.static.title = mw.message(
	'bs-discovery-dialog-details-heading'
).text();
bs.skin.dialog.DetailsDialog.static.size = 'large';
bs.skin.dialog.DetailsDialog.static.actions = [
	{ action: 'cancel', label: mw.message( 'bs-discovery-dialog-details-action-cancel' ).text(), flags: [ 'safe', 'close' ] }
];
bs.skin.dialog.DetailsDialog.prototype.initialize = function() {
	bs.skin.dialog.DetailsDialog.super.prototype.initialize.call( this );
	var $group = $( '<div class="row row-cols-1 row-cols-md-2 g-4"></div>' );
	var headerIdCount = 1;
	for( var type in this.links ) {
		var headerId = 'bs-details-dlg-itm-' + headerIdCount;
		// bs-discovery-dialog-detailssectionheading-namespaces
		// bs-discovery-dialog-detailssectionheading-views
		// bs-discovery-dialog-detailssectionheading-actions
		// bs-discovery-dialog-detailssectionheading-toolbox
		var header = mw.message( 'bs-discovery-dialog-detailssectionheading-' + type ).exists()
			? mw.message( 'bs-discovery-dialog-detailssectionheading-' + type ).text() : type;
		var myskip = false;
		var $pnl = $( '<div class="card-wrapper"></div>' );
		var $card = $( '<div class="card h-100"></div>' );
		var $head = $( '<div id="' + headerId + '" class="card-header">' + header + '</div>' );
		var $ul = $( '<ul class="list-group mx-0" aria-labelledby="' + headerId + '"></ul>' );
		for ( var i = 0; i < this.links[type].length; i++ ) {
			if ( !this.links[type][i].text || this.links[type][i].text === '' ) {
				continue;
			}
			var $a = $( '<a class="list-group-item list-group-item-action">' + this.links[type][i].text + '</a>' );
			if ( this.links[type][i].id && this.links[type][i].id !== '' ) {
				if ( $( this.links[type][i].id ).length < 1 ) {
					$a.attr( 'id', this.links[type][i].id );
				} else {
					myskip = true;
				}
			}
			if ( this.links[type][i].title && this.links[type][i].title !== '' ) {
				$a.attr( 'title', this.links[type][i].title );
			}
			if ( this.links[type][i].href && this.links[type][i].href !== '' ) {
				$a.attr( 'href', this.links[type][i].href );
			} else if ( myskip ) {
				// its a hrefless id bound link that is meant to open a dialog
				// id's can not be assinged double, so we just skip them
				continue;
			}
			var me = this;
			$a.on( 'click', function( e ) {
				me.close( { action: 'cancel' } );
			} );
			$ul.append( $a );
		}
		var $content = $( '<div class="card-body"></div>' );
		$content.append( $ul );
		$card.append( $head );
		$card.append( $content );
		$pnl.append( $card );
		$group.append( $pnl );
	}

	var panel = new OO.ui.PanelLayout( {
		expanded: false,
		padded: true,
		$content: $group
	} );

	this.$body.append( panel.$element );
};

bs.skin.dialog.DetailsDialog.prototype.getActionProcess = function ( action ) {
	var dialog = this;
	if ( action ) {
		return new OO.ui.Process( function () {
			dialog.close( { action: action } );
		} );
	}
	return bs.skin.dialog.DetailsDialog.super.prototype.getActionProcess.call( this, action );
};