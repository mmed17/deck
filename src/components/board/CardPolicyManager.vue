<template>
	<div class="card-policy-manager">
		<div class="header">
			<h4>{{ t('deck', 'Card Permissions') }}</h4>
			<p class="description">
				{{ t('deck', 'Control who can move cards and who can approve/mark them done, based on board roles and per-card rules.') }}
			</p>
		</div>

		<div v-if="loading" class="muted">{{ t('deck', 'Loading…') }}</div>
		<div v-else-if="error" class="muted error">{{ error }}</div>

		<div v-else>
			<div v-if="settings.permissionMode !== 'card_policy'" class="enable-box">
				<p class="muted">
					{{ t('deck', 'This board is still using legacy permissions. Enable card-based permissions to activate role-based rules.') }}
				</p>
				<NcButton type="primary" @click="enableMode">
					{{ t('deck', 'Enable card-based permissions') }}
				</NcButton>
			</div>

			<div v-else>
				<div class="section">
					<h5>{{ t('deck', 'Approval Column') }}</h5>
					<div class="row">
						<div class="field">
							<label>{{ t('deck', 'Approved/Done stack') }}</label>
							<NcSelect
								v-model="approvedStackSelection"
								:options="stackOptions"
								label="title"
								track-by="id"
								:clearable="false" />
						</div>
						<div class="actions">
							<NcButton type="secondary" @click="saveApprovedStack">
								{{ t('deck', 'Save') }}
							</NcButton>
						</div>
					</div>
					<p class="muted">
						{{ t('deck', 'Only approvers can move cards into or out of this stack and mark them done/undone.') }}
					</p>
				</div>

				<div class="section">
					<div class="pc-rules-header">
						<div>
							<h5>{{ t('deck', 'Board Roles & Members') }}</h5>
							<p class="muted">{{ t('deck', 'Define who belongs to which role on this board.') }}</p>
						</div>
					</div>

					<div class="pc-role-legend">
						<span v-for="role in roles" :key="role.id" class="pc-chip" :style="chipStyleByKey(role.roleKey)">
							<span class="pc-dot" :style="{ background: role.color }" />
							{{ role.name }}
							<span class="muted">({{ role.roleKey }})</span>
						</span>
					</div>

					<div class="pc-toolbar pc-toolbar--add-member">
						<div class="pc-flex-1">
							<NcSelect
								v-model="newMembership.user"
								:options="userOptions"
								:placeholder="t('deck', 'Select a member…')"
								label="label"
								track-by="value" />
						</div>
						<div class="pc-flex-1">
							<NcSelect
								v-model="newMembership.role"
								:options="roleOptions"
								:placeholder="t('deck', 'Assign to role…')"
								label="label"
								track-by="value" />
						</div>
						<NcButton type="primary" :disabled="!canAddMembership" @click="addMembership">
							{{ t('deck', 'Add member') }}
						</NcButton>
					</div>

					<div v-if="memberships.length" class="pc-grid-wrap">
						<div class="pc-grid-table">
							<div class="pc-grid-thead pc-grid-thead--members">
								<div class="pc-th">{{ t('deck', 'Member') }}</div>
								<div class="pc-th">{{ t('deck', 'Role') }}</div>
								<div class="pc-th pc-th-actions">{{ t('deck', 'Actions') }}</div>
							</div>
							<div class="pc-grid-tbody">
								<div v-for="m in memberships" :key="m.id" class="pc-grid-tr pc-grid-tr--members">
									<div class="pc-td pc-member-name">{{ getMemberDisplayName(m.participant) }}</div>
									<div class="pc-td">
										<span class="pc-chip" :style="chipStyleByRoleId(m.roleId)">
											<span class="pc-dot" :style="{ background: roleColorById(m.roleId) }" />
											{{ roleNameById[m.roleId] || m.roleId }}
										</span>
									</div>
									<div class="pc-td pc-td-actions">
										<NcButton type="tertiary" size="small" @click="deleteMembership(m)">
											{{ t('deck', 'Remove') }}
										</NcButton>
									</div>
								</div>
							</div>
						</div>
					</div>
				</div>

				<div class="section pc-defaults-section">
					<div class="pc-rules-header">
						<div>
							<h5>{{ t('deck', 'Board Defaults') }}</h5>
							<p class="muted">{{ t('deck', 'These permissions apply to all cards that do not have custom rules.') }}</p>
						</div>
						<NcButton type="secondary" @click="saveDefaults">{{ t('deck', 'Save defaults') }}</NcButton>
					</div>

					<div class="pc-defaults-grid">
						<div class="pc-default-card">
							<div class="pc-default-card__header">
								<h5>{{ t('deck', 'Who can move cards') }}</h5>
								<p class="muted">{{ t('deck', 'Allows moving cards between standard columns.') }}</p>
							</div>
							<NcSelect
								v-model="defaults.move"
								:options="roleOptions"
								label="label"
								track-by="value"
								:multiple="true"
								:close-on-select="false" />
						</div>
						<div class="pc-default-card">
							<div class="pc-default-card__header">
								<h5>{{ t('deck', 'Who can approve / mark done') }}</h5>
								<p class="muted">{{ t('deck', 'Allows moving cards into the Approved/Done stack.') }}</p>
							</div>
							<NcSelect
								v-model="defaults.approve"
								:options="roleOptions"
								label="label"
								track-by="value"
								:multiple="true"
								:close-on-select="false" />
						</div>
					</div>
				</div>

				<div class="section">
					<div class="pc-rules-header">
						<div>
							<h5>{{ t('deck', 'Per-card Rules') }}</h5>
							<p class="muted">{{ t('deck', 'Cards without explicit rules inherit the board defaults.') }}</p>
						</div>
						<div class="pc-rules-stats">
							<strong>{{ customCardCount }}</strong> {{ t('deck', 'custom') }} / <strong>{{ cards.length }}</strong> {{ t('deck', 'total') }}
						</div>
					</div>

					<div class="pc-toolbar">
						<input v-model.trim="cardSearch" type="search" :placeholder="t('deck', 'Search cards by name…')" class="pc-search-input">
						<select v-model.number="stackFilter" class="pc-stack-select">
							<option :value="0">{{ t('deck', 'All stacks') }}</option>
							<option v-for="s in stackOptions" :key="s.id" :value="Number(s.id)">{{ s.title }}</option>
						</select>
						<label class="pc-toggle-label">
							<input v-model="showDefaultCards" type="checkbox">
							{{ t('deck', 'Show default cards') }}
						</label>
					</div>

					<div class="pc-grid-wrap">
						<div class="pc-grid-table">
							<div class="pc-grid-thead">
								<div class="pc-th">{{ t('deck', 'Card') }}</div>
								<div class="pc-th">{{ t('deck', 'Move') }}</div>
								<div class="pc-th">{{ t('deck', 'Approve') }}</div>
								<div class="pc-th pc-th-actions">{{ t('deck', 'Actions') }}</div>
							</div>
							<div class="pc-grid-tbody">
								<div v-if="visibleCards.length === 0" class="pc-empty">
									{{ t('deck', 'No cards found matching your criteria.') }}
								</div>
								<div v-for="card in visibleCards" :key="card.id" class="pc-grid-tr">
									<div class="pc-td pc-td-card">
										<div class="pc-card-title">
											{{ card.title }}
											<span v-if="card.hasExplicitPolicy" class="pc-badge pc-badge--custom">{{ t('deck', 'Custom') }}</span>
										</div>
										<div class="pc-card-stack muted">{{ t('deck', 'in {stack}', { stack: getStackTitle(card.stackId) }) }}</div>
									</div>
									<div class="pc-td">
										<span v-if="card.effectivePolicy && (card.effectivePolicy.move || []).length" class="pc-chips">
											<span v-for="rk in (card.effectivePolicy.move || [])" :key="`${card.id}-m-${rk}`" class="pc-chip" :style="chipStyleByKey(rk)">
												<span class="pc-dot" :style="{ background: roleColorByKey(rk) }" />
												{{ roleNameByKey(rk) }}
											</span>
										</span>
										<span v-else class="muted">—</span>
									</div>
									<div class="pc-td">
										<span v-if="card.effectivePolicy && (card.effectivePolicy.approve || []).length" class="pc-chips">
											<span v-for="rk in (card.effectivePolicy.approve || [])" :key="`${card.id}-a-${rk}`" class="pc-chip" :style="chipStyleByKey(rk)">
												<span class="pc-dot" :style="{ background: roleColorByKey(rk) }" />
												{{ roleNameByKey(rk) }}
											</span>
										</span>
										<span v-else class="muted">—</span>
									</div>
									<div class="pc-td pc-td-actions">
										<NcButton type="secondary" size="small" @click="openEditor(card)">{{ t('deck', 'Edit') }}</NcButton>
										<NcButton v-if="card.hasExplicitPolicy" type="tertiary" size="small" @click="resetCard(card)">{{ t('deck', 'Reset') }}</NcButton>
									</div>
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>

		<NcModal v-if="editingCard" :title="t('deck', 'Permissions: {card}', { card: editingCard.title })" @close="closeEditor">
			<div class="pc-modal-body">
				<p class="muted">
					{{ t('deck', 'Override the default board permissions for this specific card. If you clear these fields, the card will inherit the board defaults.') }}
				</p>
				<div class="pc-modal-field">
					<label>{{ t('deck', 'Who can move') }}</label>
					<NcSelect
						v-model="editingCardEdits.move"
						:options="roleOptions"
						label="label"
						track-by="value"
						:multiple="true"
						:close-on-select="false" />
				</div>
				<div class="pc-modal-field">
					<label>{{ t('deck', 'Who can approve (Done)') }}</label>
					<NcSelect
						v-model="editingCardEdits.approve"
						:options="roleOptions"
						label="label"
						track-by="value"
						:multiple="true"
						:close-on-select="false" />
				</div>
				<div class="pc-modal-actions">
					<NcButton @click="closeEditor">{{ t('deck', 'Cancel') }}</NcButton>
					<NcButton type="primary" @click="saveEditor">{{ t('deck', 'Save custom rules') }}</NcButton>
				</div>
			</div>
		</NcModal>
	</div>
