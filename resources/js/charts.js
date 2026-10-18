import {
    BarController,
    BarElement,
    CategoryScale,
    Chart,
    Legend,
    LinearScale,
    Tooltip,
} from 'chart.js'

Chart.register(BarController, BarElement, CategoryScale, LinearScale, Legend, Tooltip)

const css = (name) => getComputedStyle(document.documentElement).getPropertyValue(name).trim()

/**
 * Stacked daily bars: done / pending / not updated at close of day.
 * Every value comes from the server-side report query.
 */
export function renderTrendChart(canvas, series) {
    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches

    return new Chart(canvas, {
        type: 'bar',
        data: {
            labels: series.labels,
            datasets: [
                { label: 'Done at close', data: series.done, backgroundColor: css('--color-forest'), borderRadius: 2, maxBarThickness: 28 },
                { label: 'Pending at close', data: series.pending, backgroundColor: css('--color-amber-strong'), borderRadius: 2, maxBarThickness: 28 },
                { label: 'Not updated at close', data: series.notUpdated, backgroundColor: css('--color-rule'), borderRadius: 2, maxBarThickness: 28 },
            ],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            animation: reduceMotion ? false : { duration: 250 },
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: {
                    position: 'bottom',
                    align: 'start',
                    labels: { boxWidth: 10, boxHeight: 10, color: css('--color-slate'), font: { family: css('--font-sans'), size: 12 } },
                },
                tooltip: {
                    backgroundColor: css('--color-ink'),
                    titleFont: { family: css('--font-sans'), weight: '600' },
                    bodyFont: { family: css('--font-sans') },
                    callbacks: {
                        footer: (items) => {
                            const index = items[0]?.dataIndex
                            return series.total[index] ? `Completion ${series.rate[index]}% of ${series.total[index]}` : 'Nothing scheduled'
                        },
                    },
                },
            },
            scales: {
                x: {
                    stacked: true,
                    grid: { display: false },
                    ticks: { color: css('--color-slate'), font: { family: css('--font-mono'), size: 11 }, maxRotation: 0, autoSkipPadding: 12 },
                },
                y: {
                    stacked: true,
                    beginAtZero: true,
                    ticks: { precision: 0, color: css('--color-slate'), font: { family: css('--font-mono'), size: 11 } },
                    grid: { color: css('--color-hairline') },
                    border: { display: false },
                },
            },
        },
    })
}
