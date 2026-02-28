<template>
	<div class="pc-policy-app">
		<div v-if="loading && !cards.length" class="pc-loading-state">
			<p>{{ t('deck', 'Loading permissions data...') }}</p>
		</div>

		<div v-else-if="error" class="pc-error-state">
			<h3>{{ t('deck', 'Failed to load permissions') }}</h3>
			<p>{{ error }}</p>
			<NcButton @click="load">{{ t('deck', 'Retry') }}</NcButton>
		</div>

		<div v-else-if="settings.permissionMode !== 'card_policy'" class="pc-enable-state">
			<h3>{{ t('deck', 'Granular Permissions Disabled') }}</h3>
			<p class="muted">{{ t('deck', 'This board is using legacy permissions. Upgrade to the new card-policy system to enable granular access controls.') }}</p>
			<NcButton type="primary" @click="enableMode">{{ t('deck', 'Enable Granular Permissions') }}</NcButton>
		</div>

		<div v-else class="pc-policy-container">
			<div class="pc-app-header">
				<div class="pc-header-titles">
					<h2>{{ t('deck', 'Card Permissions') }}</h2>
					<p class="muted">{{ t('deck', 'Assign view, move and approve permissions to specific cards.') }}</p>
				</div>
				<div class="pc-header-actions">
					<NcButton type="tertiary" @click="showMembersModal = true">
						{{ t('deck', 'Board Roles & Members') }}
					</NcButton>
					<NcButton type="tertiary" @click="showDefaultsModal = true">
						{{ t('deck', 'Board Defaults') }}
					</NcButton>
				</div>
			</div>

			<div class="pc-toolbar">
				<div class="pc-toolbar-search">
					<input v-model.trim="cardSearch" type="search" :placeholder="t('deck', 'Search cards...')" class="pc-search-input">
				</div>
				<div class="pc-toolbar-filters">
					<select v-model.number="stackFilter" class="pc-stack-select">
						<option :value="0">{{ t('deck', 'All stacks ({count} cards)', { count: cards.length }) }}</option>
						<option v-for="s in stackOptions" :key="s.id" :value="Number(s.id)">{{ s.title }}</option>
					</select>
					<label class="pc-filter-checkbox">
						<input v-model="showOnlyCustom" type="checkbox">
						{{ t('deck', 'Show only custom overrides') }}
					</label>
				</div>
			</div>

			<div class="pc-data-grid-wrapper">
				<table class="pc-data-grid">
					<thead>
						<tr>
							<th class="pc-col-check">
								<input type="checkbox" :checked="allVisibleSelected" :title="t('deck', 'Select all visible')" @change="toggleSelectAllVisible">
							</th>
							<th class="pc-col-name">{{ t('deck', 'Card Name') }}</th>
							<th class="pc-col-stack">{{ t('deck', 'Stack') }}</th>
							<th class="pc-col-perms">{{ t('deck', 'Who can View') }}</th>
							<th class="pc-col-perms">{{ t('deck', 'Who can Move') }}</th>
							<th class="pc-col-perms">{{ t('deck', 'Who can Approve') }}</th>
							<th class="pc-col-actions"></th>
						</tr>
					</thead>
					<tbody>
						<tr v-if="filteredCards.length === 0">
							<td colspan="7" class="pc-empty-row">{{ t('deck', 'No cards match your filters.') }}</td>
						</tr>
						<tr
							v-for="card in filteredCards"
							:key="card.id"
							:class="{ 'is-selected': isSelected(card.id) }"
							@click="toggleSelection(card.id, $event)">
							<td class="pc-col-check" @click.stop>
								<input v-model="selectedCardIds" type="checkbox" :value="card.id">
							</td>
							<td class="pc-col-name">
								<div class="pc-card-title-wrap">
									<span class="pc-card-title">{{ card.title }}</span>
									<span v-if="card.hasExplicitPolicy" class="pc-badge-custom" :title="t('deck', 'This card has specific override rules')">{{ t('deck', 'Custom') }}</span>
								</div>
							</td>
							<td class="pc-col-stack">
								<span class="pc-stack-label">{{ getStackTitle(card.stackId) }}</span>
							</td>
							<td class="pc-col-perms">
								<div class="pc-role-chips">
									<span v-for="rk in getEffectivePerms(card, 'view')" :key="`${card.id}-v-${rk}`" class="pc-role-chip" :style="chipStyleByKey(rk)">
										<span class="pc-dot" :style="{ background: roleColorByKey(rk) }" />
										{{ roleNameByKey(rk) }}
									</span>
									<span v-if="!getEffectivePerms(card, 'view').length" class="muted-dash">-</span>
								</div>
							</td>
							<td class="pc-col-perms">
								<div class="pc-role-chips">
									<span v-for="rk in getEffectivePerms(card, 'move')" :key="`${card.id}-m-${rk}`" class="pc-role-chip" :style="chipStyleByKey(rk)">
										<span class="pc-dot" :style="{ background: roleColorByKey(rk) }" />
										{{ roleNameByKey(rk) }}
									</span>
									<span v-if="!getEffectivePerms(card, 'move').length" class="muted-dash">-</span>
								</div>
							</td>
							<td class="pc-col-perms">
								<div class="pc-role-chips">
									<span v-for="rk in getEffectivePerms(card, 'approve')" :key="`${card.id}-a-${rk}`" class="pc-role-chip" :style="chipStyleByKey(rk)">
										<span class="pc-dot" :style="{ background: roleColorByKey(rk) }" />
										{{ roleNameByKey(rk) }}
									</span>
									<span v-if="!getEffectivePerms(card, 'approve').length" class="muted-dash">-</span>
								</div>
							</td>
							<td class="pc-col-actions" @click.stop>
								<NcButton v-if="card.hasExplicitPolicy" type="tertiary" size="small" @click="resetCard(card)">
									{{ t('deck', 'Reset') }}
								</NcButton>
							</td>
						</tr>
					</tbody>
				</table>
			</div>

			<transition name="slide-up">
				<div v-if="selectedCardIds.length > 0" class="pc-floating-action-bar">
					<div class="pc-fab-left">
						<span class="pc-fab-count">{{ t('deck', '{count} cards selected', { count: selectedCardIds.length }) }}</span>
						<button class="pc-fab-clear" @click="selectedCardIds = []">{{ t('deck', 'Clear selection') }}</button>
					</div>

					<div class="pc-fab-controls">
						<div class="pc-fab-field">
							<label>{{ t('deck', 'View:') }}</label>
							<NcSelect v-model="bulkEdits.view" :options="roleOptions" label="label" track-by="value" :multiple="true" :close-on-select="false" :placeholder="t('deck', 'Select roles...')" />
						</div>
						<div class="pc-fab-field">
							<label>{{ t('deck', 'Move:') }}</label>
							<NcSelect v-model="bulkEdits.move" :options="roleOptions" label="label" track-by="value" :multiple="true" :close-on-select="false" :placeholder="t('deck', 'Select roles...')" />
						</div>
						<div class="pc-fab-field">
							<label>{{ t('deck', 'Approve:') }}</label>
							<NcSelect v-model="bulkEdits.approve" :options="roleOptions" label="label" track-by="value" :multiple="true" :close-on-select="false" :placeholder="t('deck', 'Select roles...')" />
						</div>
					</div>

					<div class="pc-fab-actions">
						<NcButton type="primary" :loading="savingBulk" @click="saveBulk">{{ t('deck', 'Apply Rules') }}</NcButton>
						<NcButton type="tertiary" :loading="savingBulk" @click="resetBulk">{{ t('deck', 'Reset to Defaults') }}</NcButton>
					</div>
				</div>
			</transition>
		</div>

		<NcModal v-if="showDefaultsModal" :title="t('deck', 'Board Defaults')" @close="showDefaultsModal = false">
			<div class="pc-modal-content">
				<div class="pc-modal-header-desc">
					<p>{{ t('deck', 'These permissions apply automatically to any card that does not have custom overrides set.') }}</p>
				</div>
				<div class="pc-modal-field">
					<label>{{ t('deck', 'Approved/Done stack') }}</label>
					<NcSelect v-model="approvedStackSelection" :options="stackOptions" label="title" track-by="id" :clearable="false" />
				</div>
				<div class="pc-modal-field">
					<label>{{ t('deck', 'Who can view cards') }}</label>
					<NcSelect v-model="defaults.view" :options="roleOptions" label="label" track-by="value" :multiple="true" :close-on-select="false" />
					<p class="muted">{{ t('deck', 'If left empty, visibility is derived from Move/Approve rules.') }}</p>
				</div>
				<div class="pc-modal-field">
					<label>{{ t('deck', 'Who can move cards') }}</label>
					<NcSelect v-model="defaults.move" :options="roleOptions" label="label" track-by="value" :multiple="true" :close-on-select="false" />
				</div>
				<div class="pc-modal-field">
					<label>{{ t('deck', 'Who can approve / mark done') }}</label>
					<NcSelect v-model="defaults.approve" :options="roleOptions" label="label" track-by="value" :multiple="true" :close-on-select="false" />
				</div>
				<div class="pc-modal-footer">
					<NcButton @click="showDefaultsModal = false">{{ t('deck', 'Cancel') }}</NcButton>
					<NcButton type="primary" :loading="savingDefaults" @click="saveDefaults">{{ t('deck', 'Save Defaults') }}</NcButton>
				</div>
			</div>
		</NcModal>

		<NcModal v-if="showMembersModal" :title="t('deck', 'Board Roles & Members')" @close="showMembersModal = false">
			<div class="pc-modal-content pc-members-modal">
				<div class="pc-add-role-box">
					<h4>{{ t('deck', 'Create Role') }}</h4>
					<div class="pc-add-role-row">
						<NcTextField
							class="pc-flex-1"
							v-model="newRole.name"
							:label="t('deck', 'Role name')"
							:show-label="true"
							:placeholder="t('deck', 'e.g., Installer')" />
						<NcTextField
							class="pc-flex-1"
							v-model="newRole.roleKey"
							:label="t('deck', 'Role key')"
							:show-label="true"
							:placeholder="t('deck', 'e.g., installer')"
							@focus="newRoleKeyTouched = true"
							@input="newRoleKeyTouched = true" />
						<div class="pc-role-color-field">
							<label class="pc-role-color-label">{{ t('deck', 'Color') }}</label>
							<NcColorPicker v-model="newRole.color" class="pc-role-color-picker">
								<div class="pc-role-color-swatch" :style="{ backgroundColor: newRole.color }" />
							</NcColorPicker>
						</div>
						<NcButton type="primary" :loading="creatingRole" :disabled="!newRole.name.trim() || !newRole.roleKey.trim()" @click="createRole">
							{{ t('deck', 'Create') }}
						</NcButton>
					</div>
					<p class="pc-help-muted">
						{{ t('deck', 'Role keys must be lowercase and can contain letters, numbers, underscores and dashes.') }}
					</p>
				</div>

				<div class="pc-add-member-box">
					<h4>{{ t('deck', 'Add Member to Role') }}</h4>
					<div class="pc-add-member-row">
						<NcSelect class="pc-flex-1" v-model="newMembership.user" :options="userOptions" :placeholder="t('deck', 'Select a user...')" label="label" track-by="value" />
						<NcSelect class="pc-flex-1" v-model="newMembership.role" :options="roleOptions" :placeholder="t('deck', 'Select a role...')" label="label" track-by="value" />
						<NcButton type="primary" :disabled="!canAddMembership" @click="addMembership">{{ t('deck', 'Add') }}</NcButton>
					</div>
				</div>

				<div class="pc-members-list-box">
					<h4>{{ t('deck', 'Current Members') }}</h4>
					<div v-if="memberships.length === 0" class="muted pc-members-empty">{{ t('deck', 'No roles assigned.') }}</div>
					<table v-else class="pc-simple-table">
						<tbody>
							<tr v-for="m in memberships" :key="m.id">
								<td class="pc-simple-td-name"><strong>{{ getMemberDisplayName(m.participant) }}</strong></td>
								<td class="pc-simple-td-role">
									<span class="pc-role-chip" :style="chipStyleByRoleId(m.roleId)">
										<span class="pc-dot" :style="{ background: roleColorById(m.roleId) }" />
										{{ roleNameById[m.roleId] || m.roleId }}
									</span>
								</td>
								<td class="pc-simple-td-action">
									<button class="pc-danger-btn" @click="deleteMembership(m)">{{ t('deck', 'Remove') }}</button>
								</td>
							</tr>
						</tbody>
					</table>
				</div>
			</div>
		</NcModal>
	</div>
