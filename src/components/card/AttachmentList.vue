<!--
  - SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<AttachmentDragAndDrop
		:card-id="cardId"
		:defer-upload="true"
		class="drop-upload--sidebar"
		@files-dropped="openUploadModalForFiles">
		<div v-if="!isReadOnly" class="button-group">
			<NcButton class="icon-upload" @click="uploadNewFile()">
				{{ t('deck', 'Upload new files') }}
			</NcButton>
			<NcButton class="icon-folder" @click="shareFromFiles()">
				{{ t('deck', 'Share from Files') }}
			</NcButton>
		</div>
		<input ref="filesAttachment"
			type="file"
			style="display: none;"
			multiple
			@change="handleUploadFile">
		<ul class="attachment-list">
			<li v-for="attachment in uploadQueue" :key="attachment.name" class="attachment">
				<a class="fileicon" :style="mimetypeForAttachment()" />
				<div class="details">
					<a>
						<div class="filename">
							<span>{{ attachmentBasename(attachment) }}</span>
							<span class="extension">.{{ attachmentExtension(attachment) }}</span>
						</div>
						<progress :value="attachment.progress" max="100" />
					</a>
				</div>
			</li>
			<li v-for="attachment in attachments"
				:key="attachment.id"
				class="attachment"
				:class="{ 'attachment--deleted': attachment.deletedAt > 0 }">
				<a class="fileicon"
					:href="internalLink(attachment)"
					:style="mimetypeForAttachment(attachment)"
					@click.prevent="showViewer(attachment)" />
				<div class="details">
					<a :href="internalLink(attachment)" @click.prevent="showViewer(attachment)">
						<div class="filename">
							<span>{{ attachmentBasename(attachment) }}</span>
							<span class="extension">.{{ attachmentExtension(attachment) }}</span>
						</div>
						<div v-if="attachment.deletedAt === 0">
							<span class="filesize">{{ formattedFileSize(attachment.extendedData.filesize) }}</span>
							<span class="filedate">{{ relativeDate(attachment.createdAt*1000) }}</span>
							<span class="filedate">{{ attachment.extendedData.attachmentCreator.displayName }}</span>
						</div>
						<div v-else>
							<span class="attachment--info">{{ t('deck', 'Pending share') }}</span>
						</div>
					</a>
					<div v-if="showOcrForAttachment(attachment)" class="attachment__ocr" @click.stop>
						<span class="attachment__ocr-type-label">
							{{ processingDocumentTypeLabel(attachmentFileId(attachment)) }}
						</span>
						<div class="attachment__ocr-status"
							:class="statusBadgeClass(attachmentFileId(attachment))"
							:title="statusTooltip(attachmentFileId(attachment))">
							<component :is="statusIcon(attachmentFileId(attachment))" :size="16" />
						</div>
						<button v-if="canOpenExtractedDataModal(attachmentFileId(attachment))"
							type="button"
							class="attachment__ocr-icon-btn"
							:title="t('deck', 'View Extracted Data')"
							@click="openExtractedDataModal(attachmentFileId(attachment))">
							<EyeOutline :size="16" />
						</button>
						<button v-if="canReprocess(attachmentFileId(attachment))"
							type="button"
							class="attachment__ocr-icon-btn"
							:title="t('deck', 'Reprocess OCR')"
							@click="reprocessAttachment(attachment)">
							<Refresh :size="16" />
						</button>
						<div v-if="isProcessingBusy(attachmentFileId(attachment))" class="attachment__ocr-loading">
							<NcLoadingIcon :size="18" />
						</div>
					</div>
				</div>
				<NcActions v-if="selectable">
					<NcActionButton icon="icon-confirm" @click="$emit('select-attachment', attachment)">
						{{ t('deck', 'Add this attachment') }}
					</NcActionButton>
				</NcActions>
				<NcActions v-if="removable && !isReadOnly" :force-menu="true">
					<NcActionLink v-if="attachment.extendedData.fileid" icon="icon-folder" :href="internalLink(attachment)">
						{{ t('deck', 'Show in Files') }}
					</NcActionLink>
					<NcActionLink v-if="attachment.extendedData.fileid"
						icon="icon-download"
						:href="downloadLink(attachment)"
						download>
						{{ t('deck', 'Download') }}
					</NcActionLink>
					<NcActionButton v-if="attachment.extendedData.fileid && !isReadOnly" icon="icon-delete" @click="unshareAttachment(attachment)">
						{{ t('deck', 'Remove attachment') }}
					</NcActionButton>

					<NcActionButton v-if="!attachment.extendedData.fileid && attachment.deletedAt === 0" icon="icon-delete" @click="$emit('delete-attachment', attachment)">
						{{ t('deck', 'Delete Attachment') }}
					</NcActionButton>
					<NcActionButton v-else-if="!attachment.extendedData.fileid" icon="icon-history" @click="$emit('restore-attachment', attachment)">
						{{ t('deck', 'Restore Attachment') }}
					</NcActionButton>
				</NcActions>
			</li>
		</ul>

		<NcModal v-if="showUploadModal" :title="t('deck', 'Upload files')" @close="closeUploadModal">
			<div class="attachment__upload-modal">
				<div class="attachment__upload-modal-card">
					{{ t('deck', 'Card attachments') }}
				</div>
				<label class="attachment__upload-modal-label" for="attachment-upload-type">
					{{ t('deck', 'Document type') }}
				</label>
				<select
					id="attachment-upload-type"
					v-model="uploadDocumentTypeId"
					class="attachment__upload-type-select"
					:disabled="documentTypesLoading || documentTypes.length === 0 || uploadBusy">
					<option value="">
						{{ documentTypes.length === 0 ? t('deck', 'No document types') : t('deck', 'Select document type...') }}
					</option>
					<option v-for="type in documentTypes" :key="`upload-doc-type-${type.id}`" :value="String(type.id)">
						{{ type.name }}
					</option>
				</select>
				<div class="attachment__upload-modal-row">
					<NcButton :disabled="uploadBusy" @click="$refs.filesAttachment?.click?.()">
						{{ t('deck', 'Choose files') }}
					</NcButton>
					<span class="attachment__upload-modal-hint">
						{{ selectedUploadFiles.length === 0
							? t('deck', 'No files selected yet.')
							: t('deck', '{count} file(s) selected.', { count: selectedUploadFiles.length }) }}
					</span>
				</div>
				<ul v-if="selectedUploadFiles.length > 0" class="attachment__upload-file-list">
					<li
						v-for="file in selectedUploadFiles"
						:key="`upload-file-${file.name}-${file.size}`"
						class="attachment__upload-file-item">
						<span>{{ file.name }}</span>
						<span>{{ formattedFileSize(file.size) }}</span>
					</li>
				</ul>
				<div class="attachment__ocr-actions attachment__ocr-actions--upload">
					<NcButton :disabled="uploadBusy" @click="closeUploadModal">
						{{ t('deck', 'Cancel') }}
					</NcButton>
					<NcButton
						:disabled="uploadBusy || !uploadDocumentTypeId || selectedUploadFiles.length === 0"
						@click="uploadSelectedFiles">
						{{ uploadBusy ? t('deck', 'Uploading...') : t('deck', 'Upload and process') }}
					</NcButton>
				</div>
			</div>
		</NcModal>

		<NcModal v-if="activeExtractedFileId" :title="t('deck', 'Extracted Data')" @close="closeExtractedDataModal">
			<div class="attachment__ocr-modal">
				<div class="attachment__ocr-modal-filename">
					{{ activeExtractedFileName }}
				</div>
				<div v-if="activeExtractedData.length === 0" class="attachment__ocr-empty">
					{{ t('deck', 'No data extracted yet.') }}
				</div>
				<div v-if="activeExtractedData.length > 0 && activeMissingFieldsCount > 0" class="attachment__ocr-warning">
					{{ t('deck', '{count} field(s) still missing.', { count: activeMissingFieldsCount }) }}
				</div>
				<table v-if="activeExtractedData.length > 0" class="attachment__ocr-table">
					<tbody>
						<tr v-for="item in activeExtractedData" :key="item.key">
							<th>
								{{ item.name }}
								<span v-if="item.missing" class="attachment__ocr-missing-pill">{{ t('deck', 'Missing') }}</span>
							</th>
							<td>
								<input class="attachment__ocr-input"
									:value="activeExtractedDraft[item.key] ?? ''"
									@input="setActiveExtractedDraft(item.key, $event.target.value)">
							</td>
						</tr>
					</tbody>
				</table>
				<div v-if="activeExtractedData.length > 0" class="attachment__ocr-actions">
					<NcButton :disabled="isSavingExtracted(activeExtractedFileId)" @click="saveActiveExtractedData">
						{{ isSavingExtracted(activeExtractedFileId) ? t('deck', 'Saving...') : t('deck', 'Save extracted fields') }}
					</NcButton>
				</div>
			</div>
		</NcModal>
	</AttachmentDragAndDrop>
