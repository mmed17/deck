<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div class="po-wrapper" :tabindex="-1">
		<transition name="fade" mode="out-in">
			<div v-if="loading" key="loading" class="po-state">
				<div class="po-spinner" />
				<p>{{ t('deck', 'Loading permissions overview...') }}</p>
			</div>

			<div v-else-if="error" key="error" class="po-state">
				<div class="po-error-icon">⚠️</div>
				<h3>{{ t('deck', 'Failed to load permissions') }}</h3>
				<p>{{ error }}</p>
				<NcButton @click="load">{{ t('deck', 'Retry') }}</NcButton>
			</div>

			<div v-else key="content" class="po-content">
				<div class="po-header">
					<div class="po-header-text">
						<h3>{{ t('deck', 'Permissions Overview') }}</h3>
						<p class="po-muted">{{ t('deck', 'Summary of what each member is allowed to do on this board.') }}</p>
					</div>
					<div class="po-header-meta">
						<span class="po-badge">{{ t('deck', '{count} members', { count: members.length }) }}</span>
						<span v-if="isCardPolicyMode" class="po-badge po-badge--active">{{ t('deck', 'Granular Mode') }}</span>
						<span v-else class="po-badge po-badge--legacy">{{ t('deck', 'Legacy Mode') }}</span>
					</div>
				</div>

				<div class="po-table-wrapper">
					<table class="po-table">
						<thead>
							<tr>
								<th class="po-col-member">{{ t('deck', 'Member') }}</th>
								<th class="po-col-access">{{ t('deck', 'Board Access') }}</th>
								<th v-if="isCardPolicyMode" class="po-col-roles">{{ t('deck', 'Roles') }}</th>
								<th class="po-col-action">{{ t('deck', 'Move') }}</th>
								<th class="po-col-action">{{ t('deck', 'Sign') }}</th>
								<th class="po-col-action">{{ t('deck', 'Verify') }}</th>
								<th v-if="hasTransitionRules" class="po-col-transitions">{{ t('deck', 'Transitions') }}</th>
							</tr>
						</thead>
						<tbody>
							<tr v-if="members.length === 0">
								<td colspan="7" class="po-empty">{{ t('deck', 'No members found.') }}</td>
							</tr>
							<tr v-for="m in members" :key="m.uid + ':' + m.type">
								<td class="po-col-member">
									<div class="po-member">
										<div v-if="m.type === 0" class="po-avatar-user">{{ m.initials }}</div>
										<div v-else-if="m.type === 1" class="po-avatar-group">👥</div>
										<div v-else-if="m.type === 7" class="po-avatar-circle">🔗</div>
										<div class="po-member-info">
											<span class="po-member-name">{{ m.displayName }}</span>
											<span v-if="m.isOwner" class="po-owner-badge">{{ t('deck', 'Owner') }}</span>
											<span v-else-if="m.type === 1" class="po-type-badge">{{ t('deck', 'Group') }}</span>
											<span v-else-if="m.type === 7" class="po-type-badge">{{ t('deck', 'Team') }}</span>
										</div>
									</div>
								</td>
								<td class="po-col-access">
									<div class="po-access-chips">
										<span class="po-access-chip po-access-chip--read" :title="t('deck', 'Can read')">{{ t('deck', 'Read') }}</span>
										<span v-if="m.acl.edit" class="po-access-chip po-access-chip--edit" :title="t('deck', 'Can edit')">{{ t('deck', 'Edit') }}</span>
										<span v-if="m.acl.share" class="po-access-chip po-access-chip--share" :title="t('deck', 'Can share')">{{ t('deck', 'Share') }}</span>
										<span v-if="m.acl.manage" class="po-access-chip po-access-chip--manage" :title="t('deck', 'Can manage')">{{ t('deck', 'Manage') }}</span>
									</div>
								</td>
								<td v-if="isCardPolicyMode" class="po-col-roles">
									<div class="po-role-chips">
										<span v-for="role in m.roles" :key="role.roleKey" class="po-role-chip" :style="{ borderColor: role.color }">
											<span class="po-role-dot" :style="{ background: role.color }" />
											{{ role.name }}
										</span>
										<span v-if="m.roles.length === 0" class="po-dash">—</span>
									</div>
								</td>
								<td class="po-col-action">
									<span v-if="m.canMove" class="po-perm-yes" :title="t('deck', 'Allowed')">✓</span>
									<span v-else class="po-perm-no" :title="t('deck', 'Not allowed')">✗</span>
								</td>
								<td class="po-col-action">
									<span v-if="m.canSign" class="po-perm-yes" :title="t('deck', 'Allowed')">✓</span>
									<span v-else class="po-perm-no" :title="t('deck', 'Not allowed')">✗</span>
								</td>
								<td class="po-col-action">
									<span v-if="m.canVerify" class="po-perm-yes" :title="t('deck', 'Allowed')">✓</span>
									<span v-else class="po-perm-no" :title="t('deck', 'Not allowed')">✗</span>
								</td>
								<td v-if="hasTransitionRules" class="po-col-transitions">
									<span v-if="m.transitionCount > 0" class="po-transition-count" :title="t('deck', '{count} transition rules apply', { count: m.transitionCount })">
										{{ m.transitionCount }}
									</span>
									<span v-else class="po-dash">—</span>
								</td>
							</tr>
						</tbody>
					</table>
				</div>

				<div class="po-legend">
					<div class="po-legend-section">
						<strong>{{ t('deck', 'Board Access') }}:</strong>
						<span class="po-legend-item"><span class="po-access-chip po-access-chip--read">Read</span> {{ t('deck', 'View cards') }}</span>
						<span class="po-legend-item"><span class="po-access-chip po-access-chip--edit">Edit</span> {{ t('deck', 'Create/edit cards') }}</span>
						<span class="po-legend-item"><span class="po-access-chip po-access-chip--share">Share</span> {{ t('deck', 'Share board') }}</span>
						<span class="po-legend-item"><span class="po-access-chip po-access-chip--manage">Manage</span> {{ t('deck', 'Full admin') }}</span>
					</div>
					<div v-if="isCardPolicyMode" class="po-legend-section">
						<strong>{{ t('deck', 'Card Actions') }}:</strong>
						<span class="po-legend-item">{{ t('deck', 'Move — move cards between stacks') }}</span>
						<span class="po-legend-item">{{ t('deck', 'Sign — approve/sign cards') }}</span>
						<span class="po-legend-item">{{ t('deck', 'Verify — verify/mark cards done') }}</span>
					</div>
				</div>
			</div>
		</transition>
	</div>
