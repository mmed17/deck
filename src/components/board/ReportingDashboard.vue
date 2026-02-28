<template>
    <div class="reporting-dashboard-saas">
        <div class="saas-header">
            <div class="header-left">
                <div class="icon-box">
                    <svg class="chart-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M3 3v18h18" stroke-linecap="round"/>
                        <path d="M18.7 8l-5.1 5.2-2.8-2.7L7 14.3" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </div>
            </div>

            <div class="header-right">
                <span class="status-pill">
                    <span class="status-dot"></span> Live
                </span>
            </div>
        </div>

        <div class="saas-content-body">
            <div class="saas-kpi-grid">
                <KpiCard
                    title="Total Tasks"
                    :value="totalTasks"
                    icon="list"
                    color="#3b82f6"
                    suffix="Tasks"
                />
                <KpiCard
                    title="Completed"
                    :value="completedTasks"
                    icon="check"
                    color="#10b981"
                    suffix="Tasks"
                />
                <KpiCard
                    title="Overdue"
                    :value="overdueTasks"
                    icon="alert"
                    color="#f97316"
                    suffix="Tasks"
                />
                <KpiBreakdownCard
                    title="Open Tasks"
                    :left-label="'Important'"
                    :left-value="importantOpenTasks"
                    :left-color="'#dc2626'"
                    :right-label="'Other'"
                    :right-value="otherOpenTasks"
                    :right-color="'#334155'"
                    icon-color="#3b82f6" />
            </div>

            <div class="saas-charts-grid">
                <div class="saas-chart-card">
                    <div class="chart-header">
                        <h4>Completion Rate</h4>
                    </div>
                    <div class="chart-content center-chart">
                        <CircleProgress
                            :percentage="progressPercent"
                            :size="140"
                            :stroke-width="10"
                            color="#3b82f6"
                            label="Overall"
                        />
                    </div>
                </div>

                <div class="saas-chart-card">
                    <div class="chart-header">
                        <h4>Task Distribution</h4>
                    </div>
                    <div class="chart-content">
                        <BarChart :data="tasksByStatus" />
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import { mapState, mapGetters } from 'vuex'
import KpiCard from './reports/KpiCard.vue'
import KpiBreakdownCard from './reports/KpiBreakdownCard.vue'
import CircleProgress from './reports/CircleProgress.vue'
import BarChart from './reports/BarChart.vue'

