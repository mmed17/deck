/**
 * Embedded Deck UIs (without app navigation).
 *
 * Exposes:
 * - window.OCA.Deck.EmbeddedTasks.mount({ el, boardId })
 * - window.OCA.Deck.EmbeddedAnalytics.mount({ el, boardId })
 */

import Vue from 'vue'
import { BoardApi } from './services/BoardApi.js'
import store from './store/main.js'
import './shared-init.js'
import './models/index.js'

import EmbeddedTasksRoot from './components/embedded/EmbeddedTasksRoot.vue'
import EmbeddedAnalyticsRoot from './components/embedded/EmbeddedAnalyticsRoot.vue'

if (!window.OCA) {
	window.OCA = {}
}
if (!window.OCA.Deck) {
	window.OCA.Deck = {}
}

Vue.prototype.t = t
Vue.prototype.n = n
Vue.prototype.OC = OC

function mountRoot(RootComponent, { name, el, boardId }) {
	if (!el) {
		throw new Error(`${name}.mount: missing el`)
	}
	const initialBoardId = Number(boardId)
	if (!Number.isFinite(initialBoardId) || initialBoardId <= 0) {
		throw new Error(`${name}.mount: invalid boardId`)
	}

	// Embedded context: no app navigation and we manage card details ourselves (if needed).
	store.commit('setFullApp', false)

	const mountPoint = document.createElement('div')
	el.appendChild(mountPoint)

	const boardApi = new BoardApi()

	const vm = new Vue({
		store,
		provide: {
			boardApi,
		},
		data() {
			return {
				currentBoardId: initialBoardId,
			}
		},
		render(h) {
			return h(RootComponent, {
				props: {
					boardId: this.currentBoardId,
				},
			})
		},
	}).$mount(mountPoint)

	return {
		setBoardId(nextBoardId) {
			const id = Number(nextBoardId)
			if (!Number.isFinite(id) || id <= 0) {
				return
			}
			vm.currentBoardId = id
		},
		destroy() {
			try {
				vm.$destroy()
			} finally {
				mountPoint.remove()
			}
		},
	}
}

window.OCA.Deck.EmbeddedTasks = {
	mount({ el, boardId }) {
		return mountRoot(EmbeddedTasksRoot, { name: 'EmbeddedTasks', el, boardId })
	},
}

window.OCA.Deck.EmbeddedAnalytics = {
	mount({ el, boardId }) {
		return mountRoot(EmbeddedAnalyticsRoot, { name: 'EmbeddedAnalytics', el, boardId })
	},
}
