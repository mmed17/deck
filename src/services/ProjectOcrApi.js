/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'

export class ProjectOcrApi {

	headers() {
		return {
			'OCS-APIRequest': 'true',
			'Content-Type': 'application/json',
		}
	}

	async getProjectByBoard(boardId) {
		try {
			const response = await axios.get(generateUrl(`/apps/projectcreatoraio/api/v1/projects/board/${boardId}`), {
				headers: this.headers(),
			})
			return response?.data ?? null
		} catch (e) {
			if (e?.response?.status === 404) {
				return null
			}
			throw e
		}
	}

	async listProjectDocumentTypes(projectId) {
		const response = await axios.get(generateUrl(`/apps/projectcreatoraio/api/v1/projects/${projectId}/ocr/document-types`), {
			headers: this.headers(),
		})
		return response?.data?.document_types ?? []
	}

	async getFileProcessing(projectId, fileId) {
		try {
			const response = await axios.get(generateUrl(`/apps/projectcreatoraio/api/v1/projects/${projectId}/files/${fileId}/ocr`), {
				headers: this.headers(),
			})
			return response?.data ?? null
		} catch (e) {
			if (e?.response?.status === 404) {
				return null
			}
			throw e
		}
	}

	async assignFileDocumentType(projectId, fileId, documentTypeId) {
		const response = await axios.put(generateUrl(`/apps/projectcreatoraio/api/v1/projects/${projectId}/files/${fileId}/ocr/document-type`), {
			document_type_id: documentTypeId,
		}, {
			headers: this.headers(),
		})
		return response?.data ?? null
	}

	async uploadCardAttachment(projectId, cardId, documentTypeId, file, onUploadProgress, storageScope = 'shared') {
		const bodyFormData = new FormData()
		bodyFormData.append('document_type_id', String(documentTypeId))
		bodyFormData.append('storage_scope', String(storageScope || 'shared'))
		bodyFormData.append('file', file)
		const response = await axios.post(
			generateUrl(`/apps/projectcreatoraio/api/v1/projects/${projectId}/cards/${cardId}/ocr/attachments`),
			bodyFormData,
			{
				headers: {
					'OCS-APIRequest': 'true',
				},
				onUploadProgress,
			},
		)
		return response?.data ?? null
	}

	async finalizeCardAttachment(projectId, cardId, processingId, fields) {
		const response = await axios.post(generateUrl(`/apps/projectcreatoraio/api/v1/projects/${projectId}/cards/${cardId}/ocr/attachments/finalize`), {
			processing_id: processingId,
			fields,
		}, {
			headers: this.headers(),
		})
		return response?.data ?? null
	}

	async reprocessFileProcessing(projectId, fileId) {
		const response = await axios.post(generateUrl(`/apps/projectcreatoraio/api/v1/projects/${projectId}/files/${fileId}/ocr/reprocess`), {}, {
			headers: this.headers(),
		})
		return response?.data ?? null
	}

	async updateFileExtractedFields(projectId, fileId, fields) {
		const response = await axios.put(generateUrl(`/apps/projectcreatoraio/api/v1/projects/${projectId}/files/${fileId}/ocr/extracted`), {
			fields,
		}, {
			headers: this.headers(),
		})
		return response?.data ?? null
	}

}
