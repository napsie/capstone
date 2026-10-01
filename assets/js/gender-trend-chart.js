(function () {
    'use strict';

    const palette = {
        female: '#db2777',
        femaleFill: 'rgba(219, 39, 119, 0.12)',
        male: '#2563eb',
        maleFill: 'rgba(37, 99, 235, 0.11)',
        text: '#475569',
        muted: '#94a3b8',
        grid: 'rgba(148, 163, 184, 0.20)'
    };

    function lastTwelveMonths() {
        const months = [];
        for (let offset = 11; offset >= 0; offset--) {
            const date = new Date();
            date.setDate(1);
            date.setHours(0, 0, 0, 0);
            date.setMonth(date.getMonth() - offset);
            months.push({
                shortLabel: date.toLocaleDateString('en-PH', { month: 'short', year: '2-digit' }).replace(' ', ' ’'),
                fullLabel: date.toLocaleDateString('en-PH', { month: 'long', year: 'numeric' }),
                month: date.getMonth() + 1,
                year: date.getFullYear()
            });
        }
        return months;
    }

    function makeDataset(label, values, color, fillColor) {
        return {
            label,
            data: values,
            borderColor: color,
            backgroundColor: fillColor,
            borderWidth: 2.5,
            borderCapStyle: 'round',
            borderJoinStyle: 'round',
            cubicInterpolationMode: 'monotone',
            tension: 0.35,
            fill: true,
            pointRadius: context => Number(context.raw) > 0 ? 4 : 0,
            pointHoverRadius: 6,
            pointHitRadius: 14,
            pointBackgroundColor: '#ffffff',
            pointBorderColor: color,
            pointBorderWidth: 2.5,
            pointHoverBackgroundColor: color,
            pointHoverBorderColor: '#ffffff',
            pointHoverBorderWidth: 2
        };
    }

    const emptyStatePlugin = {
        id: 'genderTrendEmptyState',
        afterDraw(chart) {
            if (chart.data.datasets.some(dataset => dataset.data.some(value => Number(value) > 0))) return;
            const { ctx, chartArea } = chart;
            if (!chartArea) return;
            ctx.save();
            ctx.textAlign = 'center';
            ctx.fillStyle = palette.text;
            ctx.font = '700 13px Inter, Segoe UI, sans-serif';
            ctx.fillText('No senior profiles recorded', (chartArea.left + chartArea.right) / 2, (chartArea.top + chartArea.bottom) / 2 - 5);
            ctx.fillStyle = palette.muted;
            ctx.font = '500 11px Inter, Segoe UI, sans-serif';
            ctx.fillText('Data for the last 12 months will appear here.', (chartArea.left + chartArea.right) / 2, (chartArea.top + chartArea.bottom) / 2 + 15);
            ctx.restore();
        }
    };

    window.createGenderTrendChart = function (canvas, records) {
        if (!canvas || !window.Chart) return null;
        const months = lastTwelveMonths();
        const female = Array(12).fill(0);
        const male = Array(12).fill(0);

        (Array.isArray(records) ? records : []).forEach(record => {
            const index = months.findIndex(month => month.month === Number(record.month_num) && month.year === Number(record.year));
            if (index < 0) return;
            if (record.gender === 'Female') female[index] = Number.parseInt(record.count, 10) || 0;
            if (record.gender === 'Male') male[index] = Number.parseInt(record.count, 10) || 0;
        });

        const largestValue = Math.max(0, ...female, ...male);
        const existing = Chart.getChart(canvas);
        if (existing) existing.destroy();

        return new Chart(canvas.getContext('2d'), {
            type: 'line',
            data: {
                labels: months.map(month => month.shortLabel),
                datasets: [
                    makeDataset('Female', female, palette.female, palette.femaleFill),
                    makeDataset('Male', male, palette.male, palette.maleFill)
                ]
            },
            plugins: [emptyStatePlugin],
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: false,
                interaction: { mode: 'index', intersect: false },
                layout: { padding: { top: 2, right: 5, bottom: 0, left: 2 } },
                plugins: {
                    legend: {
                        position: 'top',
                        align: 'end',
                        labels: {
                            color: '#334155',
                            boxWidth: 9,
                            boxHeight: 9,
                            padding: 16,
                            usePointStyle: true,
                            pointStyle: 'circle',
                            font: { family: 'Inter, Segoe UI, sans-serif', size: 11, weight: '600' }
                        }
                    },
                    tooltip: {
                        backgroundColor: '#0f2742',
                        titleColor: '#ffffff',
                        bodyColor: '#e5edf5',
                        borderColor: 'rgba(255,255,255,.15)',
                        borderWidth: 1,
                        padding: 11,
                        displayColors: true,
                        usePointStyle: true,
                        callbacks: {
                            title: items => months[items[0]?.dataIndex]?.fullLabel || '',
                            label: context => ` ${context.dataset.label}: ${context.parsed.y.toLocaleString()} record${context.parsed.y === 1 ? '' : 's'}`,
                            footer: items => `Total: ${items.reduce((sum, item) => sum + item.parsed.y, 0).toLocaleString()} records`
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        suggestedMax: Math.max(4, largestValue + 1),
                        border: { display: false },
                        grid: { color: palette.grid, drawTicks: false },
                        ticks: {
                            color: palette.text,
                            precision: 0,
                            padding: 9,
                            maxTicksLimit: 5,
                            font: { family: 'Inter, Segoe UI, sans-serif', size: 10, weight: '600' }
                        }
                    },
                    x: {
                        border: { display: false },
                        grid: { display: false },
                        ticks: {
                            color: palette.text,
                            autoSkip: true,
                            maxTicksLimit: 6,
                            maxRotation: 0,
                            padding: 9,
                            font: { family: 'Inter, Segoe UI, sans-serif', size: 10, weight: '600' }
                        }
                    }
                }
            }
        });
    };
})();
