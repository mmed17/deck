<template>
    <div class="role-profile-manager">
        <div class="profile-header">
            <h4>{{ t('deck', 'D-RASCI-VF Role Profiles') }}</h4>
            <p class="description">
                {{ t('deck', 'Save and apply role templates across projects in your organization') }}
            </p>
        </div>

        <!-- Actions Bar -->
        <div class="actions-bar">
            <NcButton type="primary" @click="saveAsProfile">
                <template #icon>
                    <ContentSave :size="20" />
                </template>
                {{ t('deck', 'Save Current as Profile') }}
            </NcButton>

            <div class="apply-profile-section">
                <NcSelect
                    v-model="selectedProfile"
                    :options="profiles"
                    :placeholder="t('deck', 'Select a profile to apply')"
                    label="name"
                    track-by="id"
                    class="profile-select"
                />
                <NcButton
                    type="secondary"
                    :disabled="!selectedProfile"
                    @click="applyProfile"
                >
                    <template #icon>
                        <Download :size="20" />
                    </template>
                    {{ t('deck', 'Apply Profile') }}
                </NcButton>
            </div>
        </div>

        <!-- Save Profile Modal -->
        <NcModal v-if="showSaveModal" @close="showSaveModal = false">
            <div class="save-modal-content">
                <h3>{{ t('deck', 'Save Role Profile') }}</h3>
                <p>{{ t('deck', 'Save the current D-RASCI-VF configuration as a reusable template for your organization.') }}</p>
                
                <div class="form-field">
                    <label>{{ t('deck', 'Profile Name') }} *</label>
                    <input
                        v-model="newProfileName"
                        type="text"
                        class="profile-name-input"
                        :placeholder="t('deck', 'e.g., Standard Review Process')"
                    />
                </div>

                <div class="modal-actions">
                    <NcButton type="tertiary" @click="showSaveModal = false">
                        {{ t('deck', 'Cancel') }}
                    </NcButton>
                    <NcButton
                        type="primary"
                        :disabled="!newProfileName.trim()"
                        @click="confirmSaveProfile"
                    >
                        {{ t('deck', 'Save Profile') }}
                    </NcButton>
                </div>
            </div>
        </NcModal>

        <!-- Apply Profile Modal -->
        <NcModal v-if="showApplyModal" @close="showApplyModal = false">
            <div class="apply-modal-content">
                <h3>{{ t('deck', 'Apply Role Profile') }}</h3>
                <p>{{ t('deck', 'Apply "{name}" to this board?', { name: selectedProfile?.name }) }}</p>
                
                <div class="apply-options">
                    <NcCheckboxRadioSwitch
                        v-model="clearExisting"
                        type="switch"
                    >
                        {{ t('deck', 'Clear existing permissions first') }}
                    </NcCheckboxRadioSwitch>
                    <p class="option-note">
                        {{ t('deck', 'If disabled, profile permissions will be added to existing ones.') }}
                    </p>
                </div>

                <NcNoteCard type="warning">
                    {{ t('deck', 'Only permissions for stacks with matching names will be created. Users must exist in the board.') }}
                </NcNoteCard>

                <div class="modal-actions">
                    <NcButton type="tertiary" @click="showApplyModal = false">
                        {{ t('deck', 'Cancel') }}
                    </NcButton>
                    <NcButton type="primary" @click="confirmApplyProfile">
                        {{ t('deck', 'Apply') }}
                    </NcButton>
                </div>
            </div>
        </NcModal>

        <!-- Profile Details Modal -->
        <NcModal v-if="showDetailsModal" size="large" @close="showDetailsModal = false">
            <div class="details-modal-content">
                <h3>{{ viewingProfile?.name }}</h3>
                <p class="profile-owner">{{ t('deck', 'Created by {owner}', { owner: viewingProfile?.ownerId }) }}</p>
                
                <div v-if="viewingProfile?.permissions?.length > 0" class="permissions-table">
                    <div class="table-header">
                        <div class="col">{{ t('deck', 'From Stack') }}</div>
                        <div class="col">{{ t('deck', 'To Stack') }}</div>
                        <div class="col">{{ t('deck', 'Role') }}</div>
                        <div class="col">{{ t('deck', 'Participant') }}</div>
                    </div>
                    <div v-for="(perm, index) in viewingProfile.permissions" :key="index" class="table-row">
                        <div class="col">
                            <span class="stack-badge">{{ perm.fromStackName || t('deck', 'Any') }}</span>
                        </div>
                        <div class="col">
                            <span class="stack-badge">{{ perm.toStackName }}</span>
                        </div>
                        <div class="col">
                            <span class="role-badge" :class="'role-' + perm.requiredRole">{{ getRoleLabel(perm.requiredRole) }}</span>
                        </div>
                        <div class="col">
                            <span class="participant-info">{{ perm.participant }}</span>
                        </div>
                    </div>
                </div>
                <div v-else class="empty-permissions">
                    {{ t('deck', 'This profile has no permission rules defined.') }}
                </div>

                <div class="modal-actions">
                    <NcButton type="tertiary" @click="showDetailsModal = false">
                        {{ t('deck', 'Close') }}
                    </NcButton>
                </div>
            </div>
        </NcModal>

        <!-- Profiles List -->
        <div v-if="profiles.length > 0" class="profiles-list">
            <h5>{{ t('deck', 'Organization Profiles') }}</h5>
            <div class="profiles-grid">
                <div v-for="profile in profiles" :key="profile.id" class="profile-card" @click="viewProfile(profile)">
                    <div class="profile-info">
                        <strong>{{ profile.name }}</strong>
                        <span class="profile-meta">
                            {{ t('deck', '{count} rules', { count: profile.permissions?.length || 0 }) }}
                            · {{ t('deck', 'by {owner}', { owner: profile.ownerId }) }}
                        </span>
                    </div>
                    <div class="profile-actions">
                        <NcButton type="tertiary" :aria-label="t('deck', 'View details')" @click.stop="viewProfile(profile)">
                            <template #icon>
                                <Eye :size="20" />
                            </template>
                        </NcButton>
                        <NcButton type="tertiary" :aria-label="t('deck', 'Delete profile')" @click.stop="deleteProfile(profile)">
                            <template #icon>
                                <Delete :size="20" />
                            </template>
                        </NcButton>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import { NcButton, NcSelect, NcModal, NcCheckboxRadioSwitch, NcNoteCard } from '@nextcloud/vue'
