@extends('layouts.app')

@section('title', 'Daftar Anggota - Koperasi')
@section('breadcrumb', 'Anggota')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="bi bi-people me-2"></i>Daftar Anggota</h4>
    <a href="{{ route('members.create') }}" class="btn btn-primary">
        <i class="bi bi-person-plus me-1"></i> Tambah Anggota
    </a>
</div>

<!-- Filter -->
<div class="card mb-4">
    <div class="card-body">
        <form action="{{ route('members.index') }}" method="GET" class="row g-3">
            <div class="col-md-3">
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input type="text" name="search" class="form-control" placeholder="Cari NIK atau Nama..." value="{{ request('search') }}">
                </div>
            </div>
            <div class="col-md-2">
                <input type="text" name="dept" class="form-control" placeholder="Cari Departemen..." value="{{ request('dept') }}">
            </div>
            <div class="col-md-2">
                <select name="group_tag" class="form-select">
                    <option value="">Semua Group</option>
                    @foreach($groupTags as $tag)
                        <option value="{{ $tag }}" {{ request('group_tag') == $tag ? 'selected' : '' }}>{{ $tag }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <select name="is_active" class="form-select">
                    <option value="">Semua Status</option>
                    <option value="yes" {{ request('is_active') == 'yes' ? 'selected' : '' }}>Aktif</option>
                    <option value="no" {{ request('is_active') == 'no' ? 'selected' : '' }}>Nonaktif</option>
                </select>
            </div>
            <div class="col-md-2">
                <select name="has_loan" class="form-select">
                    <option value="">Semua Pinjaman</option>
                    <option value="yes" {{ request('has_loan') == 'yes' ? 'selected' : '' }}>Ada Pinjaman</option>
                    <option value="no" {{ request('has_loan') == 'no' ? 'selected' : '' }}>Tanpa Pinjaman</option>
                </select>
            </div>
            <div class="col-md-1">
                <button type="submit" class="btn btn-secondary w-100">
                    <i class="bi bi-filter"></i>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Members Table -->
<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>NIK</th>
                        <th>Nama</th>
                        <th>Dept</th>
                        <th>Group</th>
                        <th class="text-center">Status</th>
                        <th class="text-end">Sisa Hutang</th>
                        <th class="text-end">Saldo</th>
                        <th class="text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($members as $member)
                    <tr class="{{ !$member->is_active ? 'table-secondary' : '' }}">
                        <td>
                            <code>{{ $member->nik }}</code>
                        </td>
                        <td>
                            <a href="{{ route('members.show', $member) }}" class="text-decoration-none fw-medium {{ !$member->is_active ? 'text-muted' : '' }}">
                                {{ $member->name }}
                            </a>
                            @if(!$member->is_active)
                                <span class="badge bg-dark ms-1">Nonaktif</span>
                            @endif
                        </td>
                        <td>
                            <span class="badge bg-secondary badge-dept">{{ $member->dept }}</span>
                        </td>
                        <td>
                            <span class="badge bg-info">{{ $member->group_tag }}</span>
                        </td>
                        <td class="text-center">
                            @if($member->is_active)
                                @if($member->loans_count > 0)
                                    <span class="badge bg-danger">{{ $member->loans_count }} Pinjaman</span>
                                @else
                                    <span class="badge bg-success">Aktif</span>
                                @endif
                            @else
                                <span class="badge bg-dark">Nonaktif</span>
                            @endif
                        </td>
                        <td class="text-end">
                            @php
                                $sisaHutang = $member->loans->where('status', 'active')->sum('remaining_principal');
                            @endphp
                            @if($sisaHutang > 0)
                                <span class="text-danger fw-medium">
                                    Rp {{ number_format($sisaHutang, 0, ',', '.') }}
                                </span>
                            @else
                                <span class="text-muted">-</span>
                            @endif
                        </td>
                        <td class="text-end">
                            <span class="text-success fw-medium">
                                Rp {{ number_format($member->savings_balance, 0, ',', '.') }}
                            </span>
                        </td>
                        <td class="text-center">
                            <a href="{{ route('members.show', $member) }}" class="btn btn-sm btn-outline-primary btn-action" title="Detail">
                                <i class="bi bi-eye"></i>
                            </a>
                            <a href="{{ route('members.edit', $member) }}?{{ http_build_query(request()->query()) }}" class="btn btn-sm btn-outline-warning btn-action" title="Edit">
                                <i class="bi bi-pencil"></i>
                            </a>
                            
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="text-center py-4 text-muted">
                            <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                            Tidak ada data anggota
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($members->hasPages())
    <div class="card-footer">
        {{ $members->links() }}
    </div>
    @endif
</div>
@endsection
