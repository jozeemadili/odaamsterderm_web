<?php

namespace App\Livewire\Portal\Chicks;

use App\Models\ChickType;
use App\Models\HatchSchedule;
use App\Models\OrderItem;
use App\Models\Producer;
use App\Services\ChickService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Day-old chicks for the seller that sells them (Amsterdam Group): the
 * hatcheries and the chick types they produce, and the hatch dates
 * customers book boxes against, with each date's bookings.
 */
#[Layout('layouts.portal', ['title' => 'Day-old chicks'])]
class Index extends Component
{
    use WithFileUploads;

    #[Url]
    public string $tab = 'dates';

    #[Url]
    public string $type = '';

    #[Url]
    public string $hatchery = '';

    #[Url]
    public bool $showPast = false;

    /** Hatch date form */
    public bool $showDateForm = false;

    public ?int $editingDateId = null;

    public string $producer_id = '';

    public string $chick_type_id = '';

    public string $hatch_date = '';

    public string $order_cutoff_date = '';

    public string $capacity = '';

    public string $price_per_chick = '';

    public string $notes = '';

    /** CSV import */
    public $csv;

    public array $importErrors = [];

    /** Bookings of one date */
    public ?int $bookingsFor = null;

    /** Hatchery form */
    public bool $showHatcheryForm = false;

    public ?int $editingHatcheryId = null;

    public string $name = '';

    public string $deals_in = Producer::DEALS_CHICKS;

    public string $phone = '';

    public string $location = '';

    public string $description = '';

    public array $types = [];

    public $logo;

    public function mount(ChickService $chicks): void
    {
        abort_unless($chicks->canManage(Auth::user()), 403);
    }

    // --- Hatch dates -----------------------------------------------------

    public function newDate(): void
    {
        $this->resetDateForm();
        $this->producer_id = $this->hatchery;
        $this->chick_type_id = $this->type ? (string) ChickType::where('code', $this->type)->value('id') : '';
        $this->showDateForm = true;
    }

    public function editDate(int $id): void
    {
        $s = HatchSchedule::findOrFail($id);
        $this->resetDateForm();
        $this->editingDateId = $s->id;
        $this->producer_id = (string) $s->producer_id;
        $this->chick_type_id = (string) $s->chick_type_id;
        $this->hatch_date = $s->hatch_date->toDateString();
        $this->order_cutoff_date = $s->order_cutoff_date->toDateString();
        $this->capacity = (string) $s->capacity;
        $this->price_per_chick = $s->price_per_chick !== null ? rtrim(rtrim((string) $s->price_per_chick, '0'), '.') : '';
        $this->notes = (string) $s->notes;
        $this->showDateForm = true;
    }

    /** When the hatch date changes, suggest the usual cut-off. */
    public function updatedHatchDate(): void
    {
        if ($this->hatch_date !== '') {
            $this->order_cutoff_date = now()->parse($this->hatch_date)->subDays(HatchSchedule::cutoffDays())->toDateString();
        }
    }

    public function saveDate(ChickService $chicks): void
    {
        $data = $this->validate([
            'producer_id' => ['required', Rule::exists('producers', 'id')],
            'chick_type_id' => ['required', Rule::exists('chick_types', 'id')],
            'hatch_date' => ['required', 'date', $this->editingDateId ? 'date' : 'after_or_equal:today'],
            'order_cutoff_date' => ['nullable', 'date'],
            'capacity' => ['required', 'integer', 'min:1', 'max:10000000'],
            'price_per_chick' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:500'],
        ], [], ['producer_id' => 'hatchery', 'chick_type_id' => 'chick type', 'capacity' => 'number of chicks']);

        $chicks->saveSchedule($data, $this->editingDateId ? HatchSchedule::findOrFail($this->editingDateId) : null, Auth::user());

        session()->flash('status', $this->editingDateId ? 'Hatch date updated.' : 'Hatch date added. Customers can book it now.');
        $this->resetDateForm();
    }