</template>

<script>
import { mapState } from 'vuex'
import { showError } from '@nextcloud/dialogs'
import { NcButton } from '@nextcloud/vue'
import { CardPolicyApi } from '../../services/CardPolicyApi.js'
import { StackTransitionPermissionApi } from '../../services/StackTransitionPermissionApi.js'
import { createSession } from '../../sessions.js'

export default {
	name: 'EmbeddedPermissionsOverviewBoard',
	components: { NcButton },
	props: {
		boardId: {
			type: Number,
			required: true,
		},
	},
	data() {
		return {
			loading: true,
			error: '',
			session: null,
			policyApi: new CardPolicyApi(),
			transitionApi: new StackTransitionPermissionApi(),
			policyData: null,
			transitionPermissions: [],
		}
	},
	computed: {
		...mapState({
			board: state => state.currentBoard,
		}),
		isCardPolicyMode() {
			return this.policyData?.settings?.permissionMode === 'card_policy'
		},
		hasTransitionRules() {
			return this.transitionPermissions.length > 0
		},
		rolesByKey() {
			const map = {}
			if (this.policyData?.roles) {
				for (const r of this.policyData.roles) {
					map[r.roleKey] = r
				}
			}
			return map
		},
		membershipByParticipant() {
			// Map participant uid → array of { roleId, roleKey }
			const map = {}
			if (this.policyData?.memberships) {
				for (const m of this.policyData.memberships) {
					const uid = m.participant
					if (!map[uid]) map[uid] = []
					const role = this.rolesByKey[this.getRoleKeyById(m.roleId)]
					if (role) {
						map[uid].push({ roleId: m.roleId, roleKey: role.roleKey, name: role.name, color: role.color })
					}
				}
			}
			return map
		},
		defaultRoleKeysByAction() {
			const d = this.policyData?.defaultRoleKeys || {}
			return {
				move: new Set(d.move || []),
				sign: new Set(d.sign || []),
				verify: new Set(d.verify || []),
			}
		},
		cardRoleKeysByAction() {
			// Aggregate all card-specific policies to see which role keys are used for each action
			const result = { move: new Set(), sign: new Set(), verify: new Set() }
			if (this.policyData?.cards) {
				for (const card of this.policyData.cards) {
					const policy = card.effectivePolicy || card.policy || {}
					for (const key of (policy.move || [])) result.move.add(key)
					for (const key of (policy.sign || [])) result.sign.add(key)
					for (const key of (policy.verify || [])) result.verify.add(key)
				}
			}
			return result
		},
		members() {
			const memberMap = new Map()

			// 1. Board owner
			if (this.board?.owner) {
				const ownerUid = this.board.owner.uid || this.board.owner.primaryKey
				if (ownerUid) {
					memberMap.set(ownerUid + ':0', {
						uid: ownerUid,
						displayName: this.board.owner.displayname || this.board.owner.displayName || ownerUid,
						type: 0,
						isOwner: true,
						initials: this.getInitials(this.board.owner.displayname || ownerUid),
						acl: { read: true, edit: true, share: true, manage: true },
						roles: this.membershipByParticipant[ownerUid] || [],
						canMove: true,
						canSign: true,
						canVerify: true,
						transitionCount: 0,
					})
				}
			}

			// 2. ACL participants
			if (this.board?.acl) {
				for (const acl of this.board.acl) {
					const uid = acl.participant?.uid
					if (!uid) continue
					const key = uid + ':' + acl.type
					if (memberMap.has(key)) continue

					const userRoles = this.membershipByParticipant[uid] || []
					const userRoleKeys = new Set(userRoles.map(r => r.roleKey))
					const defaults = this.defaultRoleKeysByAction
					const cardOverrides = this.cardRoleKeysByAction

					memberMap.set(key, {
						uid,
						displayName: acl.participant?.displayname || uid,
						type: acl.type,
						isOwner: false,
						initials: this.getInitials(acl.participant?.displayname || uid),
						acl: {
							read: true,
							edit: !!acl.permissionEdit,
							share: !!acl.permissionShare,
							manage: !!acl.permissionManage,
						},
						roles: userRoles,
						canMove: this.userCanAction(userRoleKeys, defaults.move, cardOverrides.move, !!acl.permissionEdit),
						canSign: this.userCanAction(userRoleKeys, defaults.sign, cardOverrides.sign, !!acl.permissionEdit),
						canVerify: this.userCanAction(userRoleKeys, defaults.verify, cardOverrides.verify, !!acl.permissionEdit),
						transitionCount: this.countTransitionsForParticipant(uid, acl.type),
					})
				}
			}

			// 3. Card policy members not yet in the map
			if (this.policyData?.memberships) {
				for (const m of this.policyData.memberships) {
					const uid = m.participant
					if (!uid) continue
					// Check if already in map (by any type)
					let found = false
					for (const [, existing] of memberMap) {
						if (existing.uid === uid) {
							found = true
							break
						}
					}
					if (found) continue

					const userRoles = this.membershipByParticipant[uid] || []
					const userRoleKeys = new Set(userRoles.map(r => r.roleKey))
					const defaults = this.defaultRoleKeysByAction
					const cardOverrides = this.cardRoleKeysByAction

					memberMap.set(uid + ':0', {
						uid,
						displayName: uid,
						type: 0,
						isOwner: false,
						initials: this.getInitials(uid),
						acl: { read: true, edit: false, share: false, manage: false },
						roles: userRoles,
						canMove: this.userCanAction(userRoleKeys, defaults.move, cardOverrides.move, false),
						canSign: this.userCanAction(userRoleKeys, defaults.sign, cardOverrides.sign, false),
						canVerify: this.userCanAction(userRoleKeys, defaults.verify, cardOverrides.verify, false),
						transitionCount: this.countTransitionsForParticipant(uid, 0),
					})
				}
			}

			// 4. Transition-only participants not yet in the map
			for (const tp of this.transitionPermissions) {
				const uid = tp.participant
				if (!uid) continue
				let found = false
				for (const [, existing] of memberMap) {
					if (existing.uid === uid) {
						found = true
						break
					}
				}
				if (found) continue

				memberMap.set(uid + ':' + tp.participantType, {
					uid,
					displayName: uid,
					type: tp.participantType || 0,
					isOwner: false,
					initials: this.getInitials(uid),
					acl: { read: true, edit: false, share: false, manage: false },
					roles: [],
					canMove: false,
					canSign: false,
					canVerify: false,
					transitionCount: this.countTransitionsForParticipant(uid, tp.participantType || 0),
				})
			}

			return Array.from(memberMap.values()).sort((a, b) => {
				if (a.isOwner) return -1
				if (b.isOwner) return 1
				return (a.displayName || '').localeCompare(b.displayName || '')
			})
		},
	},
	watch: {
		boardId: {
			immediate: true,
			handler() {
				this.load()
			},
		},
	},
	beforeDestroy() {
		this.session?.close?.()
	},
	methods: {
		async load() {
			this.loading = true
			this.error = ''
			try {
				this.session?.close?.()
				this.session = createSession(this.boardId)

				await this.$store.dispatch('loadBoardById', this.boardId)

				const [policy, transitions] = await Promise.all([
					this.policyApi.getBoardPolicy(this.boardId).catch(() => null),
					this.transitionApi.getTransitionPermissions(this.boardId).catch(() => []),
				])

				this.policyData = policy
				this.transitionPermissions = Array.isArray(transitions) ? transitions : (transitions?.permissions || transitions?.data || [])
			} catch (e) {
				console.error(e)
				this.error = e?.message || 'Error loading permissions overview'
			} finally {
				this.loading = false
			}
		},
		getRoleKeyById(roleId) {
			if (this.policyData?.roles) {
				const role = this.policyData.roles.find(r => r.id === roleId)
				return role?.roleKey || ''
			}
			return ''
		},
		userCanAction(userRoleKeys, defaultKeys, cardOverrideKeys, hasEditAcl) {
			if (!this.isCardPolicyMode) {
				return hasEditAcl
			}
			// Check if user's roles appear in defaults for this action
			for (const key of userRoleKeys) {
				if (defaultKeys.has(key)) return true
			}
			// Check if user's roles appear in any card override for this action
			for (const key of userRoleKeys) {
				if (cardOverrideKeys.has(key)) return true
			}
			return false
		},
		countTransitionsForParticipant(uid, participantType) {
			let count = 0
			for (const tp of this.transitionPermissions) {
				if (tp.participant === uid && (tp.participantType === undefined || tp.participantType === participantType)) {
					count++
				}
			}
			return count
		},
		getInitials(name) {
			if (!name) return '?'
			const parts = String(name).trim().split(/\s+/)
			if (parts.length >= 2) {
				return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase()
			}
			return name.substring(0, 2).toUpperCase()
		},
	},
}
</script>

