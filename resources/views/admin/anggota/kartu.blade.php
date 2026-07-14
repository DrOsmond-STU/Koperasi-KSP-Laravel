@extends('layouts.app')

@section('title', 'Kartu Anggota')

@section('content')
    <h2>Kartu Anggota — {{ $member->name }}</h2>

    <div style="margin: 20px 0;">
        {!! $cardHtml !!}
    </div>

    <a href="{{ route('admin.members.card.pdf', $member) }}"
       style="display: inline-block; padding: 10px 18px; background: var(--pine); color: #fff; border-radius: 9px; text-decoration: none; font-weight: 700;">
        Unduh PDF
    </a>
@endsection
