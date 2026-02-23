<!--
  - SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div class="board-wrapper" :tabindex="-1" @touchend="fixActionRestriction">
		<Controls :board="board" />

		<div class="board-scroll-container">
			<transition name="fade" mode="out-in">
				<div v-if="loading" key="loading" class="emptycontent">
					<div class="icon icon-loading" />
					<h2>{{ t('deck', 'Loading board') }}</h2>
				</div>

				<div v-else-if="!board" key="notfound" class="emptycontent">
					<div class="icon icon-deck" />
					<h2>{{ t('deck', 'Board not found') }}</h2>
				</div>

				<NcEmptyContent v-else-if="isEmpty" key="empty">
					<template #icon><DeckIcon /></template>
					<template #name>{{ t('deck', 'No lists available') }}</template>
					<template v-if="canManage" #action>
						{{ t('deck', 'Create a new list to add cards to this board') }}
						<form @submit.prevent="addNewStack()">
							<NcTextField ref="newStackInput"
								:disable="loading"
								:value.sync="newStackTitle"
								:placeholder="t('deck', 'List name')"
								type="text" />
							<NcButton type="secondary"
								native-type="submit"
								:disabled="loading"
								:title="t('deck', 'Add list')">
								<template #icon>
									<CheckIcon v-if="!loading" :size="20" />
									<NcLoadingIcon v-else :size="20" />
								</template>
								{{ t('deck', 'Add list') }}
							</NcButton>
						</form>
					</template>
				</NcEmptyContent>

				<div v-else key="board">
					<div v-if="isSelectionMode" class="selection-toolbar">
						<div class="selection-toolbar__info">
							<span class="selection-toolbar__count">{{ selectedCardIds.length }}</span>
							<span>{{ t('deck', 'cards selected') }}</span>
						</div>
						<div class="selection-toolbar__actions">
							<NcButton type="tertiary" @click="cancelSelection">
								{{ t('deck', 'Cancel') }}
							</NcButton>
							<NcButton type="primary"
								:disabled="selectedCardIds.length === 0"
								@click="showAssignmentModal = true">
								{{ t('deck', 'Assign to user') }}
							</NcButton>
						</div>
					</div>

					<div ref="board" class="board" @mousedown="onMouseDown">
						<Container lock-axis="y"
							orientation="horizontal"
							:drag-handle-selector="dragHandleSelector"
							data-click-closes-sidebar="true"
							@drag-start="draggingStack = true"
							@drag-end="draggingStack = false"
							@drop="onDropStack">
							<Draggable v-for="stack in stacksByBoard"
								:key="stack.id"
								data-click-closes-sidebar="true"
								data-dragscroll-enabled
								class="stack-draggable-wrapper">
								<Stack :stack="stack" :dragging="draggingStack" data-click-closes-sidebar="true" />
							</Draggable>
						</Container>
					</div>
				</div>
			</transition>
		</div>

		<NcModal v-if="showAssignmentModal"
			size="normal"
			:out-transition="true"
			@close="closeAssignmentModal">
			<div class="assignment-modal">
				<h2>{{ t('deck', 'Assign cards') }}</h2>
				<p class="assignment-modal__subtitle">
					{{ t('deck', 'Assign {count} selected cards to a user or group', { count: selectedCardIds.length }) }}
				</p>
				<div class="assignment-modal__select-wrapper">
					<span tabindex="0" class="focus-trap" aria-hidden="true" />
					<NcSelect v-model="selectedAssignee"
						:options="formattedAssignables"
						:placeholder="t('deck', 'Select a user or group…')"
						label="displayname"
						track-by="multiselectKey"
						:user-select="true"
						:append-to-body="false"
						:calculate-position="null" />
				</div>
				<div class="assignment-modal__actions">
					<NcButton type="tertiary" @click="closeAssignmentModal">
						{{ t('deck', 'Cancel') }}
					</NcButton>
					<NcButton type="primary"
						:disabled="!selectedAssignee"
						@click="assignSelectedCards">
						{{ t('deck', 'Assign') }}
					</NcButton>
				</div>
			</div>
		</NcModal>
	</div>
</template>

<script>
import { Container, Draggable } from 'vue-smooth-dnd'
import { mapGetters, mapState } from 'vuex'
import { showError } from '@nextcloud/dialogs'

import Controls from '../Controls.vue'
import Stack from '../board/Stack.vue'

import DeckIcon from 'vue-material-design-icons/ViewDashboard.vue'
import CheckIcon from 'vue-material-design-icons/Check.vue'

import { NcButton, NcEmptyContent, NcLoadingIcon, NcModal, NcSelect, NcTextField } from '@nextcloud/vue'
import { createSession } from '../../sessions.js'