</template>

<script>
import axios from '@nextcloud/axios'
import { NcActions, NcActionButton, NcActionLink, NcButton, NcModal } from '@nextcloud/vue'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import AttachmentDragAndDrop from '../AttachmentDragAndDrop.vue'
import relativeDate from '../../mixins/relativeDate.js'
import { formatFileSize } from '@nextcloud/files'
import { getCurrentUser } from '@nextcloud/auth'
import { generateUrl, generateOcsUrl, generateRemoteUrl } from '@nextcloud/router'
import { mapState, mapActions } from 'vuex'
import { loadState } from '@nextcloud/initial-state'
import attachmentUpload from '../../mixins/attachmentUpload.js'
import { getFilePickerBuilder, showError } from '@nextcloud/dialogs'
import AlertCircleOutline from 'vue-material-design-icons/AlertCircleOutline.vue'
import CheckCircleOutline from 'vue-material-design-icons/CheckCircleOutline.vue'
import ClockOutline from 'vue-material-design-icons/ClockOutline.vue'
import EyeOutline from 'vue-material-design-icons/EyeOutline.vue'
import FileQuestionOutline from 'vue-material-design-icons/FileQuestionOutline.vue'
import Refresh from 'vue-material-design-icons/Refresh.vue'
import Sync from 'vue-material-design-icons/Sync.vue'
import { ProjectOcrApi } from '../../services/ProjectOcrApi.js'

const maxUploadSizeState = loadState('deck', 'maxUploadSize', -1)
const projectOcrApi = new ProjectOcrApi()
const SUPPORTED_OCR_MIME_TYPES = [
	'application/pdf',
	'image/jpeg',
	'image/png',
	'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
	'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
	'application/vnd.ms-excel',
]

const picker = getFilePickerBuilder(t('deck', 'File to share'))
	.setMultiSelect(false)
	.setType(1)
	.allowDirectories()
	.build()

