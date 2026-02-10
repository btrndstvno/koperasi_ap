@extends('layouts.app')

@section('title', 'Import Excel - Koperasi')
@section('breadcrumb', 'Import Excel')

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <i class="bi bi-file-earmark-excel me-2"></i>Import Data dari Excel
            </div>
            <div class="card-body">
                <div class="alert alert-info">
                    <h6><i class="bi bi-info-circle me-2"></i>Petunjuk Import</h6>
                    <ul class="mb-0">
                        <li>File Excel harus berformat <strong>.xlsx</strong> atau <strong>.xls</strong></li>
                        <li>Baris pertama file harus berisi <strong>tanggal transaksi</strong> (contoh: "31 JANUARI 2026")</li>
                        <li>Data dikelompokkan berdasarkan section: <strong>MANAGER, BANGUNAN, CSD, OFFICE/KARYAWAN</strong></li>
                        <li>Kolom yang diperlukan: <code>NIK</code>, <code>NAMA</code>, <code>DEPT</code>, <code>POT KOP</code>, <code>IUR KOP</code>, <code>SALDO</code></li>
                        <li>Member baru akan otomatis dibuat jika NIK tidak ditemukan</li>
                        <li>Pinjaman akan dihitung otomatis dari kolom <code>SISA CICILAN</code> (peraturan: 10 bulan)</li>
                    </ul>
                </div>

                <form action="{{ route('imports.direct') }}" method="POST" enctype="multipart/form-data" id="importForm">
                    @csrf
                    
                    <div class="mb-4">
                        <label class="form-label form-label-required">Pilih File Excel</label>
                        <input type="file" name="file" class="form-control @error('file') is-invalid @enderror" 
                               accept=".xlsx,.xls" required>
                        @error('file')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        <small class="text-muted">Maksimal 10MB</small>
                    </div>

                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-success btn-lg">
                            <i class="bi bi-cloud-upload me-2"></i>Import Langsung
                        </button>
                        <!-- <button type="button" class="btn btn-outline-primary" onclick="document.getElementById('importForm').action='{{ route('imports.preview') }}'; document.getElementById('importForm').submit();">
                            <i class="bi bi-eye me-2"></i>Preview Dulu
                        </button> -->
                    </div>
                </form>
            </div>
        </div>

        <!-- Format Template -->
        <div class="card mt-4">
            <div class="card-header">
                <i class="bi bi-file-earmark-spreadsheet me-2"></i>Format Kolom yang Didukung
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-bordered mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Field</th>
                                <th>Nama Kolom yang Didukung</th>
                                <th>Keterangan</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><strong>NIK</strong></td>
                                <td><code>NIK</code>, <code>NO_INDUK</code>, <code>NIP</code>, <code>ID_KARYAWAN</code></td>
                                <td>Wajib ada, harus cocok dengan database</td>
                            </tr>
                            <tr>
                                <td><strong>Simpanan</strong></td>
                                <td><code>IUR_KOP</code>, <code>IURAN</code>, <code>SIMPANAN</code>, <code>SAVING</code></td>
                                <td>Iuran simpanan bulanan</td>
                            </tr>
                            <tr>
                                <td><strong>Pinjaman</strong></td>
                                <td><code>POT_KOP</code>, <code>POTONGAN</code>, <code>ANGSURAN</code>, <code>CICILAN</code></td>
                                <td>Potongan pembayaran pokok pinjaman</td>
                            </tr>
                            <tr>
                                <td><strong>Bunga</strong></td>
                                <td><code>BUNGA</code>, <code>JASA</code>, <code>INTEREST</code></td>
                                <td>Opsional - bunga pinjaman</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
