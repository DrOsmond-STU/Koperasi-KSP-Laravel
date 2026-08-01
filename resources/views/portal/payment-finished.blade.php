@extends('layouts.portal')

@section('title', 'Pembayaran Selesai')

@section('content')
    <style>
        .notice { background: var(--leaf); color: var(--pine-bright); border-radius: 14px; padding: 24px; max-width: 480px; text-align: center; }
        .notice h2 { margin-top: 0; }
        .link-back { color: var(--pine-bright); font-size: 13px; text-decoration: none; display: block; margin-top: 14px; }
    </style>

    <div class="notice">
        <h2>Terima kasih!</h2>
        <p>Pembayaran Anda sedang diproses. Saldo/status akan diperbarui otomatis begitu Xendit mengonfirmasi pembayaran ke sistem kami — biasanya hanya butuh beberapa detik.</p>
        <a href="{{ route('portal.dashboard') }}" class="link-back">&larr; Kembali ke Dashboard Saya</a>
    </div>
@endsection
