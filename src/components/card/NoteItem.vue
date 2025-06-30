<template>
	<div class="note-item" @mouseover="handleShowingActions" @mouseleave="handleHidingActions">
		<div class="note-item__avatar">
			<img v-if="authorAvatar" :src="authorAvatar" :alt="authorName" />
			<div v-else class="avatar-initials">{{ authorInitials }}</div>
		</div>

		<div class="note-item__main">
			<div class="note-item__header">
				<span class="author-name">{{ authorName }}</span>
				<span class="timestamp" :title="fullDateTooltip">
					{{ displayDate }}
					<em v-if="isEdited" class="edited-indicator">(edited)</em>
				</span>
			</div>
			<div class="note-item__body">
				<div v-if="!isEditing" v-html="renderedContent" class="prose"></div>
				<div v-else class="edit-mode">
					<textarea
						ref="editInput"
						v-model="editedContent"
						class="edit-textarea"
						placeholder="Write your note..."
						rows="4"
					/>
					<div class="edit-actions">
						<NcButton @click="cancelEdit">
							{{ t('deck', 'Cancel') }}
						</NcButton>
						<NcButton type="primary" @click="saveNote" :disabled="!editedContent.trim()">
							{{ t('deck', 'Save note') }}
						</NcButton>
					</div>
				</div>
			</div>
		</div>

		<div v-if="showActions && !isEditing" class="note-item__actions">
			<NcButton variant="tertiary-no-background" @click="startEditing">
				<template #icon>
					<Pencil :size="20" />
				</template>
			</NcButton>
			<NcButton variant="tertiary-no-background" @click="deleteNote">
				<template #icon>
					<Delete :size="20" />
				</template>
			</NcButton>
		</div>
	</div>
</template>

<script>
import { NcButton } from '@nextcloud/vue'
import Pencil from 'vue-material-design-icons/Pencil.vue'
import Delete from 'vue-material-design-icons/Delete.vue'

import MarkdownIt from 'markdown-it'
import moment from '@nextcloud/moment'
import { getLocale } from '@nextcloud/l10n'
import { generateUrl } from '@nextcloud/router'
import { getCurrentUser } from '@nextcloud/auth'

const md = new MarkdownIt({
	html: false, 
	linkify: true,
	typographer: true,
})

export default {
	name: 'NoteItem',
	components: {
		NcButton,
		Pencil,
		Delete,
	},
	props: {
		/**
		 * The note object.
		 * Expected shape:
		 * {
		 * id: String | Number,
		 * content: String,
		 * createdAt: String (ISO 8601 Date),
		 * updatedAt: String (ISO 8601 Date)
		 * }
		 */
		note: {
			type: Object,
			required: true,
		},
        canEdit: {
            type: Boolean, 
            default: false
        }
	},
	data() {
		return {
			isEditing: false,
			editedContent: '',
            showActions: false
		}
	},
	computed: {
        authorId() {
            return getCurrentUser()?.uid;
        },
        authorName() {
            return getCurrentUser()?.displayName;
        },
        authorAvatar() {
            return generateUrl(`/avatar/${this.authorId}/64`);
        },
		/**
		 * Renders the raw note content as safe HTML.
		 */
		renderedContent() {
			return this.note.content ? md.render(this.note.content) : '<p><em>No content</em></p>'
		},

		/**
		 * Generates author initials as a fallback for the avatar.
		 */
		authorInitials() {
			if (!this.authorName) {
				return '?'
			}
			const names = this.authorName.split(' ')
			if (names.length > 1) {
				return (names[0][0] + names[names.length - 1][0]).toUpperCase()
			}
			return names[0].substring(0, 2).toUpperCase()
		},

		/**
		 * Formats the date for display.
		 * Shows a relative time for recent notes, and a full date for older notes.
		 */
		displayDate() {
			const date = new Date(this.note.updatedAt || this.note.createdAt)
			const momentDate = moment(date)
			const now = moment()

			moment.locale(getLocale());
			
			if (now.diff(momentDate, 'days') > 7) {
				return date.toLocaleDateString(undefined, {
					year: 'numeric',
					month: 'long',
					day: 'numeric',
				});
			}

			return momentDate.fromNow();
		},

        /**
		 * Generates the full, absolute date and time string for the hover tooltip.
		 */
		fullDateTooltip() {
			const date = new Date(this.note.updatedAt || this.note.createdAt)
			return date.toLocaleString(undefined, {
				dateStyle: 'full',
				timeStyle: 'medium',
			})
		},

		/**
		 * Checks if the note has been edited by comparing timestamps.
		 * Considers it edited if times differ by more than a minute.
		 */
		isEdited() {
			const createdAt = new Date(this.note.createdAt).getTime()
			const updatedAt = new Date(this.note.updatedAt).getTime()
			return updatedAt - createdAt > 0
		},
	},
	methods: {
		/**
		 * Enters editing mode.
		 */
		startEditing() {
			this.isEditing = true
			this.editedContent = this.note.content
			// Focus the textarea automatically
			this.$nextTick(() => {
				this.$refs.editInput.focus()
			})
		},

		/**
		 * Exits editing mode without saving.
		 */
		cancelEdit() {
			this.isEditing = false
			this.editedContent = ''
		},

		/**
		 * Emits an event to the parent component to save the changes.
		 * The parent is responsible for the actual API call.
		 */
		saveNote() {
			this.$emit('update-note', {
				id: this.note.id,
				content: this.editedContent
			})
			this.isEditing = false
		},

		/**
		 * Emits an event to the parent component to delete the note.
		 */
		deleteNote() {
			if (window.confirm('Are you sure you want to delete this note?')) {
				this.$emit('delete-note', this.note.id)
			}
		},
        handleShowingActions() {
            this.showActions = this.canEdit;
        },
        handleHidingActions() {
            this.showActions = false;
        }
	},
}
</script>

