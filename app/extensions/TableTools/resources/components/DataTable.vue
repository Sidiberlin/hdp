<template>
	<grid
		v-bind:class="className"
		v-bind:cols="cols"
		v-bind:rows="rows"

	></grid>
</template>
<script>
	const { defineComponent } = require( 'vue' );
	const { Grid } = require( './../vuejsplus.js' );

	module.exports = defineComponent( {
		name: 'DataTable',
		components: {
			Grid
		},
		props: {
			className: {
				type: String,
				default: ''
			},
			cols: {
				type: Array
			},
			rows: {
				type: Array
			},
		}
	} );

</script>