</template>

<script>
import { NcButton, NcModal, NcSelect } from '@nextcloud/vue'
import { showError, showSuccess } from '@nextcloud/dialogs'
import { CardPolicyApi } from '../../services/CardPolicyApi.js'

export default {
	name: 'CardPolicyManager',
	components: { NcButton, NcSelect, NcModal },
	props: {
		boardId: { type: Number, required: true },
	},
	data() {
		return {
			loading: false,
			error: '',
			api: new CardPolicyApi(),
			settings: { permissionMode: 'legacy', approvedStackId: null },
			roles: [],
			memberships: [],
			defaults: { move: [], approve: [] },
			defaultRoleKeys: { move: [], approve: [] },
			cards: [],
			approvedStackSelection: null,
			newMembership: { role: null, user: null },
			stackFilter: 0,
			showDefaultCards: false,
			cardSearch: '',
			editingCard: null,
			editingCardEdits: { move: [], approve: [] },
		}
	},
	computed: {
		stackOptions() {
			return this.$store.getters.stacksByBoard(this.boardId) || []
		},
		roleOptions() {
			return this.roles.map(r => ({ value: r.roleKey, label: r.name }))
		},
		roleNameById() {
			const map = {}
			for (const r of this.roles) map[r.id] = r.name
			return map
		},
		userOptions() {
			const users = this.$store.getters.assignableUsers || []
			return users.map(u => ({
				value: u.uid || u.id || u.userId,
				label: u.displayname || u.displayName || u.uid || u.id || u.userId,
			}))
		},
		canAddMembership() {
			return !!(this.newMembership.role && this.newMembership.user)
		},
		roleByKey() {
			const map = {}
			for (const r of this.roles) map[r.roleKey] = r
			return map
		},
		visibleCards() {
			const q = (this.cardSearch || '').toLowerCase()
			const filtered = (this.cards || [])
				.filter(c => (this.showDefaultCards ? true : !!c.hasExplicitPolicy))
				.filter(c => (this.stackFilter && Number(c.stackId) !== Number(this.stackFilter)) ? false : true)
				.filter(c => (q ? String(c.title || '').toLowerCase().includes(q) : true))

			return filtered.sort((a, b) => {
				const sa = this.getStackTitle(a.stackId)
				const sb = this.getStackTitle(b.stackId)
				if (sa !== sb) return sa.localeCompare(sb)
				return String(a.title || '').localeCompare(String(b.title || ''))
			})
		},
		customCardCount() {
			return (this.cards || []).filter(c => !!c.hasExplicitPolicy).length
		},
	},
	mounted() {
		this.load()
	},
	methods: {
		async load() {
			this.loading = true
			this.error = ''
			try {
				const data = await this.api.getBoardPolicy(this.boardId)
				this.settings = data.settings || { permissionMode: 'legacy', approvedStackId: null }
				this.roles = data.roles || []
				this.memberships = data.memberships || []
				this.defaultRoleKeys = data.defaultRoleKeys || { move: [], approve: [] }
				this.defaults = {
					move: (this.defaultRoleKeys.move || []).map(v => ({ value: v, label: this.roleOptions.find(o => o.value === v)?.label || v })),
					approve: (this.defaultRoleKeys.approve || []).map(v => ({ value: v, label: this.roleOptions.find(o => o.value === v)?.label || v })),
				}
				this.cards = data.cards || []
				if (this.stackFilter && !this.stackOptions.find(s => Number(s.id) === Number(this.stackFilter))) {
					this.stackFilter = 0
				}
				this.approvedStackSelection = this.stackOptions.find(s => s.id === this.settings.approvedStackId) || this.stackOptions[0] || null
			} catch (e) {
				this.error = e?.response?.data?.ocs?.data?.message || e?.response?.data?.message || e?.message || 'Error'
			} finally {
				this.loading = false
			}
		},
		getStackTitle(stackId) {
			const s = this.stackOptions.find(s => Number(s.id) === Number(stackId))
			return s ? s.title : t('deck', 'Unknown stack')
		},
		getMemberDisplayName(participant) {
			const member = this.userOptions.find(u => u.value === participant)
			return member ? member.label : participant
		},
		roleColorById(roleId) {
			const r = this.roles.find(x => x.id === roleId)
			return r?.color || 'var(--color-border)'
		},
		chipStyleByRoleId(roleId) {
			return { borderColor: this.roleColorById(roleId) }
		},
		roleNameByKey(roleKey) {
			return this.roleByKey[roleKey]?.name || roleKey
		},
		roleColorByKey(roleKey) {
			return this.roleByKey[roleKey]?.color || 'var(--color-border)'
		},
		chipStyleByKey(roleKey) {
			return { borderColor: this.roleColorByKey(roleKey) }
		},
		openEditor(card) {
			this.editingCard = card
			const prefillMove = card.hasExplicitPolicy ? (card.policy?.move || []) : (this.defaultRoleKeys.move || [])
			const prefillApprove = card.hasExplicitPolicy ? (card.policy?.approve || []) : (this.defaultRoleKeys.approve || [])
			this.editingCardEdits = {
				move: prefillMove.map(v => ({ value: v, label: this.roleOptions.find(o => o.value === v)?.label || v })),
				approve: prefillApprove.map(v => ({ value: v, label: this.roleOptions.find(o => o.value === v)?.label || v })),
			}
		},
		closeEditor() {
			this.editingCard = null
			this.editingCardEdits = { move: [], approve: [] }
		},
		async saveEditor() {
			if (!this.editingCard) return
			try {
				await this.api.setCardPolicy(this.boardId, this.editingCard.id, {
					move: this.editingCardEdits.move.map(o => o.value),
					approve: this.editingCardEdits.approve.map(o => o.value),
				})
				showSuccess(t('deck', 'Saved'))
				this.closeEditor()
				await this.load()
			} catch (e) {
				showError(e?.response?.data?.ocs?.data?.message || e?.message || 'Error')
			}
		},
		async enableMode() {
			try {
				await this.api.enable(this.boardId)
				showSuccess(t('deck', 'Card-based permissions enabled'))
				await this.load()
			} catch (e) {
				showError(e?.response?.data?.ocs?.data?.message || e?.message || 'Error')
			}
		},
		async saveApprovedStack() {
			if (!this.approvedStackSelection) return
			try {
				await this.api.updateSettings(this.boardId, { approvedStackId: this.approvedStackSelection.id })
				showSuccess(t('deck', 'Saved'))
				await this.load()
			} catch (e) {
				showError(e?.response?.data?.ocs?.data?.message || e?.message || 'Error')
			}
		},
		async saveDefaults() {
			try {
				await this.api.updateDefaults(this.boardId, {
					move: this.defaults.move.map(o => o.value),
					approve: this.defaults.approve.map(o => o.value),
				})
				showSuccess(t('deck', 'Saved'))
				await this.load()
			} catch (e) {
				showError(e?.response?.data?.ocs?.data?.message || e?.message || 'Error')
			}
		},
		async addMembership() {
			try {
				await this.api.addMembership(this.boardId, {
					roleKey: this.newMembership.role.value,
					participant: this.newMembership.user.value,
					participantType: 0,
				})
				this.newMembership.role = null
				this.newMembership.user = null
				showSuccess(t('deck', 'Added'))
				await this.load()
			} catch (e) {
				showError(e?.response?.data?.ocs?.data?.message || e?.message || 'Error')
			}
		},
		async deleteMembership(m) {
			try {
				await this.api.deleteMembership(this.boardId, m.id)
				showSuccess(t('deck', 'Removed'))
				await this.load()
			} catch (e) {
				showError(e?.response?.data?.ocs?.data?.message || e?.message || 'Error')
			}
		},
		async resetCard(card) {
			try {
				await this.api.clearCardPolicy(this.boardId, card.id)
				showSuccess(t('deck', 'Reset'))
				await this.load()
			} catch (e) {
				showError(e?.response?.data?.ocs?.data?.message || e?.message || 'Error')
			}
		},
	},
}
</script>

