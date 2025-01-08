
<template>
	<tr v-bind:class="rowClass" v-show="isVisible" v-bind:data-rowindex="rowIndex">
		<td is="datacell" v-for="cell in cells"
			v-bind:cell="cell"
			v-bind:row-index="rowIndex"
			@rowselected="handleSelect"
		></td>
	</tr>
</template>

<script>
var DataCell = require( './DataCell.vue' );

// @vue/component
module.exports = exports = {
	name: 'DataRow',
	props: {
		row: {
			type: Array,
			default: []
		}
	},
	components: {
		'datacell': DataCell
	},
	emits: [
	],
	data: function () {
		// TODO: Ensure object properties
		return {
			'rowClass': this.row.class,
			'isVisible': this.row.isVisible,
			'cells': this.row.data,
			'rowIndex': this.row.rowIndex
		};
	},
	methods: {
		handleSelect: function( data ) {
			if ( this.rowIndex === data.rowIndex ) {
				this.row.selected = data.selected;

				if ( data.selected === true ) {
					this.rowClass += ' selected'
				} else {
					this.rowClass = this.row.class;
				}
			}
		}
	}
};

</script>

<style lang="css">
</style>