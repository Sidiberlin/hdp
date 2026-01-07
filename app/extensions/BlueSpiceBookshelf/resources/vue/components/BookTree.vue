<template>
	<tree
		:class="classes"
		:nodes="nodes"
		:selectable="selectable"
		:selected="selected"
		:expandable="expandable"
		:expanded="expanded"
		:tools="tools"
		:toolbar="toolbar"
		:toolbarFloatingOffset="toolbarFloatingOffset"
		></tree>
</template>

<script>
	const { defineComponent } = require( 'vue' );
	const { Tree } = require( './../../vuejsplus.js' );

	module.exports = defineComponent( {
		name: 'BookTree',
		components: {
			Tree
		},
		props: {
			classes: {
				type: String,
				default: ''
			},
			nodes: {
				type: Array,
				default: []
			},
			selectable: {
				type: Boolean,
				default: false
			},
			selected: {
				type: Boolean,
				default: false
			},
			expandable: {
				type: Boolean,
				default: false
			},
			expanded: {
				type: Boolean,
				default: false
			},
			toolbar: {
				type: Boolean,
				default: false
			},
			tools: {
				type: Array,
				default: []
			},
			toolbarFloatingOffset: {
				type: Number,
				default: 0
			}
		},
		data: function () {
			return {
				classes: this.class,
				nodes: this.nodes,
				selectable:this.selectable,
				selected:this.selected,
				expandable: this.expandable,
				expanded: this.expanded,
				tools: this.tools,
				toolbar:this.toolbar,
				toolbarFloatingOffset: this.toolbarFloatingOffset
			};
		}
	} );
</script>