export default {
    name: 'ReportingDashboard',
    components: { KpiCard, KpiBreakdownCard, CircleProgress, BarChart },
    data() {
        return {
            currentTime: Date.now(),
            updateTimer: null
        }
    },
    mounted() {
        // Update the current time every 10 seconds to trigger reactivity
        // (more frequent updates since we're now tracking hours/minutes/seconds)
        this.updateTimer = setInterval(() => {
            this.currentTime = Date.now()
        }, 10000) // Update every 10 seconds
    },
    beforeDestroy() {
        if (this.updateTimer) {
            clearInterval(this.updateTimer)
        }
    },
    computed: {
        ...mapState({
            board: state => state.currentBoard,
            allCards: state => state.card.cards,
        }),
        ...mapGetters(['stacksByBoard', 'cardsByStack']),
        
        stacks() {
            return this.board?.id ? this.stacksByBoard(this.board.id) : []
        },
        
        cards() {
            if (!this.allCards) return []
            return this.allCards.filter(card => {
                return this.stacks.some(stack => stack.id === card.stackId)
            })
        },
        
        totalTasks() {
            return this.cards.length
        },
        
        completedTasks() {
            return this.cards.filter(card => this.isCardDone(card)).length
        },
        
        overdueTasks() {
            return this.cards.filter(card => {
                // Cards without due dates cannot be overdue
                if (!card.duedate) return false
                
                // Completed/closed cards should not count as overdue
                if (!this.isCardOpen(card)) return false
                
                // Calculate overdue status in real-time (client-side)
                return this.isCardOverdue(card)
            }).length
        },

        importantOpenTasks() {
            return this.cards.filter(card => this.isCardOpen(card) && this.hasImportantLabel(card)).length
        },

        otherOpenTasks() {
            return this.cards.filter(card => this.isCardOpen(card) && !this.hasImportantLabel(card)).length
        },
        
        progressPercent() {
            if (this.totalTasks === 0) return 0
            return Math.round((this.completedTasks / this.totalTasks) * 100)
        },
        
        tasksByStatus() {
            // Define color palette
            const colors = ['#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#ec4899', '#14b8a6', '#f97316']
            
            return this.stacks.map((stack, index) => {
                const stackCards = this.cardsByStack(stack.id)
                return {
                    label: stack.title,
                    value: stackCards.length,
                    color: colors[index % colors.length]
                }
            })
        }
    },
    methods: {
        getCardStack(card) {
            return this.stacks.find(s => s.id === card.stackId) || null
        },
        isCardDone(card) {
            if (card.done !== null) return true
            const stack = this.getCardStack(card)
            if (!stack) return false
            const doneStackNames = ['Done', 'Approved/Done', 'Approved / Done']
            return doneStackNames.includes(stack.title)
        },
        isCardOpen(card) {
            if (card.archived === true) return false
            return !this.isCardDone(card)
        },
        hasImportantLabel(card) {
            const labels = Array.isArray(card?.labels) ? card.labels : []
            if (labels.length === 0) return false
            const importantTitles = ['belangrijk', 'important']
            return labels.some((l) => importantTitles.includes(String(l?.title ?? '').trim().toLowerCase()))
        },
        /**
         * Calculate if a card is overdue based on its due date and time
         * Includes hours, minutes, and seconds for precise calculation
         */
        isCardOverdue(card) {
            if (!card.duedate) return false
            
            // Reference currentTime to make this reactive
            // eslint-disable-next-line no-unused-vars
            const _ = this.currentTime
            
            const now = new Date()
            const dueDate = new Date(card.duedate)
            
            // Card is overdue if the due date/time has passed
            return dueDate < now
        }
    }
}

</script>

<style scoped lang="scss">
/* --- Design Tokens --- */
$border-color: #e2e8f0;
$bg-white: #ffffff;
$text-main: #0f172a;
$text-muted: #64748b;
$radius: 8px;

/* Colors */
$color-active: #3b82f6;

/* --- Main Widget Container --- */
.reporting-dashboard-saas {
    background: $bg-white;
    /* Border removed for a cleaner, flatter look */
    border-radius: $radius;
    padding: 16px; 
    width: 100%;
    box-sizing: border-box;
    display: flex;
    flex-direction: column;
}

/* --- Header --- */
.saas-header {
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

.icon-box {
    width: 32px;
    height: 32px;
    border-radius: 6px;
    background: #eff6ff; 
    color: $color-active;
    display: flex;
    align-items: center;
    justify-content: center;

    .chart-icon {
        width: 16px;
        height: 16px;
    }
}

.header-right {
    display: flex;
    align-items: center;
}

.status-pill {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 11px;
    font-weight: 600;
    padding: 3px 10px;
    border-radius: 99px;
    background: #f0fdf4;
    color: #15803d;
    border: 1px solid #dcfce7;
    text-transform: uppercase;
    letter-spacing: 0.03em;
}

.status-dot {
    width: 6px;
    height: 6px;
    background: #16a34a;
    border-radius: 50%;
}

/* --- Content Body --- */
.saas-content-body {
    display: flex;
    flex-direction: column;
    gap: 16px;
}

/* --- Grids --- */
.saas-kpi-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 16px;
}

.saas-charts-grid {
    display: grid;
    grid-template-columns: 1fr 2fr;
    gap: 16px;

    @media (max-width: 800px) {
        grid-template-columns: 1fr;
    }
}

/* --- Internal Chart Cards --- */
/* These retain their borders to define their areas within the flat dashboard */
.saas-chart-card {
    background: $bg-white;
    border: 1px solid $border-color;
    border-radius: $radius;
    padding: 16px;
    display: flex;
    flex-direction: column;
}

.chart-header {
    margin-bottom: 16px;
    
    h4 {
        margin: 0;
        font-size: 11px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: $text-muted;
    }
}

.chart-content {
    flex: 1;
    min-height: 180px;

    &.center-chart {
        display: flex;
        align-items: center;
        justify-content: center;
    }
}
</style>
