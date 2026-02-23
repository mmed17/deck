<template>
	<div class="deck-embedded-tasks">
		<EmbeddedTasksBoard :board-id="boardId" />

		<NcModal v-if="localModal"
			:clear-view-delay="0"
			:close-button-contained="true"
			size="large"
			@close="localModal = null">
			<div class="modal__content modal__card">
				<CardSidebar :id="localModal" @close="localModal = null" />
			</div>
		</NcModal>
	</div>
</template>

<script>
import { NcModal } from '@nextcloud/vue'
import CardSidebar from '../card/CardSidebar.vue'
import EmbeddedTasksBoard from './EmbeddedTasksBoard.vue'

export default {
	name: 'EmbeddedTasksRoot',
	components: {
		CardSidebar,
		EmbeddedTasksBoard,
		NcModal,
	},
	props: {
		boardId: {
			type: Number,
			required: true,
		},
	},
	data() {
		return {
			localModal: null,
		}
	},
	watch: {
		boardId() {
			this.localModal = null
		},
	},
	created() {
		this.$root.$on('open-card', (cardId) => {
			const id = Number(cardId)
			if (!Number.isFinite(id) || id <= 0) {
				return
			}
			this.localModal = id
		})
	},
}
</script>

<style lang="scss" scoped>
.deck-embedded-tasks {
	height: 100%;
}
</style>
