<template>
	<li>
		<cdx-button
			:class="spec.class"
			:action="spec.action"
			:weight="spec.weight"
			size="large"
			@click="buttonClick"
		>
			{{ spec.label }}
		</cdx-button>
	</li>
</template>

<script>

const { CdxButton } = require( '@wikimedia/codex' );

// @vue/component
module.exports = exports = {
	name: 'Item',
	components: {
		CdxButton
	},
	props: {
		spec: {
			type: Array,
			default: []
		}
	},
	emits: [
		'itemclick'
	],
	data: function () {
		return {
			spec: this.spec
		};
	},
	methods: {
		buttonClick: function () {
			this.$emit( 'itemclick', this.spec );
		}
	}
};

</script>

<style lang="css">
/* Fix for Codex progressive primary currently not set in REL1_39 */
.vuejsplus-toolbar-items .cdx-button--action-progressive[weight="primary"] {
	background-color: #36c;
	border-color: #36c;
	color: #fff;
}
.vuejsplus-toolbar-items .cdx-button--action-progressive[weight="primary"]:hover {
	background-color: #447ff5;
	border-color: #447ff5;
	color: #fff;
}

.vuejsplus-toolbar-items .cdx-button[size="large"] {
	min-width: 44px;
	min-height: 44px;
	padding-left: 15px;
	padding-right: 15px;
}
</style>
