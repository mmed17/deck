<!--
  - SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<NcDashboardWidget :items="cards"
		empty-content-icon="icon-deck"
		:empty-content-message="t('deck', 'No overdue tasks')"
		:show-more-text="t('deck', 'overdue tasks ...')"
		:show-more-url="showMoreUrl"
		:loading="loading"
		@hide="() => {}"
		@markDone="() => {}">
		<template #default="{ item }">
			<Card :card="item" :redirect-to-project="true" />
		</template>
	</NcDashboardWidget>
</template>

<script>
import { NcDashboardWidget } from '@nextcloud/vue'
import { mapGetters, mapState } from 'vuex'
import Card from '../components/dashboard/Card.vue'
import { generateUrl } from '@nextcloud/router'

export default {
	name: 'DashboardOverdue',
	components: {
		NcDashboardWidget,
		Card,
	},
	data() {
		return {
			loading: false,
		}
	},
	computed: {
		...mapGetters([
			'assignedCardsDashboard',
		]),
		...mapState({
            selectedBoardId: state => state.dashboard.selectedBoardId
        }),
		cards() {
			const tomorrow = new Date()
			tomorrow.setDate(tomorrow.getDate() + 1)
			const list = [
				...this.assignedCardsDashboard,
			].filter((card) => {
				return card.duedate && new Date(card.duedate) <= new Date();
			});

			list.sort((a, b) => {
				return (new Date(a.duedate)).getTime() - (new Date(b.duedate)).getTime()
			})
			return list
		},
		showMoreUrl() {
			const hasMore = this.cards.length > 7;
            if (!hasMore) {
                return null;
            }
			
            if (this.selectedBoardId) {
                return generateUrl('/apps/deck/board/') + this.selectedBoardId;
            } else {
                return generateUrl('/apps/deck');
            }
		},
	},
	watch: {
		selectedBoardId(newValue, oldValue) {
            this.fetchUpcomingCards();
		}
	},
	beforeMount() {
		this.fetchUpcomingCards();
	},
	methods: {
		fetchUpcomingCards() {
			this.loading = true
			this.$store.dispatch('loadUpcoming').then(() => {
				this.loading = false
			})
        }
	}
}
</script>

<style lang="scss" scoped>
	#deck-widget-empty-content {
		text-align: center;
		margin-top: 5vh;
	}
</style>
