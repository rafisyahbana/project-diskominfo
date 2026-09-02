<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="description" content="Sistem Informasi Pelayanan Administrasi Kependudukan - Dinas Komunikasi dan Informatika">

        <title>{{ config('app.name', 'Diskominfo') }} — Login</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:300,400,500,600,700&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])

        <style>
            :root {
                --blue-primary: #1e3a5f;
                --blue-accent: #2c6fbb;
                --neutral-light: #f0f4f8;
            }

            body.login-page {
                font-family: 'Inter', system-ui, -apple-system, sans-serif;
                margin: 0;
                padding: 0;
                min-height: 100vh;
                background: var(--neutral-light);
                color: #1a1a2e;
            }

            /* ── Layout ── */
            .login-wrapper {
                display: flex;
                min-height: 100vh;
            }

            /* ── Left Panel: Branding ── */
            .login-brand {
                flex: 0 0 45%;
                background: linear-gradient(160deg, var(--blue-primary) 0%, var(--blue-accent) 100%);
                display: flex;
                flex-direction: column;
                justify-content: center;
                align-items: center;
                padding: 4rem 3rem;
                position: relative;
                overflow: hidden;
            }

            .login-brand::before {
                content: '';
                position: absolute;
                top: -30%;
                right: -20%;
                width: 500px;
                height: 500px;
                border-radius: 50%;
                background: rgba(255,255,255,0.04);
            }

            .login-brand::after {
                content: '';
                position: absolute;
                bottom: -20%;
                left: -15%;
                width: 400px;
                height: 400px;
                border-radius: 50%;
                background: rgba(255,255,255,0.03);
            }

            .brand-content {
                position: relative;
                z-index: 1;
                text-align: center;
                max-width: 380px;
            }

            .brand-icon {
                width: 80px;
                height: 80px;
                background: rgba(255,255,255,0.12);
                border-radius: 20px;
                display: flex;
                align-items: center;
                justify-content: center;
                margin: 0 auto 2rem;
                backdrop-filter: blur(10px);
                border: 1px solid rgba(255,255,255,0.15);
            }

            .brand-icon svg {
                width: 40px;
                height: 40px;
                fill: none;
                stroke: white;
                stroke-width: 1.5;
                stroke-linecap: round;
                stroke-linejoin: round;
            }

            .brand-title {
                font-size: 1.6rem;
                font-weight: 700;
                color: #ffffff;
                margin: 0 0 0.5rem;
                letter-spacing: -0.02em;
                line-height: 1.3;
            }

            .brand-subtitle {
                font-size: 0.95rem;
                font-weight: 400;
                color: rgba(255,255,255,0.7);
                margin: 0 0 3rem;
                line-height: 1.6;
            }

            .brand-features {
                list-style: none;
                padding: 0;
                margin: 0;
                text-align: left;
            }

            .brand-features li {
                display: flex;
                align-items: center;
                gap: 0.75rem;
                color: rgba(255,255,255,0.8);
                font-size: 0.875rem;
                padding: 0.6rem 0;
            }

            .brand-features li .feat-dot {
                width: 6px;
                height: 6px;
                border-radius: 50%;
                background: rgba(255,255,255,0.5);
                flex-shrink: 0;
            }

            /* ── Right Panel: Form ── */
            .login-form-panel {
                flex: 1;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 3rem;
            }

            .login-form-container {
                width: 100%;
                max-width: 420px;
            }

            .form-header {
                margin-bottom: 2.5rem;
            }

            .form-header h1 {
                font-size: 1.75rem;
                font-weight: 700;
                color: var(--blue-primary);
                margin: 0 0 0.5rem;
                letter-spacing: -0.02em;
            }

            .form-header p {
                font-size: 0.925rem;
                color: #6b7280;
                margin: 0;
                line-height: 1.5;
            }

            /* ── Form Elements ── */
            .form-group {
                margin-bottom: 1.5rem;
            }

            .form-group label {
                display: block;
                font-size: 0.8125rem;
                font-weight: 600;
                color: #374151;
                margin-bottom: 0.5rem;
                letter-spacing: 0.01em;
            }

            .form-group input[type="email"],
            .form-group input[type="password"] {
                width: 100%;
                padding: 0.8rem 1rem;
                font-size: 0.9375rem;
                font-family: inherit;
                background: #ffffff;
                border: 1.5px solid #d1d5db;
                border-radius: 10px;
                color: #1f2937;
                transition: border-color 0.2s ease, box-shadow 0.2s ease;
                outline: none;
                box-sizing: border-box;
            }

            .form-group input:focus {
                border-color: var(--blue-accent);
                box-shadow: 0 0 0 3px rgba(44, 111, 187, 0.12);
            }

            .form-group input::placeholder {
                color: #9ca3af;
            }

            .form-options {
                display: flex;
                align-items: center;
                justify-content: space-between;
                margin-bottom: 2rem;
            }

            .remember-check {
                display: flex;
                align-items: center;
                gap: 0.5rem;
                cursor: pointer;
            }

            .remember-check input[type="checkbox"] {
                width: 16px;
                height: 16px;
                accent-color: var(--blue-accent);
                border-radius: 4px;
                cursor: pointer;
            }

            .remember-check span {
                font-size: 0.8125rem;
                color: #6b7280;
            }

            .forgot-link {
                font-size: 0.8125rem;
                color: var(--blue-accent);
                text-decoration: none;
                font-weight: 500;
                transition: color 0.15s;
            }

            .forgot-link:hover {
                color: var(--blue-primary);
            }

            .btn-login {
                width: 100%;
                padding: 0.85rem 1.5rem;
                font-size: 0.9375rem;
                font-weight: 600;
                font-family: inherit;
                color: #ffffff;
                background: var(--blue-primary);
                border: none;
                border-radius: 10px;
                cursor: pointer;
                transition: background 0.2s ease, transform 0.1s ease, box-shadow 0.2s ease;
                letter-spacing: 0.01em;
            }

            .btn-login:hover {
                background: var(--blue-accent);
                box-shadow: 0 4px 16px rgba(44, 111, 187, 0.25);
            }

            .btn-login:active {
                transform: scale(0.985);
            }

            /* Error messages */
            .input-error-msg {
                font-size: 0.8rem;
                color: #dc2626;
                margin-top: 0.4rem;
            }

            /* Session status */
            .session-status {
                padding: 0.75rem 1rem;
                background: #ecfdf5;
                border: 1px solid #a7f3d0;
                border-radius: 8px;
                font-size: 0.875rem;
                color: #065f46;
                margin-bottom: 1.5rem;
            }

            .form-footer {
                text-align: center;
                margin-top: 2.5rem;
                font-size: 0.8rem;
                color: #9ca3af;
            }

            /* ── Responsive ── */
            @media (max-width: 900px) {
                .login-brand {
                    display: none;
                }
                .login-form-panel {
                    padding: 2rem 1.5rem;
                }
            }
        </style>
    </head>
    <body class="login-page">
        <div class="login-wrapper">
            {{-- Left Panel: Branding --}}
            <div class="login-brand">
                <div class="brand-content">
                    <div class="brand-icon">
                        <svg viewBox="0 0 24 24">
                            <path d="M12 2L2 7l10 5 10-5-10-5z"/>
                            <path d="M2 17l10 5 10-5"/>
                            <path d="M2 12l10 5 10-5"/>
                        </svg>
                    </div>
                    <h2 class="brand-title">DISKOMINFO</h2>
                    <p class="brand-subtitle">
                        Sistem Informasi Pelayanan<br>
                        Administrasi Kependudukan
                    </p>
                    <ul class="brand-features">
                        <li><span class="feat-dot"></span> Verifikasi data kependudukan</li>
                        <li><span class="feat-dot"></span> Proses permohonan surat</li>
                        <li><span class="feat-dot"></span> Integrasi OCR dokumen</li>
                        <li><span class="feat-dot"></span> Notifikasi real-time</li>
                    </ul>
                </div>
            </div>

            {{-- Right Panel: Form --}}
            <div class="login-form-panel">
                <div class="login-form-container">
                    {{ $slot }}
                    <div class="form-footer">
                        &copy; {{ date('Y') }} Dinas Komunikasi dan Informatika
                    </div>
                </div>
            </div>
        </div>
    </body>
</html>