export default {
	name: 'AttachmentList',
	components: {
		AlertCircleOutline,
		AttachmentDragAndDrop,
		CheckCircleOutline,
		ClockOutline,
		EyeOutline,
		FileQuestionOutline,
		NcActions,
		NcActionButton,
		NcActionLink,
		NcButton,
		NcLoadingIcon,
		NcModal,
		Refresh,
		Sync,
	},
	mixins: [relativeDate, attachmentUpload],

	props: {
		cardId: {
			type: Number,
			required: true,
		},
		selectable: {
			type: Boolean,
			required: false,
			default: false,
		},
		removable: {
			type: Boolean,
			required: false,
			default: false,
		},
	},
	data() {
		return {
			modalShow: false,
			file: '',
			overwriteAttachment: null,
			isDraggingOver: false,
			maxUploadSize: maxUploadSizeState,
			ocrBoardId: null,
			projectId: null,
			documentTypes: [],
			documentTypesLoading: false,
			documentTypesError: '',
			processingByFileId: {},
			processingLoadingByFileId: {},
			assigningByFileId: {},
			feedbackByFileId: {},
			pendingAttachmentByFileId: {},
			activeExtractedFileId: null,
			activeExtractedDraft: {},
			savingExtractedFileId: null,
			showUploadModal: false,
			uploadDocumentTypeId: '',
			selectedUploadFiles: [],
			uploadBusy: false,
		}
	},
	computed: {
		attachments() {
			// FIXME sort propertly by last modified / deleted at
			return [...this.$store.getters.attachmentsByCard(this.cardId)].filter(attachment => attachment.deletedAt >= 0).sort((a, b) => b.id - a.id)
		},
		mimetypeForAttachment() {
			return (attachment) => {
				if (!attachment) {
					return {}
				}
				const url = attachment.extendedData.hasPreview ? this.attachmentPreview(attachment) : OC.MimeType.getIconUrl(attachment.extendedData.mimetype)
				const styles = {
					'background-image': `url("${url}")`,
				}
				return styles
			}
		},
		attachmentPreview() {
			return (attachment) => (attachment.extendedData.fileid ? generateUrl(`/core/preview?fileId=${attachment.extendedData.fileid}&x=64&y=64`) : null)
		},
		attachmentUrl() {
			return (attachment) => generateUrl(`/apps/deck/cards/${attachment.cardId}/attachment/${attachment.id}`)
		},
		internalLink() {
			return (attachment) => generateUrl('/f/' + attachment.extendedData.fileid)
		},
		downloadLink() {
			return (attachment) => generateRemoteUrl(`dav/files/${getCurrentUser().uid}/${attachment.extendedData.path}`)
		},
		formattedFileSize() {
			return (filesize) => formatFileSize(filesize)
		},
		boardId() {
			const fromBoard = Number(this.currentBoard?.id)
			if (Number.isFinite(fromBoard) && fromBoard > 0) {
				return fromBoard
			}

			const fromRoute = Number(this.$route?.params?.id)
			if (Number.isFinite(fromRoute) && fromRoute > 0) {
				return fromRoute
			}

			return null
		},
		canUseOcr() {
			return this.removable && !this.selectable
		},
		activeExtractedData() {
			if (!this.activeExtractedFileId) {
				return []
			}
			return this.extractedEntries(this.activeExtractedFileId, true)
		},
		activeMissingFieldsCount() {
			if (!this.activeExtractedFileId) {
				return 0
			}
			return this.missingFieldsCount(this.activeExtractedFileId)
		},
		activeExtractedFileName() {
			if (!this.activeExtractedFileId) {
				return ''
			}

			const attachment = this.attachments.find((entry) => this.attachmentFileId(entry) === Number(this.activeExtractedFileId))
			if (!attachment) {
				return this.pendingAttachmentByFileId[String(this.activeExtractedFileId)]?.fileName || ''
			}

			const base = this.attachmentBasename(attachment)
			const ext = this.attachmentExtension(attachment)
			return ext ? `${base}.${ext}` : base
		},
		...mapState({
			currentBoard: state => state.currentBoard,
		}),
		isReadOnly() {
			return !this.$store.getters.canEdit
		},
		dropHintText() {
			if (this.isReadOnly) {
				return t('deck', 'This board is read only')
			} else {
				return t('deck', 'Drop your files to upload')
			}
		},
		attachmentBasename() {
			return (attachment) => attachment?.extendedData?.info.filename
				?? (attachment?.name ?? attachment.data).replace(/\.[^/.]+$/, '')
		},
		attachmentExtension() {
			return (attachment) => attachment?.extendedData?.info?.extension
				?? (attachment?.name ?? attachment.data).split('.').pop()
		},
	},
	watch: {
		cardId: {
			immediate: true,
			handler() {
				this.fetchAttachments(this.cardId)
				this.resetAttachmentOcrState()
				this.queueVisibleProcessingLoad()
			},
		},
		boardId: {
			immediate: true,
			handler() {
				this.initializeOcrContext()
			},
		},
		attachments() {
			this.queueVisibleProcessingLoad()
		},
	},
	methods: {
		...mapActions([
			'fetchAttachments',
		]),
		resetAttachmentOcrState() {
			this.processingByFileId = {}
			this.processingLoadingByFileId = {}
			this.assigningByFileId = {}
			this.feedbackByFileId = {}
			this.pendingAttachmentByFileId = {}
			this.activeExtractedFileId = null
			this.activeExtractedDraft = {}
			this.savingExtractedFileId = null
			this.showUploadModal = false
			this.uploadDocumentTypeId = ''
			this.selectedUploadFiles = []
			this.uploadBusy = false
		},
		resetOcrContextState() {
			this.ocrBoardId = null
			this.projectId = null
			this.documentTypes = []
			this.documentTypesLoading = false
			this.documentTypesError = ''
			this.resetAttachmentOcrState()
		},
		attachmentFileId(attachment) {
			const id = Number(attachment?.extendedData?.fileid)
			if (!Number.isFinite(id) || id <= 0) {
				return 0
			}
			return id
		},
		showOcrForAttachment(attachment) {
			if (!this.canUseOcr) {
				return false
			}

			const projectId = Number(this.projectId)
			if (!Number.isFinite(projectId) || projectId <= 0) {
				return false
			}

			if (Number(attachment?.deletedAt ?? -1) !== 0) {
				return false
			}

			const fileId = this.attachmentFileId(attachment)
			if (fileId <= 0) {
				return false
			}

			const mimeType = String(attachment?.extendedData?.mimetype || '').toLowerCase()
			return SUPPORTED_OCR_MIME_TYPES.includes(mimeType)
		},
		async initializeOcrContext() {
			if (!this.canUseOcr) {
				this.resetOcrContextState()
				return
			}

			const boardId = Number(this.boardId)
			if (!Number.isFinite(boardId) || boardId <= 0) {
				this.resetOcrContextState()
				return
			}

			if (Number(this.ocrBoardId) === boardId && Number(this.projectId) > 0) {
				this.queueVisibleProcessingLoad()
				return
			}

			this.resetOcrContextState()
			this.ocrBoardId = boardId

			try {
				const project = await projectOcrApi.getProjectByBoard(boardId)
				const projectId = Number(project?.id)
				if (!Number.isFinite(projectId) || projectId <= 0) {
					this.projectId = null
					return
				}

				this.projectId = projectId
				await this.loadDocumentTypes()
				this.queueVisibleProcessingLoad()
			} catch (error) {
				console.error('Failed to initialize OCR context for attachments:', error)
				this.projectId = null
			}
		},
		async loadDocumentTypes() {
			const projectId = Number(this.projectId)
			if (!Number.isFinite(projectId) || projectId <= 0) {
				this.documentTypes = []
				return
			}

			this.documentTypesLoading = true
			this.documentTypesError = ''
			try {
				this.documentTypes = await projectOcrApi.listProjectDocumentTypes(projectId)
			} catch (error) {
				this.documentTypes = []
				this.documentTypesError = error?.response?.data?.message || 'Could not load OCR document types.'
			} finally {
				this.documentTypesLoading = false
			}
		},
		queueVisibleProcessingLoad() {
			this.$nextTick(() => {
				this.preloadVisibleProcessing(this.attachments)
			})
		},
		async preloadVisibleProcessing(entries) {
			if (!this.canUseOcr) {
				return
			}

			const projectId = Number(this.projectId)
			if (!Number.isFinite(projectId) || projectId <= 0) {
				return
			}

			for (const entry of Array.isArray(entries) ? entries : []) {
				if (!this.showOcrForAttachment(entry)) {
					continue
				}

				const fileId = this.attachmentFileId(entry)
				const key = String(fileId)
				if (Object.prototype.hasOwnProperty.call(this.processingByFileId, key) || this.processingLoadingByFileId[key]) {
					continue
				}

				await this.loadFileProcessing(fileId)
			}
		},
		async loadFileProcessing(fileId) {
			const projectId = Number(this.projectId)
			const normalizedFileId = Number(fileId)
			if (!Number.isFinite(projectId) || projectId <= 0 || !Number.isFinite(normalizedFileId) || normalizedFileId <= 0) {
				return
			}

			const key = String(normalizedFileId)
			this.$set(this.processingLoadingByFileId, key, true)
			try {
				const payload = await projectOcrApi.getFileProcessing(projectId, normalizedFileId)
				if (payload?.processing) {
					this.$set(this.processingByFileId, key, payload.processing)
				}
			} catch (error) {
				this.$set(this.feedbackByFileId, key, error?.response?.data?.message || 'Could not load OCR status.')
			} finally {
				this.$set(this.processingLoadingByFileId, key, false)
			}
		},
		documentTypeValue(fileId) {
			const record = this.processingByFileId[String(fileId)] || null
			return record?.document_type_id || ''
		},
		processingDocumentTypeLabel(fileId) {
			const value = Number(this.documentTypeValue(fileId))
			if (!Number.isFinite(value) || value <= 0) {
				return t('deck', 'No type')
			}

			const type = this.documentTypes.find((entry) => Number(entry?.id) === value) || null
			return type?.name || t('deck', 'Unknown type')
		},
		statusLabel(fileId) {
			const record = this.processingByFileId[String(fileId)] || null
			if (!record) {
				return 'Queued'
			}
			if (record.ocr_status === 'failed') {
				return 'Failed'
			}
			if (record.ocr_status === 'aborted') {
				const missingCount = this.missingFieldsCount(fileId)
				return missingCount > 0 ? `Aborted (${missingCount} missing)` : 'Aborted'
			}
			if (record.ocr_status === 'stale') {
				return 'Stale'
			}
			if (record.ocr_status === 'processing') {
				return 'Processing'
			}
			if (record.ocr_status === 'done') {
				return 'Ready'
			}
			return 'Queued'
		},
		statusTooltip(fileId) {
			const label = this.statusLabel(fileId)
			const feedback = this.fileFeedback(fileId)
			if (!feedback) {
				return label
			}
			return `${label} - ${feedback}`
		},
		isProcessingBusy(fileId) {
			const key = String(fileId)
			return !!(this.processingLoadingByFileId[key] || this.assigningByFileId[key])
		},
		fileFeedback(fileId) {
			return this.feedbackByFileId[String(fileId)] || ''
		},
		extractedEntries(fileId, includeEmpty = false) {
			const record = this.processingByFileId[String(fileId)] || null
			const extracted = record?.extracted && typeof record.extracted === 'object' ? record.extracted : {}
			const entriesByKey = {}
			const ordered = []
			for (const name of this.expectedFieldNames(fileId)) {
				if (!entriesByKey[name]) {
					const payload = extracted[name] && typeof extracted[name] === 'object' ? extracted[name] : {}
					const value = payload.value ?? null
					entriesByKey[name] = {
						key: name,
						name,
						value,
						missing: value === null || String(value).trim() === '',
					}
					ordered.push(entriesByKey[name])
				}
			}

			for (const [key, payload] of Object.entries(extracted)) {
				const value = payload && typeof payload === 'object' ? payload.value : null
				const name = payload && typeof payload === 'object' ? (payload.name || payload.label || key) : key
				if (!entriesByKey[key]) {
					entriesByKey[key] = {
						key,
						name,
						value,
						missing: value === null || String(value).trim() === '',
					}
					ordered.push(entriesByKey[key])
				}
			}

			if (includeEmpty) {
				return ordered
			}

			return ordered.filter((item) => !item.missing)
		},
		expectedFieldNames(fileId) {
			const record = this.processingByFileId[String(fileId)] || null
			const documentTypeId = Number(record?.document_type_id ?? 0)
			if (!Number.isFinite(documentTypeId) || documentTypeId <= 0) {
				return []
			}

			const documentType = this.documentTypes.find((type) => Number(type?.id) === documentTypeId) || null
			const fields = Array.isArray(documentType?.fields) ? documentType.fields : []
			return fields
				.map((field) => {
					if (typeof field === 'string') {
						return field.trim()
					}
					const normalized = field && typeof field === 'object' ? field : {}
					return String(normalized?.name || normalized?.label || normalized?.key || '').trim()
				})
				.filter((name) => name !== '')
		},
		missingFieldsCount(fileId) {
			return this.extractedEntries(fileId, true).filter((item) => item.missing).length
		},
		hasPartialExtraction(fileId) {
			const record = this.processingByFileId[String(fileId)] || null
			if (!record || (record.ocr_status !== 'done' && record.ocr_status !== 'aborted')) {
				return false
			}
			const entries = this.extractedEntries(fileId, true)
			if (entries.length === 0) {
				return false
			}
			const filled = entries.filter((item) => !item.missing).length
			const missing = entries.length - filled
			return filled > 0 && missing > 0
		},
		isAssigning(fileId) {
			return !!this.assigningByFileId[String(fileId)]
		},
		statusIcon(fileId) {
			const record = this.processingByFileId[String(fileId)] || null
			if (!record) {
				return 'FileQuestionOutline'
			}
			if (record.ocr_status === 'failed') {
				return 'AlertCircleOutline'
			}
			if (record.ocr_status === 'aborted') {
				return 'AlertCircleOutline'
			}
			if (record.ocr_status === 'stale') {
				return 'ClockOutline'
			}
			if (record.ocr_status === 'processing') {
				return 'Sync'
			}
			if (record.ocr_status === 'done') {
				return 'CheckCircleOutline'
			}
			return 'ClockOutline'
		},
		statusBadgeClass(fileId) {
			const record = this.processingByFileId[String(fileId)] || null
			if (!record) return 'attachment__ocr-status--muted'
			if (record.ocr_status === 'failed') return 'attachment__ocr-status--error'
			if (record.ocr_status === 'aborted') return 'attachment__ocr-status--partial'
			if (record.ocr_status === 'done') return 'attachment__ocr-status--success'
			if (record.ocr_status === 'processing') return 'attachment__ocr-status--spin'
			return 'attachment__ocr-status--pending'
		},
		setProcessingResult(fileId, payload, successMessage, queuedMessage) {
			const key = String(fileId)
			if (payload?.processing) {
				this.$set(this.processingByFileId, key, payload.processing)
			}

			const nextStatus = this.statusLabel(fileId)
			if (nextStatus === 'Ready') {
				return successMessage
			}
			if (nextStatus.startsWith('Aborted')) {
				return payload?.processing?.error_message || 'Document processing was aborted because required fields are missing.'
			}
			if (nextStatus === 'Failed') {
				return payload?.processing?.error_message || 'OCR processing failed.'
			}
			return queuedMessage
		},
		canReprocess(fileId) {
			const record = this.processingByFileId[String(fileId)] || null
			if (!record || !record.document_type_id) {
				return false
			}
			if (this.isProcessingBusy(fileId)) {
				return false
			}
			return record.ocr_status !== 'processing'
		},
		canOpenExtractedDataModal(fileId) {
			const record = this.processingByFileId[String(fileId)] || null
			return !!(record && record.document_type_id && this.extractedEntries(fileId, true).length > 0)
		},
		openUploadModalForFiles(files) {
			if (this.isReadOnly || this.uploadBusy) {
				return
			}
			this.selectedUploadFiles = Array.isArray(files) ? files.filter(Boolean) : []
			this.showUploadModal = true
		},
		closeUploadModal() {
			if (this.uploadBusy) {
				return
			}
			this.showUploadModal = false
			this.selectedUploadFiles = []
		},
		async uploadSelectedFiles() {
			if (this.uploadBusy || this.selectedUploadFiles.length === 0) {
				return
			}

			const documentTypeId = Number(this.uploadDocumentTypeId)
			if (!Number.isFinite(documentTypeId) || documentTypeId <= 0) {
				return
			}

			this.uploadBusy = true
			try {
				for (const file of this.selectedUploadFiles) {
					const attachment = await this.uploadCardAttachmentWithOcr(file, documentTypeId)
					if (!attachment) {
						continue
					}
				}
				this.showUploadModal = false
				this.selectedUploadFiles = []
			} finally {
				this.uploadBusy = false
			}
		},
		async uploadCardAttachmentWithOcr(file, documentTypeId) {
			const projectId = Number(this.projectId)
			if (!Number.isFinite(projectId) || projectId <= 0) {
				const attachment = await this.onLocalAttachmentSelected(file, 'file')
				if (attachment) {
					await this.assignUploadedAttachmentDocumentType(attachment, documentTypeId)
				}
				return attachment
			}

			if (this.maxUploadSize > 0 && file.size > this.maxUploadSize) {
				showError(
					t('deck', 'Failed to upload {name}', { name: file.name }) + ' - '
						+ t('deck', 'Maximum file size of {size} exceeded', { size: formatFileSize(this.maxUploadSize) }),
				)
				return null
			}

			this.$set(this.uploadQueue, file.name, { name: file.name, progress: 0 })
			try {
				const payload = await projectOcrApi.uploadCardAttachment(
					projectId,
					this.cardId,
					documentTypeId,
					file,
					(e) => {
						const percentCompleted = Math.round((e.loaded * 100) / e.total)
						this.$set(this.uploadQueue[file.name], 'progress', percentCompleted)
					},
				)
				const attachment = payload?.attachment ?? null
				if (!attachment) {
					return null
				}
				this.$store.dispatch('registerAttachment', { cardId: this.cardId, attachment })
				const fileId = this.attachmentFileId(attachment)
				if (fileId > 0 && payload?.processing) {
					this.$set(this.processingByFileId, String(fileId), payload.processing)
					this.$delete(this.pendingAttachmentByFileId, String(fileId))
				}
				return attachment
			} catch (error) {
				const payload = error?.response?.data ?? {}
				const missingFields = Array.isArray(payload?.missing_fields) ? payload.missing_fields : []
				const message = payload?.message || error?.response?.data?.message || 'Failed to upload file'
				const processing = payload?.processing ?? null
				const stagedFileId = Number(processing?.file_id ?? 0)
				if (stagedFileId > 0 && processing) {
					this.$set(this.processingByFileId, String(stagedFileId), processing)
					this.$set(this.pendingAttachmentByFileId, String(stagedFileId), {
						processingId: Number(processing?.id ?? 0),
						fileName: String(processing?.file_name || file.name || ''),
					})
					this.openExtractedDataModal(stagedFileId)
				}
				showError(missingFields.length > 0 ? `${message} (${missingFields.join(', ')})` : message)
				return null
			} finally {
				this.$delete(this.uploadQueue, file.name)
			}
		},
		setActiveExtractedDraft(fieldName, value) {
			this.$set(this.activeExtractedDraft, fieldName, String(value ?? ''))
		},
		isSavingExtracted(fileId) {
			return String(this.savingExtractedFileId || '') === String(fileId || '')
		},
		async assignUploadedAttachmentDocumentType(attachment, documentTypeId) {
			if (!this.showOcrForAttachment(attachment)) {
				return
			}

			const projectId = Number(this.projectId)
			const fileId = this.attachmentFileId(attachment)
			const normalizedDocumentTypeId = Number(documentTypeId)
			if (!Number.isFinite(projectId) || projectId <= 0 || fileId <= 0 || !Number.isFinite(normalizedDocumentTypeId) || normalizedDocumentTypeId <= 0) {
				return
			}

			const key = String(fileId)
			this.$set(this.assigningByFileId, key, true)
			this.$delete(this.feedbackByFileId, key)
			try {
				const payload = await projectOcrApi.assignFileDocumentType(projectId, fileId, normalizedDocumentTypeId)
				const message = this.setProcessingResult(
					fileId,
					payload,
					'Document processed successfully.',
					'Document type assigned. OCR is queued.',
				)
				this.$set(this.feedbackByFileId, key, message)
			} catch (error) {
				this.$set(this.feedbackByFileId, key, error?.response?.data?.message || 'Could not assign document type.')
			} finally {
				this.$set(this.assigningByFileId, key, false)
			}
		},
		async reprocessAttachment(attachment) {
			if (!this.showOcrForAttachment(attachment)) {
				return
			}

			const projectId = Number(this.projectId)
			const fileId = this.attachmentFileId(attachment)
			if (!Number.isFinite(projectId) || projectId <= 0 || fileId <= 0) {
				return
			}

			const key = String(fileId)
			this.$set(this.assigningByFileId, key, true)
			this.$delete(this.feedbackByFileId, key)
			try {
				const payload = await projectOcrApi.reprocessFileProcessing(projectId, fileId)
				const message = this.setProcessingResult(
					fileId,
					payload,
					'Document reprocessed successfully.',
					'Document reprocessing is queued.',
				)
				this.$set(this.feedbackByFileId, key, message)
			} catch (error) {
				this.$set(this.feedbackByFileId, key, error?.response?.data?.message || 'Could not reprocess OCR for this file.')
			} finally {
				this.$set(this.assigningByFileId, key, false)
			}
		},
		openExtractedDataModal(fileId) {
			this.activeExtractedFileId = Number(fileId)
			const draft = {}
			for (const item of this.extractedEntries(fileId, true)) {
				draft[item.key] = item.value === null || item.value === undefined ? '' : String(item.value)
			}
			this.activeExtractedDraft = draft
		},
		closeExtractedDataModal() {
			this.activeExtractedFileId = null
			this.activeExtractedDraft = {}
			this.savingExtractedFileId = null
		},
		async saveActiveExtractedData() {
			const projectId = Number(this.projectId)
			const fileId = Number(this.activeExtractedFileId)
			if (!Number.isFinite(projectId) || projectId <= 0 || !Number.isFinite(fileId) || fileId <= 0) {
				return
			}

			this.savingExtractedFileId = fileId
			const key = String(fileId)
			this.$delete(this.feedbackByFileId, key)
			try {
				const pendingAttachment = this.pendingAttachmentByFileId[key] || null
				const payload = pendingAttachment?.processingId
					? await projectOcrApi.finalizeCardAttachment(projectId, this.cardId, pendingAttachment.processingId, this.activeExtractedDraft)
					: await projectOcrApi.updateFileExtractedFields(projectId, fileId, this.activeExtractedDraft)
				if (payload?.processing) {
					const nextFileId = Number(payload.processing?.file_id ?? fileId)
					if (pendingAttachment?.processingId && payload?.attachment) {
						this.$store.dispatch('registerAttachment', { cardId: this.cardId, attachment: payload.attachment })
						if (nextFileId > 0 && nextFileId !== fileId) {
							this.$delete(this.processingByFileId, key)
							this.$delete(this.pendingAttachmentByFileId, key)
							this.$set(this.processingByFileId, String(nextFileId), payload.processing)
						} else {
							this.$set(this.processingByFileId, key, payload.processing)
							this.$delete(this.pendingAttachmentByFileId, key)
						}
						this.closeExtractedDataModal()
					} else {
						this.$set(this.processingByFileId, key, payload.processing)
					}
				}
				this.$set(this.feedbackByFileId, String(payload?.processing?.file_id ?? fileId), payload?.attachment ? 'Attachment created.' : 'Extracted fields saved.')
			} catch (error) {
				const payload = error?.response?.data ?? {}
				if (payload?.processing) {
					this.$set(this.processingByFileId, key, payload.processing)
				}
				this.$set(this.feedbackByFileId, key, payload?.message || 'Could not save extracted fields.')
			} finally {
				this.savingExtractedFileId = null
			}
		},
		handleUploadFile(event) {
			const files = Array.from(event.target.files ?? [])
			event.target.value = ''
			if (files.length === 0) {
				return
			}
			this.openUploadModalForFiles(files)
		},
		uploadNewFile() {
			if (this.isReadOnly || this.uploadBusy) {
				return
			}
			this.selectedUploadFiles = []
			this.showUploadModal = true
		},
		shareFromFiles() {
			picker.pick()
				.then(async (path) => {
					console.debug(`path ${path} selected for sharing`)
					if (!path.startsWith('/')) {
						throw new Error(t('files', 'Invalid path selected'))
					}

					axios.post(generateOcsUrl('apps/files_sharing/api/v1/shares'), {
						path,
						shareType: 12,
						shareWith: '' + this.cardId,
					}).then(() => {
						this.fetchAttachments(this.cardId)
					})
				})
		},
		unshareAttachment(attachment) {
			this.$store.dispatch('unshareAttachment', attachment)
		},
		clickAddNewAttachmment() {
			this.$refs.localAttachments.click()
		},
		showViewer(attachment) {
			if (attachment.extendedData.fileid && window.OCA.Viewer.availableHandlers.map(handler => handler.mimes).flat().includes(attachment.extendedData.mimetype)) {
				window.OCA.Viewer.open({ path: attachment.extendedData.path })
				return
			}

			if (attachment.extendedData.fileid) {
				window.location = generateUrl('/f/' + attachment.extendedData.fileid)
				return
			}

			window.location = generateUrl(`/apps/deck/cards/${attachment.cardId}/attachment/${attachment.id}`)
		},
	},
}
</script>

