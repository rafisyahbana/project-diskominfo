<x-guest-layout>
    {{-- Session Status --}}
    @if (session('status'))
        <div class="session-status">
            {{ session('status') }}
        </div>
    @endif

    <div class="form-header">
        <h1>Masuk</h1>
        <p>Silakan masuk dengan akun petugas Anda untuk mengakses dashboard.</p>
    </div>

    <form method="POST" action="{{ route('login') }}">
        @csrf

        {{-- Email --}}
        <div class="form-group">
            <label for="email">Email</label>
            <input
                id="email"
                type="email"
                name="email"
                value="{{ old('email') }}"
                placeholder="nama@diskominfo.go.id"
                required
                autofocus
                autocomplete="username"
            >
            @if ($errors->get('email'))
                <div class="input-error-msg">
                    @foreach ($errors->get('email') as $message)
                        <p>{{ $message }}</p>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Password --}}
        <div class="form-group">
            <label for="password">Password</label>
            <input
                id="password"
                type="password"
                name="password"
                placeholder="••••••••"
                required
                autocomplete="current-password"
            >
            @if ($errors->get('password'))
                <div class="input-error-msg">
                    @foreach ($errors->get('password') as $message)
                        <p>{{ $message }}</p>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Options Row --}}
        <div class="form-options">
            <label for="remember_me" class="remember-check">
                <input id="remember_me" type="checkbox" name="remember">
                <span>Ingat saya</span>
            </label>

            @if (Route::has('password.request'))
                <a class="forgot-link" href="{{ route('password.request') }}">
                    Lupa password?
                </a>
            @endif
        </div>

        {{-- Submit --}}
        <button type="submit" class="btn-login">
            Masuk
        </button>
    </form>
</x-guest-layout>
