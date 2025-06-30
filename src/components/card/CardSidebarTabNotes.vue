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
                :can-edit="true"
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
import axios from '@nextcloud/axios'

import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon';
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent';
import InfiniteLoading from 'vue-infinite-loading';
import Comment from 'vue-material-design-icons/CommentOutline.vue';
import NoteItem from './NoteItem.vue';
import { showError, showSuccess } from '@nextcloud/dialogs';

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
            notesLoaderIdentifier: 0,
            page: 1,
			notesPerPage: 10,
            canLoadMoreNotes: true,
		}
	},
    mounted() {
        console.log("[mounted] TRIGGER LOADING");
        this.notesInfiniteHandler();
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
		 * Handles creating a new note.
		 * Sends a POST request to the backend.
		 */
		async handleSubmit() {
			if (this.isSubmitDisabled) return

			this.isLoading = true
			const url = generateUrl(`/apps/deck/api/v1.0/cards/${this.card.id}/notes`)
			const payload = {
				content: this.noteContent,
			}

			try {
				const response = await axios.post(url, payload)
				this.notes.unshift(response.data)
				this.noteContent = ''
			} catch (e) {
				console.error(e)
				showError(t('deck', 'Could not create note.'))
			} finally {
				this.isLoading = false
			}
		},
        /**
		 * Fetches pages of notes for the infinite scroller.
		 * Sends a GET request to the backend.
		 * @param {object} $state - The state object from the vue-infinite-loading component.
		 */
		async notesInfiniteHandler($state) {
            console.log("[notesInfiniteHandler] Called with state:", $state);

            if (this.isNotesFetching) {
                console.log("[notesInfiniteHandler] Fetch in progress, skipping...");
                return;
            }

            if (!this.canLoadMoreNotes) {
                console.log("[notesInfiniteHandler] No more notes to load, completing...");
                $state?.complete();
                return;
            }

            console.log("[notesInfiniteHandler] Loading more notes...");

            this.isNotesFetching = true;

            const offset = (this.page - 1) * this.notesPerPage;
            const url = generateUrl(`/apps/deck/api/v1.0/cards/${this.card.id}/notes?limit=${this.notesPerPage}&offset=${offset}`);
            console.log(`[notesInfiniteHandler] Fetching from URL: ${url}`);

            try {
                const response = await axios.get(url);
                console.log("[notesInfiniteHandler] Response received:", response);

                if (response.data.length) {
                    this.page += 1;
                    console.log(`[notesInfiniteHandler] Loaded ${response.data.length} notes, new page: ${this.page}`);
                    this.notes.push(...response.data);
                    $state?.loaded();
                } else {
                    console.log("[notesInfiniteHandler] No more notes returned from API.");
                    this.canLoadMoreNotes = false;
                    $state?.complete();
                }
            } catch (e) {
                console.error("[notesInfiniteHandler] Error occurred:", e);
                this.notesError = t('deck', 'Could not load notes.');
                $state?.complete();
            } finally {
                this.isNotesFetching = false;
                console.log("[notesInfiniteHandler] Fetching complete, isNotesFetching reset to false.");
            }
        },
        /**
		 * Handles updating an existing note.
		 * Sends a PUT request to the backend.
		 * @param {object} payload - The event payload from NoteItem.vue, e.g., { id, content }
		 */
		async handleUpdateNote(payload) {
			const url = generateUrl(`/apps/deck/api/v1.0/notes/${payload.id}`);
			
			try {
				const response = await axios.put(url, { content: payload.content });
				const noteIndex = this.notes.findIndex(n => n.id === payload.id);
				if (noteIndex !== -1) {
					this.$set(this.notes, noteIndex, response.data);
				}
				showSuccess(t('deck', 'Note updated'))
			} catch (e) {
				console.error(e)
				showError(t('deck', 'Could not update note.'))
			}
		},
        /**
		 * Handles deleting a note.
		 * Sends a DELETE request to the backend.
		 * @param {number} noteId - The ID of the note to delete.
		 */
		async handleDeleteNote(noteId) {
			const url = generateUrl(`/apps/deck/api/v1.0/notes/${noteId}`)

			try {
				await axios.delete(url)
				this.notes = this.notes.filter(n => n.id !== noteId)
			} catch (e) {
				console.error(e)
				showError(t('deck', 'Could not delete note.'))
			}
		},
	},
}
</script>

<style lang="scss" scoped>
.add-note {
	display: flex;
	padding-top: 16px;
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

.notes-feed {
    margin-top: 5px;
}
</style>