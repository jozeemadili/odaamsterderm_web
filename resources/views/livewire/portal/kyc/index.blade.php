<div class="space-y-4">
    <x-ui.card>
        <p class="text-[13px] mb-4">
            Verification status:
            <x-ui.badge :tone="$merchant->kyc_status === 'approved' ? 'success' : ($merchant->kyc_status === 'rejected' ? 'danger' : 'warning')">
                {{ str_replace('_', ' ', $merchant->kyc_status) }}
            </x-ui.badge>
        </p>

        <form wire:submit="upload" class="flex flex-wrap gap-4 items-end">
            <x-ui.select wire:model="document_type" label="Document type" id="doc_type">
                @foreach($documentTypes as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </x-ui.select>
            <div>
                <label class="block text-[13px] text-ink-mute mb-1.5">File</label>
                <input type="file" wire:model="file" class="text-[13px] text-ink-secondary file:mr-3 file:py-1.5 file:px-3 file:rounded-pill file:border-0 file:text-[12px] file:bg-primary-subtle/40 file:text-primary-deep">
                @error('file') <p class="text-ruby text-[12px] mt-1">{{ $message }}</p> @enderror
                <div wire:loading wire:target="file" class="text-[12px] text-ink-mute mt-1">Uploading…</div>
            </div>
            <x-ui.button type="submit" target="upload">Upload document</x-ui.button>
        </form>
    </x-ui.card>

    <x-ui.card padding="p-0">
        <table class="w-full text-[13px]">
            <thead class="bg-canvas-soft text-left text-[11px] uppercase tracking-wide text-ink-mute">
                <tr>
                    <th class="px-5 py-3 font-normal">Document type</th>
                    <th class="px-5 py-3 font-normal">Status</th>
                    <th class="px-5 py-3 font-normal">Submitted</th>
                    <th class="px-5 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-hairline">
                @forelse($documents as $doc)
                    <tr class="hover:bg-canvas-soft/60">
                        <td class="px-5 py-3 text-ink font-medium capitalize">{{ str_replace('_', ' ', $doc->document_type) }}</td>
                        <td class="px-5 py-3">
                            <x-ui.badge :tone="$doc->status === 'approved' ? 'success' : ($doc->status === 'rejected' ? 'danger' : 'neutral')">{{ $doc->status }}</x-ui.badge>
                        </td>
                        <td class="px-5 py-3 text-ink-secondary">{{ $doc->created_at->format('d M Y') }}</td>
                        <td class="px-5 py-3 text-right">
                            @if($doc->file())
                                <a href="{{ $doc->file()->getUrl() }}" target="_blank" class="text-primary hover:text-primary-deep">view</a>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-5 py-8 text-center text-ink-mute">No documents uploaded yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </x-ui.card>
</div>
