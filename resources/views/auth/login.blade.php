@extends('layouts.guest')

@section('content')
    <style>
        .field { margin-bottom: 16px; }
        .field label { display: block; font-size: 12px; font-weight: 600; color: var(--muted); margin-bottom: 6px; }
        .field input {
            width: 100%; box-sizing: border-box; padding: 10px 12px; border: 1px solid var(--line);
            border-radius: 9px; font-size: 14px;
        }
        .btn-primary {
            width: 100%; padding: 11px; background: var(--pine); color: #fff; border: none;
            border-radius: 9px; font-weight: 700; font-size: 14px; cursor: pointer;
        }
        .btn-primary:hover { background: var(--pine-deep); }
        .error-text { color: var(--brick); font-size: 12px; margin-top: 4px; }
    </style>

    @if ($errors->any())
        <div class="error-text" style="margin-bottom: 12px;">
            {{ $errors->first() }}
        </div>
    @endif

    <form method="POST" action="{{ route('login.store') }}">
        @csrf
        <div class="field">
            <label for="email">Email</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus>
        </div>
        <div class="field">
            <label for="password">Kata Sandi</label>
            <input id="password" type="password" name="password" required>
        </div>
        <button type="submit" class="btn-primary">Masuk</button>
    </form>
@endsection