import { showError, showSuccess } from '@nextcloud/dialogs'
import ContentSave from 'vue-material-design-icons/ContentSave.vue'
import Download from 'vue-material-design-icons/Download.vue'
import Delete from 'vue-material-design-icons/Delete.vue'
import Eye from 'vue-material-design-icons/Eye.vue'
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'

export default {
    name: 'RoleProfileManager',
    components: {
        NcButton,
        NcSelect,
        NcModal,
        NcCheckboxRadioSwitch,
        NcNoteCard,
        ContentSave,
        Download,
        Delete,
        Eye,
    },
    props: {
        boardId: {
            type: Number,
            required: true,
        },
        organizationId: {
            type: Number,
            required: true,
        },
    },
    data() {
        return {
            profiles: [],
            selectedProfile: null,
            showSaveModal: false,
            showApplyModal: false,
            newProfileName: '',
            clearExisting: false,
            loading: false,
            showDetailsModal: false,
            viewingProfile: null,
            roleLabels: {
                'D': 'Driver',
                'R': 'Responsible',
                'A': 'Accountable',
                'S': 'Support',
                'C': 'Consulted',
                'I': 'Informed',
                'V': 'Verify',
                'F': 'Final Approver',
            },
        }
    },
    watch: {
        organizationId(newVal, oldVal) {
            if (newVal !== oldVal) {
                this.profiles = []
                this.selectedProfile = null
                this.loadProfiles()
            }
        },
    },
    mounted() {
        this.loadProfiles()
    },
    methods: {
        async loadProfiles() {
            if (!this.organizationId) return
            
            this.loading = true
            try {
                const url = generateUrl('/apps/deck/organizations/' + this.organizationId + '/role-profiles')
                const response = await axios.get(url)
                this.profiles = response.data || []
            } catch (error) {
                console.error('Error loading profiles:', error)
                // Silently fail - profiles feature may not be available
            } finally {
                this.loading = false
            }
        },
        saveAsProfile() {
            this.newProfileName = ''
            this.showSaveModal = true
        },
        async confirmSaveProfile() {
            if (!this.newProfileName.trim()) return

            try {
                const url = generateUrl('/apps/deck/boards/' + this.boardId + '/export-role-profile')
                await axios.post(url, {
                    name: this.newProfileName.trim(),
                    organizationId: this.organizationId,
                })
                showSuccess(t('deck', 'Profile saved successfully'))
                this.showSaveModal = false
                await this.loadProfiles()
            } catch (error) {
                console.error('Error saving profile:', error)
                showError(error.response?.data?.error || t('deck', 'Failed to save profile'))
            }
        },
        applyProfile() {
            if (!this.selectedProfile) return
            this.clearExisting = false
            this.showApplyModal = true
        },
        async confirmApplyProfile() {
            if (!this.selectedProfile) return

            try {
                const url = generateUrl('/apps/deck/boards/' + this.boardId + '/apply-role-profile/' + this.selectedProfile.id)
                const response = await axios.post(url, {
                    clearExisting: this.clearExisting,
                })
                const result = response.data
                showSuccess(t('deck', 'Profile applied: {created} rules created, {skipped} skipped', {
                    created: result.created,
                    skipped: result.skipped,
                }))
                this.showApplyModal = false
                this.selectedProfile = null
                // Emit event to refresh permissions list
                this.$emit('profile-applied')
            } catch (error) {
                console.error('Error applying profile:', error)
                showError(error.response?.data?.error || t('deck', 'Failed to apply profile'))
            }
        },
        async deleteProfile(profile) {
            if (!confirm(t('deck', 'Are you sure you want to delete the profile "{name}"?', { name: profile.name }))) {
                return
            }

            try {
                const url = generateUrl('/apps/deck/role-profiles/' + profile.id)
                await axios.delete(url)
                showSuccess(t('deck', 'Profile deleted'))
                await this.loadProfiles()
            } catch (error) {
                console.error('Error deleting profile:', error)
                showError(t('deck', 'Failed to delete profile'))
            }
        },
        async viewProfile(profile) {
            try {
                const url = generateUrl('/apps/deck/role-profiles/' + profile.id)
                const response = await axios.get(url)
                this.viewingProfile = response.data
                this.showDetailsModal = true
            } catch (error) {
                console.error('Error loading profile details:', error)
                showError(t('deck', 'Failed to load profile details'))
            }
        },
        getRoleLabel(roleValue) {
            return this.roleLabels[roleValue] || roleValue
        },
    },
}
</script>

