<template>
	<button v-tooltip="tooltip" class="tooltipButton" @click.prevent>
		<icon icon="question-circle" />
		<span class="-screenReader">{{ label }}</span>
	</button>
</template>

<script>
export default {
	name: 'Tooltip',
	props: {
		tooltip: {
			type: String,
			required: true,
		},
		label: {
			type: String,
			required: true,
		},
	},
};
</script>

<style lang="less">
@import '../../styles/_import';

.tooltip {
	z-index: 9999; // Raise above modals
}

.tooltipButton {
	display: inline-block;
	border: none;
	padding: 0 0.25em;
	background: transparent;
	cursor: pointer;
}

.tooltip-inner a {
	color: #fff;
}
</style>
