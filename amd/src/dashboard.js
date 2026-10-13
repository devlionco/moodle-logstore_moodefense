// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * MooDefense dashboard charts and tooltips.
 *
 * @module     logstore_moodefense/dashboard
 * @copyright  Fensive <avishay@fensive.co.il>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['core/chartjs'], function(Chart) {

    const ROLE_COLORS_FALLBACK = ['#FF5733', '#33FF57', '#3357FF'];
    const SEVERITY_LABELS_FALLBACK = ['Critical', 'High', 'Medium', 'Low'];
    const SEVERITY_COLORS_FALLBACK = ['#c82333', '#c2410c', '#ffc107', '#1e7e34'];
    const CHART_TOOLTIP_ID = 'chart-tooltip';

    // --- Small, generic helpers -------------------------------------------------

    /**
     * Read and JSON-decode a data-* attribute, tolerating a missing element or invalid JSON.
     *
     * @param {string} elementId
     * @param {string} attribute
     * @return {?Object} the parsed payload, or null if it could not be read
     */
    const readJsonData = (elementId, attribute) => {
        const element = document.getElementById(elementId);
        if (!element) {
            return null;
        }
        try {
            return JSON.parse(element.getAttribute(attribute) || '{}');
        } catch (error) {
            return null;
        }
    };

    /**
     * Whether a parsed chart payload has at least one data point to plot.
     *
     * @param {?Object} data
     * @return {boolean}
     */
    const hasChartData = (data) => Boolean(data) && Array.isArray(data.data) && data.data.length > 0;

    // --- Sidebar "Others" tooltip ------------------------------------------------

    /**
     * Wire up every "Others" trigger so hovering/tapping it shows the breakdown markup
     * already rendered by the mustache template (see #roles-others-tooltip), as a
     * standard Bootstrap tooltip.
     */
    const initTooltipTargets = () => {
        document.querySelectorAll('[data-tooltip-target]').forEach((trigger) => {
            const target = document.querySelector(trigger.getAttribute('data-tooltip-target'));
            if (target) {
                trigger.setAttribute('data-html', 'true');
                trigger.setAttribute('title', target.innerHTML);
            }
        });
    };

    // --- Shared floating chart tooltip -----------------------------------------------
    // One tooltip element, one positioning/show/hide routine, reused by every chart on
    // this page so hovering (desktop) or tapping (touch) any of them looks and behaves
    // exactly the same way.

    /**
     * Lazily create the floating tooltip element shared by all charts on this page.
     *
     * @return {HTMLElement}
     */
    const getFloatingTooltipEl = () => {
        let tooltipEl = document.getElementById(CHART_TOOLTIP_ID);
        if (!tooltipEl) {
            tooltipEl = document.createElement('div');
            tooltipEl.id = CHART_TOOLTIP_ID;
            tooltipEl.className = 'chart-tooltip';
            document.body.appendChild(tooltipEl);
        }
        return tooltipEl;
    };

    /**
     * Hide the shared floating tooltip, if it exists.
     */
    const hideFloatingTooltip = () => {
        const tooltipEl = document.getElementById(CHART_TOOLTIP_ID);
        if (tooltipEl) {
            tooltipEl.style.opacity = 0;
        }
    };

    /**
     * Build a Chart.js `options.plugins.tooltip.external` handler around the shared
     * floating tooltip element. Positioning/show/hide is identical for every chart;
     * only how the tooltip's content is filled in differs between them.
     *
     * @param {Function} fillContent (tooltipEl, dataPoint) => void - writes the tooltip body
     * @return {Function} external tooltip callback
     */
    const createTooltipHandler = (fillContent) => (context) => {
        const tooltipModel = context.tooltip;
        const dataPoint = tooltipModel.dataPoints && tooltipModel.dataPoints[0];

        if (tooltipModel.opacity === 0 || !dataPoint) {
            hideFloatingTooltip();
            return;
        }

        const tooltipEl = getFloatingTooltipEl();
        fillContent(tooltipEl, dataPoint);

        const canvasRect = context.chart.canvas.getBoundingClientRect();
        tooltipEl.style.opacity = 1;
        tooltipEl.style.left = `${canvasRect.left + window.pageXOffset + tooltipModel.caretX}px`;
        tooltipEl.style.top = `${canvasRect.top + window.pageYOffset + tooltipModel.caretY}px`;
    };

    /**
     * On touch devices there is no "mouse left the canvas" event, so a tapped tooltip
     * would otherwise stay on screen until another slice/bar is tapped. Dismiss it on
     * any interaction outside a chart canvas instead.
     */
    const initTooltipDismissal = () => {
        document.addEventListener('pointerdown', (event) => {
            if (!event.target.closest('canvas')) {
                hideFloatingTooltip();
            }
        });
    };

    // --- Roles pie chart -----------------------------------------------------------

    /**
     * Fill the shared tooltip for the roles pie chart. The "Others" slice reuses the
     * breakdown markup already rendered by the mustache template; any other slice just
     * gets the plain "name: count" text.
     *
     * @param {Object} chartData parsed chart data (labels/colors/othersIndex/othersDetail)
     * @param {HTMLElement} tooltipEl
     * @param {Object} dataPoint Chart.js tooltip data point
     */
    const fillRolesTooltip = (chartData, tooltipEl, dataPoint) => {
        const isOthersSlice = chartData.othersIndex !== null
            && chartData.othersIndex !== undefined
            && dataPoint.dataIndex === chartData.othersIndex;

        if (isOthersSlice) {
            const source = document.getElementById('roles-others-tooltip');
            tooltipEl.innerHTML = source ? source.innerHTML : '';
        } else {
            const roleName = dataPoint.label.split(' (')[0];
            tooltipEl.textContent = `${roleName}: ${dataPoint.parsed} users`;
        }
    };

    /**
     * Build the Chart.js config for the roles pie chart.
     *
     * @param {Object} chartData
     * @return {Object} Chart.js configuration
     */
    const buildRolesChartConfig = (chartData) => ({
        type: 'pie',
        data: {
            labels: chartData.labels,
            datasets: [{
                data: chartData.data,
                backgroundColor: (chartData.colors && chartData.colors.length)
                    ? chartData.colors : ROLE_COLORS_FALLBACK,
                borderColor: '#fff',
                borderWidth: 2
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {display: false},
                tooltip: {
                    enabled: false,
                    external: createTooltipHandler(
                        (tooltipEl, dataPoint) => fillRolesTooltip(chartData, tooltipEl, dataPoint)
                    )
                }
            }
        }
    });

    // --- Severity bar chart --------------------------------------------------------

    /**
     * Fill the shared tooltip for the severity bar chart with the plain "name: count" text.
     *
     * @param {HTMLElement} tooltipEl
     * @param {Object} dataPoint Chart.js tooltip data point
     */
    const fillSeverityTooltip = (tooltipEl, dataPoint) => {
        tooltipEl.textContent = `${dataPoint.label}: ${dataPoint.parsed.y} issues`;
    };

    /**
     * Build the Chart.js config for the severity bar chart.
     *
     * @param {Object} severityData
     * @return {Object} Chart.js configuration
     */
    const buildSeverityChartConfig = (severityData) => ({
        type: 'bar',
        data: {
            labels: severityData.labels || SEVERITY_LABELS_FALLBACK,
            datasets: [{
                data: severityData.data,
                backgroundColor: (severityData.colors && severityData.colors.length)
                    ? severityData.colors : SEVERITY_COLORS_FALLBACK,
                borderRadius: 4,
                borderSkipped: false
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {display: false},
                tooltip: {
                    enabled: false,
                    external: createTooltipHandler(fillSeverityTooltip)
                }
            },
            scales: {
                x: {
                    grid: {display: false},
                    ticks: {font: {weight: 600}}
                },
                y: {
                    beginAtZero: true,
                    grid: {color: 'rgba(0,0,0,0.1)'},
                    ticks: {
                        stepSize: 1,
                        callback: (value) => Math.floor(value)
                    }
                }
            }
        }
    });

    // --- Bootstrapping ---------------------------------------------------------------

    /**
     * Render a chart into a container, provided both the container and its data exist.
     * Inserts a fresh <canvas> for Chart.js to draw into.
     *
     * @param {string} containerId DOM id of the wrapper element the canvas is appended to
     * @param {?Object} data parsed chart payload, or null if it couldn't be read
     * @param {Function} buildConfig (data) => Chart.js configuration
     */
    const renderChart = (containerId, data, buildConfig) => {
        const container = document.getElementById(containerId);
        if (!container || !hasChartData(data)) {
            return;
        }
        const canvas = document.createElement('canvas');
        container.appendChild(canvas);
        new Chart(canvas, buildConfig(data));
    };

    return {
        init: function() {
            initTooltipTargets();

            if (typeof Chart === 'undefined') {
                return;
            }

            initTooltipDismissal();
            renderChart('rolesChart', readJsonData('chart-data', 'data-chartdata'), buildRolesChartConfig);
            renderChart('severityChart', readJsonData('severity-data', 'data-severitydata'), buildSeverityChartConfig);
        }
    };
});
