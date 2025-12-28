<template>
    <div v-if="loading" class="timeline-widget skeleton">
        <div class="skeleton-header"></div>
        <div class="skeleton-bar"></div>
        <div class="skeleton-footer"></div>
    </div>

    <div 
        v-else-if="hasProjectData" 
        class="timeline-widget" 
        :class="{ 'is-collapsed': isCollapsed }"
    >
        <div class="widget-header" @click="toggleCollapse">
            <div class="header-main">
                <div class="title-row">
                    <div class="toggle-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="6 9 12 15 18 9"></polyline>
                        </svg>
                    </div>

                    <h3 class="project-title">{{ project.name }}</h3>
                    
                    <span class="status-badge" :class="statusClass">{{ statusText }}</span>
                </div>
                
                <div class="meta-row">
                    <span class="meta-label">Timeline Tracker</span>
                </div>
            </div>
            <div class="header-metric">
                <span class="percentage-value" :class="statusClass">{{ Math.round(progressPercentage) }}%</span>
            </div>
        </div>

        <div class="collapsible-wrapper">
            <div class="collapsible-inner">
                <div class="widget-body">
                    <div class="progress-container">
                        <div class="progress-track">
                            <div class="progress-fill" :class="statusClass" :style="{ width: progressPercentage + '%' }"></div>
                        </div>
                    </div>
                </div>

                <div class="widget-footer">
                    <div class="date-group">
                        <span class="date-label">Start</span>
                        <span class="date-value">{{ formatDate(project.date_start) }}</span>
                    </div>
                    
                    <div class="day-tracker-pill" :class="statusClass">
                        {{ dayTrackerText }}
                    </div>

                    <div class="date-group align-right">
                        <span class="date-label">Target</span>
                        <span class="date-value">{{ formatDate(project.date_end) }}</span>
                    </div>
                </div>
            </div>
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
            loading: true,
            error: null,
            isCollapsed: false,
        }
    },
    computed: {
        hasProjectData() {
            return this.project && this.project.date_start && this.project.date_end
        },
        progressPercentage() {
            if (!this.hasProjectData) return 0
            const start = new Date(this.project.date_start).getTime()
            const end = new Date(this.project.date_end).getTime()
            const now = new Date().getTime()

            if (now < start) return 0
            if (now > end) return 100
            
            // Strictly time elapsed percentage
            const total = end - start
            const current = now - start
            return Math.min(100, Math.max(0, (current / total) * 100))
        },
        // Helper to get raw dates
        dates() {
            if (!this.hasProjectData) return {}
            return {
                start: new Date(this.project.date_start).getTime(),
                end: new Date(this.project.date_end).getTime(),
                now: new Date().getTime()
            }
        },
        daysDiff() {
            if (!this.hasProjectData) return 0
            const { end, now } = this.dates
            // Returns positive if future, negative if past
            return Math.ceil((end - now) / (1000 * 60 * 60 * 24))
        },
        projectState() {
            if (!this.hasProjectData) return 'active'
            const { start, now } = this.dates

            // 1. Upcoming (Now is before Start)
            if (now < start) return 'upcoming'

            // 2. Overdue/Ended (Now is after End)
            if (this.daysDiff < 0) return 'overdue'

            // 3. Critical (Less than 3 days left)
            if (this.daysDiff <= 3) return 'critical'

            // 4. Default Active
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
            
            // Handle Upcoming
            const { start, now } = this.dates
            if (now < start) {
                const daysToStart = Math.ceil((start - now) / (1000 * 60 * 60 * 24))
                return `Starts in ${daysToStart} days`
            }

            const days = this.daysDiff

            // Handle Past
            if (days < 0) return `Ended ${Math.abs(days)} days ago`
            
            // Handle Today
            if (days === 0) return 'Ends today'
            
            // Handle Future
            return `${days} days left`
        },
    },
    mounted() {
        this.fetchProject()
    },
    methods: {
        async fetchProject() {
            this.loading = true
            try {
                const service = new ProjectService()
                this.project = await service.getProjectByBoardId(this.boardId)
            } catch (e) {
                console.error('Failed to load project details', e)
                this.error = e
            } finally {
                setTimeout(() => { this.loading = false }, 300)
            }
        },
        toggleCollapse() {
            this.isCollapsed = !this.isCollapsed
        },
        formatDate(dateString) {
            if (!dateString) return ''
            return new Date(dateString).toLocaleDateString(undefined, { 
                year: 'numeric', 
                month: 'short', 
                day: 'numeric' 
            })
        },
    },
}
</script>

<style scoped lang="scss">
/* Variables */
$radius-lg: 12px;
$radius-sm: 6px;
$padding: 20px;
$margin: 15px;
$transition-physics: 0.5s cubic-bezier(0.4, 0, 0.2, 1);

/* Colors */
$color-active: var(--color-primary, #0082c9);
$color-overdue: #d32f2f; /* Red */
$color-critical: #e6a23c; /* Orange */
$color-upcoming: #6c757d; /* Grey/Neutral for scheduled */

.timeline-widget {
    margin: 0px $margin;
    padding: $padding;
    margin-bottom: 20px;
    font-family: var(--font-face, sans-serif);
    transition: padding-bottom $transition-physics, border-color 0.2s ease;
    
    &.is-collapsed {
        padding-bottom: 0;
    }
    
    &:hover {
        border-color: var(--color-border-dark);
    }
}

/* --- HEADER --- */
.widget-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 16px;
    cursor: pointer;
    transition: margin-bottom $transition-physics;
    
    .is-collapsed & {
        margin-bottom: 16px; 
    }
}

