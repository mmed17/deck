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
			:name="card.title"
			:open="showSidebar"
			v-click-outside="closeSidebar"
			@close="closeSidebar">

			<NcAppSidebarTab name="Comments" id="comments-tab">
				<template #icon>
					<Comment :size="20" />
				</template>
				<div class="tab-content">
					<ul class="comments-feed">
						<CommentItem v-for="comment in comments"
							:key="comment.id"
							:comment="comment"
							:show-extra-options="false"/>

						<InfiniteLoading 
							:identifier="commentsLoaderIdentifier" 
							@infinite="commentsInfiniteHandler">
							<div slot="spinner">
								<NcLoadingIcon :size="20" />
							</div>
							<div slot="no-more" />
							<div slot="no-results" />
						</InfiniteLoading>
					</ul>

					<NcEmptyContent 
						v-if="!commentsLoading && comments.length === 0"
						:name="error ? error : t('deck', 'No comments yet.')">
						<template #icon>
							<Comment />
						</template>
					</NcEmptyContent>
                </div>
			</NcAppSidebarTab>

			<!-- show card notes using the API and add lazy loading option -->
			<!-- http://localhost:8080/ocs/v2.php/apps/deck/api/v1.0/cards/21/notes?limit=10&offset=0 -->
			<NcAppSidebarTab name="Notes" id="notes-tab">
				<template #icon><NoteTextOutline :size="20" /></template>

				<ul class="notes-feed">
					<!-- Note item -->

					<InfiniteLoading 
						:identifier="notesLoaderIdentifier" 
						@infinite="notesInfiniteHandler">
						<div slot="spinner">
							<NcLoadingIcon :size="20" />
						</div>
						<div slot="no-more" />
						<div slot="no-results" />
					</InfiniteLoading>
				</ul>

				<NcEmptyContent 
					v-if="!notesLoading && notes.length === 0"
					:name="error ? error : t('deck', 'No notes yet.')">
					<template #icon>
						<Comment />
					</template>
				</NcEmptyContent>
			</NcAppSidebarTab>
		</NcAppSidebar>
	</div>
</template>

<script>
import { generateOcsUrl, generateUrl } from '@nextcloud/router';
import { NcButton } from "@nextcloud/vue";
import DueDate from '../cards/badges/DueDate.vue';
import labelStyle from '../../mixins/labelStyle.js';
import Comment from 'vue-material-design-icons/CommentOutline.vue';
import NoteTextOutline from 'vue-material-design-icons/NoteTextOutline.vue';
import NcAppSidebarTab from '@nextcloud/vue/components/NcAppSidebarTab';
import NcAppSidebar from '@nextcloud/vue/components/NcAppSidebar';
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon';
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent';
import ClickOutside from 'vue-click-outside';
import CommentItem from '../card/CommentItem.vue';
import InfiniteLoading from 'vue-infinite-loading';
import ocs from '@nextcloud/axios';

const API_LIMIT = 10;

export default {
	name: 'Card',
	components: { 
		DueDate, 
		Comment,
		NoteTextOutline,
		NcButton,
		NcAppSidebarTab,
		NcAppSidebar,
		NcLoadingIcon,
		NcEmptyContent,
		CommentItem,
		InfiniteLoading
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
			showSidebar: false,
			comments: [],
            notes: [],
            commentsLoading: false,
            notesLoading: false,
            commentsOffset: 0,
            notesOffset: 0,
            canLoadMoreComments: true,
            canLoadMoreNotes: true,
			commentsLoaderIdentifier: 0,
			notesLoaderIdentifier: 0,
		}
	},
	computed: {
		cardLink() {
			return generateUrl('/apps/deck') + `/board/${this.card.boardId}/card/${this.card.id}`
		},
		commentsUrl() {
			return `/apps/deck/api/v1.0/cards/${this.card.id}/comments`;
		},
	},
	methods: {
		redirect() {
			const url = this.cardLink;
			window.open(url);
		},
		openSidebar() {
			this.commentsInfiniteHandler();
			this.showSidebar = true;
        },
        closeSidebar() {
            this.showSidebar = false;
			this.comments = [];
			this.commentsOffset = 0;
			this.canLoadMoreComments = true;
			this.error = null;
        },
		async commentsInfiniteHandler($state) {
            if (!this.canLoadMoreComments && $state) {
                $state.complete();
                return;
            }

            this.commentsLoading = true;
            try {
				const url = generateOcsUrl(this.commentsUrl);
				const response = await ocs.get(url, {
					params: {
						limit: API_LIMIT,
						offset: this.commentsOffset
					}
				});
				const comments = response.data.ocs.data;
				if (comments.length) {
                    this.comments.push(...comments);
                    this.commentsOffset += comments.length;
					if($state) {
						$state.loaded();
					}
                } else {
                    this.canLoadMoreComments = false;
					if($state) {
						$state.complete();
					}
                }
            } catch (e) {
                console.error('Failed to fetch comments', e);
                this.error = 'Failed to load comments';
				if($state) {
					$state.error();
				}
            } finally {
                this.commentsLoading = false;
				this.commentsLoaderIdentifier += 1;
            }
        },
		async notesInfiniteHandler($state) {

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