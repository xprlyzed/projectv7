<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BankAccount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class BankAccountController extends Controller
{
    public function index()
    {
        return Inertia::render('Admin/BankAccounts/Index', [
            'accounts' => BankAccount::orderBy('sort_order')->get()->map(fn ($b) => [
                'id'             => $b->id,
                'bank_name'      => $b->bank_name,
                'account_holder' => $b->account_holder,
                'iban'           => $b->iban,
                'note'           => $b->note,
                'is_active'      => $b->is_active,
                'sort_order'     => $b->sort_order,
                'edit_url'       => route('admin.bank-accounts.edit', $b->id),
                'destroy_url'    => route('admin.bank-accounts.destroy', $b->id),
            ])->values(),
            'stats' => [
                'total'  => BankAccount::count(),
                'active' => BankAccount::where('is_active', true)->count(),
                'passive'=> BankAccount::where('is_active', false)->count(),
            ],
            'create_url' => route('admin.bank-accounts.create'),
        ]);
    }

    public function create()
    {
        return Inertia::render('Admin/BankAccounts/Create', [
            'store_url' => route('admin.bank-accounts.store'),
            'index_url' => route('admin.bank-accounts.index'),
        ]);
    }

    public function edit(BankAccount $bankAccount)
    {
        return Inertia::render('Admin/BankAccounts/Edit', [
            'account' => [
                'id'             => $bankAccount->id,
                'bank_name'      => $bankAccount->bank_name,
                'account_holder' => $bankAccount->account_holder,
                'iban'           => $bankAccount->iban,
                'note'           => $bankAccount->note,
                'is_active'      => $bankAccount->is_active,
                'sort_order'     => $bankAccount->sort_order,
            ],
            'update_url' => route('admin.bank-accounts.update', $bankAccount->id),
            'index_url'  => route('admin.bank-accounts.index'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        BankAccount::create($this->validated($request));
        return redirect()->route('admin.bank-accounts.index')->with('success', 'Banka hesabı eklendi.');
    }

    public function update(Request $request, BankAccount $bankAccount): RedirectResponse
    {
        $bankAccount->update($this->validated($request));
        return redirect()->route('admin.bank-accounts.index')->with('success', 'Banka hesabı güncellendi.');
    }

    public function destroy(BankAccount $bankAccount)
    {
        $name = $bankAccount->bank_name;
        $bankAccount->delete();

        if (request()->wantsJson() || request()->ajax()) {
            return response()->json(['message' => $name . ' silindi.']);
        }

        return redirect()->route('admin.bank-accounts.index')->with('success', $name . ' silindi.');
    }

    private function validated(Request $request): array
    {
        $validated = $request->validate([
            'bank_name'      => ['required', 'string', 'max:100'],
            'account_holder' => ['required', 'string', 'max:150'],
            'iban'           => ['required', 'string', function ($attr, $value, $fail) {
                $n = strtoupper(preg_replace('/\s+/', '', (string) $value));
                if (! preg_match('/^TR\d{24}$/', $n)) {
                    $fail('Geçerli bir TR IBAN giriniz (TR + 24 rakam).');
                }
            }],
            'note'           => ['nullable', 'string', 'max:255'],
            'is_active'      => ['nullable', 'boolean'],
            'sort_order'     => ['nullable', 'integer', 'min:0', 'max:9999'],
        ]);

        return array_merge($validated, [
            'iban'       => strtoupper(preg_replace('/\s+/', '', (string) $request->input('iban'))),
            'is_active'  => (bool) $request->input('is_active', true),
            'sort_order' => (int) $request->input('sort_order', 0),
        ]);
    }
}
