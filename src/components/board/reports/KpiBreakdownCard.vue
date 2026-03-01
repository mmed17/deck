<template>
	<div class="saas-kpi-card">
		<div class="kpi-top">
			<span class="kpi-label">{{ title }}</span>
			<div class="kpi-icon" :style="{ color: iconColor, background: iconColor + '10' }">
				<FormatListBulletedIcon :size="16" />
			</div>
		</div>

		<div class="kpi-breakdown">
			<div class="kpi-breakdown__col">
				<span class="kpi-breakdown__value" :style="{ color: leftColor }">{{ formatBreakdownValue(leftNumeric, leftDisplayTotal) }}</span>
				<span class="kpi-breakdown__hint">{{ leftLabel }}</span>
			</div>
			<div class="kpi-breakdown__divider" />
			<div class="kpi-breakdown__col">
				<span class="kpi-breakdown__value" :style="{ color: rightColor }">{{ formatBreakdownValue(rightNumeric, rightDisplayTotal) }}</span>
				<span class="kpi-breakdown__hint">{{ rightLabel }}</span>
			</div>
		</div>

		<div v-if="showBar" class="kpi-breakdown__bar" aria-hidden="true">
			<div class="kpi-breakdown__seg" :style="{ width: leftPercent + '%', background: leftColor }" />
			<div class="kpi-breakdown__seg" :style="{ width: rightPercent + '%', background: rightColor }" />
		</div>
	</div>
</template>

<script>
import FormatListBulletedIcon from 'vue-material-design-icons/FormatListBulleted.vue'

export default {
	name: 'KpiBreakdownCard',
	components: {
		FormatListBulletedIcon,
	},
	props: {
		title: {
			type: String,
			required: true,
		},
		leftLabel: {
			type: String,
			required: true,
		},
		leftValue: {
			type: [Number, String],
			required: true,
		},
		leftTotal: {
			type: [Number, String],
			default: null,
		},
		leftColor: {
			type: String,
			default: '#ef4444',
		},
		rightLabel: {
			type: String,
			required: true,
		},
		rightValue: {
			type: [Number, String],
			required: true,
		},
		rightTotal: {
			type: [Number, String],
			default: null,
		},
		rightColor: {
			type: String,
			default: '#334155',
		},
		iconColor: {
			type: String,
			default: '#3b82f6',
		},
	},
	computed: {
		leftNumeric() {
			const left = typeof this.leftValue === 'number' ? this.leftValue : Number(this.leftValue)
			return Number.isFinite(left) ? left : 0
		},
		rightNumeric() {
			const right = typeof this.rightValue === 'number' ? this.rightValue : Number(this.rightValue)
			return Number.isFinite(right) ? right : 0
		},
		totalNumeric() {
			return this.leftNumeric + this.rightNumeric
		},
		leftDisplayTotal() {
			const leftTotal = typeof this.leftTotal === 'number' ? this.leftTotal : Number(this.leftTotal)
			const safeLeftTotal = Number.isFinite(leftTotal) ? leftTotal : this.totalNumeric
			return Math.max(safeLeftTotal, 0)
		},
		rightDisplayTotal() {
			const rightTotal = typeof this.rightTotal === 'number' ? this.rightTotal : Number(this.rightTotal)
			const safeRightTotal = Number.isFinite(rightTotal) ? rightTotal : this.totalNumeric
			return Math.max(safeRightTotal, 0)
		},
		showBar() {
			return this.totalNumeric > 0
		},
		leftPercent() {
			return this.totalNumeric > 0 ? Math.round((this.leftNumeric / this.totalNumeric) * 100) : 0
		},
		rightPercent() {
			return 100 - this.leftPercent
		},
	},
	methods: {
		formatBreakdownValue(value, total) {
			const safeValue = Number.isFinite(value) ? value : 0
			const safeTotal = Number.isFinite(total) ? Math.max(total, 0) : 0
			return `${safeValue.toLocaleString()}/${safeTotal.toLocaleString()}`
		},
	},
}
</script>

<style scoped lang="scss">
.saas-kpi-card {
	background: #ffffff;
	border: 1px solid #e2e8f0;
	border-radius: 8px;
	padding: 16px;
	display: flex;
	flex-direction: column;
	justify-content: space-between;
	transition: border-color 0.2s;
}

.saas-kpi-card:hover {
	border-color: #cbd5e1;
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

.kpi-breakdown {
	display: grid;
	grid-template-columns: 1fr 1px 1fr;
	align-items: end;
	gap: 12px;
	margin-bottom: 10px;
}

.kpi-breakdown__divider {
	width: 1px;
	height: 34px;
	background: #e2e8f0;
	justify-self: center;
}

.kpi-breakdown__col {
	display: flex;
	flex-direction: column;
	gap: 2px;
}

.kpi-breakdown__value {
	font-size: 22px;
	font-weight: 800;
	letter-spacing: -0.02em;
	line-height: 1;
}

.kpi-breakdown__hint {
	font-size: 11px;
	font-weight: 600;
	text-transform: uppercase;
	letter-spacing: 0.05em;
	color: #94a3b8;
}

.kpi-breakdown__bar {
	width: 100%;
	height: 6px;
	border-radius: 99px;
	overflow: hidden;
	display: flex;
	background: #f1f5f9;
}

.kpi-breakdown__seg {
	height: 100%;
}
</style>
