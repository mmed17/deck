<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
    <div class="tab-content">
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
            :name="notesError ? notesError : t('deck', 'No notes yet.')">
            <template #icon>
                <Comment />
            </template>
        </NcEmptyContent>
    </div>
</template>

<script>
import { generateOcsUrl } from '@nextcloud/router';
import NoteTextOutline from 'vue-material-design-icons/NoteTextOutline.vue';
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon';
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent';
import InfiniteLoading from 'vue-infinite-loading';
import ocs from '@nextcloud/axios';
import Comment from 'vue-material-design-icons/CommentOutline.vue';

const API_LIMIT = 10;

export default {
	name: 'CardNotes',
	components: { 
		NoteTextOutline,
		NcLoadingIcon,
		NcEmptyContent,
		InfiniteLoading,
        Comment
	},
	props: {
		cardId: {
		    type: Number,
			required: true,
		}
	},
	data() {
		return {
            notes: [],
            notesLoading: false,
            notesOffset: 0,
            canLoadMoreNotes: true,
			notesLoaderIdentifier: 0,
			notesError: null
		}
	},
	computed: {
		notesUrl() {
			return `/apps/deck/api/v1.0/cards/${this.cardId}/notes`;
		}
	},
	methods: {
		async notesInfiniteHandler($state) {
            if (!this.canLoadMoreNotes && $state) {
                $state.complete();
                return;
            }

            this.notesLoading = true;
            try {
				const url = generateOcsUrl(this.notesUrl);
				const response = await ocs.get(url, {
					params: {
						limit: API_LIMIT,
						offset: this.notesOffset
					}
				});
				const notes = response.data.ocs.data;
				if (notes.length) {
                    this.notes.push(...notes);
                    this.notesOffset += notes.length;
					if($state) {
						$state.loaded();
					}
                } else {
                    this.canLoadMoreNotes = false;
					if($state) {
						$state.complete();
					}
                }
            } catch (e) {
                console.error('Failed to fetch notes', e);
                this.notesError = 'Failed to load notes';
				if($state) {
					$state.error();
				}
            } finally {
                this.notesLoading = false;
				this.notesLoaderIdentifier += 1;
            }
		}
	}
}
</script>

<style  lang="scss" scoped>
</style>