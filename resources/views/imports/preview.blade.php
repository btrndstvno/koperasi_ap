@extends('layouts.app')

@section('title', 'Preview Import - Koperasi')
@section('breadcrumb')
<a href="{{ route('imports.index') }}" class="text-decoration-none">Import Excel</a> / Preview
@endsection

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-10">
        <div class="card">
            <div class="card-header">
                <i class="bi bi-file-earmark-check me-2"></i>Preview Import: {{ $originalName }}
            </div>
            <div class="card-body">
                @if(count($validSheets) === 0)
                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        <strong>Tidak ada sheet yang valid!</strong>
                        <p class="mb-0 mt-2">
                            Tidak ditemukan sheet dengan format nama "MM YYYY" (contoh: "05 2025").
                            <br>Pastikan nama sheet sesuai format yang diharapkan.
                        </p>
                    </div>
                    
                    <a href="{{ route('imports.index') }}" class="btn btn-secondary">
                        <i class="bi bi-arrow-left me-1"></i> Kembali
                    </a>
                @else
                    <form action="{{ route('imports.process') }}" method="POST">
                        @csrf
                        <input type="hidden" name="temp_path" value="{{ $tempPath }}">

                        <!-- Valid Sheets -->
                        <div class="mb-4">
                            <h6 class="text-success"><i class="bi bi-check-circle me-2"></i>Sheet yang Valid ({{ count($validSheets) }})</h6>
                            <p class="text-muted small">Pilih sheet yang ingin diimport:</p>
                            
                            <div class="table-responsive">
                                <table class="table table-bordered">
                                    <thead class="table-light">
                                        <tr>
                                            <th style="width: 50px;">
                                                <input type="checkbox" id="selectAll" checked>
                                            </th>
                                            <th>Nama Sheet</th>
                                            <th>Tanggal Transaksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($validSheets as $sheet)
                                        <tr>
                                            <td>
                                                <input type="checkbox" name="sheets[]" value="{{ $sheet['name'] }}" class="sheet-checkbox" checked>
                                            </td>
                                            <td><code>{{ $sheet['name'] }}</code></td>
                                            <td>{{ \Carbon\Carbon::parse($sheet['parsed_date'])->translatedFormat('d F Y') }}</td>
                                        </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Skipped Sheets -->
                        @if(count($skippedSheets) > 0)
                        <div class="mb-4">
                            <h6 class="text-warning"><i class="bi bi-skip-forward me-2"></i>Sheet yang Dilewati ({{ count($skippedSheets) }})</h6>
                            <p class="text-muted small">Sheet berikut tidak sesuai format dan akan dilewati:</p>
                            <div class="d-flex flex-wrap gap-2">
                                @foreach($skippedSheets as $sheet)
                                    <span class="badge bg-secondary">{{ $sheet }}</span>
                                @endforeach
                            </div>
                        </div>
                        @endif

                        <hr>

                        <div class="d-flex justify-content-between">
                            <a href="{{ route('imports.index') }}" class="btn btn-secondary">
                                <i class="bi bi-arrow-left me-1"></i> Kembali
                            </a>
                            <button type="submit" class="btn btn-success btn-lg">
                                <i class="bi bi-cloud-upload me-2"></i>Proses Import
                            </button>
                        </div>
                    </form>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.getElementById('selectAll')?.addEventListener('change', function() {
    document.querySelectorAll('.sheet-checkbox').forEach(cb => {
        cb.checked = this.checked;
    });
});
</script>
@endpush
