/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import Vue from 'vue'
import Vuex from 'vuex'
import { OverviewApi } from '../services/OverviewApi.js'

Vue.use(Vuex)

const apiClient = new OverviewApi()

export default {
	state: {
		assignedCards: [],
		selectedBoardId: null,
	},
	getters: {
		assignedCardsDashboard: state => {
			return Object.values(state.assignedCards).flat()
		},
		selectedBoardIdDashboard: state => {
			return state.selectedBoardId;
		}
	},
	mutations: {
		setAssignedCards(state, assignedCards) {
			state.assignedCards = assignedCards
		},
		setSelectedBoardId(state, boardId) {
			state.selectedBoardId = boardId;
		}
	},
	actions: {
		async loadUpcoming({ commit, state }) {
            const params = {};

            if (state.selectedBoardId) {
                params.boardId = state.selectedBoardId;
            }

            const upcommingCards = await apiClient.get('upcoming', params)
            commit('setAssignedCards', upcommingCards)
        },
	},
}
