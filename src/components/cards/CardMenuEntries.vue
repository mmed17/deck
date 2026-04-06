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
	</div>
</template>
<script>
import { NcActionButton } from '@nextcloud/vue'
import { mapState } from 'vuex'
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
		...mapState({
			currentBoard: state => state.currentBoard,
		}),
		canEdit() {
			return !this.card.archived
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
	},
}
</script>