<style scoped>
.card-policy-manager {
	display: grid;
	gap: 16px;
}

.header h4 {
	margin: 0;
}

.description,
.muted {
	color: var(--color-text-maxcontrast);
}

.error {
	color: var(--color-error);
}

.enable-box {
	border: 1px solid var(--color-border);
	border-radius: 10px;
	padding: 12px;
	display: grid;
	gap: 10px;
}

.section {
	border: 1px solid var(--color-border);
	border-radius: 10px;
	padding: 12px;
	display: grid;
	gap: 12px;
}

.row {
	display: grid;
	grid-template-columns: 1fr auto;
	gap: 10px;
	align-items: end;
}

.field label,
.pc-modal-field label {
	display: block;
	font-size: 12px;
	margin-bottom: 6px;
	color: var(--color-text-maxcontrast);
}

.actions {
	display: flex;
	justify-content: flex-end;
}

.pc-rules-header {
	display: flex;
	justify-content: space-between;
	align-items: flex-start;
	gap: 12px;
}

.pc-rules-stats {
	background: var(--color-background-dark);
	padding: 4px 12px;
	border-radius: 999px;
	font-size: 13px;
	color: var(--color-text-maxcontrast);
	white-space: nowrap;
}

.pc-toolbar {
	display: flex;
	gap: 12px;
	align-items: center;
	flex-wrap: wrap;
}