<style scoped>
.po-wrapper {
	width: 100%;
	min-height: 300px;
}

.po-state {
	display: flex;
	flex-direction: column;
	align-items: center;
	justify-content: center;
	padding: 48px 24px;
	text-align: center;
	color: var(--color-text-maxcontrast);
}

.po-spinner {
	width: 28px;
	height: 28px;
	border-radius: 50%;
	border: 3px solid var(--color-border);
	border-top-color: var(--color-primary-element);
	animation: po-spin 0.9s linear infinite;
	margin-bottom: 12px;
}

@keyframes po-spin {
	to { transform: rotate(360deg); }
}

.po-error-icon {
	font-size: 32px;
	margin-bottom: 12px;
}

.po-state h3 {
	margin: 0 0 8px;
	color: var(--color-main-text);
}

.po-state p {
	margin: 0 0 16px;
}

.po-content {
	display: flex;
	flex-direction: column;
	gap: 0;
}

.po-header {
	display: flex;
	justify-content: space-between;
	align-items: flex-start;
	padding: 20px 24px;
	border-bottom: 1px solid var(--color-border);
	gap: 16px;
	flex-wrap: wrap;
}

.po-header-text h3 {
	margin: 0 0 4px;
	font-size: 16px;
	font-weight: 700;
	color: var(--color-main-text);
}