</template>

<script>
import { NcButton, NcColorPicker, NcModal, NcSelect, NcTextField } from '@nextcloud/vue'
import { showError, showSuccess } from '@nextcloud/dialogs'
import { CardPolicyApi } from '../../services/CardPolicyApi.js'

export default {
	name: 'CardPolicyManager',
	components: { NcButton, NcColorPicker, NcSelect, NcModal, NcTextField },
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
			defaults: { view: [], move: [], approve: [] },
			defaultRoleKeys: { view: [], move: [], approve: [] },
			cards: [],
			approvedStackSelection: null,
			cardSearch: '',
			stackFilter: 0,
			showOnlyCustom: false,
			selectedCardIds: [],
			showDefaultsModal: false,
			showMembersModal: false,
			newMembership: { role: null, user: null },
			bulkEdits: { view: [], move: [], approve: [] },
			savingDefaults: false,
			savingBulk: false,
			newRole: { name: '', roleKey: '', color: '#111111' },
			newRoleKeyTouched: false,
			creatingRole: false,
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
		filteredCards() {
			const q = (this.cardSearch || '').toLowerCase()
			const filtered = (this.cards || [])
				.filter(c => (this.stackFilter && Number(c.stackId) !== Number(this.stackFilter)) ? false : true)
				.filter(c => (this.showOnlyCustom ? !!c.hasExplicitPolicy : true))
				.filter(c => (q ? String(c.title || '').toLowerCase().includes(q) : true))

			return filtered.sort((a, b) => {
				const sa = this.getStackTitle(a.stackId)
				const sb = this.getStackTitle(b.stackId)
				if (sa !== sb) return sa.localeCompare(sb)
				return String(a.title || '').localeCompare(String(b.title || ''))
			})
		},
		allVisibleSelected() {
			if (this.filteredCards.length === 0) return false
			return this.filteredCards.every(c => this.selectedCardIds.includes(c.id))
		},
	},
	mounted() {
		this.load()
	},
	watch: {
		showMembersModal(val) {
			if (val) {
				this.membersModalTab = 'members'
			}
		},
		selectedCardIds(newIds) {
			if (newIds.length === 1) {
				const card = this.cards.find(c => c.id === newIds[0])
				if (!card) return
				const perms = card.effectivePolicy || {}
				const viewKeys = perms.view || []
				const moveKeys = perms.move || []
				const approveKeys = perms.approve || []
				this.bulkEdits.view = viewKeys.map(v => ({ value: v, label: this.roleOptions.find(o => o.value === v)?.label || v }))
				this.bulkEdits.move = moveKeys.map(v => ({ value: v, label: this.roleOptions.find(o => o.value === v)?.label || v }))
				this.bulkEdits.approve = approveKeys.map(v => ({ value: v, label: this.roleOptions.find(o => o.value === v)?.label || v }))
			} else if (newIds.length === 0) {
				this.bulkEdits = { view: [], move: [], approve: [] }
			}
		},
		'newRole.name'(val) {
			if (this.newRoleKeyTouched) return
			this.newRole.roleKey = this.slugifyRoleKey(val)
		},
	},
	methods: {
		slugifyRoleKey(input) {
			const raw = String(input || '').trim().toLowerCase()
			return raw
				.replace(/\s+/g, '_')
				.replace(/[^a-z0-9_-]/g, '')
				.replace(/_+/g, '_')
				.replace(/^-+/, '')
				.replace(/^_+/, '')
				.replace(/-+$/, '')
				.replace(/_+$/, '')
		},
		resetNewRoleForm() {
			this.newRole = { name: '', roleKey: '', color: '#111111' }
			this.newRoleKeyTouched = false
		},
		async load() {
			this.loading = true
			this.error = ''
			try {
				const data = await this.api.getBoardPolicy(this.boardId)
				this.settings = data.settings || { permissionMode: 'legacy', approvedStackId: null }
				this.roles = data.roles || []
				this.memberships = data.memberships || []
				this.defaultRoleKeys = data.defaultRoleKeys || { view: [], move: [], approve: [] }
				this.defaults = {
					view: (this.defaultRoleKeys.view || []).map(v => ({ value: v, label: this.roleOptions.find(o => o.value === v)?.label || v })),
					move: (this.defaultRoleKeys.move || []).map(v => ({ value: v, label: this.roleOptions.find(o => o.value === v)?.label || v })),
					approve: (this.defaultRoleKeys.approve || []).map(v => ({ value: v, label: this.roleOptions.find(o => o.value === v)?.label || v })),
				}
				this.cards = data.cards || []
				this.selectedCardIds = this.selectedCardIds.filter(id => this.cards.some(c => c.id === id))
				this.approvedStackSelection = this.stackOptions.find(s => Number(s.id) === Number(this.settings.approvedStackId)) || this.stackOptions[0] || null
				if (this.stackFilter && !this.stackOptions.find(s => Number(s.id) === Number(this.stackFilter))) {
					this.stackFilter = 0
				}
			} catch (e) {
				this.error = e?.response?.data?.ocs?.data?.message || e?.response?.data?.message || e?.message || 'Error'
			} finally {
				this.loading = false
			}
		},
		getStackTitle(stackId) {
			const s = this.stackOptions.find(x => Number(x.id) === Number(stackId))
			return s ? s.title : this.t('deck', 'Unknown stack')
		},
		getMemberDisplayName(participant) {
			const member = this.userOptions.find(u => u.value === participant)
			return member ? member.label : participant
		},
		roleColorById(roleId) {
			const role = this.roles.find(x => x.id === roleId)
			return role?.color || 'var(--color-border)'
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
		getEffectivePerms(card, type) {
			return card?.effectivePolicy?.[type] || []
		},
		isSelected(cardId) {
			return this.selectedCardIds.includes(cardId)
		},
		toggleSelection(cardId, event) {
			if (['INPUT', 'BUTTON', 'A', 'LABEL'].includes(event.target.tagName)) return
			const index = this.selectedCardIds.indexOf(cardId)
			if (index === -1) this.selectedCardIds.push(cardId)
			else this.selectedCardIds.splice(index, 1)
		},
		toggleSelectAllVisible(e) {
			if (e.target.checked) {
				const visibleIds = this.filteredCards.map(c => c.id)
				this.selectedCardIds = Array.from(new Set([...this.selectedCardIds, ...visibleIds]))
			} else {
				const visibleSet = new Set(this.filteredCards.map(c => c.id))
				this.selectedCardIds = this.selectedCardIds.filter(id => !visibleSet.has(id))
			}
		},
		async saveDefaults() {
			this.savingDefaults = true
			try {
				if (this.approvedStackSelection) {
					await this.api.updateSettings(this.boardId, { approvedStackId: this.approvedStackSelection.id })
				}
				await this.api.updateDefaults(this.boardId, {
					view: this.defaults.view.map(o => o.value),
					move: this.defaults.move.map(o => o.value),
					approve: this.defaults.approve.map(o => o.value),
				})
				showSuccess(this.t('deck', 'Board defaults updated'))
				this.showDefaultsModal = false
				await this.load()
			} catch (e) {
				showError(e?.response?.data?.ocs?.data?.message || this.t('deck', 'Error saving defaults'))
			} finally {
				this.savingDefaults = false
			}
		},
		async resetCard(card) {
			try {
				await this.api.clearCardPolicy(this.boardId, card.id)
				showSuccess(this.t('deck', 'Card reset to default'))
				await this.load()
			} catch (e) {
				showError(e?.response?.data?.ocs?.data?.message || this.t('deck', 'Error resetting card'))
			}
		},
		async saveBulk() {
			if (this.selectedCardIds.length === 0) return
			this.savingBulk = true
			let errors = 0
			try {
				const payload = {
					view: this.bulkEdits.view.map(o => o.value),
					move: this.bulkEdits.move.map(o => o.value),
					approve: this.bulkEdits.approve.map(o => o.value),
				}
				for (let i = 0; i < this.selectedCardIds.length; i += 5) {
					const batch = this.selectedCardIds.slice(i, i + 5)
					await Promise.all(batch.map(async cardId => {
						try {
							await this.api.setCardPolicy(this.boardId, cardId, payload)
						} catch (e) {
							errors++
						}
					}))
				}
				if (errors > 0) showError(this.t('deck', 'Failed to update {count} cards', { count: errors }))
				else showSuccess(this.t('deck', 'Updated {count} cards', { count: this.selectedCardIds.length }))
				this.selectedCardIds = []
				await this.load()
			} finally {
				this.savingBulk = false
			}
		},
		async resetBulk() {
			if (this.selectedCardIds.length === 0) return
			this.savingBulk = true
			let errors = 0
			try {
				for (let i = 0; i < this.selectedCardIds.length; i += 5) {
					const batch = this.selectedCardIds.slice(i, i + 5)
					await Promise.all(batch.map(async cardId => {
						try {
							await this.api.clearCardPolicy(this.boardId, cardId)
						} catch (e) {
							errors++
						}
					}))
				}
				if (errors > 0) showError(this.t('deck', 'Failed to reset {count} cards', { count: errors }))
				else showSuccess(this.t('deck', 'Reset {count} cards', { count: this.selectedCardIds.length }))
				this.selectedCardIds = []
				await this.load()
			} finally {
				this.savingBulk = false
			}
		},
		async enableMode() {
			try {
				await this.api.enable(this.boardId)
				showSuccess(this.t('deck', 'Card policies enabled'))
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
				showSuccess(this.t('deck', 'Member added'))
				await this.load()
			} catch (e) {
				showError(e?.response?.data?.ocs?.data?.message || this.t('deck', 'Error adding member'))
			}
		},
		async deleteMembership(m) {
			try {
				await this.api.deleteMembership(this.boardId, m.id)
				showSuccess(this.t('deck', 'Member removed'))
				await this.load()
			} catch (e) {
				showError(e?.response?.data?.ocs?.data?.message || this.t('deck', 'Error removing member'))
			}
		},
		async createRole() {
			if (this.creatingRole) return
			const name = String(this.newRole.name || '').trim()
			const roleKey = String(this.newRole.roleKey || '').trim()
			const color = String(this.newRole.color || '').trim()
			if (!name || !roleKey) return
			this.creatingRole = true
			try {
				await this.api.createRole(this.boardId, { roleKey, name, color })
				showSuccess(this.t('deck', 'Role created'))
				this.resetNewRoleForm()
				await this.load()
			} catch (e) {
				showError(e?.response?.data?.ocs?.data?.message || this.t('deck', 'Error creating role'))
			} finally {
				this.creatingRole = false
			}
		},
	},
}
</script>

