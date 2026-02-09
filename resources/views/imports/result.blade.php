@extends('layouts.app')

@section('title', 'Hasil Import - Koperasi')
@section('breadcrumb')
<a href="{{ route('imports.index') }}" class="text-decoration-none">Import Excel</a> / Hasil
@endsection

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-10">
        <!-- Summary -->
        <div class="card mb-4">
            <div class="card-header bg-success text-white">
                <i class="bi bi-check-circle me-2"></i>Import Selesai
            </div>
            <div class="card-body">
                <div class="row text-center mb-3">
                    <div class="col-md-4">
                        <div class="border rounded p-3">
                            <h2 class="text-primary mb-0">{{ $results['summary']['total_rows'] }}</h2>
                            <small class="text-muted">Total Baris</small>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="border rounded p-3">
                            <h2 class="text-success mb-0">{{ $results['summary']['imported'] }}</h2>
                            <small class="text-muted">Berhasil Import</small>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="border rounded p-3">
                            <h2 class="text-warning mb-0">{{ $results['summary']['skipped'] }}</h2>
                            <small class="text-muted">Dilewati</small>
                        </div>
                    </div>
                </div>
                <div class="row text-center">
                    <div class="col-md-4">
                        <div class="border rounded p-3 bg-light">
                            <h4 class="text-info mb-0">{{ $results['summary']['members_created'] ?? 0 }}</h4>
                            <small class="text-muted">Member Baru</small>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="border rounded p-3 bg-light">
                            <h4 class="text-secondary mb-0">{{ $results['summary']['members_updated'] ?? 0 }}</h4>
                            <small class="text-muted">Member Diperbarui</small>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="border rounded p-3 bg-light">
                            <h4 class="text-primary mb-0">{{ $results['summary']['loans_created'] ?? 0 }}</h4>
                            <small class="text-muted">Pinjaman Dibuat</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Detail per Sheet -->
        @if(count($results['success']) > 0)
        <div class="card mb-4">
            <div class="card-header">
                <i class="bi bi-list-check me-2"></i>Detail per Sheet
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Sheet</th>
                                <th class="text-center">Imported</th>
                                <th class="text-center">Skipped</th>
                                <th>Errors</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($results['success'] as $sheet)
                            <tr>
                                <td><code>{{ $sheet['sheet'] }}</code></td>
                                <td class="text-center">
                                    <span class="badge bg-success">{{ $sheet['imported'] }}</span>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-warning text-dark">{{ $sheet['skipped'] }}</span>
                                </td>
                                <td>
                                    @if(count($sheet['errors']) > 0)
                                        <button type="button" class="btn btn-sm btn-outline-danger" 
                                                data-bs-toggle="collapse" data-bs-target="#errors-{{ $loop->index }}">
                                            {{ count($sheet['errors']) }} error(s)
                                        </button>
                                        <div class="collapse mt-2" id="errors-{{ $loop->index }}">
                                            <ul class="small text-danger mb-0">
                                                @foreach(array_slice($sheet['errors'], 0, 10) as $error)
                                                    <li>{{ $error }}</li>
                                                @endforeach
                                                @if(count($sheet['errors']) > 10)
                                                    <li>... dan {{ count($sheet['errors']) - 10 }} error lainnya</li>
                                                @endif
                                            </ul>
                                        </div>
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        @endif

        <!-- Global Errors -->
        @if(count($results['errors']) > 0)
        <div class="card mb-4 border-danger">
            <div class="card-header bg-danger text-white">
                <i class="bi bi-exclamation-triangle me-2"></i>Error Global
            </div>
            <div class="card-body">
                <ul class="mb-0 text-danger">
                    @foreach($results['errors'] as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
        @endif

        <div class="d-flex justify-content-between">
            <a href="{{ route('imports.index') }}" class="btn btn-primary">
                <i class="bi bi-plus me-1"></i> Import Lagi
            </a>
            <a href="{{ route('reports.index') }}" class="btn btn-outline-secondary">
                <i class="bi bi-bar-chart me-1"></i> Lihat Laporan
            </a>
        </div>
    </div>
</div>
@endsection