.po-muted {
	margin: 0;
	font-size: 13px;
	color: var(--color-text-maxcontrast);
}

.po-header-meta {
	display: flex;
	gap: 8px;
	align-items: center;
	flex-shrink: 0;
}

.po-badge {
	font-size: 11px;
	font-weight: 700;
	padding: 3px 10px;
	border-radius: 999px;
	border: 1px solid #ccc;
	background: #f0f0f0;
	color: #555;
}

.po-badge--active {
	border-color: #1a7a3a;
	color: #fff;
	background: #1a7a3a;
}

.po-badge--legacy {
	border-color: #b08500;
	color: #fff;
	background: #b08500;
}

.po-table-wrapper {
	overflow-x: auto;
}

.po-table {
	width: 100%;
	border-collapse: collapse;
	min-width: 700px;
}

.po-table th {
	padding: 10px 16px;
	font-size: 11px;
	font-weight: 700;
	text-transform: uppercase;
	letter-spacing: 0.5px;
	color: var(--color-text-maxcontrast);
	border-bottom: 2px solid var(--color-border);
	text-align: left;
	white-space: nowrap;
	background: var(--color-background-hover);
}

.po-table td {
	padding: 12px 16px;
	border-bottom: 1px solid var(--color-border);
	vertical-align: middle;
}

.po-table tbody tr:hover {
	background: var(--color-background-hover);
}

