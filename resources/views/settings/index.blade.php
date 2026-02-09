@extends('layouts.app')

@section('title', 'Pengaturan Sistem')
@section('breadcrumb', 'Pengaturan')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="bi bi-gear me-2"></i>Pengaturan Sistem</h4>
</div>

<div class="row">
    <div class="col-lg-6">
        <div class="card mb-4">
            <div class="card-header bg-primary text-white py-3">
                <h6 class="mb-0 fw-bold"><i class="bi bi-percent me-2"></i>Pengaturan Bunga</h6>
            </div>
            <div class="card-body">
                <form action="{{ route('settings.update') }}" method="POST">
                    @csrf
                    @method('PUT')

                    <div class="mb-3">
                        <label class="form-label fw-bold">Bunga Tabungan (per bulan)</label>
                        <div class="input-group">
                            <input type="number" 
                                   name="saving_interest_rate" 
                                   class="form-control @error('saving_interest_rate') is-invalid @enderror" 
                                   value="{{ old('saving_interest_rate', $settings['saving_interest_rate']) }}" 
                                   step="0.01" 
                                   min="0" 
                                   max="100">
                            <span class="input-group-text">%</span>
                        </div>
                        <small class="text-muted">Bunga yang diberikan ke simpanan anggota setiap bulan</small>
                        @error('saving_interest_rate')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Bunga SHU (per tahun)</label>
                        <div class="input-group">
                            <input type="number" 
                                   name="shu_rate" 
                                   class="form-control @error('shu_rate') is-invalid @enderror" 
                                   value="{{ old('shu_rate', $settings['shu_rate']) }}" 
                                   step="0.01" 
                                   min="0" 
                                   max="100">
                            <span class="input-group-text">%</span>
                        </div>
                        <small class="text-muted">Persentase SHU yang dibagikan berdasarkan pinjaman tahunan</small>
                        @error('shu_rate')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-bold">Bunga Pinjaman</label>
                        <div class="input-group">
                            <input type="number" 
                                   name="loan_interest_rate" 
                                   class="form-control @error('loan_interest_rate') is-invalid @enderror" 
                                   value="{{ old('loan_interest_rate', $settings['loan_interest_rate']) }}" 
                                   step="0.01" 
                                   min="0" 
                                   max="100">
                            <span class="input-group-text">%</span>
                        </div>
                        <small class="text-muted">Bunga pinjaman (dipotong di awal pencairan)</small>
                        @error('loan_interest_rate')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="alert alert-info mb-3">
                        <i class="bi bi-info-circle me-2"></i>
                        <strong>Catatan:</strong> Perubahan bunga hanya berlaku untuk transaksi/pinjaman baru. 
                        Transaksi dan pinjaman yang sudah ada tetap menggunakan bunga lama.
                    </div>

                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save me-1"></i> Simpan Pengaturan
                    </button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card">
            <div class="card-header bg-secondary text-white py-3">
                <h6 class="mb-0 fw-bold"><i class="bi bi-clock-history me-2"></i>Riwayat Perubahan Bunga</h6>
            </div>
            <div class="card-body p-0">
                @if($histories->count() > 0)
                <div class="table-responsive">
                    <table class="table table-hover mb-0 align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Tanggal</th>
                                <th>Jenis</th>
                                <th class="text-end">Lama</th>
                                <th class="text-end">Baru</th>
                                <th>Oleh</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($histories as $history)
                            <tr>
                                <td><small>{{ $history->effective_from->format('d/m/Y H:i') }}</small></td>
                                <td>{{ $history->key_label }}</td>
                                <td class="text-end text-muted">{{ $history->old_value }}%</td>
                                <td class="text-end fw-bold">{{ $history->new_value }}%</td>
                                <td><small class="text-muted">{{ $history->changedByUser?->name ?? 'System' }}</small></td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @else
                <div class="text-center py-4 text-muted">
                    <i class="bi bi-info-circle fs-1 d-block mb-2"></i>
                    Belum ada riwayat perubahan bunga
                </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
