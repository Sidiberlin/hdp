<template>
	<div v-bind:class="cardClass">
		<a  class="bs-card-anchor" v-bind:href="href" v-bind:aria-label="cardAnchorAriaLabel" v-bind:title="cardAnchorTitle" v-bind:aria-disabled="ariaDisabled" rel="nofollow noindex">
			<div class="bs-card-image" v-bind:style="{ backgroundImage: 'url(' + image_url + ')' }"></div>
			<div class="bs-card-body" >
				<div class="bs-card-title">{{title}}</div>
				<div class="bs-card-subtitle">{{subtitle}}</div>
			</div>
		</a>
		<div class="bs-card-footer">
			<ul class="bs-card-actions">
				<action v-for="primaryAction in primaryActions"
					v-bind:text="primaryAction.text"
					v-bind:title="primaryAction.title"
					v-bind:href="primaryAction.href"
					v-bind:actionclass="primaryAction.class"
					v-bind:iconclass="primaryAction.iconClass"
					v-bind:book="primaryAction.book"
				></action>
				<actionsmenu v-for="menuAction in actionsMenu"
					v-bind:title="menuAction.title"
					v-bind:label="menuAction.label"
					v-bind:actions="menuAction.actions"
				></actionsmenu>
			</div>
		</div>
	</div>
</template>

<script>
var Action = require( './Action.vue' );
var ActionsMenu = require( './ActionsMenu.vue' );

const  {toRaw} = Vue;

module.exports = {
	name: 'Card',
	props:  {
		title: String,
		subtitle: String,
		bookshelf: String,
		first_chapter_url: String,
		image_url: String,
		actions: Array,
		book_edit_url: String
	},
	components: {
		'action': Action,
		'actionsmenu': ActionsMenu
	},
	data: function () {
		var cardClass = "bs-card";
		var ariaDisabled = false;
		var href = this.first_chapter_url;
		if ( this.first_chapter_url === '' && this.book_edit_url === '' ) {
			cardClass = "bs-card new disabled";
			ariaDisabled = true;
		}
		var cardAnchorTitle = mw.message( 'bs-books-overview-page-book-anchor-title', this.title ).text();
		var cardAnchorAriaLabel = mw.message( 'bs-books-overview-page-book-anchor-aria-label', this.title ).text();

		if ( this.book_edit_url !== '' ) {
			cardAnchorTitle = mw.message( 'bs-books-overview-page-edit-book-anchor-title', this.title ).text();
			cardAnchorAriaLabel = mw.message( 'bs-books-overview-page-edit-book-anchor-aria-label', this.title ).text();
			href = this.book_edit_url;
		}

		// Get the object from proxy
		var actions = toRaw( this.actions );
		var actionKeys = Object.keys( actions );

		var primaryActions = [];
		if ( actionKeys.length >= 1 ) {
			var key = actionKeys[0];
			primaryActions.push( actions[key] );
		}
		if ( actionKeys.length >= 2 ) {
			var key = actionKeys[1];
			primaryActions.push( actions[key] );
		}

		var actionsMenu = [];
		var menuActions = [];
		if ( actionKeys.length > 2 ) {
			var menuActionKeys = actionKeys;
			menuActionKeys.shift( ...menuActionKeys.splice( 0, 1 ) );

			menuActionKeys.forEach( function( key ) {
				menuActions.push( actions[key] );
			} );

			// Nesting the menu in a array. If no menu is available the array is empty
			// and the component won't be rendered. This will prevent us from a empty dropdown menu.
			var menu = {};
			menu.label = mw.message( 'bs-books-overview-page-book-actions-dropdown-menu-aria-label' ).text();
			menu.title = mw.message( 'bs-books-overview-page-book-actions-dropdown-menu-title' ).text();
			menu.actions = menuActions;

			actionsMenu.push( menu );
		}

		return {
			cardClass: cardClass,
			ariaDisabled: ariaDisabled,
			cardAnchorTitle: cardAnchorTitle,
			cardAnchorAriaLabel: cardAnchorAriaLabel,
			primaryActions: primaryActions,
			actionsMenu: actionsMenu,
			href: href
		};
	},
}
</script>

<style lang="css">
.bs-card {
	position: relative;
	width: 325px;
	height: 450px;
	border: 1px solid #d7d7d7;
	margin: 20px 20px;
}

.bs-card.new {
	outline: var( --bs-books-overview-page-book-new ) solid 3px;
}

.bs-card-anchor {
	display: block;
	width: 100%;
	height: calc( 100% - 47px );
	text-decoration: none !important;
}

.bs-card.new .bs-card-anchor {
	pointer-events: none;
	cursor: default;
}

.bs-card:focus-within {
	outline: var( --bs-books-overview-page-focus-visible-color ) solid 3px;
}

.bs-card-image {
	width: 100%;
	height: 220px;
	background-size: cover;
	background-repeat: no-repeat;
}

.bs-card-body {
	height: 163px;
	text-align: center;
	padding: 40px 10px;
	overflow: hidden;
	color: black !important;
}

.bs-card-title {
	width: 100%;
	font-weight: bold;
	font-size: 1.4em;
	margin-bottom: 5px;
}

.bs-card-subtitle {
	width: 100%;
	font-size: 1.1em;
}

.bs-card-footer {
	position: absolute;
	bottom: 0;
	left: 0;
	width: 100%;
	padding: 0;
}

.bs-card-actions {
	display: flex;
	justify-content: space-between;
	list-style: none;
	margin: 0;
	flex-wrap: wrap;
	padding: 15px 0;
}

.bs-card-actions > li {
	margin: 0;
}
</style>