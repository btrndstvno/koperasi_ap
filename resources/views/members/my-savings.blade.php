@extends('layouts.app')

@section('title', 'Riwayat Pembayaran & Tabungan - Koperasi')
@section('breadcrumb', 'Riwayat Saya')

@section('content')
<div class="row mb-4">
    <div class="col-md-6">
        <h4 class="mb-0"><i class="bi bi-wallet2 me-2"></i>Riwayat Keuangan Saya</h4>
        <p class="text-muted mb-0">Catatan tabungan dan pembayaran angsuran.</p>
    </div>
    <div class="col-md-6 text-md-end mt-2 mt-md-0">
        <div class="card bg-success text-white d-inline-block px-4 py-2">
            <small class="d-block opacity-75">Total Simpanan</small>
            <h4 class="mb-0">Rp {{ number_format($member->savings_balance, 0, ',', '.') }}</h4>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Tanggal</th>
                        <th>Jenis Transaksi</th>
                        <th class="text-end">Simpanan (Masuk/Keluar)</th>
                        <th class="text-end">Angsuran Pinjaman</th>
                        <th class="text-end">Total</th>
                        <th>Keterangan</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($transactions as $trx)
                    <tr>
                        <td>{{ $trx->transaction_date->format('d/m/Y') }}</td>
                        <td>
                            @switch($trx->type)
                                @case('saving')
                                    <span class="badge bg-success">Setoran Simpanan</span>
                                    @break
                                @case('withdrawal')
                                    <span class="badge bg-warning text-dark">Penarikan</span>
                                    @break
                                @case('loan_repayment')
                                    <span class="badge bg-info text-dark">Bayar Angsuran</span>
                                    @break
                                @case('loan_disbursement')
                                    <span class="badge bg-primary">Pencairan Pinjaman</span>
                                    @break
                                @case('interest_revenue')
                                    <span class="badge bg-secondary">Bunga Pinjaman</span>
                                    @break
                                @case('admin_fee')
                                    <span class="badge bg-secondary">Biaya Admin</span>
                                    @break
                                @default
                                    <span class="badge bg-secondary">{{ ucwords(str_replace('_', ' ', $trx->type)) }}</span>
                            @endswitch
                        </td>
                        <td class="text-end {{ $trx->amount_saving > 0 ? 'text-success' : ($trx->amount_saving < 0 ? 'text-danger' : 'text-muted') }}">
                            @if($trx->type == 'withdrawal')
                                - Rp {{ number_format(abs($trx->total_amount), 0, ',', '.') }}
                            @elseif($trx->amount_saving != 0)
                                Rp {{ number_format($trx->amount_saving, 0, ',', '.') }}
                            @else
                                -
                            @endif
                        </td>
                        <td class="text-end">
                            @if($trx->amount_principal > 0 || $trx->amount_interest > 0)
                                <div class="d-flex flex-column align-items-end">
                                    <span class="text-dark">Rp {{ number_format($trx->amount_principal + $trx->amount_interest, 0, ',', '.') }}</span>
                                    @if($trx->amount_interest > 0)
                                    <small class="text-muted" style="font-size: 0.75rem">(Bunga: {{ number_format($trx->amount_interest) }})</small>
                                    @endif
                                </div>
                            @else
                                -
                            @endif
                        </td>
                        <td class="text-end fw-bold">
                            Rp {{ number_format($trx->total_amount, 0, ',', '.') }}
                        </td>
                        <td>
                            {{ $trx->notes }}
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="text-center py-5 text-muted">
                            <i class="bi bi-inbox fs-1 d-block mb-3"></i>
                            Belum ada riwayat transaksi.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($transactions->hasPages())
    <div class="card-footer bg-white">
        {{ $transactions->links() }}
    </div>
    @endif
</div>
@endsection