@props(['type' => 'line', 'labels' => [], 'datasets' => [], 'height' => 220, 'options' => []])

<div
    wire:ignore
    x-data="{
        chart: null,
        init() {
            const existing = Chart.getChart(this.$refs.canvas);
            if (existing) {
                existing.destroy();
            }

            this.chart = new Chart(this.$refs.canvas, {
                type: @js($type),
                data: {
                    labels: @js($labels),
                    datasets: @js($datasets),
                },
                options: Object.assign({
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                }, @js($options)),
            });
        }
    }"
    x-init="init()"
    style="height: {{ $height }}px"
>
    <canvas x-ref="canvas"></canvas>
</div>
