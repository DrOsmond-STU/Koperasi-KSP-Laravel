@extends('layouts.app')

@section('title', 'Beranda')

@section('content')
    <h2>Selamat datang, {{ auth()->user()->name }}.</h2>
    <p>Ini halaman placeholder — akan digantikan Dashboard Utama (PRD §15) setelah frontend Blade/Livewire/Inertia diputuskan (PRD §1.1).</p>
@endsection
