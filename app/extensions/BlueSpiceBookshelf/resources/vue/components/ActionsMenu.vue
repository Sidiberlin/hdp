<template>
	<li class="dropdown">
		<a href="" class="dropdown-toggle bi bi-three-dots" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false" v-bind:title="title" v-bind:aria-label="label" rel="nofollow noindex"></a>
		<ul class="dropdown-menu">
			<action v-for="secondaryAction in secondaryActions"
					v-bind:text="secondaryAction.text"
					v-bind:title="secondaryAction.title"
					v-bind:href="secondaryAction.href"
					v-bind:actionclass="secondaryAction.class"
					v-bind:iconclass="secondaryAction.iconClass"
					v-bind:book="secondaryAction.book"
				>
			</action>
		</ul>
	</li>
</template>

<script>
var Action = require( './Action.vue' );

const {toRaw} = Vue;

module.exports = {
	name: 'ActionsMenu',
	props:  {
		title: String,
		label: String,
		actions: Array
	},
	components: {
		'action': Action,
	},
	data: function() {
		var actions = toRaw( this.actions );

		return {
			secondaryActions: actions
		}
	}
}
</script>

<style lang="css">
.bs-card-actions .dropdown {
	display: inline-block;
	text-align: right;
}

.bs-card-actions .dropdown .dropdown-toggle {
	display: inline-block;
	color: #666 !important;
}

.bs-card-actions .dropdown .dropdown-toggle::before {
	vertical-align: bottom !important;
}

.bs-card-actions .dropdown .dropdown-toggle::after {
	display: none;
}

.bs-card-actions .dropdown-toggle:focus-visible {
	outline: var( --bs-books-overview-page-focus-visible-color ) solid 1px;
}

.bs-card-actions .dropdown .dropdown-menu {
	list-style: none;
	padding: 10px 5px;
}

.bs-card-actions .dropdown .dropdown-menu a {
	display: block;
	width: 100%;
	color: #666 !important;
	padding: 0 5px;
}

.bs-card-actions .dropdown .dropdown-menu a:focus-visible {
	outline: var( --bs-books-overview-page-focus-visible-color ) solid 1px;
}

.bs-card-actions .dropdown .dropdown-menu a i {
	display: inline-block;
	width: 32px;
}
</style>