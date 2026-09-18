<?php

namespace App\Http\Controllers\General;

use App\Http\Controllers\Controller;
use App\Http\Requests\General\TopUpRequest;
use App\Models\BankAccount;
use App\Models\WithdrawalRequest;
use App\Services\BalanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class BalanceController extends Controller
{
    public function __construct(
        private readonly BalanceService $balanceService
    ) {}

    public function index(Request $request): InertiaResponse
    {
        $user = $request->user();
        $transactions = $this->balanceService->history($user, perPage: 15);

        $data = collect($transactions->items())->map(fn ($tx) => [
            'id'               => $tx->id,
            'url'              => route('general.balance.show', $tx),
            'is_credit'        => $tx->isCredit(),
            'description'      => $tx->description,
            'date'            => $tx->created_at->format('d.m.Y H:i'),
            'status'           => $tx->status,
            'status_label'     => $tx->status_label,
            'formatted_amount' => $tx->formatted_amount,
            'reference_code'   => $tx->reference_code,
            'rejection_reason' => $tx->rejection_reason,
            'is_pending'       => $tx->isPending(),
        ])->values();

        return Inertia::render('General/Balance/Index', [
            'is_seller'       => $user->isSeller(),
            'formatted_balance' => $user->formatted_balance,
            'transactions' => [
                'data'      => $data,
                'links'     => $transactions->linkCollection()->toArray(),
                'has_pages' => $transactions->hasPages(),
                'total'     => $transactions->total(),
            ],
        ]);
    }

    public function create(): InertiaResponse
    {
        $presets = [50, 100, 250, 500, 1000];

        $bankAccounts = BankAccount::active()->orderBy('sort_order')->get()->map(fn ($b) => [
            'bank_name'      => $b->bank_name,
            'account_holder' => $b->account_holder,
            'iban'           => $b->iban,
            'note'           => $b->note,
        ])->values();

        // Kullanıcıya özel havale referans kodu (bilgilendirme amaçlı önizleme; asıl kod talep
        // oluşturulurken üretilir). Her talepte benzersiz üretildiği için burada örnek gösterilir.
        return Inertia::render('General/Balance/Create', [
            'presets'      => $presets,
            'bankAccounts' => $bankAccounts,
            'bankTransferEnabled' => (bool) setting('bank_transfer_enabled', false),
            'cardEnabled'  => (bool) setting('iyzico_enabled', false),
            'referenceHint' => 'EFT-' . auth()->id() . '-XXXXXX',
        ]);
    }

    public function store(TopUpRequest $request): RedirectResponse
    {
        abort_unless($this->canTopUp(), 403);

        $user = $request->user();
        $amount = (float) $request->validated('amount');
        $method = $request->validated('payment_method');

        // Banka Havalesi/EFT: bakiye ANINDA eklenmez. Talep 'pending' olarak kaydedilir,
        // admin banka ekstresiyle eşleştirip onaylayınca bakiye yansır (finansal güvenlik).
        if ($method === 'bank_transfer') {
            $tx = $this->balanceService->requestBankTransfer($user, $amount);

            return redirect()
                ->route('general.balance.index')
                ->with('success', 'Havale talebiniz alındı. Açıklamaya "' . $tx->reference_code
                    . '" kodunu yazarak ödemeyi yapın. Onaylandığında bakiyenize eklenecektir.');
        }

        try {
            // DEMO: kredi kartı (sanal POS) simülasyonu — gerçek ödeme entegrasyonu ayrı görevde.
            $reference = 'DEMO-'.strtoupper(substr(md5(uniqid()), 0, 10));

            $transaction = $this->balanceService->credit(
                user: $user,
                amount: $amount,
                paymentMethod: $method,
                description: $this->paymentMethodLabel($method).' ile Bakiye Yükleme',
                reference: $reference,
                meta: [
                    'ip' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ],
            );

            return redirect()
                ->route('general.balance.index')
                ->with('success', number_format($transaction->amount, 2, ',', '.').' ₺ bakiyenize başarıyla eklendi.');

        } catch (\Exception $e) {
            report($e);

            return back()
                ->withInput()
                ->with('error', 'Ödeme işlemi sırasında bir hata oluştu. Lütfen tekrar deneyin.');
        }
    }

    public function withdrawCreate(): InertiaResponse
    {
        abort_unless(auth()->user()->isSeller(), 403);

        $presets = [100, 250, 500, 1000];
        $user = auth()->user();

        return Inertia::render('General/Balance/Withdraw', [
            'presets'           => $presets,
            'formatted_balance' => $user->formatted_balance,
        ]);
    }

    public function withdraw(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->isSeller(), 403);

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:10', 'max:100000'],
            'iban'   => ['required', 'string', function ($attr, $value, $fail) {
                $normalized = strtoupper(preg_replace('/\s+/', '', (string) $value));
                if (! preg_match('/^TR\d{24}$/', $normalized)) {
                    $fail('Geçerli bir TR IBAN giriniz (TR ile başlayan 26 karakter).');
                }
            }],
        ], [
            'amount.required' => 'Çekilecek tutar zorunludur.',
            'amount.min'      => 'Minimum çekim tutarı 10 ₺\'dir.',
            'amount.max'      => 'Tek seferde maksimum 100.000 ₺ çekilebilir.',
            'iban.required'   => 'IBAN zorunludur.',
        ]);

        $iban = strtoupper(preg_replace('/\s+/', '', $data['iban']));
        $amount = (float) $data['amount'];

        try {
            $withdrawal = DB::transaction(function () use ($user, $amount, $iban, $request) {
                // Çift harcamayı engellemek için bakiyeyi HEMEN rezerve et (debit). Reddedilirse
                // admin tarafından geri yüklenir; onaylanıp ödendiğinde kalıcı olarak düşülmüş olur.
                $tx = $this->balanceService->debit(
                    user: $user,
                    amount: $amount,
                    description: 'Para çekme talebi (rezerve)',
                    meta: ['iban' => $iban, 'method' => 'withdraw_reserve', 'ip' => $request->ip()],
                );

                return WithdrawalRequest::create([
                    'user_id'                => $user->id,
                    'amount'                 => $amount,
                    'iban'                   => $iban,
                    'status'                 => 'pending',
                    'reserve_transaction_id' => $tx->id,
                ]);
            });
        } catch (\RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            report($e);
            return back()->withInput()->with('error', 'Çekim işlemi sırasında bir hata oluştu.');
        }

        return redirect()
            ->route('general.balance.index')
            ->with('success', number_format($withdrawal->amount, 2, ',', '.').' ₺ çekim talebiniz alındı. Tutar bakiyenizden rezerve edildi; onaylandıktan sonra 1-3 iş günü içinde IBAN adresinize gönderilecek.');
    }

    private function canTopUp(): bool
    {
        return auth()->check() && auth()->user()->hasRole('buyer');
    }

    public function show(Request $request, int $id): InertiaResponse
    {
        $transaction = $request->user()
            ->balanceTransactions()
            ->findOrFail($id);

        return Inertia::render('General/Balance/Show', [
            'transaction' => [
                'formatted_amount' => $transaction->formatted_amount,
                'is_credit'        => $transaction->isCredit(),
                'status'           => $transaction->status,
                'status_label'     => $transaction->status_label,
                'type_label'       => $transaction->type_label,
                'description'      => $transaction->description,
                'reference'        => $transaction->reference,
                'payment_method'   => $transaction->payment_method ?? '—',
                'balance_before'   => number_format($transaction->balance_before, 2, ',', '.').' ₺',
                'balance_after'    => number_format($transaction->balance_after, 2, ',', '.').' ₺',
                'created_at'       => $transaction->created_at->format('d.m.Y H:i:s'),
            ],
        ]);
    }

    private function paymentMethodLabel(string $method): string
    {
        return match ($method) {
            'credit_card' => 'Kredi Kartı',
            'bank_transfer' => 'Banka Havalesi',
            'papara' => 'Papara',
            default => 'Ödeme',
        };
    }
}
