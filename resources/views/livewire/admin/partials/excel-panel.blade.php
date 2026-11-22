{{-- Excel download/upload for a catalogue page. Expects: $what ("products"), $images (null | "photos" | "logos"). --}}
@if($showExcel || $excelResult)
    <div>
        <x-ui.card>
            <div class="grid gap-5 md:grid-cols-[1fr_1.4fr]">
                <div>
                    <h3 class="text-[14px] font-semibold text-ink">1. Download</h3>
                    <p class="mt-1 text-[12.5px] text-ink-mute">The sheet has dropdowns with the values in the system and a "Read me" tab. Edit "All {{ $what }}" to change many at once: keep the Code column.</p>
                    <div class="mt-3 flex flex-wrap gap-2">
                        <x-ui.button size="sm" variant="secondary" wire:click="downloadTemplate" target="downloadTemplate">Empty template</x-ui.button>
                        <x-ui.button size="sm" variant="secondary" wire:click="downloadAll" target="downloadAll">All {{ $what }}</x-ui.button>
                    </div>
                </div>
                <form wire:submit="importExcel" class="md:border-l md:border-hairline md:pl-5">
                    <h3 class="text-[14px] font-semibold text-ink">2. Upload</h3>
                    <div class="mt-2 grid gap-3 {{ $images ? 'sm:grid-cols-2' : '' }}">
                        <div>
                            <label class="block text-[12.5px] text-ink-mute mb-1" for="excel_file">Filled Excel file (.xlsx)</label>
                            <input type="file" wire:model="excelFile" id="excel_file" accept=".xlsx" class="block w-full text-[12.5px] text-ink-secondary file:mr-2 file:rounded-pill file:border-0 file:bg-canvas-soft file:px-3 file:py-1.5 file:text-[12px]">
                            @error('excelFile') <p class="text-ruby text-[12px] mt-1">{{ $message }}</p> @enderror
                        </div>
                        @if($images)
                            <div>
                                <label class="block text-[12.5px] text-ink-mute mb-1" for="excel_zip">{{ ucfirst($images) }} as a .zip (optional)</label>
                                <input type="file" wire:model="excelZip" id="excel_zip" accept=".zip" class="block w-full text-[12.5px] text-ink-secondary file:mr-2 file:rounded-pill file:border-0 file:bg-canvas-soft file:px-3 file:py-1.5 file:text-[12px]">
                                @error('excelZip') <p class="text-ruby text-[12px] mt-1">{{ $message }}</p> @enderror
                                <p class="mt-1 text-[11.5px] text-ink-mute">File names must match the sheet's {{ $images === 'logos' ? 'Logo' : 'photo' }} column. Links (https://) need no ZIP.</p>
                            </div>
                        @endif
                    </div>
                    <div class="mt-3 flex items-center gap-3">
                        <x-ui.button size="sm" type="submit" target="importExcel">Upload and save</x-ui.button>
                        <span wire:loading wire:target="importExcel" class="text-[12.5px] text-ink-mute">Reading the sheet… large files take a minute.</span>
                        <span wire:loading wire:target="excelFile,excelZip" class="text-[12.5px] text-ink-mute">Uploading…</span>
                    </div>
                </form>
            </div>

            @if($excelResult)
                @php($r = $excelResult)
                <div class="mt-5 border-t border-hairline pt-4">
                    <div class="flex flex-wrap items-center gap-2 text-[13px]">
                        <span class="rounded-pill bg-success-subtle px-2.5 py-1 font-semibold text-success">{{ $r['created'] }} added</span>
                        <span class="rounded-pill bg-canvas-soft px-2.5 py-1 font-semibold text-ink">{{ $r['updated'] }} updated</span>
                        @if($images)<span class="rounded-pill bg-canvas-soft px-2.5 py-1 text-ink-secondary">{{ $r['photos'] }} {{ $images }} saved</span>@endif
                        @if($r['errors'])<span class="rounded-pill bg-ruby/10 px-2.5 py-1 font-semibold text-ruby">{{ count($r['errors']) }} {{ \Illuminate\Support\Str::plural('row', count($r['errors'])) }} not saved</span>@endif
                        @if($r['warnings'])<span class="rounded-pill bg-canvas-cream px-2.5 py-1 text-ink">{{ count($r['warnings']) }} saved with notes</span>@endif
                        <button type="button" wire:click="clearExcelResult" class="ml-auto text-[12px] text-ink-mute hover:text-ink">Dismiss</button>
                    </div>
                    @if($r['errors'] || $r['warnings'])
                        <div class="mt-3 max-h-72 overflow-y-auto rounded-[12px] border border-hairline text-[12.5px]">
                            <table class="w-full">
                                <tbody class="divide-y divide-hairline">
                                    @foreach($r['errors'] as $row => $messages)
                                        <tr class="align-top"><td class="w-40 px-3 py-2 font-semibold text-ruby whitespace-nowrap">{{ $this->excelRowLabel((int) $row) }}</td><td class="px-3 py-2 text-ink">Not saved: {{ implode(' ', $messages) }}</td></tr>
                                    @endforeach
                                    @foreach($r['warnings'] as $row => $messages)
                                        <tr class="align-top"><td class="w-40 px-3 py-2 text-ink-secondary whitespace-nowrap">{{ $this->excelRowLabel((int) $row) }}</td><td class="px-3 py-2 text-ink-secondary">{{ implode(' ', $messages) }}</td></tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            @endif
        </x-ui.card>
    </div>
@endif
