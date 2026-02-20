/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'

export class ProjectService {
    /**
     * Get project details by board ID
     *
     * @param {number} boardId
     * @return {Promise}
     */
    getProjectByBoardId(boardId) {
        return axios.get(generateUrl(`/apps/projectcreatoraio/api/v1/projects/board/${boardId}`))
            .then((response) => {
                return Promise.resolve(response.data)
            })
            .catch((err) => {
                return Promise.reject(err)
            })
    }

    /**
     * Get timeline items for a project
     *
     * @param {number} projectId
     * @return {Promise}
     */
    getTimelineByProjectId(projectId) {
        return axios.get(generateUrl(`/apps/projectcreatoraio/api/v1/projects/${projectId}/timeline`))
            .then((response) => {
                return Promise.resolve(response.data)
            })
            .catch((err) => {
                return Promise.reject(err)
            })
    }
}
