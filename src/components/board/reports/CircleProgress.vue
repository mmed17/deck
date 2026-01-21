<template>
    <div class="circle-progress-wrapper">
        <svg :width="size" :height="size" :viewBox="`0 0 ${size} ${size}`">
            <circle
                class="circle-bg"
                :cx="center"
                :cy="center"
                :r="radius"
                :stroke-width="strokeWidth"
                fill="none"
            />
            <circle
                class="circle-fill"
                :cx="center"
                :cy="center"
                :r="radius"
                :stroke-width="strokeWidth"
                :stroke="color"
                :stroke-dasharray="circumference"
                :stroke-dashoffset="dashOffset"
                fill="none"
                stroke-linecap="round"
            />
        </svg>
        <div class="circle-content">
            <span class="value">{{ percentage }}%</span>
            <span v-if="label" class="label">{{ label }}</span>
        </div>
    </div>
</template>

<script>
export default {
    name: 'CircleProgress',
    props: {
        percentage: { type: Number, default: 0 },
        size: { type: Number, default: 120 },
        strokeWidth: { type: Number, default: 10 },
        color: { type: String, default: '#3b82f6' },
        label: { type: String, default: '' },
    },
    computed: {
        center() { return this.size / 2 },
        radius() { return (this.size - this.strokeWidth) / 2 },
        circumference() { return 2 * Math.PI * this.radius },
        dashOffset() {
            const progress = Math.min(100, Math.max(0, this.percentage))
            return this.circumference - (progress / 100) * this.circumference
        },
    },
}
</script>

<style scoped lang="scss">
.circle-progress-wrapper {
    position: relative;
    display: inline-flex;
    align-items: center;
    justify-content: center;

    svg {
        transform: rotate(-90deg);
    }
}

.circle-bg {
    stroke: #e2e8f0; /* Light gray */
}

.circle-fill {
    transition: stroke-dashoffset 0.8s ease-out;
}

.circle-content {
    position: absolute;
    display: flex;
    flex-direction: column;
    align-items: center;
}

.value {
    font-size: 28px;
    font-weight: 700;
    color: #1e293b;
    line-height: 1;
}

.label {
    font-size: 12px;
    font-weight: 500;
    color: #94a3b8;
    margin-top: 4px;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}
</style>