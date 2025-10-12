/**
 * Mind the Wait - Custom ECharts Theme
 * Matches the Tailwind CSS theme defined in app.css
 */
export const mindTheWaitTheme = {
    // Color palette - matches our Tailwind theme
    color: [
        '#0ea5e9', // chart-1 (primary-500)
        '#8b5cf6', // chart-2 (purple)
        '#ec4899', // chart-3 (pink)
        '#f59e0b', // chart-4 (warning)
        '#10b981', // chart-5 (success)
        '#6366f1', // chart-6 (indigo)
    ],

    // Background
    backgroundColor: 'transparent',

    // Text styles
    textStyle: {
        fontFamily: 'ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif',
        color: '#111827', // gray-900
    },

    // Title
    title: {
        textStyle: {
            color: '#111827', // gray-900
            fontWeight: 600,
            fontSize: 18,
        },
        subtextStyle: {
            color: '#6b7280', // gray-500
            fontSize: 14,
        },
    },

    // Line chart
    line: {
        itemStyle: {
            borderWidth: 2,
        },
        lineStyle: {
            width: 3,
        },
        symbolSize: 6,
        symbol: 'circle',
        smooth: false,
    },

    // Bar chart
    bar: {
        itemStyle: {
            barBorderRadius: [4, 4, 0, 0],
        },
    },

    // Axis
    categoryAxis: {
        axisLine: {
            show: true,
            lineStyle: {
                color: '#e5e7eb', // gray-200
            },
        },
        axisTick: {
            show: false,
        },
        axisLabel: {
            color: '#6b7280', // gray-500
            fontSize: 12,
        },
        splitLine: {
            show: false,
        },
    },

    valueAxis: {
        axisLine: {
            show: false,
        },
        axisTick: {
            show: false,
        },
        axisLabel: {
            color: '#6b7280', // gray-500
            fontSize: 12,
        },
        splitLine: {
            show: true,
            lineStyle: {
                color: '#f3f4f6', // gray-100
                type: 'dashed',
            },
        },
    },

    // Legend
    legend: {
        textStyle: {
            color: '#6b7280', // gray-500
            fontSize: 13,
        },
        icon: 'circle',
    },

    // Tooltip
    tooltip: {
        backgroundColor: 'rgba(255, 255, 255, 0.95)',
        borderColor: '#e5e7eb', // gray-200
        borderWidth: 1,
        textStyle: {
            color: '#111827', // gray-900
            fontSize: 13,
        },
        padding: [10, 15],
        extraCssText: 'box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06); border-radius: 8px;',
    },

    // Grid
    grid: {
        left: '3%',
        right: '4%',
        bottom: '3%',
        top: '12%',
        containLabel: true,
    },

    // Toolbox (export, zoom, etc.)
    toolbox: {
        iconStyle: {
            borderColor: '#6b7280', // gray-500
        },
        emphasis: {
            iconStyle: {
                borderColor: '#0ea5e9', // primary-500
            },
        },
    },

    // DataZoom
    dataZoom: {
        backgroundColor: 'rgba(243, 244, 246, 0.8)', // gray-100 with opacity
        dataBackgroundColor: 'rgba(229, 231, 235, 0.8)', // gray-200 with opacity
        fillerColor: 'rgba(14, 165, 233, 0.2)', // primary-500 with opacity
        handleColor: '#0ea5e9', // primary-500
        handleSize: '100%',
        textStyle: {
            color: '#6b7280', // gray-500
        },
    },

    // Heatmap
    visualMap: {
        textStyle: {
            color: '#6b7280', // gray-500
        },
        inRange: {
            color: ['#f0f9ff', '#0ea5e9', '#0369a1'], // primary-50 to primary-500 to primary-700
        },
    },

    // Timeline
    timeline: {
        lineStyle: {
            color: '#e5e7eb', // gray-200
        },
        itemStyle: {
            color: '#0ea5e9', // primary-500
        },
        controlStyle: {
            color: '#0ea5e9', // primary-500
            borderColor: '#0ea5e9',
        },
        checkpointStyle: {
            color: '#0ea5e9', // primary-500
            borderColor: 'rgba(14, 165, 233, 0.3)',
        },
        label: {
            color: '#6b7280', // gray-500
        },
    },
};
