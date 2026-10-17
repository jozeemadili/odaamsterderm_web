import { Chart, registerables } from 'chart.js';

Chart.register(...registerables);
Chart.defaults.font.family = "'Inter', 'SF Pro Display', system-ui, -apple-system, sans-serif";
Chart.defaults.font.weight = 400;
Chart.defaults.color = '#64748d';

window.Chart = Chart;
