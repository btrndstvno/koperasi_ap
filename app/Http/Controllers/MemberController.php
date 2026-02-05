<?php

namespace App\Http\Controllers;

use App\Models\Loan;
use App\Models\Member;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class MemberController extends Controller
{
    /**
     * Display a listing of members.
     */
    public function index(Request $request)
    {
        $query = Member::query();

        // Search
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('nik', 'like', "%{$search}%")
                  ->orWhere('name', 'like', "%{$search}%");
            });
        }

        // Filter by department
        if ($request->filled('dept')) {
            $query->where('dept', $request->dept);
        }

        // Filter by status
        if ($request->filled('status')) {
            $query->where('employee_status', $request->status);
        }

        // Filter by loan status
        if ($request->filled('has_loan')) {
            if ($request->has_loan === 'yes') {
                $query->whereHas('loans', fn($q) => $q->where('status', 'active'));
            } else {
                $query->whereDoesntHave('loans', fn($q) => $q->where('status', 'active'));
            }
        }

        $members = $query
            ->with(['loans' => fn($q) => $q->where('status', 'active')])
            ->withCount(['loans' => fn($q) => $q->where('status', 'active')])
            ->orderBy('name')
            ->paginate(50)
            ->appends($request->query());

        $departments = Member::distinct()->pluck('dept')->sort();

        return view('members.index', compact('members', 'departments'));
    }

    /**
     * Show the form for creating a new member.
     */
    public function create()
    {
        $existing_depts = Member::distinct()->whereNotNull('dept')->pluck('dept')->sort()->values();
        $existing_csds = Member::distinct()->whereNotNull('csd')->where('csd', '!=', '')->pluck('csd')->sort()->values();
        $group_tags = Member::getGroupTags();
        
        return view('members.create', compact('existing_depts', 'existing_csds', 'group_tags'));
    }

    /**
     * Store a newly created member.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'nik' => 'required|string|max:50|unique:members,nik',
            'name' => 'required|string|max:255',
            'group_tag' => 'required|in:Manager,Bangunan,CSD,Office',
            'csd' => 'nullable|string|max:100',
            'dept' => 'required|string|max:100',
            'employee_status' => 'required|in:monthly,weekly',
            'savings_balance' => 'nullable|numeric|min:0',
        ]);

        $validated['savings_balance'] = $validated['savings_balance'] ?? 0;
        
        // Jika group_tag Manager, CSD otomatis 'Manager'
        if ($validated['group_tag'] === 'Manager') {
            $validated['csd'] = 'Manager';
        }

        DB::transaction(function () use ($validated) {
            $member = Member::create($validated);

            // Jika ada saldo awal, catat sebagai transaksi
            if ($member->savings_balance > 0) {
                Transaction::create([
                    'member_id' => $member->id,
                    'loan_id' => null,
                    'transaction_date' => now(),
                    'type' => Transaction::TYPE_SAVING_DEPOSIT,
                    'amount_saving' => $member->savings_balance,
                    'amount_principal' => 0,
                    'amount_interest' => 0,
                    'total_amount' => $member->savings_balance,
                    'notes' => 'Saldo Awal Simpanan',
                    'payment_method' => 'cash',
                ]);
            }
        });

        return redirect()->route('members.index')
            ->with('success', 'Anggota berhasil ditambahkan.');
    }

    /**
     * Display the specified member (Member-Centric View).
     */
    public function show(Member $member)
    {
        $member->load([
            'loans' => fn($q) => $q->orderByDesc('created_at'),
            'loans.transactions' => fn($q) => $q->orderByDesc('transaction_date'),
            'transactions' => fn($q) => $q->orderByDesc('transaction_date')->limit(50),
        ]);

        $activeLoan = $member->activeLoans()->first();
        
        // Mutasi Simpanan
        $savingTransactions = $member->transactions()
            ->whereIn('type', [Transaction::TYPE_SAVING_DEPOSIT, Transaction::TYPE_SAVING_WITHDRAW])
            ->orderByDesc('transaction_date')
            ->get();

        // Riwayat Pembayaran Pinjaman
        $loanTransactions = $member->transactions()
            ->where('type', Transaction::TYPE_LOAN_REPAYMENT)
            ->orderByDesc('transaction_date')
            ->get();

        return view('members.show', compact(
            'member',
            'activeLoan',
            'savingTransactions',
            'loanTransactions'
        ));
    }

    /**
     * Show the form for editing the specified member.
     */
    public function edit(Member $member)
    {
        $existing_depts = Member::distinct()->whereNotNull('dept')->pluck('dept')->sort()->values();
        $existing_csds = Member::distinct()->whereNotNull('csd')->where('csd', '!=', '')->pluck('csd')->sort()->values();
        $group_tags = Member::getGroupTags();
        
        return view('members.edit', compact('member', 'existing_depts', 'existing_csds', 'group_tags'));
    }

    /**
     * Update the specified member.
     */
    public function update(Request $request, Member $member)
    {
        $validated = $request->validate([
            'nik' => ['required', 'string', 'max:50', Rule::unique('members')->ignore($member->id)],
            'name' => 'required|string|max:255',
            'group_tag' => 'required|in:Manager,Bangunan,CSD,Office',
            'csd' => 'nullable|string|max:100',
            'dept' => 'required|string|max:100',
            'employee_status' => 'required|in:monthly,weekly',
        ]);

        // Jika group_tag Manager, CSD otomatis 'Manager'
        if ($validated['group_tag'] === 'Manager') {
            $validated['csd'] = 'Manager';
        }

        $member->update($validated);

        return redirect()->route('members.show', $member)
            ->with('success', 'Data anggota berhasil diperbarui.');
    }

    /**
     * Remove the specified member.
     */
    public function destroy(Member $member)
    {
        // Check if member has active loans
        if ($member->activeLoans()->exists()) {
            return back()->with('error', 'Tidak dapat menghapus anggota yang masih memiliki pinjaman aktif.');
        }

        $member->delete();

        return redirect()->route('members.index')
            ->with('success', 'Anggota berhasil dihapus.');
    }

    /**
     * Add manual saving deposit.
     */
    public function addSaving(Request $request, Member $member)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:1000',
            'transaction_date' => 'required|date',
            'notes' => 'nullable|string|max:255',
        ]);

        DB::transaction(function () use ($member, $validated) {
            // Update saldo
            $member->increment('savings_balance', $validated['amount']);

            // Buat transaksi
            Transaction::create([
                'member_id' => $member->id,
                'loan_id' => null,
                'transaction_date' => $validated['transaction_date'],
                'type' => Transaction::TYPE_SAVING_DEPOSIT,
                'amount_saving' => $validated['amount'],
                'amount_principal' => 0,
                'amount_interest' => 0,
                'total_amount' => $validated['amount'],
                'notes' => $validated['notes'] ?? 'Setoran manual',
            ]);
        });

        return back()->with('success', 'Simpanan berhasil ditambahkan.');
    }

    /**
     * Withdraw saving.
     */
    public function withdrawSaving(Request $request, Member $member)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:1000|max:' . $member->savings_balance,
            'transaction_date' => 'required|date',
            'notes' => 'nullable|string|max:255',
        ]);

        DB::transaction(function () use ($member, $validated) {
            // Kurangi saldo
            $member->decrement('savings_balance', $validated['amount']);

            // Buat transaksi
            Transaction::create([
                'member_id' => $member->id,
                'loan_id' => null,
                'transaction_date' => $validated['transaction_date'],
                'type' => Transaction::TYPE_SAVING_WITHDRAW,
                'amount_saving' => $validated['amount'],
                'amount_principal' => 0,
                'amount_interest' => 0,
                'total_amount' => $validated['amount'],
                'notes' => $validated['notes'] ?? 'Penarikan simpanan',
            ]);
        });

        return back()->with('success', 'Penarikan simpanan berhasil.');
    }

    /**
     * Search members for Select2 AJAX (JSON response).
     * Only returns members WITHOUT active loans.
     */
    public function searchMembers(Request $request)
    {
        $term = $request->get('term', '');

        $members = Member::query()
            ->where(function ($q) use ($term) {
                $q->where('name', 'like', "%{$term}%")
                  ->orWhere('nik', 'like', "%{$term}%");
            })
            // Hanya anggota tanpa pinjaman aktif
            ->whereDoesntHave('loans', fn($q) => $q->where('status', 'active'))
            ->orderBy('name')
            ->limit(20)
            ->get();

        // Format untuk Select2
        $results = $members->map(function ($member) {
            return [
                'id' => $member->id,
                'text' => "{$member->nik} - {$member->name} ({$member->dept})",
            ];
        });

        return response()->json($results);
    }
}