.po-empty {
	text-align: center;
	padding: 32px;
	color: var(--color-text-maxcontrast);
}

/* Member column */
.po-member {
	display: flex;
	align-items: center;
	gap: 10px;
}

.po-avatar-user,
.po-avatar-group,
.po-avatar-circle {
	width: 32px;
	height: 32px;
	border-radius: 50%;
	display: flex;
	align-items: center;
	justify-content: center;
	font-size: 12px;
	font-weight: 700;
	flex-shrink: 0;
}

.po-avatar-user {
	background: var(--color-primary-element);
	color: var(--color-primary-text);
}

.po-avatar-group,
.po-avatar-circle {
	background: var(--color-background-dark);
	font-size: 14px;
}

.po-member-info {
	display: flex;
	flex-direction: column;
	gap: 2px;
	min-width: 0;
}

.po-member-name {
	font-weight: 600;
	font-size: 14px;
	color: var(--color-main-text);
	overflow: hidden;
	text-overflow: ellipsis;
	white-space: nowrap;
}

.po-owner-badge {
	font-size: 9px;
	font-weight: 700;
	padding: 2px 6px;
	border-radius: 999px;
	background: #006fa0;
	color: #fff;
	text-transform: uppercase;
	letter-spacing: 0.3px;
}

.po-type-badge {
	font-size: 10px;
	color: #888;
}

/* Board Access chips */
.po-access-chips {
	display: flex;
	flex-wrap: wrap;
	gap: 4px;
}

.po-access-chip {
	font-size: 10px;
	font-weight: 700;
	padding: 3px 8px;
	border-radius: 999px;
	text-transform: uppercase;
	letter-spacing: 0.3px;
}

.po-access-chip--read {
	background: #006fa0;
	color: #fff;
}

.po-access-chip--edit {
	background: #1a7a3a;
	color: #fff;
}

.po-access-chip--share {
	background: #b08500;
	color: #fff;
}

.po-access-chip--manage {
	background: #c0392b;
	color: #fff;
}

/* Role chips */
.po-role-chips {
	display: flex;
	flex-wrap: wrap;
	gap: 4px;
}

.po-role-chip {
	display: inline-flex;
	align-items: center;
	gap: 4px;
	padding: 3px 10px;
	border-radius: 999px;
	border: 2px solid var(--color-border-dark, #ccc);
	font-size: 11px;
	font-weight: 700;
	background: var(--color-background-hover, #f5f5f5);
	color: var(--color-main-text);
}

.po-role-dot {
	width: 7px;
	height: 7px;
	border-radius: 50%;
	flex-shrink: 0;
}

/* Action columns */
.po-col-action {
	text-align: center;
	width: 60px;
}

.po-perm-yes {
	display: inline-flex;
	align-items: center;
	justify-content: center;
	width: 24px;
	height: 24px;
	border-radius: 50%;
	background: #2d7d46;
	color: #fff;
	font-weight: 900;
	font-size: 14px;
}

.po-perm-no {
	display: inline-flex;
	align-items: center;
	justify-content: center;
	width: 24px;
	height: 24px;
	border-radius: 50%;
	background: #e0e0e0;
	color: #888;
	font-weight: 900;
	font-size: 14px;
}

.po-dash {
	color: var(--color-border);
	font-weight: 700;
}

/* Transitions */
.po-transition-count {
	display: inline-flex;
	align-items: center;
	justify-content: center;
	min-width: 22px;
	height: 22px;
	padding: 0 6px;
	border-radius: 999px;
	background: var(--color-primary-element);
	color: var(--color-primary-text);
	font-size: 11px;
	font-weight: 700;
}

/* Legend */
.po-legend {
	padding: 16px 24px;
	border-top: 1px solid var(--color-border);
	background: var(--color-background-hover);
	display: flex;
	flex-direction: column;
	gap: 8px;
}

.po-legend-section {
	display: flex;
	flex-wrap: wrap;
	gap: 12px;
	align-items: center;
	font-size: 12px;
	color: var(--color-text-maxcontrast);
}

.po-legend-section strong {
	color: var(--color-main-text);
	font-size: 11px;
	text-transform: uppercase;
	letter-spacing: 0.3px;
}

.po-legend-item {
	display: inline-flex;
	align-items: center;
	gap: 4px;
}

/* Transitions */
.fade-enter-active,
.fade-leave-active {
	transition: opacity 0.2s ease;
}

.fade-enter,
.fade-leave-to {
	opacity: 0;
}

@media (max-width: 800px) {
	.po-header {
		flex-direction: column;
	}

	.po-col-transitions {
		display: none;
	}
}
</style>