.pc-search-input,
.pc-stack-select {
	border: 1px solid var(--color-border);
	border-radius: 6px;
	padding: 8px 12px;
	background: var(--color-main-background);
	color: var(--color-main-text);
}

.pc-search-input {
	flex: 1;
	min-width: 220px;
}

.pc-toggle-label {
	display: flex;
	align-items: center;
	gap: 6px;
	cursor: pointer;
	color: var(--color-text-maxcontrast);
}

.pc-grid-wrap {
	border: 1px solid var(--color-border);
	border-radius: 8px;
	overflow: hidden;
	background: var(--color-main-background);
}

.pc-grid-thead {
	display: grid;
	grid-template-columns: minmax(200px, 2fr) 1.5fr 1.5fr 140px;
	gap: 16px;
	padding: 12px 16px;
	background: var(--color-background-hover);
	border-bottom: 1px solid var(--color-border);
	font-weight: 700;
	color: var(--color-text-maxcontrast);
	font-size: 12px;
	text-transform: uppercase;
}

.pc-grid-tr {
	display: grid;
	grid-template-columns: minmax(200px, 2fr) 1.5fr 1.5fr 140px;
	gap: 16px;
	padding: 12px 16px;
	border-bottom: 1px solid var(--color-border);
	align-items: center;
}

