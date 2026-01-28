<template>
    <div class="transition-permissions-manager">
        <div class="permissions-header">
            <h4>{{ t('deck', 'Stack Transition Permissions') }}</h4>
            <p class="description">
                {{ t('deck', 'Control who can move cards between columns using D-RASCI-VF roles') }}
            </p>
        </div>

        <!-- Add Permission Form -->
        <div class="add-permission-form">
            <h5>{{ t('deck', 'Add New Permission Rule') }}</h5>
            <div class="form-grid">
                <div class="form-field">
                    <label>{{ t('deck', 'From Stack') }}</label>
                    <NcSelect v-model="newPermission.fromStackId" :options="stackOptions"
                        :placeholder="t('deck', 'Any stack')" :clearable="true" label="title" track-by="id" />
                </div>

                <div class="form-field">
                    <label>{{ t('deck', 'To Stack') }} *</label>
                    <NcSelect v-model="newPermission.toStackId" :options="stackOptions"
                        :placeholder="t('deck', 'Select destination')" label="title" track-by="id" />
                </div>

                <div class="form-field">
                    <label>{{ t('deck', 'Required Roles') }} *</label>
                    <NcSelect v-model="newPermission.requiredRoles" :options="roleOptions"
                        :placeholder="t('deck', 'Select one or more roles')" label="label" track-by="value"
                        :multiple="true" :close-on-select="false" />
                </div>

                <div class="form-field">
                    <label>{{ t('deck', 'Participant Type') }} *</label>
                    <NcSelect v-model="newPermission.participantType" :options="participantTypeOptions"
                        :placeholder="t('deck', 'User or Group')" label="label" track-by="value" />
                </div>

                <div class="form-field participant-field">
                    <label>{{ t('deck', 'Participant') }} *</label>
                    <NcSelect v-model="newPermission.participant" :options="participantOptions"
                        :placeholder="getParticipantPlaceholder()" label="label" track-by="value" />
                </div>

                <div class="form-field form-actions">
                    <NcButton type="primary" :disabled="!canAddPermission" @click="addPermission">
                        <template #icon>
                            <Plus :size="20" />
                        </template>
                        {{ t('deck', 'Add Rule') }}
                    </NcButton>
                </div>
            </div>
        </div>

        <!-- Permissions List -->
        <div v-if="permissions.length > 0" class="permissions-list">
            <h5>{{ t('deck', 'Active Permission Rules') }}</h5>
            <div class="permissions-table">
                <div class="table-header">
                    <div class="col-from">{{ t('deck', 'From') }}</div>
                    <div class="col-to">{{ t('deck', 'To') }}</div>
                    <div class="col-role">{{ t('deck', 'Roles') }}</div>
                    <div class="col-participant">{{ t('deck', 'Who') }}</div>
                    <div class="col-actions">{{ t('deck', 'Actions') }}</div>
                </div>
                <div v-for="group in groupedPermissions" :key="group.key" class="table-row">
                    <div class="col-from">
                        <span class="stack-badge">
                            {{ getStackName(group.fromStackId) || 'Any' }}
                        </span>
                    </div>
                    <div class="col-to">
                        <span class="stack-badge">
                            {{ getStackName(group.toStackId) }}
                        </span>
                    </div>
                    <div class="col-role">
                        <div class="role-badges">
                            <span v-for="role in group.roles" :key="role.id" 
                                  class="role-badge clickable" :class="`role-${role.requiredRole}`"
                                  :title="t('deck', 'Click to remove this role')"
                                  @click="deletePermission(role.id)">
                                {{ getRoleLabel(role.requiredRole) }}
                                <span class="remove-icon">×</span>
                            </span>
                        </div>
                    </div>
                    <div class="col-participant">
                        <span class="participant-info">
                            <component :is="getParticipantIcon(group.participantType)" :size="16" />
                            {{ group.participant }}
                        </span>
                    </div>
                    <div class="col-actions">
                        <NcButton type="error" :aria-label="t('deck', 'Delete all roles for this user')"
                            @click="deleteGroupPermissions(group)">
                            <template #icon>
                                <Delete :size="20" />
                            </template>
                        </NcButton>
                    </div>
                </div>
            </div>
        </div>

        <div v-else class="empty-state">
            <p>{{ t('deck', 'No permission rules defined. All card movements are allowed.') }}</p>
        </div>

        <!-- Role Reference Guide -->
        <div class="role-guide">
            <h5>{{ t('deck', 'D-RASCI-VF Roles Reference') }}</h5>
            <div class="role-grid">
                <div v-for="role in roleOptions" :key="role.value" class="role-item">
                    <span class="role-badge" :class="`role-${role.value}`">{{ role.value }}</span>
                    <div class="role-info">
                        <strong>{{ role.label }}</strong>
                        <p>{{ role.description }}</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import { NcButton, NcSelect } from '@nextcloud/vue'
