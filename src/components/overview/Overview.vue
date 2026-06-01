<!--
  - SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div class="overview-wrapper">
		<Controls :overview-name="filterDisplayName" />

		<div v-if="loading" key="loading" class="emptycontent">
			<div class="icon icon-loading" />
			<h2>{{ t('deck', 'Loading filtered view') }}</h2>
			<p />
		</div>

		<div v-else-if="isValidFilter" class="overview">
			<div class="dashboard-column">
				<h3>{{ t('deck', 'Overdue') }}</h3>
				<div v-for="group in projectGroups('overdue')" :key="group.key" class="project-group">
					<h4 class="project-group__title">{{ group.projectName }}</h4>
					<div v-for="card in group.cards" :key="card.id">
						<CardItem :id="card.id" />
					</div>
				</div>
			</div>

			<div class="dashboard-column">
				<h3>{{ t('deck', 'Today') }}</h3>
				<div v-for="group in projectGroups('today')" :key="group.key" class="project-group">
					<h4 class="project-group__title">{{ group.projectName }}</h4>
					<div v-for="card in group.cards" :key="card.id">
						<CardItem :id="card.id" />
					</div>
				</div>
			</div>

			<div class="dashboard-column">
				<h3>{{ t('deck', 'Tomorrow') }}</h3>
				<div v-for="group in projectGroups('tomorrow')" :key="group.key" class="project-group">
					<h4 class="project-group__title">{{ group.projectName }}</h4>
					<div v-for="card in group.cards" :key="card.id">
						<CardItem :id="card.id" />
					</div>
				</div>
			</div>

			<div class="dashboard-column">
				<h3>{{ t('deck', 'Next 7 days') }}</h3>
				<div v-for="group in projectGroups('nextSevenDays')" :key="group.key" class="project-group">
					<h4 class="project-group__title">{{ group.projectName }}</h4>
					<div v-for="card in group.cards" :key="card.id">
						<CardItem :id="card.id" />
					</div>
				</div>
			</div>

			<div class="dashboard-column">
				<h3>{{ t('deck', 'Later') }}</h3>
				<div v-for="group in projectGroups('later')" :key="group.key" class="project-group">
					<h4 class="project-group__title">{{ group.projectName }}</h4>
					<div v-for="card in group.cards" :key="card.id">
						<CardItem :id="card.id" />
					</div>
				</div>
			</div>

			<div class="dashboard-column">
				<h3>{{ t('deck', 'No due') }}</h3>
				<div v-for="group in projectGroups('nodue')" :key="group.key" class="project-group">
					<h4 class="project-group__title">{{ group.projectName }}</h4>
					<div v-for="card in group.cards" :key="card.id">
						<CardItem :id="card.id" />
					</div>
				</div>
			</div>
		</div>

		<GlobalSearchResults />
	</div>
</template>

<script>
import Controls from '../Controls.vue'
import CardItem from '../cards/CardItem.vue'
import { mapGetters } from 'vuex'
import GlobalSearchResults from '../search/GlobalSearchResults.vue'
import { groupCardsByProject } from '../../utils/dashboardProjectGroups.js'

const FILTER_UPCOMING = 'upcoming'

const SUPPORTED_FILTERS = [
	FILTER_UPCOMING,
]

export default {
	name: 'Overview',
	components: {
		GlobalSearchResults,
		Controls,
		CardItem,
	},
	props: {
		filter: {
			type: String,
			default: FILTER_UPCOMING,
		},
	},
	data() {
		return {
			loading: true,
		}
	},
	computed: {
		isValidFilter() {
			return SUPPORTED_FILTERS.indexOf(this.filter) > -1
		},
		filterDisplayName() {
			switch (this.filter) {
			case FILTER_UPCOMING:
				return t('deck', 'Upcoming cards')
			default:
				return ''
			}
		},
		...mapGetters(['assignedCardsDashboard']),
	},
	watch: {
		'$route.params.filter'() {
			this.getData()
		},
	},
	created() {
		this.getData()
	},
	methods: {
		async getData() {
			this.loading = true
			try {
				if (this.filter === FILTER_UPCOMING) {
					await this.$store.dispatch('loadUpcoming')
				}
			} catch (e) {
				console.error(e)
			}
			this.loading = false
		},
		sortCards(when) {
			const cards = this.assignedCardsDashboard[when]

			if (!cards) {
				return null
			} else {
				return cards.toSorted((current, next) => {
					const currentDueDate = new Date(current.duedate)
					const nextDueDate = new Date(next.duedate)

					return currentDueDate - nextDueDate
				})
			}
		},
		projectGroups(when) {
			const cards = this.sortCards(when)
			if (!cards || cards.length === 0) {
				return []
			}
			return groupCardsByProject(cards, this.t('deck', 'Other'))
		},
	},

}
</script>

<style lang="scss" scoped>
@import './../../css/variables';

.overview-wrapper {
	position: relative;
	width: 100%;
	height: 100%;
	max-height: calc(100vh - 50px);
	display: flex;
	flex-direction: column;
}

.overview {
	position: relative;
	height: calc(100% - var(--default-clickable-area));
	overflow-x: scroll;
	display: flex;
	align-items: stretch;
	padding-left: $board-spacing;
	padding-right: $board-spacing;

	.dashboard-column {
		display: flex;
		flex-direction: column;
		min-width: $stack-width;
		width: $stack-width;
		margin-left: $stack-spacing;
		margin-right: $stack-spacing;

		h3 {
			font-size: var(--default-font-size);
			margin: -6px;
			margin-bottom: 12px;
			padding: 6px 13px;
			position: sticky;
			top: 0;
			z-index: 100;
			background-color: var(--color-main-background);
			border: 1px solid var(--color-main-background);
		}
	}

	.project-group {
		& + & {
			margin-top: 12px;
			padding-top: 8px;
			border-top: 1px solid var(--color-border);
		}
	}

	.project-group__title {
		margin: 0 0 4px;
		padding: 2px 0;
		font-size: 0.85rem;
		font-weight: 600;
		color: var(--color-text-maxcontrast);
		display: flex;
		align-items: center;
		gap: 6px;

		&::before {
			content: '';
			display: inline-block;
			width: 3px;
			height: 1em;
			background-color: var(--color-primary-element);
			border-radius: 2px;
		}
	}
}

</style>
