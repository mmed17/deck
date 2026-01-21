<template>
    <div class="simple-bar-chart">
        <div class="chart-container">
            <div 
                v-for="(item, index) in normalizedData" 
                :key="index" 
                class="bar-group"
            >
                <div class="bar-tooltip">{{ item.originalValue }} Tasks</div>
                
                <div 
                    class="bar" 
                    :style="{ 
                        height: item.percentage + '%', 
                        backgroundColor: item.color 
                    }"
                ></div>
                <span class="bar-label">{{ item.label }}</span>
            </div>
        </div>
    </div>
</template>

<script>
export default {
    name: 'BarChart',
    props: {
        data: {
            type: Array,
            default: () => [] // Expected: [{ label, value, color }]
        }
    },
    computed: {
        maxValue() {
            return Math.max(...this.data.map(d => d.value), 1)
        },
        normalizedData() {
            // Convert values to percentages relative to the highest bar
            return this.data.map(item => ({
                ...item,
                originalValue: item.value,
                percentage: (item.value / this.maxValue) * 100
            }))
        }
    }
}
</script>

<style scoped lang="scss">
.simple-bar-chart {
    width: 100%;
    height: 200px;
    padding-top: 20px;
}

.chart-container {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    height: 100%;
    gap: 12px;
}

.bar-group {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    position: relative;
    height: 100%;
    justify-content: flex-end;
    
    // Tooltip logic
    &:hover .bar-tooltip {
        opacity: 1;
        transform: translate(-50%, -5px);
    }
}

.bar {
    width: 100%;
    max-width: 40px;
    border-radius: 4px 4px 0 0;
    transition: height 0.5s cubic-bezier(0.4, 0, 0.2, 1), opacity 0.2s;
    opacity: 0.8;
    min-height: 4px; // Ensure visibility even if 0

    .bar-group:hover & {
        opacity: 1;
    }
}

.bar-label {
    margin-top: 8px;
    font-size: 11px;
    color: #64748b;
    text-align: center;
    font-weight: 500;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    width: 100%;
}

.bar-tooltip {
    position: absolute;
    bottom: 100%; // Sit on top of the bar area
    left: 50%;
    transform: translate(-50%, 5px);
    background: #1e293b;
    color: #fff;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 10px;
    white-space: nowrap;
    opacity: 0;
    pointer-events: none;
    transition: all 0.2s ease;
    z-index: 10;
    margin-bottom: 5px; /* Offset from bar top */
}
</style>