    public function toggleClosed(int $id): void
    {
        $s = HatchSchedule::findOrFail($id);
        $s->update(['is_closed' => ! $s->is_closed]);
        session()->flash('status', $s->is_closed ? 'Booking closed for that hatch date.' : 'Booking reopened for that hatch date.');
    }

    public function deleteDate(int $id): void
    {
        $s = HatchSchedule::findOrFail($id);
        if ($s->orderItems()->exists()) {
            session()->flash('error', 'This hatch date has bookings, so it can\'t be deleted. Close it instead.');

            return;
        }
        $s->delete();
        session()->flash('status', 'Hatch date deleted.');
    }

    public function showBookings(int $id): void
    {
        $this->bookingsFor = $this->bookingsFor === $id ? null : $id;
    }

    /**
     * CSV columns: hatchery, chick_type, hatch_date, chicks, and optionally
     * price_per_chick, order_cutoff_date, notes. A header row is skipped.
     */
    public function importCsv(ChickService $chicks): void
    {
        $this->validate(['csv' => ['required', 'file', 'mimes:csv,txt', 'max:1024']]);
        $this->importErrors = [];

        $rows = array_map('str_getcsv', file($this->csv->getRealPath(), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES));
        $producers = Producer::hatcheries()->get();
        $types = ChickType::all();
        $saved = 0;

        foreach ($rows as $i => $row) {
            $line = $i + 1;
            $row = array_map(fn ($v) => trim((string) $v), $row);
            if ($i === 0 && ! preg_match('/\d{4}-\d{2}-\d{2}|\d{1,2}\/\d{1,2}\/\d{2,4}/', $row[2] ?? '')) {
                continue; // header
            }

            $producer = $producers->first(fn ($p) => in_array(Str::lower($row[0] ?? ''), [Str::lower($p->name), Str::lower((string) $p->code)], true));
            $type = $types->first(fn ($t) => in_array(Str::lower($row[1] ?? ''), [$t->code, Str::lower($t->name), rtrim($t->code, 's')], true));

            if (! $producer || ! $type) {
                $this->importErrors[] = "Line {$line}: ".(! $producer ? "unknown hatchery '{$row[0]}'" : "unknown chick type '{$row[1]}'");

                continue;
            }

            try {
                $existing = HatchSchedule::where('producer_id', $producer->id)->where('chick_type_id', $type->id)->whereDate('hatch_date', now()->parse($row[2]))->first();
                $chicks->saveSchedule([
                    'producer_id' => $producer->id,
                    'chick_type_id' => $type->id,
                    'hatch_date' => $row[2],
                    'capacity' => (int) str_replace(',', '', $row[3] ?? '0'),
                    'price_per_chick' => str_replace(',', '', $row[4] ?? ''),
                    'order_cutoff_date' => $row[5] ?? '',
                    'notes' => $row[6] ?? '',
                ], $existing, Auth::user());
                $saved++;
            } catch (ValidationException $e) {
                $this->importErrors[] = "Line {$line}: ".collect($e->errors())->flatten()->first();
            } catch (\Throwable) {
                $this->importErrors[] = "Line {$line}: could not read this row.";
            }
        }

        $this->reset('csv');
        session()->flash('status', "{$saved} hatch ".Str::plural('date', $saved).' imported.');
    }

    protected function resetDateForm(): void
    {
        $this->reset(['showDateForm', 'editingDateId', 'producer_id', 'chick_type_id', 'hatch_date', 'order_cutoff_date', 'capacity', 'price_per_chick', 'notes']);
        $this->resetErrorBag();
    }

    // --- Hatcheries ------------------------------------------------------

    public function newHatchery(): void
    {
        $this->resetHatcheryForm();
        $this->showHatcheryForm = true;
    }

