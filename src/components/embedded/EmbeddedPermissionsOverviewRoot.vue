<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div class="deck-embedded-permissions-overview">
		<EmbeddedPermissionsOverviewBoard :board-id="boardId" />
	</div>
</template>

<script>
import EmbeddedPermissionsOverviewBoard from './EmbeddedPermissionsOverviewBoard.vue'

export default {
	name: 'EmbeddedPermissionsOverviewRoot',
	components: {
		EmbeddedPermissionsOverviewBoard,
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
.deck-embedded-permissions-overview {
	width: 100%;
	height: 100%;
}
</style>
