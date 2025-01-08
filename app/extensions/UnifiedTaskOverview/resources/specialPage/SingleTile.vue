<template>
	<div class="uto-tile"><a v-bind:href="url">
		<div class="tile-content">
			<div class="tile-header">
				<div class="icn-cnt" v-bind:class="'task-' + type" ></div>
				<div class="tile-header-content">
					<div class="task-header" v-html="header"></div>
					<div class="task-subheader" v-html="subheader"></div>
				</div>
			</div>
			<div class="act-container">
				<div class="task-body" v-html="body"></div>
			</div>
		</div>
	</a></div>
</template>

<script>

	module.exports = {
		name: 'SingleTile',
		props:  {
			type: String,
			header: String,
			subheader: String,
			body: String,
			url: String,
		}
	}

</script>

<style lang="less">
.uto-tile {
	position: relative;
	word-wrap: break-word;
	background-color: #e9e9ee;
	margin: 1.16%;
	width: 31%;
	border-radius: 5px;
	a {
		text-decoration: none !important;
	}
	.tile-content {
		padding: 1rem;
		word-wrap: break-word;
		display: flex;
		flex-flow: row wrap;
		.tile-header {
			display: table;
			.icn-cnt {
				display: table-cell;
				margin: 0 0;
				padding: 0 2rem;
				background-position: center;
				background-repeat: no-repeat;
				background-size: 40px;
				vertical-align: top;
				width: 40px;
				height: 40px;
				background-image: url('../images/task.svg');
			}
			.tile-header-content {
				display: table-cell;
				padding-left: 5px;
			}
		}

		.act-container {
			display: block;
			padding: 0.5rem;
			width: 100%;
		}
	}
}
.uto-tile:hover {
	background-color: darken( #e9e9ee, 5% );
}
.uto-tile:hover a {
	text-decoration: none;
	cursor: pointer;
}

.uto-tile:hover #task-header{
	text-decoration: underline;
}

.task-body {
	line-height: 1.2;
	color: #54595d;
	font-size: 0.9em;
	padding-top: 0.5rem;
	ul li {
		list-style-type: circle;
	}
}

.task-header {
	width: 100%;
	font-weight: bold;
	line-height: 1.5;
	padding: 5px 5px 0 5px;
	color: black;
}
.task-subheader {
	width: 100%;
	line-height: 1.2;
	padding: 0 5px 5px 5px;
	color: black;
	font-size: 0.9em;
}
@media ( max-width: 768px ) {
	.uto-tile{
		width: 100%;
	}
}
</style>
