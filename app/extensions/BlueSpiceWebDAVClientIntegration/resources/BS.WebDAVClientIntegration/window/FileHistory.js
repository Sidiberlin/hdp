Ext.define( 'BS.WebDAVClientIntegration.window.FileHistory', {
	extend: 'Ext.window.Window',
	width: 600,
	height: 360,

	bsFileName: '',

	initComponent: function() {
		this.setTitle( mw.message('bs-webdav-ci-filehistory', this.bsFileName ).plain() );
		this.gdVersions = new Ext.grid.Panel({
			store: new Ext.data.JsonStore({
				autoLoad: true,
				proxy: {
					type: 'ajax',
					url: mw.util.wikiScript('api'),
					extraParams: {
						action: 'bs-filehistory-store',
						format: 'json',
						query: this.bsFileName
					},
					reader: {
						type: 'json',
						rootProperty: 'results',
						idProperty: 'file_timestamp'
					}
				},
				fields: [
					'file_user_link', 'file_size', 'file_url',
					{ name: 'file_timestamp', type: 'date', defaultValue: '19700101000000', dateFormat: 'YmdHis' }
				],
				sorters: [{
						property:  'file_timestamp',
						direction: 'DESC'
				}]
			}),
			columns: [
				{
					text: mw.message( 'bs-webdav-ci-filehistory-timestamp' ).plain(),
					dataIndex: 'file_timestamp',
					flex: 1,
					renderer: this.renderTimestamp
				},
				{
					text: mw.message( 'bs-webdav-ci-filehistory-user' ).plain(),
					dataIndex: 'file_user_link',
					flex: 1
				},
				{
					text: mw.message( 'bs-webdav-ci-filehistory-size' ).plain(),
					dataIndex: 'file_size',
					flex: 1,
					renderer: this.renderSize
				}
			]
		});
		this.items = [
			this.gdVersions
		];

		this.callParent(arguments);
	},

	renderTimestamp: function( ts, meta, record ) {
		var link = mw.html.element(
			'a',
			{
				href: record.get('file_url')
			},
			Ext.Date.format( ts, 'd.m.Y G:i' )
		);
		return link;
	},

	renderSize: function( size ) {
		return Ext.util.Format.fileSize( size );
	}
});