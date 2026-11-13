{{-- A feed post while it loads: same shape as livewire/market/feed-post. --}}
<div {{ $attributes->merge(['class' => 'flex flex-col overflow-hidden rounded-[18px] border border-hairline bg-canvas']) }} aria-hidden="true">
    <div class="flex items-center gap-2.5 px-3 py-2.5">
        <div class="skeleton h-9 w-9 !rounded-full"></div>
        <div class="space-y-1.5">
            <div class="skeleton h-3 w-28"></div>
            <div class="skeleton h-2.5 w-20"></div>
        </div>
    </div>
    <div class="skeleton aspect-square !rounded-none"></div>
    <div class="flex items-center gap-3 px-3.5 pt-3">
        <div class="skeleton h-6 w-6 !rounded-full"></div>
        <div class="skeleton h-6 w-6 !rounded-full"></div>
        <div class="skeleton ml-auto h-8 w-16 !rounded-full"></div>
    </div>
    <div class="space-y-2 px-3.5 pb-4 pt-3">
        <div class="skeleton h-4 w-24"></div>
        <div class="skeleton h-3 w-3/4"></div>
    </div>
</div>
