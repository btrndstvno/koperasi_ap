<?php

namespace App\Http\Controllers;

use App\Models\Member;
use App\Models\Transaction;
use App\Models\Withdrawal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class WithdrawalController extends Controller
{
    /**
     * Display a listing of withdrawals.
     */
    public function index(Request $request)
    {
        $query = Withdrawal::with('member');

        // If user is member, only show their withdrawals
        if (Auth::user()->isMember()) {
            $member = Auth::user()->member;
            if ($member) {
                $query->where('member_id', $member->id);
            } else {
                $query->where('member_id', 0);
            }
        }

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Search by member
        if ($request->filled('search')) {
            $search = $request->search;
            $query->whereHas('member', function ($q) use ($search) {
                $q->where('nik', 'like', "%{$search}%")
                  ->orWhere('name', 'like', "%{$search}%");
            });
        }

        $withdrawals = $query->orderByDesc('created_at')
            ->paginate(25)
            ->appends($request->query());

        // Statistics
        if (Auth::user()->isAdmin()) {
            $pendingCount = Withdrawal::where('status', 'pending')->count();
            $totalApproved = Withdrawal::where('status', 'approved')->sum('amount');
        } else {
            $memberId = Auth::user()->member->id ?? 0;
            $pendingCount = Withdrawal::where('member_id', $memberId)->where('status', 'pending')->count();
            $totalApproved = Withdrawal::where('member_id', $memberId)->where('status', 'approved')->sum('amount');
        }

        return view('withdrawals.index', compact('withdrawals', 'pendingCount', 'totalApproved'));
    }

    /**
     * Show form for creating a withdrawal request.
     */
    public function create(Request $request)
    {
        $member = null;
        
        // Admin can create for any member
        if (Auth::user()->isAdmin() && $request->filled('member_id')) {
            $member = Member::findOrFail($request->member_id);
        }
        
        // Member creates for themselves
        if (Auth::user()->isMember()) {
            $member = Auth::user()->member;
        }

        return view('withdrawals.create', compact('member'));
    }

    /**
     * Store a new withdrawal request (pending).
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'member_id' => 'required|exists:members,id',
            'amount' => 'required|numeric|min:10000',
            'notes' => 'nullable|string|max:500',
        ]);

        $member = Member::findOrFail($validated['member_id']);

        // Check if amount doesn't exceed savings balance
        if ($validated['amount'] > $member->savings_balance) {
            return back()->with('error', 'Jumlah penarikan melebihi saldo simpanan (Rp ' . number_format($member->savings_balance, 0, ',', '.') . ').')
                ->withInput();
        }

        // Check if member has pending withdrawal
        if ($member->withdrawals()->where('status', Withdrawal::STATUS_PENDING)->exists()) {
            return back()->with('error', 'Anggota masih memiliki pengajuan penarikan yang belum diproses.')
                ->withInput();
        }

        Withdrawal::create([
            'member_id' => $validated['member_id'],
            'amount' => $validated['amount'],
            'request_date' => now()->toDateString(),
            'status' => Withdrawal::STATUS_PENDING,
            'notes' => $validated['notes'],
        ]);

        return redirect()->route('withdrawals.index')
            ->with('success', 'Pengajuan penarikan saldo berhasil diajukan.');
    }

    /**
     * Display the specified withdrawal.
     */
    public function show(Withdrawal $withdrawal)
    {
        // Authorization check
        if (Auth::user()->isMember()) {
            $member = Auth::user()->member;
            if (!$member || $withdrawal->member_id !== $member->id) {
                abort(403);
            }
        }

        $withdrawal->load('member');
        return view('withdrawals.show', compact('withdrawal'));
    }

    /**
     * Approve withdrawal request.
     */
    public function approve(Withdrawal $withdrawal)
    {
        if ($withdrawal->status !== Withdrawal::STATUS_PENDING) {
            return back()->with('error', 'Hanya pengajuan pending yang dapat disetujui.');
        }

        $member = $withdrawal->member;

        // Re-check if amount doesn't exceed current savings balance
        if ($withdrawal->amount > $member->savings_balance) {
            return back()->with('error', 'Saldo simpanan tidak mencukupi (Rp ' . number_format($member->savings_balance, 0, ',', '.') . ').');
        }

        DB::transaction(function () use ($withdrawal, $member) {
            // Update withdrawal status
            $withdrawal->update([
                'status' => Withdrawal::STATUS_APPROVED,
                'approved_date' => now()->toDateString(),
            ]);

            // Deduct from member's savings balance
            $member->decrement('savings_balance', $withdrawal->amount);

            // Create transaction record for mutasi simpanan
            Transaction::create([
                'member_id' => $member->id,
                'loan_id' => null,
                'transaction_date' => now()->toDateString(),
                'type' => Transaction::TYPE_SAVING_WITHDRAW,
                'amount_saving' => -$withdrawal->amount, // Negative for withdrawal
                'amount_principal' => 0,
                'amount_interest' => 0,
                'total_amount' => -$withdrawal->amount,
                'payment_method' => 'cash',
                'notes' => 'Penarikan saldo simpanan #' . $withdrawal->id . ($withdrawal->notes ? ' - ' . $withdrawal->notes : ''),
            ]);
        });

        return redirect()->route('withdrawals.index')
            ->with('success', 'Pengajuan penarikan saldo telah disetujui.');
    }

    /**
     * Reject withdrawal request.
     */
    public function reject(Request $request, Withdrawal $withdrawal)
    {
        if ($withdrawal->status !== Withdrawal::STATUS_PENDING) {
            return back()->with('error', 'Hanya pengajuan pending yang dapat ditolak.');
        }

        $validated = $request->validate([
            'rejection_reason' => 'required|string|max:500',
        ]);

        $withdrawal->update([
            'status' => Withdrawal::STATUS_REJECTED,
            'rejection_reason' => $validated['rejection_reason'],
        ]);

        return redirect()->route('withdrawals.index')
            ->with('success', 'Pengajuan penarikan saldo telah ditolak.');
    }

    /**
     * Print withdrawal document.
     */
    public function print(Withdrawal $withdrawal)
    {
        $withdrawal->load('member');
        return view('withdrawals.print', compact('withdrawal'));
    }
}
