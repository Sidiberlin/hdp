<template>
	<div
		:class="toolbarClass"
		:aria-label="ariaLabel"
		role="toolbar"
	>
		<ul v-show="hasItemsLeft" class="vuejsplus-toolbar-items left">
			<item
				v-for="item in itemsLeft"
				:spec="item"
				@itemclick="handleItemClick"
			></item>
		</ul>
		<ul v-show="hasItemsRight" class="vuejsplus-toolbar-items right">
			<item
				v-for="item in itemsRight"
				:spec="item"
				@itemclick="handleItemClick"
			></item>
		</ul>
	</div>
</template>

<script>
const Item = require( './Item.vue' );

// @vue/component
module.exports = exports = {
	name: 'App',
	components: {
		item: Item
	},
	props: {
		class: {
			type: String,
			default: ''
		},
		items: {
			type: Array,
			default: []
		},
		ariaLabel: {
			type: String,
			default: ''
		}
	},
	emits: [
		'toolclick'
	],
	data: function () {
		const itemsLeft = [];
		const itemsRight = [];

		if ( this.items.length === 1 ) {
			this.items[ 0 ].action = 'progressive';
			this.items[ 0 ].weight = 'primary';
		}

		for ( let index = 0; index < this.items.length; index++ ) {
			const item = this.items[ index ];
			if ( item.hasOwnProperty( 'slot' ) && item.slot === 'right' ) {
				itemsRight.push( item );
			} else {
				itemsLeft.push( item );
			}
		}

		sortTools( itemsLeft );
		sortTools( itemsRight );

		let hasItemsLeft = false;
		if ( itemsLeft.length > 0 ) {
			hasItemsLeft = true;
		}

		let hasItemsRight = false;
		if ( itemsRight.length > 0 ) {
			hasItemsRight = true;
		}

		const toolbarClasses = [ this.class ];
		toolbarClasses.push( 'vuejsplus-toolbar' );
		if ( hasItemsLeft === false || hasItemsRight === false ) {
			toolbarClasses.push( 'one-slot-only' );
		}

		return {
			toolbarClass: toolbarClasses.join( ' ' ),
			ariaLabel: this.ariaLabel,
			itemsLeft: itemsLeft,
			itemsRight: itemsRight,
			hasItemsLeft: hasItemsLeft,
			hasItemsRight: hasItemsRight
		};
	},
	methods: {
		handleItemClick: function ( data ) {
			if ( data.hasOwnProperty( 'callback' ) ) {
				this.$emit( 'toolclick', data );
			}
		}
	}
};

function sortTools( tools ) {
	tools.sort( sortFunction );
	function sortFunction( a, b ) {
		a = a.position;
		b = b.position;
		return ( a - b );
	}
}

</script>

<style lang="css">
.vuejsplus-toolbar {
	display: flex;
	justify-content: space-between;
	border-bottom: 1px solid #c8ccd1;
	box-shadow: 0 1px 1px 0 rgba(0,0,0,0.1);
	background-color: #fff;
}
.vuejsplus-toolbar.vuejsplus-toolbar-floating {
	position: fixed;
	z-index: 5;
}
.vuejsplus-toolbar-items {
	display: inline-flex;
	list-style: none;
	margin: 0 !important;
}
.vuejsplus-toolbar-items.left {
	justify-content: flex-start;
}
.vuejsplus-toolbar-items.right {
	justify-content: flex-end;
}
.one-slot-only .vuejsplus-toolbar-items {
	width: 100%;
}
</style>
