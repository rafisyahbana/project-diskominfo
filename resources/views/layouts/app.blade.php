<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Diskominfo') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:300,400,500,600,700&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])

        <style>
            :root {
                --blue-primary: #1e3a5f;
                --blue-accent: #2c6fbb;
                --neutral-bg: #f0f4f8;
                --white: #ffffff;
            }

            * { box-sizing: border-box; }

            body.app-body {
                font-family: 'Inter', system-ui, -apple-system, sans-serif;
                margin: 0;
                background: var(--neutral-bg);
                color: #1f2937;
                min-height: 100vh;
            }

            /* ── Sidebar ── */
            .app-sidebar {
                position: fixed;
                top: 0;
                left: 0;
                width: 260px;
                height: 100vh;
                background: var(--blue-primary);
                display: flex;
                flex-direction: column;
                z-index: 40;
                transition: transform 0.25s ease;
            }

            .sidebar-brand {
                padding: 1.75rem 1.5rem;
                border-bottom: 1px solid rgba(255,255,255,0.08);
            }

            .sidebar-brand-link {
                display: flex;
                align-items: center;
                gap: 0.75rem;
                text-decoration: none;
            }

            .sidebar-brand-icon {
                width: 38px;
                height: 38px;
                background: rgba(255,255,255,0.12);
                border-radius: 10px;
                display: flex;
                align-items: center;
                justify-content: center;
                flex-shrink: 0;
            }

            .sidebar-brand-icon svg {
                width: 20px;
                height: 20px;
                fill: none;
                stroke: white;
                stroke-width: 1.5;
                stroke-linecap: round;
                stroke-linejoin: round;
            }

            .sidebar-brand-text {
                font-size: 1rem;
                font-weight: 700;
                color: #ffffff;
                letter-spacing: 0.02em;
            }

            .sidebar-brand-sub {
                font-size: 0.7rem;
                color: rgba(255,255,255,0.5);
                font-weight: 400;
                margin-top: 1px;
            }

            /* Nav Items */
            .sidebar-nav {
                padding: 1.25rem 0.75rem;
                flex: 1;
            }

            .sidebar-nav-label {
                font-size: 0.65rem;
                font-weight: 600;
                text-transform: uppercase;
                letter-spacing: 0.08em;
                color: rgba(255,255,255,0.35);
                padding: 0 0.75rem;
                margin-bottom: 0.6rem;
            }

            .sidebar-nav-item {
                display: flex;
                align-items: center;
                gap: 0.75rem;
                padding: 0.7rem 0.85rem;
                border-radius: 8px;
                font-size: 0.875rem;
                font-weight: 500;
                color: rgba(255,255,255,0.65);
                text-decoration: none;
                transition: all 0.15s ease;
                margin-bottom: 2px;
            }

            .sidebar-nav-item:hover {
                background: rgba(255,255,255,0.08);
                color: #ffffff;
            }

            .sidebar-nav-item.active {
                background: var(--blue-accent);
                color: #ffffff;
            }

            .sidebar-nav-item svg {
                width: 18px;
                height: 18px;
                stroke: currentColor;
                fill: none;
                stroke-width: 1.5;
                stroke-linecap: round;
                stroke-linejoin: round;
                flex-shrink: 0;
            }

            /* Sidebar Footer */
            .sidebar-footer {
                padding: 1rem 0.75rem;
                border-top: 1px solid rgba(255,255,255,0.08);
            }

            .sidebar-user {
                display: flex;
                align-items: center;
                gap: 0.75rem;
                padding: 0.5rem 0.75rem;
            }

            .sidebar-user-avatar {
                width: 36px;
                height: 36px;
                border-radius: 50%;
                background: var(--blue-accent);
                display: flex;
                align-items: center;
                justify-content: center;
                color: white;
                font-size: 0.8rem;
                font-weight: 600;
                flex-shrink: 0;
            }

            .sidebar-user-info {
                flex: 1;
                min-width: 0;
            }

            .sidebar-user-name {
                font-size: 0.8125rem;
                font-weight: 600;
                color: #ffffff;
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
            }

            .sidebar-user-role {
                font-size: 0.7rem;
                color: rgba(255,255,255,0.45);
            }

            .sidebar-logout {
                display: inline-flex;
                align-items: center;
                gap: 0.5rem;
                margin-top: 0.75rem;
                padding: 0.5rem 0.75rem;
                border-radius: 6px;
                background: none;
                border: 1px solid rgba(255,255,255,0.12);
                color: rgba(255,255,255,0.6);
                font-size: 0.8rem;
                font-family: inherit;
                cursor: pointer;
                transition: all 0.15s ease;
                width: 100%;
                text-decoration: none;
                justify-content: center;
            }

            .sidebar-logout:hover {
                background: rgba(255,255,255,0.08);
                color: #ffffff;
                border-color: rgba(255,255,255,0.25);
            }

            /* ── Main Content ── */
            .app-main {
                margin-left: 260px;
                min-height: 100vh;
            }

            /* Top Bar */
            .app-topbar {
                background: var(--white);
                border-bottom: 1px solid #e5e7eb;
                padding: 0 2rem;
                height: 64px;
                display: flex;
                align-items: center;
                justify-content: space-between;
                position: sticky;
                top: 0;
                z-index: 30;
            }

            .topbar-title {
                font-size: 1.125rem;
                font-weight: 600;
                color: var(--blue-primary);
            }

            .topbar-right {
                display: flex;
                align-items: center;
                gap: 1rem;
            }

            .topbar-date {
                font-size: 0.8rem;
                color: #9ca3af;
            }

            /* Mobile hamburger */
            .mobile-toggle {
                display: none;
                padding: 0.5rem;
                border: none;
                background: none;
                color: var(--blue-primary);
                cursor: pointer;
            }

            .mobile-toggle svg {
                width: 24px;
                height: 24px;
                stroke: currentColor;
                fill: none;
                stroke-width: 2;
                stroke-linecap: round;
                stroke-linejoin: round;
            }

            /* Content area */
            .app-content {
                padding: 2rem;
            }

            /* ── Card Component ── */
            .dkm-card {
                background: var(--white);
                border-radius: 12px;
                border: 1px solid #e5e7eb;
                overflow: hidden;
            }

            .dkm-card-header {
                padding: 1.25rem 1.5rem;
                border-bottom: 1px solid #f3f4f6;
                display: flex;
                align-items: center;
                justify-content: space-between;
            }

            .dkm-card-header h3 {
                font-size: 1rem;
                font-weight: 600;
                color: var(--blue-primary);
                margin: 0;
            }

            .dkm-card-body {
                padding: 1.5rem;
            }

            /* ── Table ── */
            .dkm-table {
                width: 100%;
                border-collapse: collapse;
            }

            .dkm-table thead th {
                padding: 0.75rem 1rem;
                text-align: left;
                font-size: 0.75rem;
                font-weight: 600;
                text-transform: uppercase;
                letter-spacing: 0.04em;
                color: #6b7280;
                background: var(--neutral-bg);
                border-bottom: 1px solid #e5e7eb;
            }

            .dkm-table tbody td {
                padding: 0.85rem 1rem;
                font-size: 0.875rem;
                color: #374151;
                border-bottom: 1px solid #f3f4f6;
            }

            .dkm-table tbody tr:last-child td {
                border-bottom: none;
            }

            .dkm-table tbody tr:hover {
                background: #f8fafc;
            }

            /* ── Badge ── */
            .badge {
                display: inline-flex;
                align-items: center;
                padding: 0.2rem 0.65rem;
                border-radius: 999px;
                font-size: 0.75rem;
                font-weight: 600;
                white-space: nowrap;
            }

            .badge-blue { background: #dbeafe; color: #1e40af; }
            .badge-yellow { background: #fef3c7; color: #92400e; }
            .badge-green { background: #d1fae5; color: #065f46; }
            .badge-red { background: #fee2e2; color: #991b1b; }

            /* ── Tab Filters ── */
            .tab-filters {
                display: flex;
                gap: 0.375rem;
                margin-bottom: 1.5rem;
            }

            .tab-filter {
                padding: 0.55rem 1.1rem;
                border-radius: 8px;
                font-size: 0.8125rem;
                font-weight: 600;
                text-decoration: none;
                transition: all 0.15s ease;
                border: 1px solid transparent;
                color: #6b7280;
                background: var(--white);
                border-color: #e5e7eb;
            }

            .tab-filter:hover {
                opacity: 0.85;
            }

            .tab-filter-blue {
                background: #2563eb;
                color: white;
                border-color: #2563eb;
            }
            .tab-filter-blue:not(.active) {
                background: #dbeafe;
                color: #1e40af;
                border-color: #bfdbfe;
            }
            .tab-filter-blue.active {
                background: #2563eb;
                color: white;
                box-shadow: 0 2px 8px rgba(37, 99, 235, 0.3);
            }

            .tab-filter-yellow {
                background: #d97706;
                color: white;
                border-color: #d97706;
            }
            .tab-filter-yellow:not(.active) {
                background: #fef3c7;
                color: #92400e;
                border-color: #fde68a;
            }
            .tab-filter-yellow.active {
                background: #d97706;
                color: white;
                box-shadow: 0 2px 8px rgba(217, 119, 6, 0.3);
            }

            .tab-filter-green {
                background: #059669;
                color: white;
                border-color: #059669;
            }
            .tab-filter-green:not(.active) {
                background: #d1fae5;
                color: #065f46;
                border-color: #a7f3d0;
            }
            .tab-filter-green.active {
                background: #059669;
                color: white;
                box-shadow: 0 2px 8px rgba(5, 150, 105, 0.3);
            }

            /* ── Buttons ── */
            .btn {
                display: inline-flex;
                align-items: center;
                gap: 0.4rem;
                padding: 0.6rem 1.2rem;
                border-radius: 8px;
                font-size: 0.875rem;
                font-weight: 600;
                font-family: inherit;
                text-decoration: none;
                border: none;
                cursor: pointer;
                transition: all 0.15s ease;
            }

            .btn-primary {
                background: var(--blue-primary);
                color: white;
            }

            .btn-primary:hover {
                background: var(--blue-accent);
                box-shadow: 0 4px 12px rgba(44, 111, 187, 0.2);
            }

            .btn-success {
                background: #059669;
                color: white;
            }

            .btn-success:hover {
                background: #047857;
            }

            .btn-danger {
                background: #dc2626;
                color: white;
            }

            .btn-danger:hover {
                background: #b91c1c;
            }

            .btn-outline {
                background: transparent;
                border: 1.5px solid #d1d5db;
                color: #374151;
            }

            .btn-outline:hover {
                border-color: var(--blue-accent);
                color: var(--blue-accent);
            }

            /* ── Link ── */
            .link-primary {
                color: var(--blue-accent);
                text-decoration: none;
                font-weight: 500;
            }

            .link-primary:hover {
                color: var(--blue-primary);
                text-decoration: underline;
            }

            /* ── Alert ── */
            .alert {
                padding: 0.85rem 1.25rem;
                border-radius: 10px;
                font-size: 0.875rem;
                display: flex;
                align-items: flex-start;
                gap: 0.5rem;
                margin-bottom: 1.25rem;
            }

            .alert-success {
                background: #ecfdf5;
                border: 1px solid #a7f3d0;
                color: #065f46;
            }

            .alert-error {
                background: #fef2f2;
                border: 1px solid #fecaca;
                color: #991b1b;
            }

            /* ── Form ── */
            .form-label {
                display: block;
                font-size: 0.8125rem;
                font-weight: 600;
                color: #374151;
                margin-bottom: 0.4rem;
            }

            .form-input,
            .form-textarea {
                width: 100%;
                padding: 0.65rem 0.85rem;
                font-size: 0.875rem;
                font-family: inherit;
                border: 1.5px solid #d1d5db;
                border-radius: 8px;
                background: var(--white);
                color: #1f2937;
                outline: none;
                transition: border-color 0.15s ease, box-shadow 0.15s ease;
            }

            .form-input:focus,
            .form-textarea:focus {
                border-color: var(--blue-accent);
                box-shadow: 0 0 0 3px rgba(44, 111, 187, 0.1);
            }

            /* ── Data Detail ── */
            .detail-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
                gap: 1.25rem;
            }

            .detail-item label {
                display: block;
                font-size: 0.75rem;
                font-weight: 500;
                color: #9ca3af;
                margin-bottom: 0.3rem;
                text-transform: uppercase;
                letter-spacing: 0.03em;
            }

            .detail-item p {
                margin: 0;
                font-size: 0.9375rem;
                font-weight: 500;
                color: #1f2937;
            }

            /* ── Overlay for mobile ── */
            .sidebar-overlay {
                display: none;
                position: fixed;
                inset: 0;
                background: rgba(0,0,0,0.4);
                z-index: 35;
            }

            /* ── Responsive ── */
            @media (max-width: 1024px) {
                .app-sidebar {
                    transform: translateX(-100%);
                }
                .app-sidebar.open {
                    transform: translateX(0);
                }
                .sidebar-overlay.open {
                    display: block;
                }
                .app-main {
                    margin-left: 0;
                }
                .mobile-toggle {
                    display: block;
                }
            }
        </style>
    </head>
    <body class="app-body" x-data="{ sidebarOpen: false }">

        {{-- Sidebar Overlay (mobile) --}}
        <div class="sidebar-overlay" :class="{ 'open': sidebarOpen }" @click="sidebarOpen = false"></div>

        {{-- Sidebar --}}
        <aside class="app-sidebar" :class="{ 'open': sidebarOpen }">
            {{-- Brand --}}
            <div class="sidebar-brand">
                <a href="{{ route('dashboard.permohonan.index') }}" class="sidebar-brand-link">
                    <div class="sidebar-brand-icon">
                        <svg viewBox="0 0 24 24">
                            <path d="M12 2L2 7l10 5 10-5-10-5z"/>
                            <path d="M2 17l10 5 10-5"/>
                            <path d="M2 12l10 5 10-5"/>
                        </svg>
                    </div>
                    <div>
                        <div class="sidebar-brand-text">DISKOMINFO</div>
                        <div class="sidebar-brand-sub">Sistem Pelayanan</div>
                    </div>
                </a>
            </div>

            {{-- Navigation --}}
            <nav class="sidebar-nav">
                <div class="sidebar-nav-label">Menu Utama</div>

                <a href="{{ route('dashboard.index') }}"
                   class="sidebar-nav-item {{ request()->routeIs('dashboard.index') ? 'active' : '' }}">
                    <svg viewBox="0 0 24 24">
                        <path d="M3 13h8V3H3v10zm0 8h8v-6H3v6zm10 0h8V11h-8v10zm0-18v6h8V3h-8z"/>
                    </svg>
                    Dashboard & Grafik
                </a>

                <a href="{{ route('dashboard.permohonan.index') }}"
                   class="sidebar-nav-item {{ request()->routeIs('dashboard.permohonan.*') ? 'active' : '' }}">
                    <svg viewBox="0 0 24 24">
                        <path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2"/>
                        <rect x="9" y="3" width="6" height="4" rx="1"/>
                        <path d="M9 14l2 2 4-4"/>
                    </svg>
                    Daftar Permohonan
                </a>
            </nav>

            {{-- User --}}
            <div class="sidebar-footer">
                <div class="sidebar-user">
                    <div class="sidebar-user-avatar">
                        {{ strtoupper(substr(Auth::guard('petugas')->user()->nama, 0, 2)) }}
                    </div>
                    <div class="sidebar-user-info">
                        <div class="sidebar-user-name">{{ Auth::guard('petugas')->user()->nama }}</div>
                        <div class="sidebar-user-role">{{ ucfirst(Auth::guard('petugas')->user()->role ?? 'Petugas') }}</div>
                    </div>
                </div>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="sidebar-logout">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/>
                            <polyline points="16 17 21 12 16 7"/>
                            <line x1="21" y1="12" x2="9" y2="12"/>
                        </svg>
                        Keluar
                    </button>
                </form>
            </div>
        </aside>

        {{-- Main --}}
        <div class="app-main">
            {{-- Top Bar --}}
            <div class="app-topbar">
                <div style="display: flex; align-items: center; gap: 0.75rem;">
                    <button class="mobile-toggle" @click="sidebarOpen = !sidebarOpen">
                        <svg viewBox="0 0 24 24">
                            <path d="M4 6h16M4 12h16M4 18h16"/>
                        </svg>
                    </button>
                    <span class="topbar-title">
                        @isset($header)
                            {{ $header }}
                        @else
                            Dashboard
                        @endisset
                    </span>
                </div>
                <div class="topbar-right">
                    <span class="topbar-date">{{ now()->locale('id')->isoFormat('dddd, D MMMM Y') }}</span>
                </div>
            </div>

            {{-- Page Content --}}
            <main class="app-content">
                {{ $slot }}
            </main>
        </div>

        {{-- Anti-BFCache: Force reload on browser Back button if logged out --}}
        <script>
            window.addEventListener('pageshow', function(event) {
                if (event.persisted || (window.performance && window.performance.getEntriesByType && window.performance.getEntriesByType("navigation")[0] && window.performance.getEntriesByType("navigation")[0].type === "back_forward")) {
                    window.location.reload();
                }
            });
        </script>
    </body>
</html>
