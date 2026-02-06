@extends('layouts.app')

@section('title', 'Pengaturan Profil - Koperasi')
@section('breadcrumb', 'Pengaturan Profil')

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <i class="bi bi-person-gear me-2"></i>Pengaturan Profil
            </div>
            <div class="card-body">
                @if(session('success'))
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="bi bi-check-circle me-2"></i>{{ session('success') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                @endif

                <form action="{{ route('profile.update') }}" method="POST">
                    @csrf
                    @method('PUT')

                    <!-- Info Section -->
                    <div class="mb-4 p-3 bg-light rounded">
                        <div class="row">
                            <div class="col-md-6">
                                <small class="text-muted">Role</small>
                                <p class="mb-0 fw-bold">
                                    @if($user->isAdmin())
                                        <span class="badge bg-danger">Administrator</span>
                                    @else
                                        <span class="badge bg-primary">Member</span>
                                    @endif
                                </p>
                            </div>
                            @if($user->member)
                            <div class="col-md-6">
                                <small class="text-muted">NIK Anggota</small>
                                <p class="mb-0"><code>{{ $user->member->nik }}</code></p>
                            </div>
                            @endif
                        </div>
                    </div>

                    <!-- Basic Info -->
                    <h6 class="text-muted mb-3">Informasi Dasar</h6>
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label form-label-required">Nama</label>
                            <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" 
                                   value="{{ old('name', $user->name) }}" required>
                            @error('name')
                            <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label form-label-required">Email</label>
                            <input type="email" name="email" class="form-control @error('email') is-invalid @enderror" 
                                   value="{{ old('email', $user->email) }}" required>
                            @error('email')
                            <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            @if($user->member)
                            <small class="text-muted">Email akan disinkronkan ke data anggota koperasi</small>
                            @endif
                        </div>
                    </div>

                    <hr class="my-4">

                    <!-- Password Change -->
                    <h6 class="text-muted mb-3">Ganti Password</h6>
                    <p class="text-muted small mb-3">Kosongkan jika tidak ingin mengubah password</p>
                    
                    <div class="row g-3 mb-4">
                        <div class="col-md-4">
                            <label class="form-label">Password Saat Ini</label>
                            <input type="password" name="current_password" class="form-control @error('current_password') is-invalid @enderror">
                            @error('current_password')
                            <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Password Baru</label>
                            <input type="password" name="password" class="form-control @error('password') is-invalid @enderror" minlength="8">
                            @error('password')
                            <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Konfirmasi Password</label>
                            <input type="password" name="password_confirmation" class="form-control">
                        </div>
                    </div>

                    <div class="text-end">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-lg me-1"></i> Simpan Perubahan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