<style lang="scss" scoped>

	.drop-upload--sidebar {
		min-height: 100%;
	}

	.button-group {
		display: flex;
		gap: calc(var(--default-grid-baseline) * 3);

		.icon-upload, .icon-folder {
			padding-left: var(--default-clickable-area);
			background-position: 16px center;
			flex-grow: 1;
			height: var(--default-clickable-area);
			margin-bottom: 12px;
			text-align: left;
		}
	}

	.attachment-list {
		&.selector {
			padding: 10px;
			position: absolute;
			width: 30%;
			max-width: 500px;
			min-width: 200px;
			max-height: 50%;
			top: 50%;
			left: 50%;
			transform: translate(-50%, -50%);
			background-color: #eee;
			z-index: 2;
			border-radius: 3px;
			box-shadow: 0 0 3px darkgray;
			overflow: scroll;
		}
		h3.attachment-selector {
			margin: 0 0 10px;
			padding: 0;
			.icon-close {
				display: inline-block;
				float: right;
			}
		}

		li.attachment {
			display: flex;
			align-items: flex-start;
			padding: 3px;
			min-height: var(--default-clickable-area);

			&.deleted {
				opacity: .5;
			}

			.fileicon {
				display: inline-block;
				min-width: 32px;
				width: 32px;
				height: 32px;
				background-size: contain;
			}
			.details {
				flex-grow: 1;
				flex-shrink: 1;
				min-width: 0;
				flex-basis: 50%;
				line-height: 110%;
				padding: 2px;
			}
			.filename {
				width: 70%;
				display: flex;
				.basename {
					white-space: nowrap;
					overflow: hidden;
					text-overflow: ellipsis;
					padding-bottom: 2px;
				}
				.extension {
					opacity: 0.7;
				}
			}
			.attachment--info,
			.filesize, .filedate {
				font-size: 90%;
				color: var(--color-text-maxcontrast);
			}
			.app-popover-menu-utils {
				position: relative;
				right: -10px;
				button {
					height: 32px;
					width: 42px;
				}
			}
			button.icon-history {
				width: var(--default-clickable-area);
			}
			progress {
				margin-top: 3px;
			}
		}
	}

	.attachment__ocr {
		display: flex;
		align-items: center;
		gap: 6px;
		margin-top: 6px;
		min-height: 28px;
	}

	.attachment__ocr-type-label {
		display: inline-flex;
		align-items: center;
		border: 1px solid var(--color-border-dark);
		border-radius: 6px;
		background: var(--color-main-background);
		color: var(--color-main-text);
		font-size: 12px;
		padding: 4px 8px;
		max-width: 170px;
		min-height: 26px;
		white-space: nowrap;
		overflow: hidden;
		text-overflow: ellipsis;
	}

	.attachment__ocr-status {
		display: inline-flex;
		align-items: center;
		justify-content: center;
		width: 24px;
		height: 24px;
		border-radius: 999px;
		border: 1px solid transparent;
	}

	.attachment__ocr-status--muted {
		background: var(--color-background-dark);
		color: var(--color-text-maxcontrast);
		border-color: var(--color-border);
	}

	.attachment__ocr-status--pending {
		background: rgba(255, 184, 0, 0.12);
		color: #a06700;
		border-color: rgba(255, 184, 0, 0.35);
	}

	.attachment__ocr-status--success {
		background: rgba(0, 128, 0, 0.1);
		color: #0a7a23;
		border-color: rgba(0, 128, 0, 0.25);
	}

	.attachment__ocr-status--partial {
		background: rgba(255, 184, 0, 0.12);
		color: #a06700;
		border-color: rgba(255, 184, 0, 0.35);
	}

	.attachment__ocr-status--error {
		background: rgba(255, 0, 0, 0.1);
		color: #c63131;
		border-color: rgba(255, 0, 0, 0.25);
	}

	.attachment__ocr-status--spin {
		background: rgba(0, 124, 255, 0.1);
		color: #0f5cb8;
		border-color: rgba(0, 124, 255, 0.25);
	}

	.attachment__ocr-status--spin :deep(svg) {
		animation: attachment-ocr-spin 1.2s linear infinite;
	}

	.attachment__ocr-icon-btn {
		display: inline-flex;
		align-items: center;
		justify-content: center;
		width: 26px;
		height: 26px;
		border: 1px solid var(--color-border-dark);
		border-radius: 6px;
		background: var(--color-main-background);
		color: var(--color-main-text);
		cursor: pointer;
		padding: 0;
	}

	.attachment__ocr-icon-btn:hover {
		background: var(--color-background-hover);
	}

	.attachment__ocr-loading {
		display: inline-flex;
		align-items: center;
	}

	.attachment__ocr-modal {
		padding: 8px;
	}

	.attachment__upload-modal {
		padding: 8px;
		min-width: min(560px, 90vw);
	}

	.attachment__upload-modal-card {
		font-weight: 600;
		margin-bottom: 10px;
	}

	.attachment__upload-modal-label {
		display: block;
		margin-bottom: 6px;
		font-size: 13px;
		font-weight: 600;
	}

	.attachment__upload-type-select {
		width: 100%;
		margin-bottom: 12px;
		border: 1px solid var(--color-border-dark);
		border-radius: 6px;
		background: var(--color-main-background);
		color: var(--color-main-text);
		font-size: 13px;
		padding: 8px 10px;
	}

	.attachment__upload-modal-row {
		display: flex;
		align-items: center;
		gap: 10px;
		margin-bottom: 12px;
	}

	.attachment__upload-modal-hint {
		font-size: 13px;
		color: var(--color-text-maxcontrast);
	}

	.attachment__upload-file-list {
		margin: 0;
		padding: 0;
		list-style: none;
		border: 1px solid var(--color-border);
		border-radius: 8px;
		overflow: hidden;
	}

	.attachment__upload-file-item {
		display: flex;
		align-items: center;
		justify-content: space-between;
		gap: 12px;
		padding: 10px 12px;
		font-size: 13px;
		border-top: 1px solid var(--color-border);
	}

	.attachment__upload-file-item:first-child {
		border-top: 0;
	}

	.attachment__ocr-modal-filename {
		font-weight: 600;
		margin-bottom: 10px;
	}

	.attachment__ocr-empty {
		font-size: 13px;
		color: var(--color-text-maxcontrast);
	}

	.attachment__ocr-warning {
		margin-bottom: 10px;
		padding: 8px 10px;
		font-size: 13px;
		border-radius: 6px;
		background: rgba(255, 184, 0, 0.12);
		color: #a06700;
		border: 1px solid rgba(255, 184, 0, 0.35);
	}

	.attachment__ocr-table {
		width: 100%;
		border-collapse: collapse;
	}

	.attachment__ocr-table th,
	.attachment__ocr-table td {
		font-size: 13px;
		padding: 8px;
		text-align: left;
		border-bottom: 1px solid var(--color-border);
		vertical-align: top;
	}

	.attachment__ocr-table th {
		width: 35%;
		font-weight: 600;
	}

	.attachment__ocr-missing-pill {
		display: inline-flex;
		margin-left: 8px;
		padding: 2px 6px;
		font-size: 11px;
		border-radius: 999px;
		background: rgba(255, 184, 0, 0.12);
		color: #a06700;
		border: 1px solid rgba(255, 184, 0, 0.35);
	}

	.attachment__ocr-input {
		width: 100%;
		border: 1px solid var(--color-border);
		background: var(--color-main-background);
		color: var(--color-main-text);
		border-radius: 6px;
		padding: 7px 9px;
		font-size: 13px;
	}

	.attachment__ocr-actions {
		display: flex;
		justify-content: flex-end;
		margin-top: 10px;
	}

	.attachment__ocr-actions--upload {
		gap: 8px;
	}

	@keyframes attachment-ocr-spin {
		from {
			transform: rotate(0deg);
		}
		to {
			transform: rotate(360deg);
		}
	}

</style>
