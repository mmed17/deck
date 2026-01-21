<template>
    <div class="saas-kpi-card">
        <div class="kpi-top">
            <span class="kpi-label">{{ title }}</span>
            <div class="kpi-icon" :style="{ color: color, background: color + '10' }">
                <component :is="iconComponent" :size="16" />
            </div>
        </div>
        <div class="kpi-mid">
            <span class="kpi-val">{{ formattedValue }}</span>
            <span v-if="suffix" class="kpi-unit">{{ suffix }}</span>
        </div>
    </div>
</template>

<script>
import CheckCircleIcon from 'vue-material-design-icons/CheckCircle.vue'
import ClockAlertIcon from 'vue-material-design-icons/ClockAlert.vue'
import FormatListBulletedIcon from 'vue-material-design-icons/FormatListBulleted.vue'
import AccountGroupIcon from 'vue-material-design-icons/AccountGroup.vue'

export default {
    name: 'KpiCard',
    components: { CheckCircleIcon, ClockAlertIcon, FormatListBulletedIcon, AccountGroupIcon },
    props: {
        title: String,
        value: [Number, String],
        icon: String,
        color: { type: String, default: '#3b82f6' },
        trend: String,
        trendValue: String,
        suffix: String
    },
    computed: {
        iconComponent() {
            const icons = { list: 'FormatListBulletedIcon', check: 'CheckCircleIcon', alert: 'ClockAlertIcon', group: 'AccountGroupIcon' }
            return icons[this.icon] || 'FormatListBulletedIcon'
        },
        formattedValue() {
            return typeof this.value === 'number' ? this.value.toLocaleString() : this.value
        }
    }
}
</script>

<style scoped lang="scss">
.saas-kpi-card {
    background: #ffffff;
    /* Clean, thin border */
    border: 1px solid #e2e8f0; 
    border-radius: 8px;
    padding: 16px;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    transition: border-color 0.2s;

    &:hover {
        border-color: #cbd5e1; /* Subtle darken on hover only */
    }
}

.kpi-top {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 12px;
}

.kpi-label {
    font-size: 13px;
    color: #64748b;
    font-weight: 500;
}

.kpi-icon {
    width: 28px;
    height: 28px;
    border-radius: 6px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.kpi-mid {
    display: flex;
    align-items: baseline;
    gap: 4px;
    margin-bottom: 8px;
}

.kpi-val {
    font-size: 24px;
    font-weight: 700;
    color: #0f172a;
    letter-spacing: -0.02em;
}

.kpi-unit {
    font-size: 12px;
    color: #94a3b8;
}

.kpi-btm {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 12px;
}

.trend-indicator {
    display: flex;
    align-items: center;
    gap: 2px;
    font-weight: 600;

    &.up { color: #10b981; }
    &.down { color: #ef4444; }
}

.period {
    color: #94a3b8;
}
</style>