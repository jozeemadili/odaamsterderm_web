<?php

namespace App\Livewire\Portal\Expenses;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.portal', ['title' => 'Expenses'])]
class Index extends Component
{
    use WithPagination;

    public string $category = '';

    public bool $addingNewCategory = false;

    public string $newCategoryName = '';

    public string $amount = '';

    public string $description = '';

    public string $expense_date;

    public function mount()
    {
        $this->expense_date = now()->toDateString();

        $merchantId = Auth::user()->merchant_id;
        ExpenseCategory::ensureDefaultsFor($merchantId);
        $this->category = (string) ExpenseCategory::where('merchant_id', $merchantId)->orderBy('name')->value('id');
    }

    public function updatedCategory(string $value): void
    {
        $this->addingNewCategory = $value === '__new__';
    }

    public function saveNewCategory()
    {
        $this->validate([
            'newCategoryName' => ['required', 'string', 'max:255'],
        ]);

        $category = ExpenseCategory::firstOrCreate([
            'merchant_id' => Auth::user()->merchant_id,
            'name' => $this->newCategoryName,
        ]);

        $this->category = (string) $category->id;
        $this->newCategoryName = '';
        $this->addingNewCategory = false;
    }

    public function save()
    {
        $this->validate([
            'category' => ['required', 'exists:expense_categories,id'],
            'amount' => ['required', 'numeric', 'min:0'],
            'expense_date' => ['required', 'date'],
        ]);

        $categoryName = ExpenseCategory::find($this->category)->name;

        Expense::create([
            'merchant_id' => Auth::user()->merchant_id,
            'category' => $categoryName,
            'amount' => $this->amount,
            'description' => $this->description,
            'expense_date' => $this->expense_date,
            'recorded_by' => Auth::id(),
        ]);

        $this->reset(['amount', 'description']);
        $this->expense_date = now()->toDateString();
        session()->flash('status', 'Expense recorded.');
    }

    public function render()
    {
        $merchantId = Auth::user()->merchant_id;

        $expenses = Expense::where('merchant_id', $merchantId)
            ->latest('expense_date')
            ->paginate(15);

        return view('livewire.portal.expenses.index', [
            'expenses' => $expenses,
            'categories' => ExpenseCategory::where('merchant_id', $merchantId)->orderBy('name')->get(),
        ]);
    }
}
