<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ExpenseResource;
use App\Models\Expense;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ExpenseController extends Controller
{
    public function index(Request $request)
    {
        $expenses = Expense::where('merchant_id', Auth::user()->merchant_id)
            ->when($request->filled('date_from'), fn ($q) => $q->whereDate('expense_date', '>=', $request->date('date_from')))
            ->when($request->filled('date_to'), fn ($q) => $q->whereDate('expense_date', '<=', $request->date('date_to')))
            ->latest('expense_date')
            ->paginate($request->integer('per_page', 20));

        return ExpenseResource::collection($expenses);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'category' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'description' => ['nullable', 'string', 'max:255'],
            'expense_date' => ['required', 'date'],
        ]);

        $expense = Expense::create([
            ...$data,
            'merchant_id' => Auth::user()->merchant_id,
            'recorded_by' => Auth::id(),
        ]);

        return new ExpenseResource($expense);
    }
}
