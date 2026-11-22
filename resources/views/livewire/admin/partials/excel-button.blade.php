{{-- Toggles the Excel panel (livewire.admin.partials.excel-panel). --}}
<button type="button" wire:click="$toggle('showExcel')" class="inline-flex items-center gap-2 rounded-pill border border-hairline bg-canvas px-3.5 py-1.5 text-[13px] font-medium text-ink-secondary hover:border-primary/40 hover:text-ink">
        <svg class="h-4 w-4 text-success" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M8 8l8 8M16 8l-8 8"/></svg>
        Excel
        <svg class="h-3.5 w-3.5 transition {{ $showExcel ? 'rotate-180' : '' }}" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M5.3 7.3a1 1 0 0 1 1.4 0L10 10.6l3.3-3.3a1 1 0 1 1 1.4 1.4l-4 4a1 1 0 0 1-1.4 0l-4-4a1 1 0 0 1 0-1.4z"/></svg>
    </button>
