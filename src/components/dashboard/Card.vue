<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div :key="card.id" class="card-wrapper">
		<a 	@click="redirect"
			target="_blank"	
			class="card">
			<div class="card--header">
				<DueDate class="right" :card="card" />
				<span class="title" dir="auto">{{ card.title }}</span>
			</div>
			<ul v-if="card.labels && card.labels.length" class="labels">
				<li v-for="label in card.labels" :key="label.id" :style="labelStyle(label)">
					<span dir="auto">{{ label.title }}</span>
				</li>
			</ul>
			<div class="card-actions">
				<NcButton 
					class="comment-section" 
					@click.stop="openSidebar"
					variant="tertiary-no-background">
					<template #icon>
						<CommentOutline :size="20" />
					</template>
				</NcButton>
			</div>
		</a>
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
			console.log("[Card] Open sidebar");
			this.$emit('open:sidebar', this.card);
		}
	}
}
</script>

<style  lang="scss" scoped>
	@import '../../css/labels';

	.card {
		display: block;
		border-radius: var(--border-radius-large);
		padding: 8px;
		height: 60px;

		&:hover {
			background-color: var(--color-background-hover);
		}
	}

	.card--header {
		overflow: hidden;
		.title {
			overflow: hidden;
			text-overflow: ellipsis;
			white-space: nowrap;
			display: block;
		}
	}

	.labels {
		margin-left: 0;
	}

	.duedate:deep(.due) {
		margin: 0 0 0 10px;
		padding: 2px 4px;
		font-size: 90%;
	}

	.right {
		float: right;
	}

	.comment-section {
		margin-left: auto;
	}

	.comments-feed {
		list-style: none;
	}
</style>