<style lang="scss" scoped>
.note-item {
	display: flex;
	padding: 12px;
	border-radius: var(--border-radius-large);
	background-color: var(--color-background-soft);
	border: 1px solid var(--color-border);
	position: relative;
	gap: 12px;
    margin: 5px 0px;


	&:hover {
		border-color: var(--color-primary-element);
	}
}

.note-item__avatar {
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

.note-item__main {
	flex-grow: 1;
	display: flex;
	flex-direction: column;
	gap: 8px;
	// Prevent content from overflowing when actions appear
	max-width: calc(100% - 100px);
}

.note-item__header {
	display: flex;
	justify-content: start;
	align-items: center;
	gap: 16px;

	.author-name {
		font-weight: bold;
		color: var(--color-main-text);
	}

	.timestamp {
		font-size: 0.85em;
		color: var(--color-text-maxcontrast);
		white-space: nowrap;
        text-overflow: ellipsis;
        overflow: hidden;
	}

	.edited-indicator {
		opacity: 0.7;
	}
}

.note-item__body {
	.prose {
		// Styles for the rendered markdown content
		// This ensures links, bold, lists etc look good.
		:deep(p) {
			margin: 0 0 0.5em;
			&:last-child {
				margin-bottom: 0;
			}
		}
		:deep(a) {
			color: var(--color-primary-element);
			text-decoration: none;
			&:hover {
				text-decoration: underline;
			}
		}
		:deep(ul),
		:deep(ol) {
			padding-left: 20px;
		}
	}
}

.note-item__actions {
	position: absolute;
	top: 4px;
	right: 4px;
	display: flex;
	background-color: var(--color-background-soft);
	border-radius: var(--border-radius-large);
	border: 1px solid var(--color-border);
	box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.edit-mode {
	display: flex;
	flex-direction: column;
	gap: 8px;
}

.edit-textarea {
	width: 100%;
	padding: 8px 12px;
	border: 1px solid var(--color-border-dark);
	border-radius: var(--border-radius);
	font-family: inherit;
	font-size: 1em;
	resize: vertical;

	&:focus {
		outline: none;
		border-color: var(--color-primary-element);
		box-shadow: 0 0 0 1px var(--color-primary-element);
	}
}

.edit-actions {
	display: flex;
	justify-content: flex-end;
	gap: 8px;
}
</style>