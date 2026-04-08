/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

global.t = (_app, text) => text

const AttachmentList = require('./AttachmentList.vue').default

describe('AttachmentList storage tabs', () => {
	it('filters attachments by storage scope when tabs are enabled', () => {
		const attachments = [
			{ id: 1, type: 'file' },
			{ id: 2, type: 'project_private_file' },
			{ id: 3, type: 'deck_file' },
		]

		const vm = {
			storageTabsEnabled: true,
			activeStorageTab: 'private',
			attachments,
			attachmentStorageScope: AttachmentList.methods.attachmentStorageScope,
		}

		expect(AttachmentList.computed.visibleAttachments.call(vm)).toEqual([attachments[1]])
		vm.activeStorageTab = 'shared'
		expect(AttachmentList.computed.visibleAttachments.call(vm)).toEqual([attachments[0], attachments[2]])
	})

	it('keeps the existing single-list behavior when tabs are disabled', () => {
		const attachments = [
			{ id: 1, type: 'file' },
			{ id: 2, type: 'project_private_file' },
		]

		const vm = {
			storageTabsEnabled: false,
			activeStorageTab: 'private',
			attachments,
			attachmentStorageScope: AttachmentList.methods.attachmentStorageScope,
		}

		expect(AttachmentList.computed.visibleAttachments.call(vm)).toEqual(attachments)
		expect(AttachmentList.computed.showShareFromFilesAction.call(vm)).toBe(true)
	})
})
