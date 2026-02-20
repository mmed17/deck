<template>
    <div v-if="loading" class="timeline-widget-saas skeleton">
        <div class="skeleton-header"></div>
        <div class="skeleton-bar"></div>
        <div class="skeleton-footer"></div>
    </div>

    <div v-else class="timeline-widget-saas">
        <template v-if="hasProjectData">
            <div class="widget-header">
                <div class="header-left">
                    <span class="status-badge" :class="statusClass">{{ statusText }}</span>
                </div>
                <div class="header-right">
                    <span class="percentage-value" :class="statusClass">{{ Math.round(progressPercentage) }}%</span>
                </div>
            </div>

            <div class="widget-body">
                <div class="progress-track">
                    <div class="progress-fill" 
                         :class="statusClass" 
                         :style="{ width: progressPercentage + '%' }">
                    </div>
                </div>
            </div>

            <div class="widget-footer">
                <div class="date-group">
                    <span class="date-label">Start Date</span>
                    <span class="date-value">{{ formatDate(timelineRange.start) }}</span>
                </div>
                
                <div class="day-tracker-pill" :class="statusClass">
                    {{ dayTrackerText }}
                </div>

                <div class="date-group align-right">
                    <span class="date-label">Target Date</span>
                    <span class="date-value">{{ formatDate(timelineRange.end) }}</span>
                </div>
            </div>
        </template>

        <div v-else class="empty-state">
            <div class="empty-title">Timeline not set</div>
            <div v-if="error" class="empty-hint">Timeline could not be loaded</div>
            <div v-else class="empty-hint">Add timeline items to the project to see progress here.</div>
        </div>
    </div>
</template>

<script>
import { ProjectService } from '../../services/ProjectService.js'

export default {
    name: 'TimelineWidget',
    props: {
        boardId: { type: Number, required: true },
    },
    data() {
        return {
            project: null,
            timelineItems: [],
            loading: true,
            error: null,
        }
    },
    computed: {
        timelineRange() {
            const items = Array.isArray(this.timelineItems) ? this.timelineItems : []
            let minStart = null
            let maxEnd = null
            for (const item of items) {
                const start = this.parseDate(item?.startDate)
                if (start === null) {
                    continue
                }
                const end = this.parseDate(item?.endDate ?? item?.startDate) ?? start

                minStart = (minStart === null) ? start : Math.min(minStart, start)
                maxEnd = (maxEnd === null) ? end : Math.max(maxEnd, end)
            }

            return {
                start: minStart,
                end: maxEnd,
            }
        },
        hasProjectData() {
            return this.timelineRange.start !== null && this.timelineRange.end !== null
        },
        progressPercentage() {
            if (!this.hasProjectData) return 0
            const start = this.timelineRange.start
            const end = this.timelineRange.end
            const now = new Date().getTime()

            if (now < start) return 0
            if (now > end) return 100
            
            const total = end - start
            const current = now - start
            return Math.min(100, Math.max(0, (current / total) * 100))
        },
        dates() {
            if (!this.hasProjectData) return {}
            return {
                start: this.timelineRange.start,
                end: this.timelineRange.end,
                now: new Date().getTime()
            }
        },
        daysDiff() {
            if (!this.hasProjectData) return 0
            const { end, now } = this.dates
            return Math.ceil((end - now) / (1000 * 60 * 60 * 24))
        },
        projectState() {
            if (!this.hasProjectData) return 'active'
            const { start, now } = this.dates
            if (now < start) return 'upcoming'
            if (this.daysDiff < 0) return 'overdue'
            if (this.daysDiff <= 3) return 'critical'
            return 'active'
        },
        statusClass() {
            return `status-${this.projectState}`
        },
        statusText() {
            switch (this.projectState) {
                case 'upcoming': return 'Scheduled'
                case 'overdue': return 'Timeline Ended'
                case 'critical': return 'Due Soon'
                default: return 'In Progress'
            }
        },
        dayTrackerText() {
            if (!this.hasProjectData) return ''
            const { start, now } = this.dates
            if (now < start) {
                const daysToStart = Math.ceil((start - now) / (1000 * 60 * 60 * 24))
                return `Starts in ${daysToStart} days`
            }
            const days = this.daysDiff
            if (days < 0) return `Ended ${Math.abs(days)} days ago`
            if (days === 0) return 'Ends today'
            return `${days} days left`
        },
    },
    watch: {
        boardId() {
            this.fetchTimeline()
        },
    },
    mounted() {
        this.fetchTimeline()
    },
    methods: {
        parseDate(value) {
            if (!value) {
                return null
            }

            const str = String(value).trim()
            if (str === '') {
                return null
            }

            const m = /^([0-9]{4})-([0-9]{2})-([0-9]{2})$/.exec(str)
            if (m) {
                const year = Number(m[1])
                const month = Number(m[2])
                const day = Number(m[3])
                return new Date(year, month - 1, day).getTime()
            }

            const t = Date.parse(str)
            return Number.isNaN(t) ? null : t
        },
        async fetchTimeline() {
            this.loading = true
            try {
                const service = new ProjectService()
                this.error = null
                this.project = await service.getProjectByBoardId(this.boardId)
                this.timelineItems = []

                const projectId = this.project?.id
                if (projectId !== null && projectId !== undefined) {
                    this.timelineItems = await service.getTimelineByProjectId(projectId)
                }
            } catch (e) {
                console.error('Failed to load project details', e)
                this.error = e
            } finally {
                setTimeout(() => { this.loading = false }, 300)
            }
        },
        formatDate(dateString) {
            if (dateString === null || dateString === undefined || dateString === '') return ''
            const date = (typeof dateString === 'number') ? new Date(dateString) : new Date(String(dateString))
            return date.toLocaleDateString(undefined, { 
                year: 'numeric', 
                month: 'short', 
                day: 'numeric' 
            })
        },
    },
}
</script>

