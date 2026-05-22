<!--
  - SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div>
		<div v-if="loading" class="dashboard-loading">
			<div class="icon icon-loading" />
		</div>

		<div v-else-if="visibleCards.length === 0 && !dueDateFilter" class="dashboard-empty">
			<div class="empty-content-icon icon-deck" />
			<p>{{ t('deck', 'No upcoming cards') }}</p>
		</div>

		<div v-else-if="visibleCards.length === 0 && dueDateFilter" class="dashboard-empty">
			<div class="empty-content-icon icon-deck" />
			<p>{{ t('deck', 'No upcoming cards in this period') }}</p>
		</div>

		<div v-else class="dashboard-project-groups">
			<div class="dashboard-filters">
				<button
					v-for="opt in dueDateOptions"
					:key="opt.value"
					class="dashboard-filter-chip"
					:class="{ active: dueDateFilter === opt.value }"
					@click="dueDateFilter = dueDateFilter === opt.value ? '' : opt.value">
					{{ opt.label }}
				</button>
			</div>
			<p class="dashboard-total-count">
				{{ cards.length }} {{ t('deck', 'upcoming cards') }}
			</p>
			<div v-for="group in groupedCards" :key="group.key" class="dashboard-project-group">
				<h3 class="dashboard-project-group__title">
					{{ group.projectName }}
				</h3>
				<template v-for="card in group.cards">
					<Card :key="card.id"
						:card="card"
						:redirect-to-project="true"
						@open:sidebar="handleSidebarOpen" />
					<CardNotesAndComments v-if="openedCardId === card.id"
						:key="`notes-${card.id}`"
						:title="card.title"
						:card-id="card.id"
						@close="handleSidebarClose" />
				</template>
			</div>
		</div>

		<div v-if="showMoreUrl" class="dashboard-show-more">
			<a :href="showMoreUrl">{{ t('deck', 'upcoming cards ...') }}</a>
		</div>

		<div class="center-button">
			<NcButton v-if="isAdmin" @click="toggleAddCardModel">
				<template #icon>
					<PlusIcon :size="20" />
				</template>
				{{ t('deck', 'New card') }}
			</NcButton>
			<NcModal v-if="showAddCardModal" class="card-selector" @close="toggleAddCardModel">
				<CreateNewCardCustomPicker show-created-notice @cancel="toggleAddCardModel" />
			</NcModal>
		</div>
	</div>
</template>

<script>
import PlusIcon from 'vue-material-design-icons/Plus.vue'
import { NcButton, NcModal } from '@nextcloud/vue'
import { mapGetters, mapState } from 'vuex'
import Card from '../components/dashboard/Card.vue'
import { generateUrl } from '@nextcloud/router'
import CreateNewCardCustomPicker from './CreateNewCardCustomPicker.vue'
import { getCurrentUser } from '@nextcloud/auth'
import CardNotesAndComments from '../components/card/CardNotesAndComments.vue'
import { groupCardsByProject } from '../utils/dashboardProjectGroups.js'

