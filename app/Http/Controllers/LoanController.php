<?php

namespace App\Http\Controllers;

use App\Models\Loan;
use App\Models\Member;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LoanController extends Controller
{
    /**
     * Display a listing of loans.
     */
    public function index(Request $request)
    {
        $query = Loan::with('member');

        // If user is member, only show their loans
        if (\Illuminate\Support\Facades\Auth::user()->isMember()) {
            $member = \Illuminate\Support\Facades\Auth::user()->member;
            if ($member) {
                $query->where('member_id', $member->id);
            } else {
                // If member record not found, showing empty
                $query->where('member_id', 0);
            }
        }

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        } 


        // Search by member (Only for admin)
        if ($request->filled('search')) {
            $search = $request->search;
            $query->whereHas('member', function ($q) use ($search) {
                $q->where('nik', 'like', "%{$search}%")
                  ->orWhere('name', 'like', "%{$search}%");
            });
        }

        $loans = $query->orderByDesc('created_at')
            ->paginate(25)
            ->appends($request->query());
            
        // Statistics (Only meaningful for Admin or specific member context)
        if (\Illuminate\Support\Facades\Auth::user()->isAdmin()) {
            $totalActive = Loan::where('status', 'active')->sum('remaining_principal');
            $countActive = Loan::where('status', 'active')->count();
        } else {
            // For member, stats for their own loans
            $memberId = \Illuminate\Support\Facades\Auth::user()->member->id ?? 0;
            $totalActive = Loan::where('member_id', $memberId)->where('status', 'active')->sum('remaining_principal');
            $countActive = Loan::where('member_id', $memberId)->where('status', 'active')->count();
        }

        return view('loans.index', compact('loans', 'totalActive', 'countActive'));
    }

    /**
     * Show the form for creating a new loan.
     */
    public function create(Request $request)
    {
        $member = null;
        if ($request->filled('member_id')) {
            $member = Member::findOrFail($request->member_id);
            
            // Check if member has active loan
            if ($member->activeLoans()->exists()) {
                return back()->with('error', 'Anggota masih memiliki pinjaman aktif.');
            }
        }

        // Tidak perlu load semua members, karena pakai Select2 AJAX
        return view('loans.create', compact('member'));
    }

    /**
     * simpan pinjaman baru jadi pending
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'member_id' => 'required|exists:members,id',
            'amount' => 'required|numeric|min:100000',
            'interest_rate' => 'required|numeric|min:0|max:10',
            'duration' => 'required|integer|min:1|max:60',
        ]);

        // Check member punya pinjaman aktif ga
        $member = Member::findOrFail($validated['member_id']);
        if ($member->activeLoans()->exists()) {
            return back()->with('error', 'Anggota masih memiliki pinjaman aktif.')
                ->withInput();
        }

        // Check member punya pengajuan pinjaman pending ga
        if ($member->loans()->where('status', Loan::STATUS_PENDING)->exists()) {
            return back()->with('error', 'Anggota masih memiliki pengajuan pinjaman yang belum diproses.')
                ->withInput();
        }

        $loan = DB::transaction(function () use ($validated) {
            $pokok = (float) $validated['amount'];
            $bungaPersen = (float) $validated['interest_rate'];
            $tenor = (int) $validated['duration'];

            // Hitung bunga potong di awal
            $totalBunga = round($pokok * ($bungaPersen / 100), 2);
            
            // Hitung Biaya Admin 1% (Hidden Fee)
            // Biaya admin dipotong dari pencairan
            $adminFee = round($pokok * 0.01, 2);
            
            // Uang Cair = Pokok - Bunga - Admin Fee
            $uangCair = $pokok - $totalBunga - $adminFee;
            
            $cicilanBulanan = round($pokok / $tenor, 2);

            // Create loan dengan status PENDING
            return Loan::create([
                'member_id' => $validated['member_id'],
                'amount' => $pokok,
                'interest_rate' => $bungaPersen,
                'duration' => $tenor,
                'remaining_principal' => $pokok, // Belum dikurangi, tunggu approve
                'monthly_installment' => $cicilanBulanan,
                'total_interest' => $totalBunga,
                'admin_fee' => $adminFee,
                'disbursed_amount' => $uangCair,
                'status' => Loan::STATUS_PENDING, // Status PENDING
            ]);
        });

        return redirect()->route('loans.show', $loan)
            ->with('success', 'Pengajuan pinjaman berhasil dibuat. Silakan cetak SPJ dan lakukan persetujuan.');
    }

    /**
     * Update amount of a pending loan.
     */
    public function updateAmount(Request $request, Loan $loan)
    {
        if ($loan->status !== Loan::STATUS_PENDING) {
            return back()->with('error', 'Pinjaman ini tidak dalam status pending.');
        }

        $validated = $request->validate([
            'amount' => 'required|numeric|min:100000',
        ]);

        DB::transaction(function () use ($loan, $validated) {
            // Recalculate everything based on new amount
            $pokok = (float) $validated['amount'];
            $bungaPersen = $loan->interest_rate; // Maintain original rate
            $tenor = $loan->duration; // Maintain original duration

            // Hitung bunga potong di awal
            $totalBunga = round($pokok * ($bungaPersen / 100), 2);
            
            // Hitung Biaya Admin 1% (Hidden Fee)
            $adminFee = round($pokok * 0.01, 2);
            
            // Uang Cair = Pokok - Bunga - Admin Fee
            $uangCair = $pokok - $totalBunga - $adminFee;
            
            $cicilanBulanan = round($pokok / $tenor, 2);

            // Update amounts
            $loan->update([
                'amount' => $pokok,
                'remaining_principal' => $pokok,
                'monthly_installment' => $cicilanBulanan,
                'total_interest' => $totalBunga,
                'admin_fee' => $adminFee,
                'disbursed_amount' => $uangCair,
            ]);
        });

        return redirect()->route('loans.show', $loan)
            ->with('success', 'Nominal pinjaman berhasil diperbarui.');
    }

    /**
     * terima dan proses pinjaman yang pending
     */
    public function approve(Request $request, Loan $loan)
    {
        if ($loan->status !== Loan::STATUS_PENDING) {
            return back()->with('error', 'Pinjaman ini tidak dalam status pending.');
        }

        DB::transaction(function () use ($loan) {
            $member = $loan->member;

            // Update status menjadi active
            $loan->update(['status' => Loan::STATUS_ACTIVE]);

            // Buat transaksi: Bunga sebagai pendapatan koperasi
            if ($loan->total_interest > 0) {
                Transaction::create([
                    'member_id' => $member->id,
                    'loan_id' => $loan->id,
                    'transaction_date' => now()->toDateString(),
                    'type' => Transaction::TYPE_INTEREST_REVENUE,
                    'amount_saving' => 0,
                    'amount_principal' => 0,
                    'amount_interest' => $loan->total_interest,
                    'total_amount' => $loan->total_interest,
                    'payment_method' => 'deduction',
                    'notes' => 'Bunga dipotong di awal pencairan pinjaman',
                ]);
            }

            // Buat transaksi: Admin Fee (jika ada)
            if ($loan->admin_fee > 0) {
                Transaction::create([
                    'member_id' => $member->id,
                    'loan_id' => $loan->id,
                    'transaction_date' => now()->toDateString(),
                    'type' => Transaction::TYPE_ADMIN_FEE,
                    'amount_saving' => 0,
                    'amount_principal' => 0,
                    'amount_interest' => 0,
                    'total_amount' => $loan->admin_fee,
                    'payment_method' => 'deduction',
                    'notes' => 'Biaya Admin (1%)',
                ]);
            }

            // Buat transaksi: Pencairan
            Transaction::create([
                'member_id' => $member->id,
                'loan_id' => $loan->id,
                'transaction_date' => now()->toDateString(),
                'type' => Transaction::TYPE_LOAN_DISBURSEMENT,
                'amount_saving' => 0,
                'amount_principal' => $loan->disbursed_amount,
                'amount_interest' => 0,
                'total_amount' => $loan->disbursed_amount,
                'payment_method' => 'cash',
                'notes' => 'Pencairan pinjaman ke anggota',
            ]);
        });

        return redirect()->route('loans.show', $loan)
            ->with('success', 'Pinjaman berhasil dicairkan! Dana sebesar Rp ' . number_format($loan->disbursed_amount, 0, ',', '.') . ' telah diberikan.');
    }

    /**
     * Reject a pending loan.
     */
    public function reject(Request $request, Loan $loan)
    {
        if ($loan->status !== Loan::STATUS_PENDING) {
            return back()->with('error', 'Pinjaman ini tidak dalam status pending.');
        }

        $loan->update(['status' => Loan::STATUS_REJECTED]);

        return redirect()->route('loans.index')
            ->with('success', 'Pengajuan pinjaman ditolak.');
    }

    /**
     * Print SPJ (Surat Perjanjian) for a loan.
     */
    public function print(Loan $loan)
    {
        $loan->load('member');
        return view('loans.print', compact('loan'));
    }

    /**
     * Display the specified loan.
     */
    public function show(Loan $loan)
    {
        // Authorization check
        $user = \Illuminate\Support\Facades\Auth::user();
        if ($user->isMember()) {
            if ($loan->member_id !== $user->member->id) {
                abort(403, 'Unauthorized access to this loan.');
            }
        }

        $loan->load(['member', 'transactions' => fn($q) => $q->orderByDesc('transaction_date')]);

        return view('loans.show', compact('loan'));
    }

    /**
     * Manual loan repayment.
     */
    public function repay(Request $request, Loan $loan)
    {
        if ($loan->status !== 'active') {
            return back()->with('error', 'Pinjaman sudah lunas.');
        }

        $validated = $request->validate([
            'amount_principal' => 'required|numeric|min:0|max:' . $loan->remaining_principal,
            'amount_interest' => 'required|numeric|min:0',
            'transaction_date' => 'required|date',
            'notes' => 'nullable|string|max:255',
        ]);

        DB::transaction(function () use ($loan, $validated) {
            // Kurangi sisa pokok
            if ($validated['amount_principal'] > 0) {
                $loan->reduceRemainingPrincipal($validated['amount_principal']);
            }

            // Buat transaksi
            Transaction::create([
                'member_id' => $loan->member_id,
                'loan_id' => $loan->id,
                'transaction_date' => $validated['transaction_date'],
                'type' => Transaction::TYPE_LOAN_REPAYMENT,
                'amount_saving' => 0,
                'amount_principal' => $validated['amount_principal'],
                'amount_interest' => $validated['amount_interest'],
                'total_amount' => $validated['amount_principal'] + $validated['amount_interest'],
                'notes' => $validated['notes'] ?? 'Pembayaran manual',
            ]);
        });

        return back()->with('success', 'Pembayaran pinjaman berhasil dicatat.');
    }
}
