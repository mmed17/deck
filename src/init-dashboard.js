/**
 * SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import './css/dashboard.scss'

import './shared-init.js'

const debug = process.env.NODE_ENV !== 'production'

let _imports = null

const getAsyncImports = async () => {
	if (_imports) {
		return _imports
	}

	const { default: Vue } = await import('vue')
	const { default: Vuex } = await import('vuex')
	const { default: dashboard } = await import('./store/dashboard.js')

	Vue.prototype.t = t
	Vue.prototype.n = n
	Vue.prototype.OC = OC
	Vue.use(Vuex)

	const store = new Vuex.Store({
		modules: {
			dashboard,
		},
		strict: debug,
	})

	_imports = {
		store, Vue,
	}

	return _imports
}

document.addEventListener('DOMContentLoaded', async () => {

	const { store } = await getAsyncImports()
	document.addEventListener('projectcreatoraio:project-selected', (event) => {
        const project = event.detail;
        const boardId = project ? project.boardId : null;
        store.commit('setSelectedBoardId', boardId);
    })

	OCA.Dashboard.register('deckUpcoming', async (el) => {
		const { Vue, store } = await getAsyncImports()
		const { default: DashboardUpcoming } = await import('./views/DashboardUpcoming.vue')

		const View = Vue.extend(DashboardUpcoming)
		const vm = new View({
			propsData: {},
			store,
		}).$mount(el)
		return vm
	})

	OCA.Dashboard.register('deckOpen', async (el) => {
		const { Vue, store } = await getAsyncImports()
		const { default: DashboardOpen } = await import('./views/DashboardOpen.vue')
		const View = Vue.extend(DashboardOpen)
		const vm = new View({
			propsData: {},
			store,
		}).$mount(el)
		return vm
	})

	OCA.Dashboard.register('deckOverdue', async (el) => {
		const { Vue, store } = await getAsyncImports()
		const { default: DashboardOverdue } = await import('./views/DashboardOverdue.vue')
		const View = Vue.extend(DashboardOverdue)
		const vm = new View({
			propsData: {},
			store,
		}).$mount(el)
		return vm
	})
})