.pc-grid-tr:last-child {
	border-bottom: none;
}

.pc-grid-tr:hover {
	background: var(--color-background-hover);
}

.pc-empty {
	padding: 24px;
	text-align: center;
	color: var(--color-text-maxcontrast);
}

.pc-card-title {
	font-weight: 600;
	display: flex;
	align-items: center;
	gap: 8px;
	margin-bottom: 4px;
}

.pc-card-stack {
	font-size: 12px;
}

.pc-badge {
	font-size: 11px;
	padding: 2px 6px;
	border-radius: 4px;
	font-weight: 700;
	text-transform: uppercase;
}

.pc-badge--custom {
	background: var(--color-primary-element);
	color: var(--color-primary-text);
}

.pc-chips,
.pc-role-legend {
	display: flex;
	flex-wrap: wrap;
	gap: 6px;
}

.pc-chip {
	display: inline-flex;
	align-items: center;
	gap: 6px;
	padding: 2px 8px;
	border-radius: 999px;
	border: 1px solid var(--color-border);
	font-size: 12px;
	background: var(--color-main-background);
	white-space: nowrap;
}

.pc-dot {
	width: 8px;
	height: 8px;
	border-radius: 50%;
}

.pc-td-actions {
	display: flex;
	gap: 8px;
	justify-content: flex-end;
}

