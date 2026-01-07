<template>
	<div class="bs-bookshelf">
		<h2 tabindex="0">{{bookshelf}}</h2>
		<div class="bs-cards-container">
			<card  v-for="book in books"
				v-show="book.isVisible"
				v-bind:title="book.displaytitle"
				v-bind:subtitle="book.subtitle"
				v-bind:bookshelf="book.bookshelf"
				v-bind:first_chapter_url="book.first_chapter_url"
				v-bind:book_edit_url="book.book_edit_url"
				v-bind:image_url=book.image_url
				v-bind:actions=book.actions
			></card>
    	</div>
	</div>
</template>

<script>
var Card = require( './Card.vue' );

// @vue/component
module.exports = exports = {
	name: 'Bookshelf',
	props: {
		bookshelf: String,
		books: Array
	},
	components: {
		'card': Card
	},
	data: function () {
		return {
			bookshelf: this.bookshelf,
			books: this.books
		};
	},
};
</script>

<style lang="css">
.bs-bookshelf > h2 {
	padding: 0 26px;
}

.bs-bookshelf > h2:focus-visible {
	outline: 1px solid var( --bs-books-overview-page-focus-visible-color );
}

.bs-cards-container {
	display: flex;
	justify-content: start;
	flex-wrap: wrap;
}

@media ( max-width: 768px ) {
	.bs-bookshelf > h2 {
		padding: 0;
	}

	.bs-cards-container {
		justify-content: center;
	}
}
</style>