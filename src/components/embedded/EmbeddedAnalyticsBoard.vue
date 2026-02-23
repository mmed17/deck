<!--
  - SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div class="analytics-wrapper" :tabindex="-1">
		<transition name="fade" mode="out-in">
			<div v-if="loading" key="loading" class="emptycontent">
				<div class="icon icon-loading" />
				<h2>{{ t('deck', 'Loading analytics') }}</h2>
			</div>

			<div v-else-if="!board" key="notfound" class="emptycontent">
				<div class="icon icon-deck" />
				<h2>{{ t('deck', 'Board not found') }}</h2>
			</div>

			<div v-else key="analytics" class="analytics-content">
				<ReportingDashboard />
			</div>
		</transition>
	</div>
</template>

<script>
import { mapState } from 'vuex'
import { showError } from '@nextcloud/dialogs'

import ReportingDashboard from '../board/ReportingDashboard.vue'
import { createSession } from '../../sessions.js'

export default {
	name: 'EmbeddedAnalyticsBoard',
	components: {
		ReportingDashboard,
	},
	props: {
		boardId: {
			type: Number,
			required: true,
		},
	},
	data() {
		return {
			loading: true,
			session: null,
		}
	},
	computed: {
		...mapState({
			board: state => state.currentBoard,
		}),
	},
	watch: {
		boardId: {
			immediate: true,
			handler() {
				this.fetchData()
			},
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
			} catch (e) {
				console.error(e)
				showError(e)
			} finally {
				this.loading = false
			}
		},
	},
}
</script>

<style lang="scss" scoped>
@import '../../css/animations';
@import '../../css/variables';

.analytics-wrapper {
	width: 100%;
	height: 100%;
	padding: 12px;
	box-sizing: border-box;
}

.analytics-content {
	width: 100%;
}
</style>
