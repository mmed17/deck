/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import Color from './color.js'

export default {
	mixins: [Color],
	computed: {
		labelStyle() {
			return (label) => {
				const title = String(label?.title ?? '').trim().toLowerCase()
				const importantTitles = ['important', 'kritieke processtap', 'belangrijk']
				if (importantTitles.includes(title)) {
					return {
						backgroundColor: '#ffffff',
						color: 'var(--color-error-text)',
						border: '1px solid var(--color-error-text)',
					}
				}
				return {
					backgroundColor: '#' + label.color,
					color: this.textColor(label.color),
				}
			}
		},
	},
}