import { showError, showSuccess } from '@nextcloud/dialogs'
import Plus from 'vue-material-design-icons/Plus.vue'
import Delete from 'vue-material-design-icons/Delete.vue'
import Account from 'vue-material-design-icons/Account.vue'
import AccountGroup from 'vue-material-design-icons/AccountGroup.vue'
import GoogleCirclesExtended from 'vue-material-design-icons/GoogleCirclesExtended.vue'
import { StackTransitionPermissionApi } from '../../services/StackTransitionPermissionApi.js'

export default {
    name: 'TransitionPermissionsManager',
    components: {
        NcButton,
        NcSelect,
        Plus,
        Delete,
        Account,
        AccountGroup,
        GoogleCirclesExtended,
    },
    props: {
        boardId: {
            type: Number,
            required: true,
        },
    },
    data() {
        return {
            permissions: [],
            loading: false,
            newPermission: {
                fromStackId: null,
                toStackId: null,
                requiredRoles: [],
                participant: '',
                participantType: null,
            },
            roleOptions: [
                { value: 'D', label: 'Driver', description: 'Initiates and drives the work forward' },
                { value: 'R', label: 'Responsible', description: 'Does the actual work' },
                { value: 'A', label: 'Accountable', description: 'Ultimately answerable for completion' },
                { value: 'S', label: 'Support', description: 'Provides support and resources' },
                { value: 'C', label: 'Consulted', description: 'Provides input and feedback' },
                { value: 'I', label: 'Informed', description: 'Kept informed of progress' },
                { value: 'V', label: 'Verify', description: 'Verifies that work meets requirements' },
                { value: 'F', label: 'Final Approver', description: 'Gives final approval' },
            ],
            participantTypeOptions: [
                { value: 0, label: 'User' },
                { value: 1, label: 'Group' },
                { value: 7, label: 'Circle' },
            ],
            api: new StackTransitionPermissionApi(),
        }
    },
    computed: {
        stackOptions() {
            return this.$store.getters.stacksByBoard(this.boardId)
        },
        boardUsers() {
            // Get all users who have access to this board
            return this.$store.getters.assignableUsers || []
        },
        participantOptions() {
            if (!this.newPermission.participantType) {
                return []
            }

            if (this.newPermission.participantType.value === 0) {
                // Users - return board users
                return this.boardUsers.map(user => ({
                    value: user.uid || user.id || user.userId,
                    label: user.displayname || user.displayName || user.uid || user.id || user.userId
                }))
            }

            // For groups and circles, return empty for now (can be enhanced later)
            return []
        },
        canAddPermission() {
            return this.newPermission.toStackId
                && this.newPermission.requiredRoles
                && this.newPermission.requiredRoles.length > 0
                && this.newPermission.participant
                && this.newPermission.participantType !== null
        },
        groupedPermissions() {
            // Group permissions by fromStackId + toStackId + participant + participantType
            const groups = {}
            for (const permission of this.permissions) {
                const key = `${permission.fromStackId || 'any'}-${permission.toStackId}-${permission.participant}-${permission.participantType}`
                if (!groups[key]) {
                    groups[key] = {
                        key,
                        fromStackId: permission.fromStackId,
                        toStackId: permission.toStackId,
                        participant: permission.participant,
                        participantType: permission.participantType,
                        roles: []
                    }
                }
                groups[key].roles.push({
                    id: permission.id,
                    requiredRole: permission.requiredRole
                })
            }
            return Object.values(groups)
        },
    },
    mounted() {
        this.loadPermissions()
    },
    methods: {
        async loadPermissions() {
            this.loading = true
            try {
                const response = await this.api.getTransitionPermissions(this.boardId)
                this.permissions = response
            } catch (error) {
                console.error('Error loading permissions:', error)
                showError(t('deck', 'Failed to load permission rules'))
            } finally {
                this.loading = false
            }
        },
        async addPermission() {
            if (!this.canAddPermission) return

            try {
                const baseData = {
                    fromStackId: this.newPermission.fromStackId?.id || null,
                    toStackId: this.newPermission.toStackId.id,
                    participant: typeof this.newPermission.participant === 'string'
                        ? this.newPermission.participant.trim()
                        : this.newPermission.participant.value,
                    participantType: this.newPermission.participantType.value,
                }

                // Create one permission record per selected role
                const selectedRoles = this.newPermission.requiredRoles
                const promises = selectedRoles.map(role => {
                    return this.api.addTransitionPermission(this.boardId, {
                        ...baseData,
                        requiredRole: role.value,
                    })
                })

                await Promise.all(promises)
                
                const roleCount = selectedRoles.length
                showSuccess(t('deck', '{count} permission rule(s) added', { count: roleCount }))

                // Reset form
                this.newPermission = {
                    fromStackId: null,
                    toStackId: null,
                    requiredRoles: [],
                    participant: '',
                    participantType: null,
                }

                // Reload permissions
                await this.loadPermissions()
            } catch (error) {
                console.error('Error adding permission:', error)
                showError(error.response?.data?.message || t('deck', 'Failed to add permission rule'))
            }
        },
        async deletePermission(id) {
            if (!confirm(t('deck', 'Are you sure you want to delete this permission rule?'))) {
                return
            }

            try {
                await this.api.deleteTransitionPermission(id)
                showSuccess(t('deck', 'Permission rule deleted'))
                await this.loadPermissions()
            } catch (error) {
                console.error('Error deleting permission:', error)
                showError(t('deck', 'Failed to delete permission rule'))
            }
        },
        async deleteGroupPermissions(group) {
            if (!confirm(t('deck', 'Are you sure you want to delete all {count} role(s) for this user?', { count: group.roles.length }))) {
                return
            }

            try {
                const promises = group.roles.map(role => this.api.deleteTransitionPermission(role.id))
                await Promise.all(promises)
                showSuccess(t('deck', '{count} permission rule(s) deleted', { count: group.roles.length }))
                await this.loadPermissions()
            } catch (error) {
                console.error('Error deleting permissions:', error)
                showError(t('deck', 'Failed to delete permission rules'))
            }
        },
        getStackName(stackId) {
            if (!stackId) return null
            const stack = this.stackOptions.find(s => s.id === stackId)
            return stack?.title || `Stack #${stackId}`
        },
        getRoleLabel(roleValue) {
            const role = this.roleOptions.find(r => r.value === roleValue)
            return role?.label || roleValue
        },
        getParticipantIcon(type) {
            switch (type) {
                case 0: return 'Account'
                case 1: return 'AccountGroup'
                case 7: return 'GoogleCirclesExtended'
                default: return 'Account'
            }
        },
        getParticipantPlaceholder() {
            if (!this.newPermission.participantType) {
                return t('deck', 'Select type first')
            }
            switch (this.newPermission.participantType.value) {
                case 0: return t('deck', 'Select user')
                case 1: return t('deck', 'Enter group name')
                case 7: return t('deck', 'Enter circle name')
                default: return t('deck', 'Select participant')
            }
        },
    },
}
</script>

