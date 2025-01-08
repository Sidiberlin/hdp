window.ext = window.ext || {};
ext.TableTools = ext.TableTools || {};
ext.TableTools.ve = ext.TableTools.ve || {};

// extend document model
ve.dm.MWTableNode.static.classAttributes[ 'mw-sticky-header' ] = { sticky: true };

ext.TableTools.ve.TableOptions = function ( config ) {
	ext.TableTools.ve.TableOptions.super.call( this, config );
};

OO.inheritClass( ext.TableTools.ve.TableOptions, bs.vec.ui.plugin.MWTableDialog );

ext.TableTools.ve.TableOptions.prototype.initialize = function () {
	this.component.stickyToggle = new OO.ui.ToggleSwitchWidget();
	this.component.stickyToggle.connect( this.component, { change: 'updateActions' } );
	this.component.panel.$element.append( new OO.ui.FieldLayout( this.component.stickyToggle, {
		align: 'left',
		label: ve.msg( 'tabletools-ve-plugin-table-style-sticky' )
	} ).$element );
};

ext.TableTools.ve.TableOptions.prototype.getValues = function ( values ) {
	return ve.extendObject( values, {
		sticky: this.component.stickyToggle.getValue()
	} );
};
// eslint-disable-next-line no-unused-vars
ext.TableTools.ve.TableOptions.prototype.getSetupProcess = function ( parentProcess, data ) {
	parentProcess.next( function () {
		const tableNode =
			this.component
				.getFragment()
				.getSelection()
				.getTableNode( this.component.getFragment().document ),
			isSticky = !!tableNode.getAttribute( 'sticky' );

		this.component.stickyToggle.setValue( isSticky );

		ve.extendObject( this.component.initialValues, {
			sticky: isSticky
		} );
	}, this );
	return parentProcess;
};

ext.TableTools.ve.TableOptions.prototype.getActionProcess = function ( parentProcess, action ) {
	parentProcess.next( function () {
		let surfaceModel, fragment;
		if ( action === 'done' ) {
			surfaceModel = this.component.getFragment().getSurface();
			fragment = surfaceModel.getLinearFragment(
				this.component.getFragment().getSelection().tableRange, true
			);
			fragment.changeAttributes( {
				sticky: this.component.stickyToggle.getValue()
			} );
		}
	}, this );
	return parentProcess;
};

bs.vec.registerComponentPlugin(
	bs.vec.components.TABLE_DIALOG,
	function ( component ) {
		return new ext.TableTools.ve.TableOptions( component );
	}
);
