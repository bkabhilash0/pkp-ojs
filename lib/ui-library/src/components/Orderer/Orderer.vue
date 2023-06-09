<template>
	<div class="orderer">
		<span v-if="isDraggable" class="orderer__dragDrop" aria-hidden="true">
			<icon icon="sort" />
		</span>
		<button class="orderer__up" @click.prevent="up">
			<icon icon="angle-up" />
			<span class="-screenReader">
				{{ __('common.orderUp', {itemTitle: itemTitle}) }}
			</span>
		</button>
		<button class="orderer__down" @click.prevent="down">
			<icon icon="angle-down" />
			<span class="-screenReader">
				{{ __('common.orderDown', {itemTitle: itemTitle}) }}
			</span>
		</button>
	</div>
</template>

<script>
export default {
	name: 'Orderer',
	props: {
		itemId: [String, Number],
		itemTitle: String,
		isDraggable: {
			type: Boolean,
			default: true,
		},
	},
	methods: {
		/**
		 * Emit an event to move this item up
		 */
		up() {
			this.$emit('up', this.itemId);
			this.$nextTick(() => {
				this.setFocus('up');
			});
		},

		/**
		 * Emit an event to move this item down
		 */
		down() {
			this.$emit('down', this.itemId);
			this.$nextTick(() => {
				this.setFocus('down');
			});
		},

		/**
		 * Set the focus on one of the up/down buttons
		 */
		setFocus: function (dir) {
			const selector = dir === 'up' ? '.orderer__up' : '.orderer__down';
			this.$el.querySelector(selector).focus();
		},
	},
};
</script>

<style lang="less">
@import '../../styles/_import';

.orderer__dragDrop,
.orderer__up,
.orderer__down {
	position: absolute;
	top: 0;
	left: 0;
	width: 3rem;
	height: 100%;
	background: transparent;
	border: none;

	&:focus {
		outline: 0;
	}

	.fa {
		position: absolute;
		top: 50%;
		left: 50%;
		transform: translate(-50%, -50%);
	}
}

.orderer__dragDrop {
	color: @primary;
	border-inline-end: @grid-border;
	cursor: move;
}

.orderer__up,
.orderer__down {
	left: auto;
	right: 0;
	color: @text-light;
	border-inline-start: @grid-border;
	cursor: pointer;

	&:hover {
		color: @primary;
		box-shadow: 0 2px 0 @primary;
	}

	&:focus {
		background: @bg-light;
	}

	.fa {
		font-size: 20px;
		font-weight: @bold;
	}
}

.orderer__up {
	right: 3rem;
}

[dir='rtl'] {
	.orderer__dragDrop {
		right: 0;
		left: auto;
	}

	.orderer__up,
	.orderer__down {
		right: auto;
		left: 0;
	}

	.orderer__down {
		left: 3rem;
	}
}
</style>
