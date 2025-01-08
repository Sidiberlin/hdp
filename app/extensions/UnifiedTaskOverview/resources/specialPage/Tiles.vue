<template>
	<div class="task-overview">
		<cdx-search-input
			:clearable="true"
			:placeholder="searchPlaceholderLabel"
			:aria-label="searchPlaceholderLabel"
			@update:model-value="getSearchResults"
		></cdx-search-input>
		<div class="no-tasks" v-if="noTaskDesc">
			<p>{{ $i18n( 'unifiedtaskoverview-label-no-task' ) }}</p>
			<div class="notasks-icon"></div>
		</div>
		<ul class="ul-tiles">
			<transition appear name="list" v-for="task in tasks" v-bind:key="task.id">
				<single-tile
					v-show="task.isVisible"
					v-bind:type="task.type"
					v-bind:header="task.header"
					v-bind:subheader="task.subheader"
					v-bind:body="task.body"
					v-bind:url="task.url"
				>
				</single-tile>
			</transition>
		</ul>
	</div>
</template>

<script>
var Vue = require( 'vue' ),
	SingleTile = require( './SingleTile.vue' ),
	{ CdxSearchInput } = require( '@wikimedia/codex' );

	module.exports = exports = Vue.defineComponent( {
		name: 'Tiles',
		props: {
			items: {
				type: Array
			},
			noTaskDesc: {
				type: Boolean
			},
			searchElements: {
				type: Array
			},
			searchPlaceholderLabel: {
				type: String
			}
		},
		components: {
			'single-tile': SingleTile,
			CdxSearchInput: CdxSearchInput
		},
		data: function () {
			return {
				searchInput: [],
				tasks: this.items
			};
		},
		methods: {
			getSearchResults: function( search ) {
				search = search.toLowerCase();
				found = 1;
				if ( !this.tasks ) {
					return;
				}
				if ( search !== '' && search !== false ) {
					if ( search.search( ' ' ) !== -1 ) {
						this.searchInput = search.split( " " );
					} else  {
						this.searchInput[ 0 ] = search;
					}

					for ( let x = 0; x < this.searchElements.length; x++ ) {
						for (let y = 0; y < this.searchInput.length; y++ ) {
							if ( this.searchElements[x].search( this.searchInput[y] )  === -1 ) {
								found = 0;
							}
						}
						if ( found === 0 ) {
							this.tasks[x].isVisible = false;
						} else {
							this.tasks[x].isVisible = true;
						}
						found = 1;
					}
				} else {
					this.tasks.forEach( function ( item )  {
						item.isVisible = true;
						this.searchInput = [];
					});
				}
			}
		}
	} );
</script>

<style lang="less">

ul {
	list-style-type: none;
	padding: 0;
}
.ul-tiles {
	margin-top: 2rem;
	margin-left: 0;
	display: flex;
	flex-flow: row wrap;
	position: relative;
}

.list-enter,
.list-leave-to {
	visibility: hidden;
	height: 0;
	margin: 0;
	padding: 0;
	opacity: 0;
}

.list-enter-active,
.list-leave-active {
	transition: all ease-out 250ms;
}
div.no-tasks {
	width: 100%;
	text-align: center;
}
.notasks-icon {
	background-image: url('../images/Icon_notasks.svg');
	width: 80px;
	height: 80px;
	background-position: center;
	background-repeat: no-repeat;
	background-size: 80px;
	margin: 1rem auto 0 auto;
}
</style>