<style scoped lang="scss">
/* --- SAAS DESIGN TOKENS --- */
$bg-white: #ffffff;
$border-color: #e2e8f0;
$text-main: #0f172a;
$text-muted: #64748b;

/* Colors */
$color-active: #3b82f6;   /* Blue */
$color-overdue: #ef4444;  /* Red */
$color-critical: #f59e0b; /* Amber */
$color-upcoming: #64748b; /* Slate */

.timeline-widget-saas {
    background: $bg-white;
    /* Border removed */
    border-radius: 8px;
    padding: 16px; 
    width: 100%;
    margin: 0; 
    box-sizing: border-box;
    display: flex;
    flex-direction: column;
}

/* --- HEADER --- */
.widget-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 16px; 
}

.header-left {
    display: flex;
    align-items: center;
    gap: 12px;
}

/* STATUS BADGE */
.status-badge {
    font-size: 11px;
    font-weight: 600;
    padding: 3px 10px;
    border-radius: 99px;
    text-transform: uppercase;
    letter-spacing: 0.03em;
    
    &.status-active { background: #eff6ff; color: $color-active; }
    &.status-overdue { background: #fef2f2; color: $color-overdue; }
    &.status-critical { background: #fffbeb; color: $color-critical; }
    &.status-upcoming { background: #f1f5f9; color: $color-upcoming; }
}

.header-right { 
    text-align: right; 
}

.percentage-value {
    font-size: 20px;
    font-weight: 800;
    line-height: 1;
    
    &.status-active { color: $color-active; }
    &.status-overdue { color: $color-overdue; }
    &.status-critical { color: $color-critical; }
    &.status-upcoming { color: $color-upcoming; }
}

/* --- BODY (Progress Bar) --- */
.widget-body { 
    margin-bottom: 16px; 
}

.progress-track {
    height: 10px;
    background: #f1f5f9; /* Slate-100 */
    border-radius: 5px;
    overflow: hidden;
    position: relative;
}

.progress-fill {
    height: 100%;
    border-radius: 5px;
    transition: width 0.8s cubic-bezier(0.4, 0, 0.2, 1);
    
    &.status-active { background-color: $color-active; }
    &.status-overdue { background-color: $color-overdue; }
    &.status-critical { background-color: $color-critical; }
    &.status-upcoming { width: 0 !important; }

    /* Animated Stripes */
    background-image: linear-gradient(
        45deg, 
        rgba(255, 255, 255, 0.15) 25%, 
        transparent 25%, 
        transparent 50%, 
        rgba(255, 255, 255, 0.15) 50%, 
        rgba(255, 255, 255, 0.15) 75%, 
        transparent 75%, 
        transparent
    );
    background-size: 1rem 1rem;
    animation: move-stripes 1s linear infinite;
}

@keyframes move-stripes {
    from { background-position: 1rem 0; }
    to { background-position: 0 0; }
}

/* --- FOOTER --- */
.widget-footer {
    display: flex;
    justify-content: space-between;
    align-items: flex-end;
    border-top: 1px solid #f1f5f9;
    padding-top: 16px;
}

.date-group { display: flex; flex-direction: column; gap: 4px; }
.date-group.align-right { align-items: flex-end; text-align: right; }

.date-label { 
    font-size: 11px; 
    text-transform: uppercase; 
    color: $text-muted; 
    font-weight: 600; 
}

.date-value { 
    font-size: 14px; 
    font-weight: 500; 
    color: $text-main; 
}

.day-tracker-pill {
    background: #f8fafc;
    color: $text-muted;
    font-size: 13px;
    font-weight: 500;
    padding: 6px 16px;
    border-radius: 8px;
    
    &.status-overdue { background: #fef2f2; color: $color-overdue; font-weight: 600; }
    &.status-critical { background: #fffbeb; color: $color-critical; font-weight: 600; }
}

/* --- EMPTY STATE --- */
.empty-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 6px;
    padding: 8px 0;
}

.empty-title {
    font-size: 14px;
    font-weight: 700;
    color: $text-main;
}

.empty-hint {
    font-size: 13px;
    color: $text-muted;
    text-align: center;
}

/* --- SKELETON LOADING --- */
.skeleton {
    pointer-events: none;
    /* Border removed */
    border-radius: 8px;
    padding: 16px;
    
    .skeleton-header { height: 24px; width: 40%; background: #f1f5f9; border-radius: 4px; margin-bottom: 24px; }
    .skeleton-bar { height: 10px; width: 100%; background: #f1f5f9; border-radius: 5px; margin-bottom: 24px; }
    .skeleton-footer { height: 16px; width: 100%; background: #f1f5f9; border-radius: 4px; }
    animation: pulse 1.5s infinite ease-in-out;
}

@keyframes pulse {
    0% { opacity: 0.6; }
    50% { opacity: 0.4; }
    100% { opacity: 0.6; }
}
</style>
