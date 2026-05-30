import ApexCharts from 'apexcharts';

const dataEl = document.getElementById('visitor-analytics-data');
if (dataEl) {
    const data = JSON.parse(dataEl.textContent || '{}');
    const stringsEl = document.getElementById('visitor-analytics-strings');
    const strings = stringsEl ? JSON.parse(stringsEl.textContent || '{}') : {};
    const labelMap = strings.label_map || {};
    const noDataLabel = strings.no_data || 'No data available';
    const pageviewsLabel = strings.pageviews || 'Pageviews';
    const uniqueVisitorsLabel = strings.unique_visitors || 'Unique Visitors';
    const countLabel = strings.count || 'Count';
    const isDark = document.documentElement.classList.contains('dark');

    const chartTheme = {
        mode: isDark ? 'dark' : 'light',
        palette: 'palette2',
    };

    const axisLabelColor = isDark ? '#cbd5f5' : '#475569';
    const gridColor = isDark ? '#1f2937' : '#e2e8f0';

    const renderEmpty = (id, message) => {
        const el = document.getElementById(id);
        if (!el) {
            return;
        }
        el.textContent = message;
        el.classList.add('text-sm', 'text-gray-500', 'dark:text-gray-400');
    };

    const localizeLabel = (label) => labelMap[label] || label;

    const mapCategoryData = (values) => {
        const entries = Object.entries(values || {});
        return {
            labels: entries.map(([label]) => localizeLabel(label)),
            values: entries.map(([, value]) => value),
        };
    };

    const renderLineChart = (id, labels, series) => {
        const el = document.getElementById(id);
        if (!el) {
            return;
        }
        if (!labels.length || !series.length) {
            renderEmpty(id, noDataLabel);
            return;
        }

        const options = {
            chart: {
                type: 'line',
                height: 280,
                toolbar: { show: false },
            },
            series,
            xaxis: {
                categories: labels,
                labels: { style: { colors: axisLabelColor } },
            },
            yaxis: {
                labels: { style: { colors: axisLabelColor } },
            },
            stroke: { curve: 'smooth', width: 3 },
            grid: { borderColor: gridColor },
            theme: chartTheme,
            colors: ['#2563eb', '#10b981'],
            legend: { labels: { colors: axisLabelColor } },
        };

        new ApexCharts(el, options).render();
    };

    const renderBarChart = (id, labels, values) => {
        const el = document.getElementById(id);
        if (!el) {
            return;
        }
        if (!labels.length || !values.length) {
            renderEmpty(id, noDataLabel);
            return;
        }

        const options = {
            chart: {
                type: 'bar',
                height: 280,
                toolbar: { show: false },
            },
            series: [{
                name: countLabel,
                data: values,
            }],
            xaxis: {
                categories: labels,
                labels: { style: { colors: axisLabelColor } },
            },
            yaxis: {
                labels: { style: { colors: axisLabelColor } },
            },
            plotOptions: {
                bar: { borderRadius: 6, columnWidth: '55%' },
            },
            grid: { borderColor: gridColor },
            theme: chartTheme,
            colors: ['#6366f1'],
            tooltip: { theme: chartTheme.mode },
        };

        new ApexCharts(el, options).render();
    };

    const renderDonutChart = (id, labels, values) => {
        const el = document.getElementById(id);
        if (!el) {
            return;
        }
        if (!labels.length || !values.length) {
            renderEmpty(id, noDataLabel);
            return;
        }

        const options = {
            chart: {
                type: 'donut',
                height: 280,
            },
            series: values,
            labels,
            legend: { labels: { colors: axisLabelColor } },
            theme: chartTheme,
            colors: ['#0ea5e9', '#22c55e', '#f97316', '#8b5cf6', '#f43f5e'],
        };

        new ApexCharts(el, options).render();
    };

    renderLineChart('chart-traffic', data.dates || [], [
        { name: pageviewsLabel, data: data.pageviews || [] },
        { name: uniqueVisitorsLabel, data: data.uniques || [] },
    ]);

    const devicesData = mapCategoryData(data.devices);
    renderDonutChart('chart-devices', devicesData.labels, devicesData.values);

    const referrersData = mapCategoryData(data.referrers);
    renderBarChart('chart-referrers', referrersData.labels, referrersData.values);

    const countriesData = mapCategoryData(data.countries);
    renderBarChart('chart-countries', countriesData.labels, countriesData.values);

    const searchEnginesData = mapCategoryData(data.search_engines);
    renderBarChart('chart-search-engines', searchEnginesData.labels, searchEnginesData.values);

    const browsersData = mapCategoryData(data.browsers);
    renderBarChart('chart-browsers', browsersData.labels, browsersData.values);
}