.title-row {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 4px;
}

.toggle-icon {
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--color-text-lighter);
    margin-right: -4px;
    transition: transform $transition-physics;
    
    svg { width: 18px; height: 18px; }
    .is-collapsed & { transform: rotate(-90deg); }
}

.project-title {
    margin: 0;
    font-size: 16px;
    font-weight: 700;
    color: var(--color-main-text);
}

/* DYNAMIC STATUS BADGE */
.status-badge {
    font-size: 11px;
    font-weight: 600;
    padding: 2px 8px;
    border-radius: 12px;
    text-transform: uppercase;
    letter-spacing: 0.02em;
    border: 1px solid transparent;
    
    &.status-active {
        background-color: rgba($color-active, 0.1);
        color: $color-active;
        border-color: rgba($color-active, 0.2);
    }
    &.status-overdue {
        background-color: rgba($color-overdue, 0.1);
        color: $color-overdue;
        border-color: rgba($color-overdue, 0.2);
    }
    &.status-critical {
        background-color: rgba($color-critical, 0.15);
        color: $color-critical;
        border-color: rgba($color-critical, 0.2);
    }
    &.status-upcoming {
        background-color: rgba($color-upcoming, 0.1);
        color: $color-upcoming;
        border-color: rgba($color-upcoming, 0.2);
    }
}

.meta-row {
    transition: opacity 0.3s ease;
    .is-collapsed & { opacity: 0; pointer-events: none; }
}

.meta-label {
    font-size: 13px;
    color: var(--color-text-lighter);
}

.header-metric { text-align: right; }

/* Dynamic Percentage Color */
.percentage-value {
    font-size: 24px;
    font-weight: 800;
    line-height: 1;
    transition: font-size $transition-physics, color 0.3s ease;
    
    /* Default / Active */
    color: $color-active; 
    &.status-active { color: $color-active; }

    /* Override states */
    &.status-overdue { color: $color-overdue; }
    &.status-critical { color: $color-critical; }
    &.status-upcoming { color: $color-upcoming; }
    
    .is-collapsed & { font-size: 18px; }
}

/* --- COLLAPSIBLE --- */
.collapsible-wrapper {
    display: grid;
    grid-template-rows: 1fr;
    transition: grid-template-rows $transition-physics, opacity $transition-physics;
    opacity: 1;
    .is-collapsed & { grid-template-rows: 0fr; opacity: 0; }
}

.collapsible-inner { overflow: hidden; min-height: 0; }

/* --- BODY --- */
.widget-body { margin-bottom: 16px; }

.progress-track {
    height: 10px;
    background: var(--color-background-dark);
    border-radius: 10px;
    overflow: hidden;
}

.progress-fill {
    height: 100%;
    border-radius: 10px;
    transition: width 0.6s cubic-bezier(0.4, 0, 0.2, 1), background-color 0.3s ease;
    
    background-color: $color-active;

    &.status-overdue { background-color: $color-overdue; }
    &.status-critical { background-color: $color-critical; }
    &.status-upcoming { background-color: $color-upcoming; width: 0 !important; /* Force 0 width for upcoming */ }

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
    align-items: center;
    border-top: 1px solid var(--color-border-light);
    padding-top: 12px;
}

.date-group { display: flex; flex-direction: column; }
.date-group.align-right { align-items: flex-end; text-align: right; }
.date-label { font-size: 10px; text-transform: uppercase; color: var(--color-text-lighter); font-weight: 600; margin-bottom: 2px; }
.date-value { font-size: 13px; font-weight: 600; color: var(--color-text-maxcontrast); }

.day-tracker-pill {
    background: var(--color-background-dark);
    color: var(--color-text-light);
    font-size: 12px;
    font-weight: 600;
    padding: 4px 12px;
    border-radius: $radius-sm;
    transition: all 0.3s ease;
    
    &.status-overdue { background: $color-overdue; color: #fff; }
    &.status-critical { background: $color-critical; color: #fff; }
    &.status-upcoming { background: rgba($color-upcoming, 0.2); color: $color-upcoming; }
}

/* --- SKELETON --- */
.skeleton {
    pointer-events: none;
    .skeleton-header { height: 24px; width: 60%; background: var(--color-background-dark); border-radius: 4px; margin-bottom: 20px; opacity: 0.6; }
    .skeleton-bar { height: 10px; width: 100%; background: var(--color-background-dark); border-radius: 10px; margin-bottom: 20px; opacity: 0.4; }
    .skeleton-footer { height: 16px; width: 100%; background: var(--color-background-dark); border-radius: 4px; opacity: 0.3; }
    animation: pulse 1.5s infinite ease-in-out;
}

@keyframes pulse {
    0% { opacity: 0.6; }
    50% { opacity: 0.3; }
    100% { opacity: 0.6; }
}
</style>