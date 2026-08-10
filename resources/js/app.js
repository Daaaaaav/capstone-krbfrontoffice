import './bootstrap';
import rover from '@sheaf/rover';
import './globals/modals';
import './validation/manager-input-validator';
import { Chart, registerables } from 'chart.js';

Chart.register(...registerables);
window.Chart = Chart;

document.addEventListener('alpine:init', () => {
	if (!window.Alpine) return;
	window.Alpine.plugin(rover);
	import('./components/select');
});
