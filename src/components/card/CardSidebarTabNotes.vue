<template>
	<div>
        <div class="add-note">
            <div class="add-note__avatar">
                <img v-if="currentUserAvatar" :src="currentUserAvatar" :alt="currentUser.name" />
                <div v-else class="avatar-initials">{{ authorInitials }}</div>
            </div>

            <div class="add-note__main">
                <textarea
                    v-model="noteContent"
                    class="note-textarea"
                    :placeholder="t('deck', 'Write a private note...')"
                    rows="3"
                    :disabled="isLoading"/>
                
                <div class="add-note__actions">
                    <NcButton
                        type="primary"
                        :disabled="isSubmitDisabled"
                        :loading="isLoading"
                        @click="handleSubmit">
                        {{ t('deck', 'Add note') }}
                    </NcButton>
                </div>
            </div>
        </div>

        <ul class="notes-feed">
            <NoteItem
                v-for="note in notes"
                :key="note.id"
                :note="note"
                @update-note="handleUpdateNote"
                @delete-note="handleDeleteNote" />
            
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
            v-if="!isNotesFetching && notes.length === 0"
            :name="notesError ? notesError : t('deck', 'No notes yet.')">
            <template #icon>
                <Comment />
            </template>
        </NcEmptyContent>
    </div>
</template>

<script>
import { NcButton } from '@nextcloud/vue'
import { getCurrentUser } from '@nextcloud/auth';
import { generateUrl } from '@nextcloud/router';

import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon';
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent';
import InfiniteLoading from 'vue-infinite-loading';
import Comment from 'vue-material-design-icons/CommentOutline.vue';
import NoteItem from './NoteItem.vue';

export default {
	name: 'CardSidebarTabNotes',
	components: {
        Comment,
        NoteItem,
		NcButton,
        NcLoadingIcon,
        NcEmptyContent,
        InfiniteLoading,
	},
	props: {
        card: {
            type: Object,
            required: true
        },
        tabQuery: {
			type: String,
			required: false,
			default: null,
		}
    },
	data() {
		return {
            notes: [],
			noteContent: '',
            isLoading: false,
            isNotesFetching: false,
            notesError: null,
            notesLoaderIdentifier: 0
		}
	},
	computed: {
        currentUser() {
            return getCurrentUser();
        },
        currentUserAvatar() {
            if(!this.currentUser || !this.currentUser.uid) return;

            return generateUrl(`/avatar/${this.currentUser.uid}/64`);
        },
		/**
		 * Generates user initials as a fallback for the avatar.
		 */
		authorInitials() {
			if (!this.currentUser || !this.currentUser.name) {
				return '?'
			}
			const names = this.currentUser.name.split(' ')
			if (names.length > 1) {
				return (names[0][0] + names[names.length - 1][0]).toUpperCase()
			}
			return names[0].substring(0, 2).toUpperCase()
		},

		/**
		 * Determines if the submit button should be disabled.
		 */
		isSubmitDisabled() {
			return !this.noteContent.trim() || this.isLoading
		},
	},
	methods: {
		/**
		 * Handles the submission of a new note.
		 */
		handleSubmit() {
			if (this.isSubmitDisabled) {
				return
			}
			// Emits the content of the note to the parent component.
			// The parent is responsible for creating the full note object
			// with timestamps and IDs from the server.
			this.$emit('add-note', {
				content: this.noteContent,
			})

			// Clear the textarea for the next note
			this.noteContent = ''
		},
        notesInfiniteHandler($state) {},
        handleUpdateNote() {},
        handleDeleteNote() {}
	},
}
</script>

<style lang="scss" scoped>
.add-note {
	display: flex;
	padding-top: 16px; // Space above the add note form
	gap: 12px;
}

.add-note__avatar {
	flex-shrink: 0;

	img,
	.avatar-initials {
		width: 36px;
		height: 36px;
		border-radius: 50%;
		object-fit: cover;
	}

	.avatar-initials {
		display: flex;
		align-items: center;
		justify-content: center;
		background-color: var(--color-primary-element);
		color: var(--color-primary-text);
		font-weight: bold;
	}
}

.add-note__main {
	flex-grow: 1;
	display: flex;
	flex-direction: column;
	gap: 8px;
}

.note-textarea {
	width: 100%;
	padding: 8px 12px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
	font-family: inherit;
	font-size: 1em;
	resize: vertical;
	background-color: var(--color-main-background);
	transition: border-color 0.2s, box-shadow 0.2s;

	&:focus {
		outline: none;
		border-color: var(--color-primary-element);
		box-shadow: 0 0 0 1px var(--color-primary-element);
	}

	&:disabled {
		background-color: var(--color-background-soft);
	}
}

.add-note__actions {
	display: flex;
	justify-content: flex-end;
}
</style>