<style lang="scss" scoped>
.transition-permissions-manager {
    padding: 20px;
    background: var(--color-main-background);
}

.permissions-header {
    margin-bottom: 24px;

    h4 {
        margin: 0 0 8px 0;
        font-size: 16px;
        font-weight: 600;
        color: var(--color-main-text);
    }

    .description {
        margin: 0;
        color: var(--color-text-maxcontrast);
        font-size: 14px;
    }
}

.add-permission-form {
    background: var(--color-background-dark);
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 24px;

    h5 {
        margin: 0 0 16px 0;
        font-size: 14px;
        font-weight: 600;
        color: var(--color-main-text);
    }

    .form-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 20px 16px;
        align-items: end;
        overflow: auto;

        @media (min-width: 768px) {
            grid-template-columns: repeat(3, 1fr);
        }

        .form-actions {
            grid-column: 1 / -1;
            display: flex;
            justify-content: flex-end;
            margin-top: 8px;
        }
    }

    .form-field {
        display: flex;
        flex-direction: column;
        gap: 8px;

        label {
            font-size: 13px;
            font-weight: 500;
            color: var(--color-text-maxcontrast);
        }

        .participant-input {
            padding: 8px 12px;
            border: 1px solid var(--color-border-dark);
            border-radius: 6px;
            background: var(--color-main-background);
            color: var(--color-main-text);
            font-size: 14px;

            &:focus {
                outline: none;
                border-color: var(--color-primary-element);
            }
        }

        &.form-actions {
            justify-content: flex-end;
        }
    }
}

