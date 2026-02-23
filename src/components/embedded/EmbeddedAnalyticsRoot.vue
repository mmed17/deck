<!--
  - SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div class="deck-embedded-analytics">
		<EmbeddedAnalyticsBoard :board-id="boardId" />
	</div>
</template>

<script>
import EmbeddedAnalyticsBoard from './EmbeddedAnalyticsBoard.vue'

export default {
	name: 'EmbeddedAnalyticsRoot',
	components: {
		EmbeddedAnalyticsBoard,
	},
	props: {
		boardId: {
			type: Number,
			required: true,
		},
	},
}
</script>

<style scoped>
.deck-embedded-analytics {
	width: 100%;
	height: 100%;
}
</style>
