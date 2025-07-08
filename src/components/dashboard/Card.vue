<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div :key="card.id"
		class="card"
		@click="redirect"
		target="_blank">

		<div class="card--header">
			<span class="title" dir="auto">{{ card.title }}</span>
			<DueDate :card="card" />
		</div>

		<div class="card--footer">
			<ul v-if="card.labels && card.labels.length" class="labels">
				<li v-for="label in card.labels" :key="label.id" :style="labelStyle(label)">
					<span dir="auto">{{ label.title }}</span>
				</li>
			</ul>
			<div v-else />
			<NcButton
				class="comment-section"
				@click.stop="openSidebar"
				variant="tertiary-no-background">
				<template #icon>
					<CommentOutline :size="20" />
				</template>
			</NcButton>
		</div>
	</div>
</template>

<script>
import DueDate from '../cards/badges/DueDate.vue';
import labelStyle from '../../mixins/labelStyle.js';
import CommentOutline from 'vue-material-design-icons/CommentOutline.vue';
import { NcButton } from "@nextcloud/vue";
import { generateUrl } from '@nextcloud/router';

export default {
	name: 'Card',
	components: { 
		DueDate, 
		CommentOutline,
		NcButton
	},
	mixins: [labelStyle],
	props: {
		card: {
		  type: Object,
			required: true,
		},
		redirectToProject: {
			type: Boolean,
			default: false
		}
	},
	computed: {
		cardLink() {
			return generateUrl('/apps/deck') + `/board/${this.card.boardId}/card/${this.card.id}`
		},
	},
	methods: {
		redirect() {
			const url = this.cardLink;
			window.open(url);
		},
		openSidebar() {
			this.$emit('open:sidebar', this.card);
		}
	}
}
</script>

<style  lang="scss" scoped>
	@import '../../css/labels';

	.card {
		display: flex;
		flex-direction: column;
		justify-content: space-between;

		border-radius: var(--border-radius-large);
		padding: 8px;
		height: 60px;
		cursor: pointer;

		&:hover {
			background-color: var(--color-background-hover);
		}
	}

	.card--header {
		display: flex;
		align-items: center;
		justify-content: space-between; 

		.title {
			overflow: hidden;
			text-overflow: ellipsis;
			white-space: nowrap;
		}
	}

	.card--footer {
		display: flex;
		align-items: center;
		justify-content: space-between;
	}

	.labels {
		margin-left: 0;
		flex-shrink: 1;
		overflow: hidden;
		white-space: nowrap;
	}

	.duedate:deep(.due) {
		margin: 0; 
		padding: 2px 4px;
		font-size: 90%;
		flex-shrink: 0;
	}
</style>