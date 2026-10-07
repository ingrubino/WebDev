<script setup>
// Porting in Vue di GraficoCartesiano.php: grafico SVG a punti e linea,
// ridisegnato automaticamente quando cambiano i dati del form.
import { computed } from 'vue'

const props = defineProps({
  points: { type: Array, required: true }, // [[x, y], ...]
  title: { type: String, default: '' },
  xLabel: { type: String, default: 'X' },
  yLabel: { type: String, default: 'Y' },
  width: { type: Number, default: 600 },
  height: { type: Number, default: 400 },
  margin: { type: Number, default: 70 },
})

// Passo "arrotondato" (1, 2, 5 x 10^n) per avere circa `count` tacche
function niceStep(range, count = 8) {
  const raw = (range || 1) / count
  const mag = 10 ** Math.floor(Math.log10(raw))
  const norm = raw / mag
  return (norm < 1.5 ? 1 : norm < 3 ? 2 : norm < 7 ? 5 : 10) * mag
}

function axis(values) {
  let min = Math.min(...values)
  let max = Math.max(...values)
  if (min === max) { min -= 1; max += 1 }
  const pad = (max - min) * 0.1
  min -= pad
  max += pad
  const step = niceStep(max - min)
  const ticks = []
  for (let v = Math.ceil(min / step) * step; v <= max + 1e-9; v += step) ticks.push(+v.toPrecision(12))
  return { min, max, ticks }
}

const chart = computed(() => {
  const pts = props.points
  if (pts.length === 0) return null
  const x = axis(pts.map((p) => p[0]))
  const y = axis(pts.map((p) => p[1]))
  const w = props.width - 2 * props.margin
  const h = props.height - 2 * props.margin
  const px = (v) => props.margin + ((v - x.min) / (x.max - x.min)) * w
  const py = (v) => props.height - props.margin - ((v - y.min) / (y.max - y.min)) * h
  const screen = pts.map(([a, b]) => [px(a), py(b)])
  return {
    x, y, px, py,
    screen,
    path: screen.map(([a, b], i) => `${i ? 'L' : 'M'} ${a.toFixed(2)} ${b.toFixed(2)}`).join(' '),
  }
})

const fmt = (v) => (Math.abs(v) >= 1000 ? v.toExponential(1) : +v.toFixed(2))
</script>

<template>
  <svg :viewBox="`0 0 ${width} ${height}`" class="chart" role="img" :aria-label="title">
    <rect :width="width" :height="height" class="bg" />
    <text v-if="!chart" :x="width / 2" :y="height / 2" text-anchor="middle" class="label">No data to plot</text>
    <template v-else>
      <g v-for="t in chart.y.ticks" :key="'y' + t">
        <line :x1="margin" :x2="width - margin" :y1="chart.py(t)" :y2="chart.py(t)" class="grid" />
        <text :x="margin - 8" :y="chart.py(t) + 4" text-anchor="end" class="label">{{ fmt(t) }}</text>
      </g>
      <g v-for="t in chart.x.ticks" :key="'x' + t">
        <line :x1="chart.px(t)" :x2="chart.px(t)" :y1="margin" :y2="height - margin" class="grid" />
        <text :x="chart.px(t)" :y="height - margin + 18" text-anchor="middle" class="label">{{ fmt(t) }}</text>
      </g>
      <line v-if="chart.y.min <= 0 && chart.y.max >= 0" :x1="margin" :x2="width - margin" :y1="chart.py(0)" :y2="chart.py(0)" class="axis" />
      <line v-if="chart.x.min <= 0 && chart.x.max >= 0" :x1="chart.px(0)" :x2="chart.px(0)" :y1="margin" :y2="height - margin" class="axis" />
      <path :d="chart.path" class="line" />
      <circle v-for="([cx, cy], i) in chart.screen" :key="i" :cx="cx" :cy="cy" r="4" class="point">
        <title>{{ points[i][0] }}, {{ points[i][1] }}</title>
      </circle>
    </template>
    <text :x="width / 2" y="30" text-anchor="middle" class="title">{{ title }}</text>
    <text :x="width / 2" :y="height - 15" text-anchor="middle" class="title">{{ xLabel }}</text>
    <text x="22" :y="height / 2" text-anchor="middle" class="title" :transform="`rotate(-90, 22, ${height / 2})`">{{ yLabel }}</text>
  </svg>
</template>

<style scoped>
.chart { width: 100%; max-width: 640px; height: auto; display: block; }
.bg { fill: #ffffff; }
.grid { stroke: #e0e0e0; stroke-width: 1; }
.axis { stroke: #b4b4b4; stroke-width: 2; }
.line { stroke: #009900; stroke-width: 2; fill: none; }
.point { fill: #ff6600; stroke: #ff6600; }
.label { font: 12px Arial, sans-serif; fill: #000; }
.title { font: bold 15px Arial, sans-serif; fill: #000; }
</style>
