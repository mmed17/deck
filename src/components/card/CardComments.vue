<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
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
            :name="commentsError ? commentsError : t('deck', 'No comments yet.')">
            <template #icon>
                <Comment />
            </template>
        </NcEmptyContent>
    </div>
</template>

<script>
import Comment from 'vue-material-design-icons/CommentOutline.vue';
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon';
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent';
import { generateOcsUrl } from '@nextcloud/router';
import InfiniteLoading from 'vue-infinite-loading';
import CommentItem from '../card/CommentItem.vue';
import ocs from '@nextcloud/axios';

const API_LIMIT = 10;

export default {
	name: 'CardComments',
	components: { 
		Comment,
		NcLoadingIcon,
		NcEmptyContent,
		CommentItem,
		InfiniteLoading
	},
	props: {
		cardId: {
		    type: Number,
			required: true,
		}
	},
	data() {
		return {
			comments: [],
            commentsLoading: false,
            commentsOffset: 0,
            canLoadMoreComments: true,
			commentsLoaderIdentifier: 0,
			commentsError: null,
		}
	},
	computed: {
		commentsUrl() {
			return `/apps/deck/api/v1.0/cards/${this.cardId}/comments`;
		}
	},
	methods: {
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
                this.commentsError = 'Failed to load comments';
				if($state) {
					$state.error();
				}
            } finally {
                this.commentsLoading = false;
				this.commentsLoaderIdentifier += 1;
            }
        }
	}
}
</script>

<style lang="scss" scoped>
</style>