    public function editHatchery(int $id): void
    {
        $p = Producer::findOrFail($id);
        $this->resetHatcheryForm();
        $this->editingHatcheryId = $p->id;
        $this->name = $p->name;
        $this->deals_in = $p->deals_in ?? Producer::DEALS_CHICKS;
        $this->phone = (string) $p->phone;
        $this->location = (string) $p->location;
        $this->description = (string) $p->description;
        $this->types = $p->chickTypes()->pluck('chick_types.id')->map(fn ($id) => (string) $id)->all();
        $this->showHatcheryForm = true;
    }

    public function saveHatchery(): void
    {
        $this->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('producers', 'name')->ignore($this->editingHatcheryId)],
            'deals_in' => ['required', Rule::in([Producer::DEALS_CHICKS, Producer::DEALS_CHICKS_FEED])],
            'phone' => ['nullable', 'string', 'max:40'],
            'location' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'types' => ['array', 'min:1'],
            'types.*' => [Rule::exists('chick_types', 'id')],
            'logo' => ['nullable', 'image', 'max:2048'],
        ], ['types.min' => 'Tick at least one chick type this hatchery produces.']);

        $producer = $this->editingHatcheryId
            ? Producer::findOrFail($this->editingHatcheryId)
            : new Producer(['is_active' => true, 'country' => 'Tanzania', 'role' => 'Hatchery (day-old chicks)', 'slug' => $this->uniqueSlug($this->name)]);

        $producer->fill([
            'name' => $this->name,
            'deals_in' => $this->deals_in,
            'phone' => $this->phone ?: null,
            'location' => $this->location ?: null,
            'description' => $this->description ?: null,
        ])->save();
        $producer->chickTypes()->sync($this->types);

        if ($this->logo) {
            $producer->addMedia($this->logo->getRealPath())
                ->usingFileName($this->logo->getClientOriginalName())
                ->toMediaCollection('logo');
        }

        session()->flash('status', $this->editingHatcheryId ? 'Hatchery updated.' : 'Hatchery added. You can now add its hatch dates.');
        $this->resetHatcheryForm();
    }

    public function toggleHatchery(int $id): void
    {
        $p = Producer::findOrFail($id);
        $p->update(['is_active' => ! $p->is_active]);
    }

    protected function resetHatcheryForm(): void
    {
        $this->reset(['showHatcheryForm', 'editingHatcheryId', 'name', 'phone', 'location', 'description', 'types', 'logo']);
        $this->deals_in = Producer::DEALS_CHICKS;
        $this->resetErrorBag();
    }

    protected function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'hatchery';
        $slug = $base;
        for ($i = 2; Producer::where('slug', $slug)->exists(); $i++) {
            $slug = "{$base}-{$i}";
        }

        return $slug;
    }

    public function render()
    {
        $chickTypes = ChickType::active()->get();
        $hatcheries = Producer::hatcheries()->with(['chickTypes', 'media'])->withCount(['hatchSchedules as upcoming_dates' => fn ($q) => $q->whereDate('hatch_date', '>=', today())])->orderBy('name')->get();

        $dates = HatchSchedule::query()
            ->withBooked()
            ->with(['producer.media', 'chickType'])
            ->when(! $this->showPast, fn ($q) => $q->whereDate('hatch_date', '>=', today()))
            ->when($this->type !== '', fn ($q) => $q->whereRelation('chickType', 'code', $this->type))
            ->when($this->hatchery !== '', fn ($q) => $q->where('producer_id', $this->hatchery))
            ->orderBy('hatch_date', $this->showPast ? 'desc' : 'asc')
            ->limit(200)
            ->get();

        $bookings = $this->bookingsFor
            ? OrderItem::where('hatch_schedule_id', $this->bookingsFor)->with('order.branch')->latest()->get()
            : collect();

        return view('livewire.portal.chicks.index', [
            'chickTypes' => $chickTypes,
            'hatcheries' => $hatcheries,
            'dates' => $dates,
            'bookings' => $bookings,
            'boxSize' => HatchSchedule::boxSize(),
        ]);
    }
}