<style lang="scss" scoped>
.role-profile-manager {
    background: var(--color-main-background);
    border-bottom: 1px solid var(--color-border);
    margin-bottom: 20px;
}

.profile-header {
    margin-bottom: 16px;

    h4 {
        margin: 0 0 4px 0;
        font-size: 14px;
        font-weight: 600;
    }

    .description {
        margin: 0;
        font-size: 13px;
        color: var(--color-text-maxcontrast);
    }
}

.actions-bar {
    display: flex;
    gap: 16px;
    align-items: center;
    flex-wrap: wrap;
    margin-bottom: 20px;

    .apply-profile-section {
        display: flex;
        gap: 8px;
        align-items: center;
        flex: 1;
        min-width: 300px;

        .profile-select {
            flex: 1;
            max-width: 300px;
        }
    }
}

.save-modal-content,
.apply-modal-content {
    padding: 20px;
    max-width: 480px;

    h3 {
        margin: 0 0 8px 0;
    }

    p {
        margin: 0 0 20px 0;
        color: var(--color-text-maxcontrast);
    }

    .form-field {
        margin-bottom: 20px;

        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
        }

        .profile-name-input {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid var(--color-border-dark);
            border-radius: 6px;
            font-size: 14px;

            &:focus {
                outline: none;
                border-color: var(--color-primary-element);
            }
        }
    }

    .apply-options {
        margin-bottom: 16px;

        .option-note {
            margin: 8px 0 0 0;
            font-size: 12px;
        }
    }

    .modal-actions {
        display: flex;
        gap: 8px;
        justify-content: flex-end;
        margin-top: 20px;
    }
}

.profiles-list {
    h5 {
        margin: 0 0 12px 0;
        font-size: 13px;
        font-weight: 600;
        color: var(--color-text-maxcontrast);
    }
}

.profiles-grid {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.profile-card {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px 16px;
    background: var(--color-background-dark);
    border-radius: 8px;

    .profile-info {
        display: flex;
        flex-direction: column;
        gap: 2px;

        strong {
            font-size: 14px;
        }

        .profile-meta {
            font-size: 12px;
            color: var(--color-text-maxcontrast);
        }
    }

    .profile-actions {
        display: flex;
        gap: 4px;
    }

    &:hover {
        background: var(--color-background-hover);
        cursor: pointer;
    }
}

.details-modal-content {
    padding: 20px;
    min-width: 600px;

    h3 {
        margin: 0 0 4px 0;
    }

    .profile-owner {
        margin: 0 0 20px 0;
        font-size: 13px;
        color: var(--color-text-maxcontrast);
    }

    .permissions-table {
        border: 1px solid var(--color-border);
        border-radius: 8px;
        overflow: hidden;
        margin-bottom: 20px;

        .table-header,
        .table-row {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr 1.5fr;
            gap: 12px;
            padding: 10px 16px;
            align-items: center;
        }

        .table-header {
            background: var(--color-background-dark);
            font-weight: 600;
            font-size: 12px;
            color: var(--color-text-maxcontrast);
        }

        .table-row {
            border-top: 1px solid var(--color-border);
            font-size: 13px;

            &:hover {
                background: var(--color-background-hover);
            }
        }

        .stack-badge {
            display: inline-block;
            padding: 3px 8px;
            background: var(--color-background-dark);
            border-radius: 4px;
            font-size: 12px;
        }

        .role-badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 600;
            color: white;

            &.role-D { background: #e91e63; }
            &.role-R { background: #9c27b0; }
            &.role-A { background: #3f51b5; }
            &.role-S { background: #00bcd4; }
            &.role-C { background: #4caf50; }
            &.role-I { background: #8bc34a; }
            &.role-V { background: #ff9800; }
            &.role-F { background: #f44336; }
        }
    }

    .empty-permissions {
        padding: 40px 20px;
        text-align: center;
        color: var(--color-text-maxcontrast);
        background: var(--color-background-dark);
        border-radius: 8px;
        margin-bottom: 20px;
    }

    .modal-actions {
        display: flex;
        gap: 8px;
        justify-content: flex-end;
    }
}
</style>