export default {
	name: 'DashboardUpcoming',
	components: {
		CreateNewCardCustomPicker,
		CardNotesAndComments,
		NcModal,
		NcButton,
		PlusIcon,
		Card,
	},
	data() {
		return {
			loading: false,
			showAddCardModal: false,
			dueDateFilter: '',
		}
	},
	computed: {
		dueDateOptions() {
			return [
				{ value: '7days', label: this.t('deck', 'Next 7 days') },
				{ value: '30days', label: this.t('deck', 'Next 30 days') },
				{ value: '3months', label: this.t('deck', 'Next 3 months') },
			]
		},
		...mapGetters([
			'assignedCardsDashboard',
		]),
		...mapState({
			selectedBoardId: state => state.dashboard.selectedBoardId,
			openedCardId: state => state.card.openedCardId,
		}),
		isAdmin() {
			return !!getCurrentUser()?.isAdmin
		},
		cardLimit() {
			return 5
		},
		cards() {
			const now = new Date()
			const ranges = {
				'7days': 7 * 24 * 60 * 60 * 1000,
				'30days': 30 * 24 * 60 * 60 * 1000,
				'3months': 90 * 24 * 60 * 60 * 1000,
			}

			const list = [
				...this.assignedCardsDashboard,
			].filter((card) => {
				return card.duedate && new Date(card.duedate) > new Date()
			}).filter((card) => {
				if (!this.dueDateFilter) return true
				return new Date(card.duedate).getTime() - now.getTime() <= ranges[this.dueDateFilter]
			})
			list.sort((a, b) => {
				return (new Date(a.duedate)).getTime() - (new Date(b.duedate)).getTime()
			})
			return list
		},
		visibleCards() {
			return this.cards.slice(0, this.cardLimit)
		},
		groupedCards() {
			return groupCardsByProject(this.visibleCards, this.t('deck', 'Other'))
		},
		showMoreUrl() {
			const hasMore = this.cards.length > this.cardLimit
			if (!hasMore) {
				return null
			}

			if (this.selectedBoardId) {
				return generateUrl('/apps/deck/board/') + this.selectedBoardId
			} else {
				return generateUrl('/apps/deck')
			}
		},
	},
	watch: {
		selectedBoardId(newValue, oldValue) {
			this.fetchUpcomingCards()
		},
	},
	beforeMount() {
		this.fetchUpcomingCards()
	},
	methods: {
		toggleAddCardModel() {
			this.showAddCardModal = !this.showAddCardModal
		},
		fetchUpcomingCards() {
			this.loading = true
			this.$store.dispatch('loadUpcoming').then(() => {
				this.loading = false
			})
		},
		handleSidebarOpen(card) {
			this.$store.commit('setOpenedCardId', card.id)
		},
		handleSidebarClose() {
			setTimeout(() => {
				this.$store.commit('setOpenedCardId', null)
			}, 200)
		},
	},
}
</script>

<style lang="scss" scoped>
	.center-button {
		display: flex;
		align-items: center;
		justify-content: center;
		margin-top: 10px;
	}

	.dashboard-loading {
		display: flex;
		justify-content: center;
		padding: 32px 0;
	}

	.dashboard-empty {
		text-align: center;
		padding: 5vh 0;
	}

	.dashboard-show-more {
		text-align: center;
		padding: 8px 0;
		a {
			font-weight: 600;
		}
	}

	.dashboard-total-count {
		padding: 8px;
		font-weight: 600;
		color: var(--color-text-maxcontrast);
	}

	.dashboard-filters {
		display: flex;
		gap: 8px;
		padding: 8px;
	}

	.dashboard-filter-chip {
		padding: 4px 12px;
		border: 1px solid var(--color-border);
		border-radius: 16px;
		background: transparent;
		color: var(--color-text-maxcontrast);
		font-size: 0.9rem;
		cursor: pointer;

		&.active {
			background: var(--color-primary-element);
			color: var(--color-primary-text);
			border-color: var(--color-primary-element);
		}
	}

	.dashboard-project-group__title {
		margin: 16px 8px 8px;
		font-size: 1.1rem;
		font-weight: 700;
		color: var(--color-main-text);
		display: flex;
		align-items: center;
		gap: 8px;

		&::before {
			content: '';
			display: inline-block;
			width: 4px;
			height: 1.2em;
			background-color: var(--color-primary-element);
			border-radius: 2px;
		}
	}

	.dashboard-project-group {
		& + & {
			margin-top: 12px;
			padding-top: 8px;
			border-top: 1px solid var(--color-border);
		}
	}

	#deck-widget-empty-content {
		text-align: center;
		margin-top: 5vh;
	}

	.card {
		display: block;
		border-radius: var(--border-radius-large);
		padding: 5px 8px;
		height: 70px;
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
			position: relative;
			top: 3px;
		}
	}

	.labels {
		margin-left: 0;
		margin-top: 3px;
	}

	.duedate:deep {
		.due {
			margin: 0 0 0 10px;
			padding: 0px 4px;
			font-size: 90%;
			margin-bottom: 7px;
		}
	}

	.right {
		float: right;
	}
</style>
