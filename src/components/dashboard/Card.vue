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
						<Comment :size="20" />
					</template>
				</NcButton>
			</div>
		</a>

		<NcAppSidebar
			id="card-notes"
			ref="sidebar" 
			name="Card details"
			:open="showSidebar"
			v-click-outside="closeSidebar"
			@close="closeSidebar">
			
			<NcAppSidebarTab name="Comments" id="comments-tab">
				<template #icon><Comment :size="20" /></template>
				Single tab content
			</NcAppSidebarTab>

			<NcAppSidebarTab name="Notes" id="notes-tab">
				<template #icon><NoteTextOutline :size="20" /></template>
				Second tab content
			</NcAppSidebarTab>
		</NcAppSidebar>
	</div>
</template>

<script>
import DueDate from '../cards/badges/DueDate.vue'
import { generateUrl } from '@nextcloud/router'
import labelStyle from '../../mixins/labelStyle.js'
import { NcButton } from "@nextcloud/vue";
import Comment from 'vue-material-design-icons/Comment.vue';
import NoteTextOutline from 'vue-material-design-icons/NoteTextOutline.vue';
import NcAppSidebarTab from '@nextcloud/vue/components/NcAppSidebarTab';
import NcAppSidebar from '@nextcloud/vue/components/NcAppSidebar';
import ClickOutside from 'vue-click-outside';

export default {
	name: 'Card',
	components: { 
		DueDate, 
		Comment,
		NoteTextOutline,
		NcButton,
		NcAppSidebarTab,
		NcAppSidebar,
	},
	directives: {
		ClickOutside
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
	data() {
		return {
			showSidebar: false
		}
	},
	watch: {
        showSidebar(newValue) {
            if (newValue) {
                this.$nextTick(() => {
                    const sidebarEl = this.$refs.sidebar.$el;
                    document.body.appendChild(sidebarEl);
                });
            }
        }
    },
	computed: {
		cardLink() {
			return generateUrl('/apps/deck') + `/board/${this.card.boardId}/card/${this.card.id}`
		}
	},
	methods: {
		redirect() {
			const url = this.cardLink;
			window.open(url);
		},
		openSidebar(event) {
            this.showSidebar = true;
        },
        closeSidebar() {
            this.showSidebar = false;
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
</style>