/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'

export class StackTransitionPermissionApi {
    url(url) {
        url = `/apps/deck${url}`
        return generateUrl(url)
    }

    /**
     * Get all transition permissions for a board
     * @param {number} boardId
     * @returns {Promise}
     */
    getTransitionPermissions(boardId) {
        return axios.get(this.url(`/boards/${boardId}/transition-permissions`))
            .then(
                (response) => {
                    // Handle both OCS and regular response formats
                    const data = response.data.ocs?.data || response.data
                    return Promise.resolve(data)
                },
                (err) => {
                    return Promise.reject(err)
                },
            )
            .catch((err) => {
                return Promise.reject(err)
            })
    }

    /**
     * Add a new transition permission
     * @param {number} boardId
     * @param {object} data - Permission data
     * @returns {Promise}
     */
    addTransitionPermission(boardId, data) {
        return axios.post(this.url(`/boards/${boardId}/transition-permissions`), data)
            .then(
                (response) => {
                    const data = response.data.ocs?.data || response.data
                    return Promise.resolve(data)
                },
                (err) => {
                    return Promise.reject(err)
                },
            )
            .catch((err) => {
                return Promise.reject(err)
            })
    }

    /**
     * Delete a transition permission
     * @param {number} id
     * @returns {Promise}
     */
    deleteTransitionPermission(id) {
        return axios.delete(this.url(`/transition-permissions/${id}`))
            .then(
                (response) => {
                    return Promise.resolve(response.data)
                },
                (err) => {
                    return Promise.reject(err)
                },
            )
            .catch((err) => {
                return Promise.reject(err)
            })
    }

    /**
     * Check if a card can be moved between stacks
     * This is a client-side helper that calls the existing reorder endpoint
     * The backend will throw an error if the transition is not allowed
     * @param {number} cardId
     * @param {number} stackId
     * @param {number} order
     * @returns {Promise}
     */
    checkTransitionPermission(cardId, stackId, order) {
        // The permission check happens server-side in the reorder endpoint
        // This is just a placeholder for future client-side validation
        return Promise.resolve(true)
    }
}
