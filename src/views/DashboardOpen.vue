<!--
  - SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<NcDashboardWidget :items="cards"
		empty-content-icon="icon-deck"
		:empty-content-message="t('deck', 'No non-due tasks')"
		:show-more-text="t('deck', 'Non-due tasks ...')"
		:show-more-url="showMoreUrl"
		:loading="loading"
		@hide="() => {}"
		@markDone="() => {}">
		<template #default="{ item }">
			<Card 
				:card="item" 
				:redirect-to-project="true" 
				@open:sidebar="handleSidebarOpen" />
			
			<CardNotesAndComments 
				v-if="openedCardId === item.id"
				:title="item.title"
				:card-id="item.id"
				@close="handleSidebarClose" />
		</template>
	</NcDashboardWidget>
</template>

<script>
import { NcDashboardWidget } from '@nextcloud/vue'
import { mapGetters, mapState } from 'vuex'
import { generateUrl } from '@nextcloud/router'
import Card from '../components/dashboard/Card.vue'
import CardNotesAndComments from "../components/card/CardNotesAndComments.vue";

export default {
	name: 'DashboardOpen',
	components: {
		NcDashboardWidget,
		CardNotesAndComments,
		Card
	},
	data() {
		return {
			loading: false
		}
	},
	computed: {
		...mapGetters([
			'assignedCardsDashboard'
		]),
		...mapState({
            selectedBoardId: state => state.dashboard.selectedBoardId,
			openedCardId: state => state.card.openedCardId
        }),
		cards() {
			const list = [
				...this.assignedCardsDashboard,
			].filter((card) => {
				return !card.duedate;
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
        },
		handleSidebarOpen(card) {
			this.$store.commit('setOpenedCardId', card.id);
		},
		handleSidebarClose() {
			setTimeout(() => {
				this.$store.commit('setOpenedCardId', null);
			}, 200);
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
