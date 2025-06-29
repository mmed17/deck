<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
    <NcAppSidebar
        id="card-notes"
        ref="sidebar" 
        :open="showSidebar"
        :name="title"
        v-click-outside="closeSidebar"
        @close="closeSidebar">

        <NcAppSidebarTab name="Comments" id="comments-tab">
            <template #icon>
                <Comment :size="20" />
            </template>
			<CardComments :card-id="cardId" />
        </NcAppSidebarTab>

        <NcAppSidebarTab name="Notes" id="notes-tab">
            <template #icon>
				<NoteTextOutline :size="20" />
			</template>
			<CardNotes :card-id="cardId" />
        </NcAppSidebarTab>
    </NcAppSidebar>
</template>

<script>
import NoteTextOutline from 'vue-material-design-icons/NoteTextOutline.vue';
import NcAppSidebarTab from '@nextcloud/vue/components/NcAppSidebarTab';
import Comment from 'vue-material-design-icons/CommentOutline.vue';
import NcAppSidebar from '@nextcloud/vue/components/NcAppSidebar';
import CardComments from '../card/CardComments.vue';
import CardNotes from '../card/CardNotes.vue';
import ClickOutside from 'vue-click-outside';

export default {
	name: 'CardNotesAndComments',
	components: { 
		Comment,
		NoteTextOutline,
		NcAppSidebarTab,
		NcAppSidebar,
		CardComments,
		CardNotes
	},
	directives: {
		ClickOutside
	},
	props: {
		title: {
		    type: String,
			required: true,
		},
		cardId: {
		    type: Number,
			required: true,
		}
	},
	data() {
		return {
			showSidebar: false
		};
	},
	created() {
		this.showSidebar = true;
	},
	methods: {
        closeSidebar() {
			this.showSidebar = false;
            this.$emit('close');
        }
	}
}
</script>

<style lang="scss" scoped></style>