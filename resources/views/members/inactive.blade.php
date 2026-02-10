@extends('layouts.app')

@section('title', 'Daftar Anggota Nonaktif')
@section('breadcrumb')
<a href="{{ route('members.index') }}" class="text-decoration-none">Anggota</a> / Nonaktif
@endsection

@section('content')
<div class="card">
    <div class="card-header bg-danger text-white d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="bi bi-person-x me-2"></i>Arsip Anggota Nonaktif</h5>
        <a href="{{ route('members.index') }}" class="btn btn-sm btn-light text-danger">
            <i class="bi bi-arrow-left me-1"></i> Kembali ke Anggota Aktif
        </a>
    </div>
    <div class="card-body">
        <form action="{{ route('members.inactive') }}" method="GET" class="mb-4">
            <div class="input-group">
                <input type="text" name="search" class="form-control" placeholder="Cari NIK atau Nama..." value="{{ request('search') }}">
                <button class="btn btn-outline-secondary" type="submit">Cari</button>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Nama / NIK</th>
                        <th>Departemen</th>
                        <th>Tanggal Keluar</th>
                        <th class="text-end">Saldo Terakhir</th>
                        <th>Sisa Hutang (Write Off)</th> {{-- KOLOM BARU --}}
                        <th>Alasan Nonaktif</th>
                        <th class="text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($members as $member)
                    <tr>
                        <td>
                            <div class="fw-bold">{{ $member->name }}</div>
                            <small class="text-muted">{{ $member->nik }}</small>
                        </td>
                        <td><span class="badge bg-secondary">{{ $member->dept }}</span></td>
                        <td>
                            @if($member->deactivation_date)
                                {{ \Carbon\Carbon::parse($member->deactivation_date)->format('d M Y') }}
                            @else
                                -
                            @endif
                        </td>
                        <td class="text-end">
                            <span class="fw-bold text-success">
                                Rp {{ number_format($member->final_savings_balance, 0, ',', '.') }}
                            </span>
                        </td>
                        {{-- LOGIKA MENAMPILKAN HUTANG TERAKHIR --}}
                        <td>
                            @php
                                // Hitung total transaksi write_off (Hutang yang diputihkan)
                                $writeOffAmount = $member->transactions->where('type', 'loan_write_off')->sum('amount_principal');
                            @endphp

                            @if($writeOffAmount > 0)
                                <span class="badge bg-danger fs-6">
                                    Rp {{ number_format($writeOffAmount, 0, ',', '.') }}
                                </span>
                                <div class="small text-muted mt-1">Diputihkan/Kerugian</div>
                            @else
                                <span class="badge bg-success">
                                    <i class="bi bi-check-circle me-1"></i> Lunas / Bersih
                                </span>
                            @endif
                        </td>

                        <td>
                            <span class="text-muted fst-italic">"{{ Str::limit($member->deactivation_reason, 50) }}"</span>
                        </td>
                        <td class="text-center">
                            <a href="{{ route('members.show', $member->id) }}" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-eye"></i> Detail
                            </a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="text-center py-5 text-muted">
                            <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                            Tidak ada data anggota nonaktif.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        
        <div class="mt-3">
            {{ $members->links() }}
        </div>
    </div>
</div>
@endsection