<style scoped>
.pc-policy-app {
	--pc-border: var(--color-border, #ededed);
	--pc-bg: var(--color-main-background, #fff);
	--pc-bg-hover: var(--color-background-hover, #f5f5f5);
	--pc-text: var(--color-main-text, #222);
	--pc-text-muted: var(--color-text-maxcontrast, #777);
	--pc-primary: var(--color-primary-element, #0082c9);
	--pc-primary-text: var(--color-primary-text, #fff);
	--pc-radius: var(--border-radius-large, 8px);

	position: relative;
	height: calc(100vh - 120px);
	min-height: 500px;
	display: flex;
	flex-direction: column;
	background: var(--pc-bg);
	border: 1px solid var(--pc-border);
	border-radius: var(--pc-radius);
	box-shadow: 0 2px 14px rgba(0, 0, 0, 0.05);
	overflow: hidden;
}

.pc-loading-state,
.pc-error-state,
.pc-enable-state {
	display: flex;
	flex-direction: column;
	align-items: center;
	justify-content: center;
	height: 100%;
	padding: 40px;
	text-align: center;
}

.pc-policy-container {
	display: flex;
	flex-direction: column;
	height: 100%;
	position: relative;
}

.pc-app-header {
	padding: 24px 32px;
	display: flex;
	justify-content: space-between;
	align-items: flex-start;
	background: var(--pc-bg);
	border-bottom: 1px solid var(--pc-border);
	z-index: 10;
}

.pc-header-titles h2 {
	margin: 0 0 8px;
	font-size: 24px;
	font-weight: 700;
	color: var(--pc-text);
}

.pc-header-titles p,
.muted {
	margin: 0;
	color: var(--pc-text-muted);
	font-size: 14px;
}

.pc-header-actions {
	display: flex;
	gap: 12px;
}

.pc-toolbar {
	padding: 16px 32px;
	display: flex;
	justify-content: space-between;
	align-items: center;
	background: var(--pc-bg-hover);
	border-bottom: 1px solid var(--pc-border);
	gap: 16px;
}

.pc-toolbar-search {
	width: 300px;
	max-width: 100%;
}

.pc-search-input {
	width: 100%;
	padding: 8px 14px;
	border: 1px solid var(--pc-border);
	border-radius: 20px;
	background: var(--pc-bg);
	color: var(--pc-text);
	transition: border-color 0.2s;
}

.pc-search-input:focus {
	border-color: var(--pc-primary);
	outline: none;
}

.pc-toolbar-filters {
	display: flex;
	align-items: center;
	gap: 24px;
}

.pc-stack-select {
	padding: 8px 12px;
	border: 1px solid var(--pc-border);
	border-radius: 8px;
	background: var(--pc-bg);
}

.pc-filter-checkbox {
	display: flex;
	align-items: center;
	gap: 8px;
	font-size: 14px;
	cursor: pointer;
	color: var(--pc-text);
}

.pc-data-grid-wrapper {
	flex: 1;
	overflow: auto;
	background: var(--pc-bg);
}

.pc-data-grid {
	width: 100%;
	border-collapse: collapse;
	text-align: left;
}

.pc-data-grid th {
	position: sticky;
	top: 0;
	background: var(--pc-bg);
	padding: 12px 16px;
	font-size: 13px;
	font-weight: 600;
	color: var(--pc-text-muted);
	border-bottom: 2px solid var(--pc-border);
	z-index: 5;
	white-space: nowrap;
}

.pc-data-grid td {
	padding: 14px 16px;
	border-bottom: 1px solid var(--pc-border);
	vertical-align: middle;
}

.pc-data-grid tr {
	transition: background-color 0.15s;
	cursor: pointer;
}

.pc-data-grid tr:hover {
	background-color: var(--pc-bg-hover);
}

.pc-data-grid tr.is-selected {
	background-color: rgba(0, 130, 201, 0.04);
}

.pc-col-check {
	width: 40px;
	text-align: center;
}

.pc-col-check input {
	width: 16px;
	height: 16px;
	cursor: pointer;
}

.pc-col-name {
	min-width: 250px;
	font-weight: 500;
	color: var(--pc-text);
}

.pc-col-stack {
	width: 150px;
}

.pc-col-perms {
	width: 250px;
}

.pc-col-actions {
	width: 80px;
	text-align: right;
}

.pc-card-title-wrap {
	display: flex;
	align-items: center;
	gap: 12px;
}

.pc-badge-custom {
	font-size: 10px;
	padding: 2px 6px;
	border-radius: 4px;
	background: var(--pc-primary);
	color: var(--pc-primary-text);
	text-transform: uppercase;
	font-weight: 700;
	letter-spacing: 0.5px;
}

.pc-stack-label {
	font-size: 13px;
	color: var(--pc-text-muted);
}

.muted-dash {
	color: var(--pc-border);
	font-weight: 700;
}

.pc-role-chips {
	display: flex;
	flex-wrap: wrap;
	gap: 6px;
}

.pc-role-chip {
	display: inline-flex;
	align-items: center;
	gap: 6px;
	padding: 2px 8px;
	border-radius: 999px;
	border: 1px solid var(--pc-border);
	font-size: 12px;
	background: var(--pc-bg);
}

.pc-dot {
	width: 8px;
	height: 8px;
	border-radius: 50%;
}

.pc-empty-row {
	text-align: center;
	padding: 28px;
	color: var(--pc-text-muted);
}

.pc-floating-action-bar {
	position: absolute;
	bottom: 24px;
	left: 50%;
	transform: translateX(-50%);
	background: var(--pc-bg);
	border: 1px solid var(--pc-border);
	border-radius: 12px;
	box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
	display: flex;
	align-items: center;
	padding: 12px 24px;
	gap: 24px;
	z-index: 50;
	white-space: nowrap;
}

.pc-fab-left {
	display: flex;
	flex-direction: column;
	gap: 4px;
}

.pc-fab-count {
	font-weight: 700;
	color: var(--pc-primary);
	font-size: 15px;
}

.pc-fab-clear {
	background: none;
	border: none;
	padding: 0;
	color: var(--pc-text-muted);
	font-size: 12px;
	cursor: pointer;
	text-decoration: underline;
	text-align: left;
}

.pc-fab-controls {
	display: flex;
	gap: 16px;
	border-left: 1px solid var(--pc-border);
	border-right: 1px solid var(--pc-border);
	padding: 0 20px;
}

.pc-fab-field {
	display: flex;
	align-items: center;
	gap: 10px;
}

.pc-fab-field label {
	font-weight: 600;
	font-size: 14px;
}

.pc-fab-field ::v-deep .multiselect {
	min-width: 180px;
}

.pc-fab-actions {
	display: flex;
	gap: 10px;
}

.pc-modal-content {
	padding: 24px;
}

.pc-modal-header-desc {
	margin-bottom: 24px;
	color: var(--pc-text-muted);
	line-height: 1.5;
}

.pc-modal-field {
	margin-bottom: 20px;
}

.pc-modal-field label {
	display: block;
	font-weight: 700;
	margin-bottom: 8px;
}

.pc-modal-footer {
	display: flex;
	justify-content: flex-end;
	gap: 12px;
	margin-top: 26px;
	border-top: 1px solid var(--pc-border);
	padding-top: 18px;
}

.pc-members-modal {
	padding: 0;
	display: flex;
	flex-direction: column;
	gap: 0;
}

.pc-modal-tabs {
	display: flex;
	gap: 8px;
	padding: 12px 24px;
	border-bottom: 1px solid var(--pc-border);
	background: var(--pc-bg);
}

.pc-modal-tab {
	appearance: none;
	border: 1px solid var(--pc-border);
	background: var(--pc-bg);
	color: var(--pc-text);
	border-radius: 999px;
	padding: 8px 12px;
	font-weight: 700;
	cursor: pointer;
	transition: background 0.15s ease, border-color 0.15s ease;
}

.pc-modal-tab:hover {
	background: var(--pc-bg-hover);
}

.pc-modal-tab--active {
	border-color: var(--pc-primary);
	box-shadow: 0 0 0 2px rgba(0, 130, 201, 0.15);
}

.pc-add-role-box {
	padding: 24px;
	background: var(--pc-bg-hover);
	border-bottom: 1px solid var(--pc-border);
}

.pc-add-role-box h4 {
	margin: 0 0 16px;
	font-size: 16px;
}

.pc-add-role-row {
	display: flex;
	gap: 16px;
	align-items: flex-end;
}

.pc-role-color-field {
	width: 120px;
}

.pc-role-color-label {
	display: block;
	font-weight: 700;
	margin-bottom: 8px;
}

.pc-role-color-swatch {
	width: 36px;
	height: 36px;
	border-radius: 10px;
	border: 1px solid var(--pc-border);
	box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
}

.pc-help-muted {
	margin: 12px 0 0;
	color: var(--pc-text-muted);
	font-size: 13px;
}

.pc-roles-list-box {
	padding: 24px;
	max-height: 320px;
	overflow-y: auto;
}

.pc-roles-list-box h4 {
	margin: 0 0 16px;
	font-size: 16px;
}

.pc-roles-chips {
	display: flex;
	flex-wrap: wrap;
	gap: 10px;
}

.pc-role-key {
	margin-left: 6px;
	color: var(--pc-text-muted);
	font-weight: 600;
}

.pc-add-member-box {
	padding: 24px;
	background: var(--pc-bg-hover);
	border-bottom: 1px solid var(--pc-border);
}

.pc-add-member-box h4 {
	margin: 0 0 16px;
	font-size: 16px;
}

.pc-add-member-row {
	display: flex;
	gap: 16px;
	align-items: center;
}

.pc-flex-1 {
	flex: 1;
}

.pc-members-list-box {
	padding: 24px;
	max-height: 500px;
	overflow-y: auto;
}

.pc-members-list-box h4 {
	margin: 0 0 16px;
	font-size: 16px;
}

.pc-members-empty {
	padding: 20px;
	text-align: center;
}

.pc-simple-table {
	width: 100%;
	border-collapse: collapse;
}

.pc-simple-table td {
	padding: 12px 8px;
	border-bottom: 1px solid var(--pc-border);
}

.pc-simple-td-role {
	width: 200px;
}

.pc-simple-td-action {
	width: 90px;
	text-align: right;
}

.pc-danger-btn {
	background: none;
	border: 1px solid var(--color-error);
	color: var(--color-error);
	border-radius: 4px;
	padding: 4px 12px;
	cursor: pointer;
	font-size: 12px;
	transition: all 0.2s;
}

.pc-danger-btn:hover {
	background: var(--color-error);
	color: #fff;
}

.slide-up-enter-active,
.slide-up-leave-active {
	transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
}

.slide-up-enter,
.slide-up-leave-to {
	opacity: 0;
	transform: translate(-50%, 20px);
}

@media (max-width: 1200px) {
	.pc-floating-action-bar {
		position: sticky;
		left: 0;
		bottom: 0;
		transform: none;
		margin: 8px;
		flex-wrap: wrap;
		white-space: normal;
	}

	.pc-fab-controls {
		border: none;
		padding: 0;
		width: 100%;
		justify-content: space-between;
	}
}

@media (max-width: 900px) {
	.pc-app-header,
	.pc-toolbar {
		padding: 14px;
		flex-direction: column;
		align-items: stretch;
		gap: 10px;
	}

	.pc-toolbar-search {
		width: 100%;
	}

	.pc-toolbar-filters {
		gap: 12px;
		flex-wrap: wrap;
	}

	.pc-add-member-row {
		flex-direction: column;
		align-items: stretch;
	}

	.pc-add-role-row {
		flex-direction: column;
		align-items: stretch;
	}

	.pc-role-color-field {
		width: auto;
	}
}
</style>
