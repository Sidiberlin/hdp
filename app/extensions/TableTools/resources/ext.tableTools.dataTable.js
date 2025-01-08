/* eslint-disable */

( function ( mw, $, d, undefined ) {
    const Vue = require( 'vue' );
    const GridApp = require( 'ext.vuejsplus-data-grid.vue' );

    var tables = $( '.datagrid, .data-grid' );
    for ( tableIndex = 0; tableIndex < tables.length; tableIndex++ ) {
        var invalidFormat = false;
        const fragment = document.createDocumentFragment();

        var table = tables[tableIndex];
        var tableClassName = $( table ).attr( 'class' );

        if ( tableClassName === undefined ) {
            tableClassName = '';
        }

        var spec = {
            class: tableClassName,
            cols: [],
            rows: []
        };

        var colTypes = [];
        var rows = $( table ).find( 'tr' );
        for ( rowIndex = 0; rowIndex < rows.length; rowIndex++ ) {
            var row = rows[rowIndex];
            var cells = $( row ).find( 'th' );
            if ( cells.length === 0 ) {
                cells = $( row ).find( 'td' );
            }
            var rowCells = [];
            for ( cellIndex = 0; cellIndex < cells.length; cellIndex++ ) {
                var cell = cells[cellIndex];
                if ( $( cell ).attr( 'rowspan' ) || $( cell ).attr( 'colspan' ) ) {
                    invalidFormat = true;
                }
                let cellClass = $( cell ).attr( 'class' );
                if ( !cellClass ) {
                    cellClass = '';
                }

                if ( rowIndex === 0 ) {
                    let colType = 'html';
                    if ( $( cell ).hasClass( 'int' ) || $( cell ).hasClass( 'numeric' ) ) {
                        colType = 'numeric'
                    } else if ( $( cell ).hasClass( 'string' ) ) {
                        colType = 'string'
                    } else if ( $( cell ).hasClass( 'boolean' ) ) {
                        colType = 'boolean'
                    }
                    colTypes.push( colType );

                    var col = {
                        label: $( cell ).html(),
                        dataIndex: cellIndex+1,
                        type: colType,
                        sortable: true,
                        filterable: true,
                        class: cellClass
                    }
                    spec.cols.push( col );
                } else {
                    let cellContent = $( cell ).html();
                    if ( colTypes[cellIndex] === 'boolean' ) {
                        if ( cellContent.trim() === 'true' || cellContent.trim() === '1' ) {
                            cellContent = true;
                        } else if ( cellContent.trim() === 'false' || cellContent.trim() === '0' ) {
                            cellContent = false;
                        } else {
                            cellContent = undefined;
                        }
                    }
                    var cell = {
                        dataIndex: cellIndex+1,
                        content: cellContent,
                        class: cellClass
                    }
                    rowCells.push( cell );
                }
            }
            if ( rowCells.length > 0 ) {
                spec.rows.push( {
                    class: '',
                    data: rowCells
                } );
            }
        }

        function render() {
            var h = Vue.h;

            var vm = Vue.createMwApp( {
                render: function() {
                    return h( GridApp, spec );
                }
            } );

            vm.mount( fragment );

            // https://www.reddit.com/r/vuejs/comments/i1vwyw/vue_3_createapp_does_not_replace_mount_element/?rdt=38765
            table.parentNode.replaceChild( fragment, table );
        };

        // rowspan and colspan is not supported
        if ( invalidFormat === false ) {
            render();
        }
    }
} )( mediaWiki, jQuery, document );