import { Controller } from '@hotwired/stimulus';
import * as echarts from 'echarts/dist/echarts.js';
import { mindTheWaitTheme } from '../themes/echarts-theme.js';

/**
 * ECharts controller - using full dist bundle for AssetMapper compatibility
 */
export default class extends Controller {
    static values = {
        options: Object,
        theme: { type: String, default: 'mindTheWait' }
    };

    connect() {
        requestAnimationFrame(() => {
            this.initializeChart();
        });
    }

    initializeChart() {
        if (this.element.clientWidth === 0 || this.element.clientHeight === 0) {
            console.error('Chart container has zero dimensions!');
            return;
        }

        // Register custom theme
        echarts.registerTheme('mindTheWait', mindTheWaitTheme);

        // Initialize with full bundle and custom theme
        this.chart = echarts.init(this.element, this.themeValue);

        // Fix heatmap formatter (can't be passed from PHP as a string)
        const options = this.optionsValue;
        if (options.series && options.series[0]?.type === 'heatmap') {
            options.series[0].label.formatter = function(params) {
                return params.value[2] + '%';
            };
            // Also fix tooltip formatter
            options.tooltip.formatter = function(params) {
                const day = params.name;
                const time = params.value[1];
                const performance = params.value[2];
                return `<strong>${day}</strong><br/>Time: ${options.yAxis.data[time]}<br/>Performance: ${performance}%`;
            };
        }

        // Set the options from PHP
        this.chart.setOption(options);

        // Handle resize
        this.resizeObserver = new ResizeObserver(() => {
            if (this.chart) {
                this.chart.resize();
            }
        });
        this.resizeObserver.observe(this.element);

        window.addEventListener('resize', () => this.chart.resize());
    }

    disconnect() {
        if (this.resizeObserver) {
            this.resizeObserver.disconnect();
        }
        if (this.chart) {
            this.chart.dispose();
        }
    }
}
