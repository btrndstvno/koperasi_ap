@extends('layouts.app')

@section('title', 'Laporan Pembagian SHU')
@section('breadcrumb', 'Laporan SHU')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="bi bi-gift me-2"></i>Laporan Pembagian SHU</h4>
</div>

<div class="card mb-4">
    <div class="card-body">
        <form action="{{ route('reports.shu') }}" method="GET" class="row g-3 align-items-end">
            <div class="col-auto">
                <label class="form-label">Tahun Laporan</label>
                <div class="input-group">
                    <select name="year" class="form-select">
                        @for($y = now()->year - 2; $y <= now()->year + 1; $y++)
                            <option value="{{ $y }}" {{ $year == $y ? 'selected' : '' }}>{{ $y }}</option>
                        @endfor
                    </select>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-search me-1"></i> Tampilkan
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="card mt-4">
    <div class="card-header bg-warning text-dark py-3 d-flex justify-content-between align-items-center">
        <div>
            <h6 class="mb-0 fw-bold"><i class="bi bi-table me-2"></i>Simulasi Pembagian SHU Tahun {{ $year }}</h6>
            <small class="text-dark">Didistribusikan pada Awal {{ $year + 1 }}</small>
        </div>
        <div>
            @if(isset($shuPreview) && count($shuPreview) > 0)
                @if(isset($isDistributed) && $isDistributed)
                    <span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>Sudah Didistribusikan</span>
                @else
                    <form action="{{ route('reports.shu.distribute') }}" method="POST" onsubmit="return confirm('Apakah Anda yakin ingin memproses pembagian SHU untuk tahun {{ $year }}? Saldo simpanan anggota akan bertambah sesuai nominal SHU.');">
                        @csrf
                        <input type="hidden" name="year" value="{{ $year }}">
                        <button type="submit" class="btn btn-dark btn-sm">
                            <i class="bi bi-cash-coin me-1"></i> Proses Pembagian SHU
                        </button>
                    </form>
                @endif
            @endif
        </div>
    </div>
    <div class="card-body p-0">
        @if(isset($shuPreview) && count($shuPreview) > 0)
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4">NIK</th>
                        <th>Nama</th>
                        <th>Dept</th>
                        <th class="text-end">Saldo Simpanan</th>
                        <th class="text-end">Total Pinjaman</th>
                        <th class="text-end">Mulai Pinjam</th>
                        <th class="text-end">SHU Hitung</th>
                        <th class="text-end pe-4">SHU Pembulatan</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($shuPreview as $shu)
                    <tr>
                        <td class="ps-4"><code>{{ $shu['nik'] }}</code></td>
                        <td>{{ $shu['name'] }}</td>
                        <td>{{ $shu['dept'] }}</td>
                        <td class="text-end">Rp {{ number_format($shu['saldo'], 0, ',', '.') }}</td>
                        <td class="text-end">Rp {{ number_format($shu['total_loan'], 0, ',', '.') }}</td>
                        <td class="text-end"><small class="text-muted">{{ \Carbon\Carbon::parse($shu['debug_start_date'])->format('d M Y') }}</small></td>
                        <td class="text-end text-muted">Rp {{ number_format($shu['shu_raw'], 0, ',', '.') }}</td>
                        <td class="text-end fw-bold text-success pe-4">Rp {{ number_format($shu['shu_rounded'], 0, ',', '.') }}</td>
                    </tr>
                    @endforeach
                </tbody>
                <tfoot class="table-warning">
                    <tr class="fw-bold">
                        <td colspan="6" class="text-end">Total SHU:</td>
                        <td class="text-end">Rp {{ number_format(collect($shuPreview)->sum('shu_raw'), 0, ',', '.') }}</td>
                        <td class="text-end text-success pe-4">Rp {{ number_format(collect($shuPreview)->sum('shu_rounded'), 0, ',', '.') }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
        @else
        <div class="text-center py-5 text-muted">
            <i class="bi bi-info-circle fs-1 d-block mb-3"></i>
            <p class="mb-0">Tidak ada data pinjaman yang memenuhi syarat SHU untuk tahun {{ $year }}</p>
            <small>Pastikan ada pinjaman Aktif/Lunas yang memiliki cicilan di tahun {{ $year }}</small>
        </div>
        @endif
    </div>
    <div class="card-footer bg-light p-3">
        <h6 class="fw-bold mb-2">Keterangan Rumus:</h6>
        <ul class="mb-0 ps-3 text-muted small">
            <li>Rumus SHU: <code>Total Pinjaman × (Jumlah Cicilan di Tahun {{ $year }} / 10) × 5%</code></li>
            <li>Pembulatan: Ke pecahan 500 terdekat (Contoh: 761.600 -> 761.500, 545.370 -> 545.500)</li>
        </ul>
    </div>
</div>
@endsection