.pc-modal-body {
	padding: 20px;
	display: grid;
	gap: 16px;
	width: 520px;
	max-width: 100%;
}

.pc-modal-actions {
	display: flex;
	justify-content: flex-end;
	gap: 12px;
	margin-top: 8px;
}

.pc-toolbar--add-member {
	background: var(--color-background-hover);
	padding: 12px;
	border-radius: 8px;
	border: 1px solid var(--color-border);
}

.pc-flex-1 {
	flex: 1;
	min-width: 180px;
}

.pc-grid-thead--members,
.pc-grid-tr--members {
	grid-template-columns: 2fr 1fr 100px;
}

.pc-member-name {
	font-weight: 600;
}

.pc-defaults-grid {
	display: grid;
	grid-template-columns: 1fr 1fr;
	gap: 16px;
}

.pc-default-card {
	border: 1px solid var(--color-border);
	border-radius: 8px;
	padding: 16px;
	background: var(--color-background-hover);
	display: flex;
	flex-direction: column;
	gap: 12px;
}

.pc-default-card__header h5 {
	margin: 0;
	font-size: 14px;
	font-weight: 600;
}

.pc-default-card__header p {
	margin: 4px 0 0;
	font-size: 12px;
	line-height: 1.4;
}

@media (max-width: 900px) {
	.row {
		grid-template-columns: 1fr;
	}

	.pc-grid-thead {
		display: none;
	}

	.pc-grid-tr {
		grid-template-columns: 1fr;
		gap: 8px;
	}

	.pc-td-actions {
		justify-content: flex-start;
		margin-top: 8px;
	}

	.pc-defaults-grid {
		grid-template-columns: 1fr;
	}

	.pc-toolbar--add-member {
		flex-direction: column;
		align-items: stretch;
	}
}
</style>
