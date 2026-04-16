<!--
  - SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div>
		<NcActionButton v-if="!hideDetailsEntry" :close-after-click="true" @click="openCard">
			<CardBulletedIcon slot="icon" :size="20" decorative />
			{{ t('deck', 'Card details') }}
		</NcActionButton>
		<NcActionButton v-if="canEdit" :close-after-click="true" @click="editTitle">
			<template #icon>
				<PencilIcon :size="20" decorative />
			</template>
			{{ t('deck', 'Edit title') }}
		</NcActionButton>
		<NcActionButton v-for="action in visibleCardActions"
			:key="action.id || action.label"
			:close-after-click="true"
			:icon="action.icon"
			@click="action.callback(cardRichObject)">
			{{ action.label }}
		</NcActionButton>
	</div>
</template>
<script>
import { NcActionButton } from '@nextcloud/vue'
import { mapGetters, mapState } from 'vuex'
import { generateUrl } from '@nextcloud/router'
import CardBulletedIcon from 'vue-material-design-icons/CardBulleted.vue'
import PencilIcon from 'vue-material-design-icons/Pencil.vue'

export default {
	name: 'CardMenuEntries',
	components: { NcActionButton, CardBulletedIcon, PencilIcon },
	props: {
		card: {
			type: Object,
			default: null,
		},
		hideDetailsEntry: {
			type: Boolean,
			default: false,
		},
	},
	emits: ['edit-title'],
	data() {
		return {
			modalShow: false,
			selectedBoard: '',
			selectedStack: '',
			stacksFromBoard: [],
		}
	},
	computed: {
		...mapGetters([
			'cardActions',
			'stackById',
			'boardById',
		]),
		...mapState({
			currentBoard: state => state.currentBoard,
		}),
		canEdit() {
			return !this.card.archived && !this.$store.getters.isCurrentBoardCombiProject
		},
		boardId() {
			return this.card?.boardId ? this.card.boardId : Number(this.$route.params.id)
		},
		cardRichObject() {
			return {
				id: '' + this.card.id,
				boardId: String(this.boardId),
				name: this.card.title,
				boardname: this.boardById(this.boardId)?.title,
				stackname: this.stackById(this.card.stackId)?.title,
				link: window.location.protocol + '//' + window.location.host + generateUrl('/apps/deck/') + `card/${this.card.id}`,
			}
		},
		visibleCardActions() {
			return this.cardActions.filter((action) => this.isAllowedCardAction(action))
		},
	},
	methods: {
		openCard() {
			const boardId = this.card?.boardId ? this.card.boardId : this.$route?.params.id ?? this.currentBoard.id

			if (this.$router) {
				this.$router?.push({ name: 'card', params: { id: boardId, cardId: this.card.id } }).catch(() => {})
				return
			}

			this.$root.$emit('open-card', this.card.id)
		},
		editTitle() {
			this.$emit('edit-title', this.card.id)
		},
		isAllowedCardAction(action) {
			const source = (action?.source ?? '').toLowerCase()
			const id = (action?.id ?? '').toLowerCase()

			// Preferred controlled contract for integrations
			if (source === 'talk' || source === 'spreed') {
				return true
			}
			if (id.startsWith('talk:') || id.startsWith('spreed:')) {
				return true
			}

			// Backward-compatible fallback for legacy Talk registrations that
			// don't provide source/id metadata.
			const icon = (action?.icon ?? '').toLowerCase()
			const label = (action?.label ?? '').toLowerCase()
			const talkLikeIcon = icon.includes('talk')
			const talkLikeLabel = label.includes('conversation') || label.includes('chat') || label.includes('post to')
			return talkLikeIcon || talkLikeLabel
		},
	},
}
</script>
