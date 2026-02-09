<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Koperasi AP')</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <!-- SweetAlert2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
    <!-- Animate.css for SweetAlert animations -->
    <link href="https://cdn.jsdelivr.net/npm/animate.css@4.1.1/animate.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <style>
        :root {
            --sidebar-width: 260px;
        }
        
        body {
            background-color: #f4f6f9;
            min-height: 100vh;
        }
        
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            width: var(--sidebar-width);
            height: 100vh;
            background: linear-gradient(180deg, #2c3e50 0%, #1a252f 100%);
            padding-top: 0;
            z-index: 1000;
            display: flex;
            flex-direction: column;
        }
        
        .sidebar-content {
            flex: 1;
            overflow-y: auto;
        }

        .sidebar-footer {
            padding: 1rem;
            background: rgba(0,0,0,0.2);
            border-top: 1px solid rgba(255,255,255,0.1);
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #3498db;
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 12px;
            font-weight: bold;
            flex-shrink: 0;
        }

        /* Sidebar Footer Dropup Styles */
        .sidebar-footer .dropdown-toggle-custom {
            border: none;
            background: transparent;
            outline: none;
            box-shadow: none;
            border-radius: 0.5rem;
            transition: background 0.2s;
            padding: 0.5rem !important;
        }
        
        .sidebar-footer .dropdown-toggle-custom:hover,
        .sidebar-footer .show > .dropdown-toggle-custom {
            background: rgba(255,255,255,0.05);
        }
        
        .sidebar-footer .dropdown-toggle-custom::after {
            display: none;
        }
        
        .sidebar-footer .dropdown-menu {
            background-color: #1a252f;
            border: 1px solid rgba(255,255,255,0.1);
            padding: 0.5rem;
            margin-bottom: 0.5rem !important;
        }
        
        .sidebar-footer .dropdown-item {
            color: rgba(255,255,255,0.8);
            border-radius: 0.375rem;
            padding: 0.5rem 1rem;
            margin-bottom: 2px;
        }
        
        .sidebar-footer .dropdown-item:hover {
            color: #fff;
            background: rgba(255,255,255,0.1);
        }
        
        .sidebar-footer .dropdown-item.text-danger {
            color: #ff6b6b !important;
        }
        
        .sidebar-footer .dropdown-item.text-danger:hover {
            background: rgba(220, 53, 69, 0.15);
        }
        
        .sidebar-brand {
            padding: 1.25rem 1.5rem;
            background: rgba(0,0,0,0.15);
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        
        .sidebar-brand h4 {
            color: #fff;
            margin: 0;
            font-weight: 600;
        }
        
        .sidebar-brand small {
            color: rgba(255,255,255,0.6);
            font-size: 0.75rem;
        }
        
        .sidebar .nav-link {
            color: rgba(255,255,255,0.7);
            padding: 0.875rem 1.5rem;
            border-left: 3px solid transparent;
            transition: all 0.2s;
        }
        
        .sidebar .nav-link:hover {
            color: #fff;
            background: rgba(255,255,255,0.05);
            border-left-color: rgba(255,255,255,0.3);
        }
        
        .sidebar .nav-link.active {
            color: #fff;
            background: rgba(255,255,255,0.1);
            border-left-color: #3498db;
        }
        
        .sidebar .nav-link i {
            width: 24px;
            margin-right: 0.75rem;
        }
        
        .sidebar-heading {
            color: rgba(255,255,255,0.4);
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            padding: 1.25rem 1.5rem 0.5rem;
        }
        
        .main-content {
            margin-left: var(--sidebar-width);
            min-height: 100vh;
        }
        
        .top-navbar {
            background: #fff;
            box-shadow: 0 2px 4px rgba(0,0,0,0.08);
            padding: 0.75rem 1.5rem;
        }
        
        .page-content {
            padding: 1.5rem;
        }
        
        .card {
            border: none;
            box-shadow: 0 0 10px rgba(0,0,0,0.05);
            border-radius: 0.5rem;
        }
        
        .card-header {
            background: #fff;
            border-bottom: 1px solid #eee;
            font-weight: 600;
        }
        
        .stat-card {
            border-radius: 0.5rem;
            padding: 1.25rem;
            color: #fff;
        }
        
        .stat-card.bg-success { background: linear-gradient(135deg, #28a745 0%, #20c997 100%); }
        .stat-card.bg-danger { background: linear-gradient(135deg, #dc3545 0%, #fd7e14 100%); }
        .stat-card.bg-primary { background: linear-gradient(135deg, #007bff 0%, #6610f2 100%); }
        .stat-card.bg-info { background: linear-gradient(135deg, #17a2b8 0%, #20c997 100%); }
        
        .stat-card .stat-icon {
            font-size: 2.5rem;
            opacity: 0.3;
        }
        
        .stat-card .stat-value {
            font-size: 1.75rem;
            font-weight: 700;
        }
        
        .stat-card .stat-label {
            font-size: 0.8rem;
            opacity: 0.8;
        }
        
        .badge-dept {
            font-weight: 500;
        }
        
        .table th {
            font-weight: 600;
            color: #555;
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }
        
        .btn-action {
            padding: 0.25rem 0.5rem;
            font-size: 0.8rem;
        }
        
        .member-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-size: 2rem;
            font-weight: 600;
        }
        
        .input-currency {
            text-align: right;
        }
        
        .form-label-required::after {
            content: '*';
            color: #dc3545;
            margin-left: 4px;
        }
        
        /* Fix pagination arrow size */
        .pagination {
            margin-bottom: 0;
        }
        
        .pagination .page-link svg {
            display: none;
        }
        
        .pagination .page-link {
            padding: 0.375rem 0.75rem;
            font-size: 0.875rem;
        }
        
        .badge-notification {
            background-color: #ff3b30;
            color: white;
            font-size: 0.75rem;
            padding: 2px 8px;
            border-radius: 10px;
            margin-left: auto;
            font-weight: 700;
            min-width: 20px;
            text-align: center;
        }
        
        @media (max-width: 991.98px) {
            .sidebar {
                margin-left: calc(-1 * var(--sidebar-width));
                transition: margin 0.25s;
            }
            
            .sidebar.show {
                margin-left: 0;
            }
            
            .main-content {
                margin-left: 0;
            }
        }
    </style>
    @stack('styles')
</head>
<body>
    <!-- Sidebar -->
    <nav class="sidebar">
        <div class="sidebar-content">
            <div class="sidebar-brand">
                <h4><i class="bi bi-building"></i> Koperasi</h4>
                <small>Koperasi AP</small>
            </div>
            
            <div class="sidebar-heading">Menu Utama</div>
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}">
                        <i class="bi bi-speedometer2"></i> Dashboard
                    </a>
                </li>

                {{-- ADMIN MENUS (Only Admin can access this system) --}}
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('members.*') ? 'active' : '' }}" href="{{ route('members.index') }}">
                        <i class="bi bi-people"></i> Anggota
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('loans.*') && !request()->routeIs('loans.show') ? 'active' : '' }} d-flex align-items-center" href="{{ route('loans.index') }}">
                        <i class="bi bi-cash-stack"></i> 
                        <span class="flex-grow-1">Pinjaman</span>
                        @if(isset($globalPendingLoans) && $globalPendingLoans > 0)
                            <span class="badge-notification">{{ $globalPendingLoans }}</span>
                        @endif
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('withdrawals.*') ? 'active' : '' }} d-flex align-items-center" href="{{ route('withdrawals.index') }}">
                        <i class="bi bi-wallet2"></i> 
                        <span class="flex-grow-1">Penarikan Saldo</span>
                        @if(isset($globalPendingWithdrawals) && $globalPendingWithdrawals > 0)
                            <span class="badge-notification">{{ $globalPendingWithdrawals }}</span>
                        @endif
                    </a>
                </li>
            </ul>
            
            <div class="sidebar-heading">Transaksi</div>
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('transactions.bulk*') ? 'active' : '' }}" href="{{ route('transactions.bulk.create') }}">
                        <i class="bi bi-receipt"></i> Input Massal/Gajian
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('imports.*') ? 'active' : '' }}" href="{{ route('imports.index') }}">
                        <i class="bi bi-file-earmark-excel"></i> Import Excel
                    </a>
                </li>
            </ul>
            
            <div class="sidebar-heading">Laporan</div>
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('reports.monthly') ? 'active' : '' }}" href="{{ route('reports.monthly') }}">
                        <i class="bi bi-table"></i> Laporan Transaksi
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('reports.shu') ? 'active' : '' }}" href="{{ route('reports.shu') }}">
                        <i class="bi bi-gift"></i> Laporan SHU
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('reports.index') ? 'active' : '' }}" href="{{ route('reports.index') }}">
                        <i class="bi bi-speedometer"></i> Ringkasan
                    </a>
                </li>
            </ul>
            
            <div class="sidebar-heading">Pengaturan</div>
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('settings.*') ? 'active' : '' }}" href="{{ route('settings.index') }}">
                        <i class="bi bi-gear"></i> Pengaturan Bunga
                    </a>
                </li>
            </ul>
        </div>

        <div class="sidebar-footer">
            <div class="dropup w-100">
                <button type="button" class="btn w-100 d-flex align-items-center dropdown-toggle-custom" data-bs-toggle="dropdown" aria-expanded="false">
                    <div class="user-avatar text-uppercase">
                        {{ substr(Auth::user()->name ?? 'A', 0, 2) }}
                    </div>
                    <div class="text-start flex-grow-1 overflow-hidden">
                        <div class="fw-semibold text-white text-truncate">{{ Auth::user()->name ?? 'Admin' }}</div>
                        <div class="small text-white opacity-75">{{ Auth::user()->role ?? 'Admin' }}</div>
                    </div>
                    <i class="bi bi-chevron-up text-white opacity-75 ms-2"></i>
                </button>
                <ul class="dropdown-menu w-100 shadow-lg">
                    <li>
                        <a class="dropdown-item" href="{{ route('profile.edit') }}">
                            <i class="bi bi-person-gear me-2"></i> Edit Profil
                        </a>
                    </li>
                    <li>
                        <form action="{{ route('logout') }}" method="POST">
                            @csrf
                            <button type="submit" class="dropdown-item text-danger">
                                <i class="bi bi-box-arrow-right me-2"></i> Logout
                            </button>
                        </form>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Navbar -->
        <nav class="top-navbar d-flex justify-content-between align-items-center">
            <div>
                <button class="btn btn-link d-lg-none p-0 me-3" id="sidebarToggle">
                    <i class="bi bi-list fs-4"></i>
                </button>
                <span class="text-muted">@yield('breadcrumb', 'Dashboard')</span>
            </div>
            <div class="d-flex align-items-center">
                <span class="me-3 text-muted small">{{ now()->translatedFormat('l, d F Y') }}</span>
            </div>
        </nav>
        
        <!-- Page Content -->
        <div class="page-content">
            @yield('content')
        </div>
    </div>

    <!-- jQuery (Required for Select2) -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <!-- SweetAlert2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <script>
        // Sidebar toggle for mobile
        document.getElementById('sidebarToggle')?.addEventListener('click', function() {
            document.querySelector('.sidebar').classList.toggle('show');
        });

        // Format currency input
        function formatCurrency(input) {
            let value = input.value.replace(/\D/g, '');
            value = new Intl.NumberFormat('id-ID').format(value);
            input.value = value;
        }
        
        // Parse currency to number
        function parseCurrency(value) {
            return parseInt(value.replace(/\D/g, '')) || 0;
        }

        // ============================================
        // GLOBAL SWEETALERT2 HANDLERS
        // ============================================

        // 1. Flash Message Handler (Success/Error dari Controller)
        @if(session('success'))
            Swal.fire({
                icon: 'success',
                title: 'Berhasil!',
                text: '{{ session('success') }}',
                confirmButtonText: 'OK',
                confirmButtonColor: '#0d6efd',
                showClass: {
                    popup: 'animate__animated animate__fadeInDown'
                },
                hideClass: {
                    popup: 'animate__animated animate__fadeOutUp'
                }
            });
        @endif

        @if(session('error'))
            Swal.fire({
                icon: 'error',
                title: 'Gagal!',
                text: '{{ session('error') }}',
                confirmButtonText: 'OK',
                confirmButtonColor: '#dc3545',
                showClass: {
                    popup: 'animate__animated animate__fadeInDown'
                },
                hideClass: {
                    popup: 'animate__animated animate__fadeOutUp'
                }
            });
        @endif

        // 2. Global Delete Confirmation Handler
        // Semua form dengan class .delete-form akan otomatis dapat konfirmasi
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.delete-form').forEach(function(form) {
                form.addEventListener('submit', function(e) {
                    e.preventDefault();
                    
                    const deleteForm = this;
                    const itemName = this.dataset.name || 'data ini';
                    
                    Swal.fire({
                        title: 'Apakah Anda Yakin?',
                        text: `Data ${itemName} akan dihapus dan tidak dapat dikembalikan!`,
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#dc3545',
                        cancelButtonColor: '#6c757d',
                        confirmButtonText: 'Ya, Hapus!',
                        cancelButtonText: 'Batal',
                        reverseButtons: true
                    }).then((result) => {
                        if (result.isConfirmed) {
                            deleteForm.submit();
                        }
                    });
                });
            });
        });

        // 3. Helper function untuk konfirmasi custom
        function confirmAction(options) {
            return Swal.fire({
                title: options.title || 'Konfirmasi',
                text: options.text || 'Apakah Anda yakin?',
                icon: options.icon || 'question',
                showCancelButton: true,
                confirmButtonColor: options.confirmColor || '#3085d6',
                cancelButtonColor: '#6c757d',
                confirmButtonText: options.confirmText || 'Ya',
                cancelButtonText: options.cancelText || 'Batal',
                reverseButtons: true
            });
        }
    </script>
    
    @stack('scripts')
</body>
</html>
