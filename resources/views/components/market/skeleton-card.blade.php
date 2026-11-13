{{-- A product card while it loads: same shape as market/product-card. --}}
<div {{ $attributes->merge(['class' => 'w-full flex flex-col overflow-hidden rounded-xl bg-canvas border border-hairline']) }} aria-hidden="true">
    <div class="skeleton aspect-square !rounded-none"></div>
    <div class="flex flex-1 flex-col p-4 gap-2">
        <div class="skeleton h-2.5 w-1/2"></div>
        <div class="skeleton h-4 w-4/5"></div>
        <div class="skeleton h-3 w-2/5"></div>
        <div class="mt-auto pt-3 flex items-end justify-between gap-2">
            <div class="space-y-1.5">
                <div class="skeleton h-2.5 w-8"></div>
                <div class="skeleton h-5 w-24"></div>
            </div>
            <div class="skeleton h-3 w-14"></div>
        </div>
    </div>
</div>
