/**
 * SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import Vue from 'vue'
import { generateUrl } from '@nextcloud/router'

import CardCreateDialog from './CardCreateDialog.vue'
import { buildSelector } from './helpers/selector.js'
import { showError } from './helpers/errors.js'
import { ProjectService } from './services/ProjectService.js'
import './init-collections.js'

import './shared-init.js'

const projectService = new ProjectService()

Vue.prototype.t = t
Vue.prototype.n = n
Vue.prototype.OC = OC

window.addEventListener('DOMContentLoaded', () => {
	if (!window.OCA?.Talk?.registerMessageAction) {
		return
	}

	window.OCA.Talk.registerMessageAction({
		label: t('deck', 'Create a card'),
		icon: 'icon-deck',
		async callback({ message: { message, messageParameters, actorDisplayName }, metadata: { name: conversationName, token: conversationToken } }) {
			let fixedBoardId = null

			if (conversationToken) {
				try {
					const project = await projectService.getProjectByTalkConversationToken(conversationToken)
					const projectBoardId = Number(project?.boardId)

					if (Number.isInteger(projectBoardId) && projectBoardId > 0) {
						fixedBoardId = projectBoardId
					}
				} catch (error) {
					if (error?.response?.status !== 404) {
						console.debug('Could not resolve project context for Talk conversation', error)
						showError(new Error(t('deck', 'Could not resolve the related project board for this conversation.')))
						return
					}
				}
			}

			const parsedMessage = message.replace(/{[a-z0-9-_]+}/gi, function(parameter) {
				const parameterName = parameter.substr(1, parameter.length - 2)

				if (messageParameters[parameterName]) {
					if (messageParameters[parameterName].type === 'file' && messageParameters[parameterName].path) {
						return messageParameters[parameterName].path
					}
					if (messageParameters[parameterName].type === 'user' || messageParameters[parameterName].type === 'call') {
						return '@' + messageParameters[parameterName].name
					}
					if (messageParameters[parameterName].name) {
						return messageParameters[parameterName].name
					}
				}

				// Do not replace so insert with curly braces again
				return parameter
			})

			const shortenedMessageCandidate = parsedMessage.replace(/^(.{255}[^\s]*).*/, '$1')
			const shortenedMessage = shortenedMessageCandidate === '' ? parsedMessage.substr(0, 255) : shortenedMessageCandidate
			try {
				await buildSelector(CardCreateDialog, {
					props: {
						title: shortenedMessage,
						fixedBoardId,
						description: parsedMessage + '\n\n' + '['
							+ t('deck', 'Message from {author} in {conversationName}', {
								author: actorDisplayName,
								conversationName,
							})
							+ '](' + window.location.protocol + '//' + window.location.host + generateUrl('/call/' + conversationToken) + ')',
					},
				})
			} catch (e) {
				console.debug('Card creation dialog was canceled')
			}
		},
	})
})
