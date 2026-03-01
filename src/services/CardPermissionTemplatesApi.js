/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'

export class CardPermissionTemplatesApi {
	url(url) {
		return generateUrl(`/apps/deck${url}`)
	}

	headers() {
		return {
			Accept: 'application/json',
			'OCS-APIRequest': 'true',
			'Content-Type': 'application/json',
		}
	}

	async list(boardId) {
		const response = await axios.get(this.url('/card-policy/templates'), {
			params: { boardId: Number(boardId) },
			headers: this.headers(),
		})
		return response.data?.ocs?.data || response.data
	}

	async createFromBoard(boardId, name) {
		const response = await axios.post(this.url('/card-policy/templates/from-board'), {
			boardId: Number(boardId),
			name,
		}, { headers: this.headers() })
		return response.data?.ocs?.data || response.data
	}

	async get(templateId, boardId) {
		const response = await axios.get(this.url(`/card-policy/templates/${Number(templateId)}`), {
			params: { boardId: Number(boardId) },
			headers: this.headers(),
		})
		return response.data?.ocs?.data || response.data
	}

	async delete(templateId, boardId = null) {
		const params = {}
		if (boardId !== null && boardId !== undefined && Number(boardId) > 0) {
			params.boardId = Number(boardId)
		}
		const response = await axios.delete(this.url(`/card-policy/templates/${Number(templateId)}`), { params, headers: this.headers() })
		return response.data?.ocs?.data || response.data
	}
}
