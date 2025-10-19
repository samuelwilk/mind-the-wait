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

        // Fix heatmap and scatter formatter (can't be passed from PHP as a string)
        const options = this.optionsValue;

        if (options.series && (options.series[0]?.type === 'heatmap')) {
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

        // Fix scatter chart tooltip (Temperature Threshold Analysis)
        // Check if any series is a scatter type (chart may have multiple series)
        const hasScatter = options.series?.some(s => s.type === 'scatter');
        if (hasScatter) {
            options.tooltip.formatter = function(params) {
                // Handle both array params (multiple series) and single params
                const param = Array.isArray(params) ? params[0] : params;

                if (param && param.value && Array.isArray(param.value)) {
                    // Parse and format values explicitly to avoid locale issues
                    const temperature = Math.round(parseFloat(param.value[0]));
                    const performance = Math.round(parseFloat(param.value[1]) * 10) / 10;
                    return `Temperature: ${temperature}°C<br/>Performance: ${performance}%`;
                }
                return '';
            };
        }

        // Fix bunching by weather tooltip
        if (options.title?.text?.includes('Bunching Rate by Weather')) {
            options.tooltip.formatter = function(params) {
                const condition = params[0].name;
                const rate = params[0].value;
                const exposureHours = params[0].data.exposureHours || 0;

                return `
                    <strong>${condition}</strong><br/>
                    Rate: ${rate.toFixed(2)} incidents/hour<br/>
                    Exposure: ${Math.round(exposureHours)} hours
                `;
            };

            // Update label formatter to show rate with decimals
            if (options.series[0].label) {
                options.series[0].label.formatter = function(params) {
                    return params.value.toFixed(2);
                };
            }
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