export default {
	name: 'EmbeddedTasksBoard',
	components: {
		CheckIcon,
		Container,
		Controls,
		DeckIcon,
		Draggable,
		NcButton,
		NcEmptyContent,
		NcLoadingIcon,
		NcModal,
		NcSelect,
		NcTextField,
		Stack,
	},
	props: {
		boardId: {
			type: Number,
			required: true,
		},
	},
	data() {
		return {
			draggingStack: false,
			loading: true,
			newStackTitle: '',
			currentScrollPosX: null,
			currentMousePosX: null,
			session: null,
			showAssignmentModal: false,
			selectedAssignee: null,
		}
	},
	computed: {
		...mapState({
			board: state => state.currentBoard,
			showArchived: state => state.showArchived,
		}),
		...mapGetters(['canEdit', 'canManage', 'isSelectionMode', 'selectedCardIds', 'assignables']),
		stacksByBoard() {
			return this.board?.id ? this.$store.getters.stacksByBoard(this.board.id) : []
		},
		dragHandleSelector() {
			return this.canEdit ? '.stack__title' : '.no-drag'
		},
		isEmpty() {
			return this.stacksByBoard.length === 0
		},
		formattedAssignables() {
			return this.assignables.map((item) => {
				return {
					...item,
					user: item.primaryKey,
					displayName: item.displayname,
					icon: 'icon-user',
					isNoUser: false,
					multiselectKey: item.type + ':' + item.uid,
				}
			})
		},
	},
	watch: {
		boardId: {
			immediate: true,
			handler() {
				this.fetchData()
			},
		},
		showArchived() {
			this.fetchData()
		},
		isEmpty(newValue) {
			newValue && this.$nextTick(() => {
				this.$refs?.newStackInput?.focus()
			})
		},
	},
	beforeDestroy() {
		this.session?.close?.()
	},
	methods: {
		async fetchData() {
			this.loading = true
			try {
				this.session?.close?.()
				this.session = createSession(this.boardId)

				await this.$store.dispatch('loadBoardById', this.boardId)
				await this.$store.dispatch('loadStacks', this.boardId)

				// Keep parity with Board.vue behavior for opening archived cards when needed.
				const routeCardId = this.$route?.params?.cardId ? parseInt(this.$route.params.cardId, 10) : NaN
				if (routeCardId && !this.$store.getters.cardById(routeCardId)) {
					await this.$store.dispatch('loadArchivedStacks', this.boardId)
					if (this.$store.getters.cardById(routeCardId)) {
						this.$store.commit('toggleShowArchived', true)
					}
				}
			} catch (e) {
				console.error(e)
				showError(e)
			} finally {
				this.loading = false
			}
		},
		onDropStack({ removedIndex, addedIndex }) {
			this.$store.dispatch('orderStack', { stack: this.stacksByBoard[removedIndex], removedIndex, addedIndex })
		},
		addNewStack() {
			const newStack = { title: this.newStackTitle, boardId: this.boardId }
			this.$store.dispatch('createStack', newStack)
			this.newStackTitle = ''
		},
		onMouseDown(event) {
			this.startMouseDrag(event)
		},
		startMouseDrag(event) {
			if (!('dragscrollEnabled' in event.target.dataset)) {
				return
			}
			event.preventDefault()
			this.currentMousePosX = event.clientX
			this.currentScrollPosX = this.$refs.board.scrollLeft
			window.addEventListener('mousemove', this.handleMouseDrag)
			window.addEventListener('mouseup', this.stopMouseDrag)
			window.addEventListener('mouseleave', this.stopMouseDrag)
		},
		handleMouseDrag(event) {
			event.preventDefault()
			const deltaX = event.clientX - this.currentMousePosX
			this.$refs.board.scrollLeft = this.currentScrollPosX - deltaX
		},
		stopMouseDrag() {
			window.removeEventListener('mousemove', this.handleMouseDrag)
			window.removeEventListener('mouseup', this.stopMouseDrag)
			window.removeEventListener('mouseleave', this.stopMouseDrag)
		},
		fixActionRestriction() {
			document.body.classList.remove('smooth-dnd-no-user-select', 'smooth-dnd-disable-touch-action')
		},
		cancelSelection() {
			this.$store.dispatch('exitSelectionMode')
			this.closeAssignmentModal()
		},
		closeAssignmentModal() {
			this.showAssignmentModal = false
			this.selectedAssignee = null
		},
		async assignSelectedCards() {
			if (!this.selectedAssignee || this.selectedCardIds.length === 0) {
				return
			}
			const assignee = {
				userId: this.selectedAssignee.uid,
				type: this.selectedAssignee.type,
			}
			await this.$store.dispatch('bulkAssignCards', assignee)
			this.closeAssignmentModal()
		},
	},
}
</script>

<style lang="scss" scoped>
@import '../../css/animations';
@import '../../css/variables';

.board-wrapper {
	position: relative;
	width: 100%;
	height: 100%;
	display: flex;
	flex-direction: column;
}

.board-scroll-container {
	flex-grow: 1;
	overflow-y: auto;
	overflow-x: hidden;
	display: flex;
	flex-direction: column;
	padding: 16px 20px 20px;
}

.board {
	position: relative;
	flex-grow: 1;
	min-height: 460px;
	overflow: hidden;
	overflow-x: auto;
	padding-bottom: 20px;
}

.selection-toolbar {
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: 12px;
	padding: 10px 0 10px;
	border-bottom: 1px solid var(--color-border);
	margin-bottom: 10px;
}

.selection-toolbar__count {
	font-weight: 700;
}
</style>
