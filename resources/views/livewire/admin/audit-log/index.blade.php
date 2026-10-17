<div class="space-y-4">
    <x-ui.card padding="p-0">
        <table class="w-full text-[13px]">
            <thead class="bg-canvas-soft text-left text-[11px] uppercase tracking-wide text-ink-mute">
                <tr>
                    <th class="px-5 py-3 font-normal">When</th>
                    <th class="px-5 py-3 font-normal">Actor</th>
                    <th class="px-5 py-3 font-normal">Event</th>
                    <th class="px-5 py-3 font-normal">Subject</th>
                    <th class="px-5 py-3 font-normal">Description</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-hairline">
                @forelse($activities as $activity)
                    <tr class="hover:bg-canvas-soft/60">
                        <td class="px-5 py-3 whitespace-nowrap text-ink-secondary tnum">{{ $activity->created_at->format('d M Y H:i') }}</td>
                        <td class="px-5 py-3 text-ink font-medium">{{ $activity->causer?->name ?? 'System' }}</td>
                        <td class="px-5 py-3"><x-ui.badge tone="neutral">{{ $activity->event }}</x-ui.badge></td>
                        <td class="px-5 py-3 text-ink-secondary">{{ class_basename($activity->subject_type ?? '') }} #{{ $activity->subject_id }}</td>
                        <td class="px-5 py-3 text-ink-mute">{{ $activity->description }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-5 py-8 text-center text-ink-mute">No activity recorded yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </x-ui.card>

    <div>{{ $activities->links() }}</div>
</div>