.permissions-list {
    margin-bottom: 24px;

    h5 {
        margin: 0 0 16px 0;
        font-size: 14px;
        font-weight: 600;
    }
}

.permissions-table {
    border: 1px solid var(--color-border);
    border-radius: 8px;
    overflow: hidden;

    .table-header,
    .table-row {
        display: grid;
        grid-template-columns: 1.5fr 1.5fr 1fr 1.5fr 100px;
        gap: 12px;
        padding: 12px 16px;
        align-items: center;
    }

    .table-header {
        background: var(--color-background-dark);
        font-weight: 600;
        font-size: 13px;
        color: var(--color-text-maxcontrast);
    }

    .table-row {
        border-top: 1px solid var(--color-border);

        &:hover {
            background: var(--color-background-hover);
        }
    }

    .stack-badge {
        display: inline-block;
        padding: 4px 12px;
        background: var(--color-background-dark);
        border-radius: 12px;
        font-size: 13px;
        font-weight: 500;

        &.primary {
            background: var(--color-primary-element-light);
            color: var(--color-primary-element-text);
        }
    }

    .role-badges {
        display: flex;
        flex-wrap: wrap;
        gap: 4px;
    }

    .role-badge {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 4px 10px;
        border-radius: 4px;
        font-size: 12px;
        font-weight: 600;
        text-align: center;
        min-width: 24px;
        transition: all 0.2s ease;

        &.clickable {
            cursor: pointer;

            &:hover {
                transform: scale(1.05);
                box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
            }

            .remove-icon {
                opacity: 0;
                transition: opacity 0.2s ease;
                font-size: 14px;
                font-weight: bold;
            }

            &:hover .remove-icon {
                opacity: 1;
            }
        }

        &.role-D {
            background: #e91e63;
            color: white;
        }

        &.role-R {
            background: #9c27b0;
            color: white;
        }

        &.role-A {
            background: #3f51b5;
            color: white;
        }

        &.role-S {
            background: #00bcd4;
            color: white;
        }

        &.role-C {
            background: #4caf50;
            color: white;
        }

        &.role-I {
            background: #8bc34a;
            color: white;
        }

        &.role-V {
            background: #ff9800;
            color: white;
        }

        &.role-F {
            background: #f44336;
            color: white;
        }
    }

    .participant-info {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 14px;
    }
}

.empty-state {
    text-align: center;
    padding: 40px 20px;
    margin-bottom: 24px;
    color: var(--color-text-maxcontrast);
    background: var(--color-background-dark);
    border-radius: 8px;
    font-style: italic;
}

.role-guide {
    background: var(--color-background-dark);
    border-radius: 8px;
    padding: 20px;

    h5 {
        margin: 0 0 16px 0;
        font-size: 14px;
        font-weight: 600;
    }

    .role-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
        gap: 12px;
    }

    .role-item {
        display: flex;
        gap: 12px;
        align-items: start;
        padding: 12px;
        background: var(--color-main-background);
        border-radius: 6px;

        .role-info {
            flex: 1;

            strong {
                display: block;
                font-size: 13px;
                margin-bottom: 4px;
            }

            p {
                margin: 0;
                font-size: 12px;
                color: var(--color-text-maxcontrast);
            }
        }
    }
}
</style>
