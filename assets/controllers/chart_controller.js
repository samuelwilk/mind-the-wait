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

        // Apply mobile optimizations for stop-level reliability chart
        if (options.title?.text?.includes('Stop-Level Reliability')) {
            this.applyMobileOptimizations(options);
        }

        // Set the options from PHP
        this.chart.setOption(options);

        // Handle resize
        this.resizeObserver = new ResizeObserver(() => {
            if (this.chart) {
                // Reapply mobile optimizations on resize for stop-level chart
                if (options.title?.text?.includes('Stop-Level Reliability')) {
                    this.applyMobileOptimizations(options);
                    this.chart.setOption(options, true);
                }
                this.chart.resize();
            }
        });
        this.resizeObserver.observe(this.element);

        window.addEventListener('resize', () => {
            if (options.title?.text?.includes('Stop-Level Reliability')) {
                this.applyMobileOptimizations(options);
                this.chart.setOption(options, true);
            }
            this.chart.resize();
        });
    }

    applyMobileOptimizations(options) {
        const isMobile = window.innerWidth < 768;
        const isSmallMobile = window.innerWidth < 480;

        if (isMobile) {
            // Adjust grid for mobile to give more space for stop names
            options.grid = {
                left: isSmallMobile ? '60%' : '50%',
                right: '10%',
                top: '60',
                bottom: '5%',
                containLabel: false,
            };

            // Reduce font sizes for mobile
            if (options.yAxis && options.yAxis.axisLabel) {
                options.yAxis.axisLabel.fontSize = isSmallMobile ? 10 : 11;
            } else if (options.yAxis) {
                options.yAxis.axisLabel = {
                    fontSize: isSmallMobile ? 10 : 11,
                };
            }

            if (options.xAxis && options.xAxis.axisLabel) {
                options.xAxis.axisLabel.fontSize = isSmallMobile ? 10 : 11;
            } else if (options.xAxis) {
                options.xAxis.axisLabel = {
                    fontSize: isSmallMobile ? 10 : 11,
                };
            }

            if (options.xAxis && options.xAxis.nameTextStyle) {
                options.xAxis.nameTextStyle.fontSize = isSmallMobile ? 11 : 12;
            } else if (options.xAxis) {
                options.xAxis.nameTextStyle = {
                    fontSize: isSmallMobile ? 11 : 12,
                };
            }

            // Adjust title font size
            if (options.title) {
                options.title.textStyle = {
                    fontSize: isSmallMobile ? 12 : 14,
                };
                // Shorten title on mobile
                if (isSmallMobile) {
                    options.title.text = 'Stop-Level Reliability';
                }
            }

            // Adjust series label font size
            if (options.series && options.series[0] && options.series[0].label) {
                options.series[0].label.fontSize = isSmallMobile ? 9 : 10;
            }

            // Truncate long stop names on y-axis
            if (options.yAxis) {
                options.yAxis.axisLabel = {
                    ...options.yAxis.axisLabel,
                    fontSize: isSmallMobile ? 10 : 11,
                    width: isSmallMobile ? 120 : 150,
                    overflow: 'truncate',
                    ellipsis: '...',
                };
            }
        }
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
