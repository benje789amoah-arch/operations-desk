/**
 * Front-end behaviour for the Operations Desk.
 *
 * Livewire ships Alpine; we only register small, focused pieces here:
 *  - a toast store fed by session flashes and Livewire "toast" events
 *  - friendly handling of expired sessions (HTTP 419) for Livewire requests
 *  - a lazily-loaded Chart.js renderer for the completion trend
 */

document.addEventListener('alpine:init', () => {
    const Alpine = window.Alpine

    Alpine.store('toasts', {
        items: [],
        push({ message, tone = 'positive', timeout = 5000 }) {
            if (!message) return
            const id = Date.now() + Math.random()
            this.items.push({ id, message, tone })
            if (timeout) setTimeout(() => this.dismiss(id), timeout)
        },
        dismiss(id) {
            this.items = this.items.filter((toast) => toast.id !== id)
        },
    })

    Alpine.data('trendChart', (series) => ({
        chart: null,
        async init() {
            const { renderTrendChart } = await import('./charts.js')
            this.chart = renderTrendChart(this.$refs.canvas, series)
        },
        destroy() {
            this.chart?.destroy()
        },
    }))
})

window.addEventListener('toast', (event) => {
    const detail = Array.isArray(event.detail) ? event.detail[0] : event.detail
    window.Alpine?.store('toasts')?.push(detail ?? {})
})

document.addEventListener('livewire:init', () => {
    const Livewire = window.Livewire
    if (!Livewire?.interceptRequest) return

    let expired = false

    Livewire.interceptRequest(({ onError }) => {
        onError(({ response, preventDefault }) => {
            if (response?.status !== 419) return
            preventDefault()
            if (expired) return
            expired = true
            document.getElementById('session-expired')?.removeAttribute('hidden')
        })
    })
})
