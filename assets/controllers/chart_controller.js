import { Controller } from '@hotwired/stimulus';
import * as echarts from 'echarts';
import { mindTheWaitTheme } from '../themes/echarts-theme.js';

/**
 * ECharts controller for rendering interactive charts
 *
 * Usage:
 *   <div data-controller="chart"
 *        data-chart-options-value="{{ chartOptions|json_encode }}"
 *        data-chart-theme-value="mindTheWait"
 *        style="height: 400px;"></div>
 */
export default class extends Controller {
    static values = {
        options: Object,
        theme: { type: String, default: 'mindTheWait' }
    };

    connect() {
        // Register custom theme
        echarts.registerTheme('mindTheWait', mindTheWaitTheme);

        // Initialize chart
        this.chart = echarts.init(this.element, this.themeValue);
        this.chart.setOption(this.optionsValue);

        // Handle window resize
        this.resizeObserver = new ResizeObserver(() => {
            this.chart.resize();
        });
        this.resizeObserver.observe(this.element);

        // Also listen to window resize for mobile
        this.boundResize = this.resize.bind(this);
        window.addEventListener('resize', this.boundResize);
    }

    disconnect() {
        if (this.resizeObserver) {
            this.resizeObserver.disconnect();
        }
        window.removeEventListener('resize', this.boundResize);
        if (this.chart) {
            this.chart.dispose();
        }
    }

    resize() {
        if (this.chart) {
            this.chart.resize();
        }
    }

    /**
     * Update chart with new options
     * Can be called from other controllers or via actions
     */
    updateOptions(newOptions) {
        if (this.chart) {
            this.chart.setOption(newOptions, true);
        }
    }

    /**
     * Get chart instance for advanced usage
     */
    getChart() {
        return this.chart;
    }
}
