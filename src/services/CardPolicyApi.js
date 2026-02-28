/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'

export class CardPolicyApi {
	url(url) {
		return generateUrl(`/apps/deck${url}`)
	}

	getBoardPolicy(boardId) {
		return axios.get(this.url(`/boards/${boardId}/card-policy`)).then((response) => {
			return response.data?.ocs?.data || response.data
		})
	}

	enable(boardId) {
		return axios.post(this.url(`/boards/${boardId}/card-policy/enable`)).then((response) => {
			return response.data?.ocs?.data || response.data
		})
	}

	updateSettings(boardId, payload) {
		return axios.put(this.url(`/boards/${boardId}/card-policy/settings`), payload).then((response) => {
			return response.data?.ocs?.data || response.data
		})
	}

	updateDefaults(boardId, payload) {
		return axios.put(this.url(`/boards/${boardId}/card-policy/defaults`), payload).then((response) => {
			return response.data?.ocs?.data || response.data
		})
	}

	setCardPolicy(boardId, cardId, payload) {
		return axios.put(this.url(`/boards/${boardId}/card-policy/cards/${cardId}`), payload).then((response) => {
			return response.data?.ocs?.data || response.data
		})
	}

	clearCardPolicy(boardId, cardId) {
		return axios.delete(this.url(`/boards/${boardId}/card-policy/cards/${cardId}`)).then((response) => {
			return response.data?.ocs?.data || response.data
		})
	}

	addMembership(boardId, payload) {
		return axios.post(this.url(`/boards/${boardId}/card-policy/memberships`), payload).then((response) => {
			return response.data?.ocs?.data || response.data
		})
	}

	deleteMembership(boardId, membershipId) {
		return axios.delete(this.url(`/boards/${boardId}/card-policy/memberships/${membershipId}`)).then((response) => {
			return response.data?.ocs?.data || response.data
		})
	}

	createRole(boardId, payload) {
		return axios.post(this.url(`/boards/${boardId}/card-policy/roles`), payload).then((response) => {
			return response.data?.ocs?.data || response.data
		})
	}

	updateRole(boardId, roleId, payload) {
		return axios.put(this.url(`/boards/${boardId}/card-policy/roles/${roleId}`), payload).then((response) => {
			return response.data?.ocs?.data || response.data
		})
	}

	deleteRole(boardId, roleId) {
		return axios.delete(this.url(`/boards/${boardId}/card-policy/roles/${roleId}`)).then((response) => {
			return response.data?.ocs?.data || response.data
		})
	}
}
