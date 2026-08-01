@extends('layouts.app')

@section('title', 'Ubah Izin Role')

@section('content')
    <style>
        .form-card { background: var(--surface); border: 1px solid var(--line); border-radius: 14px; padding: 22px; max-width: 720px; }
        .btn-primary { padding: 10px 18px; background: var(--pine); color: #fff; border: none; border-radius: 9px; font-weight: 700; cursor: pointer; }
        .error-text { color: var(--brick); font-size: 12px; margin-top: 4px; }
        .module-block { border: 1px solid var(--line); border-radius: 9px; padding: 12px 14px; margin-bottom: 12px; }
        .module-block h4 { margin: 0 0 8px; font-size: 13px; color: var(--muted); text-transform: uppercase; letter-spacing: .04em; }
        .permission-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 8px; }
        .permission-grid label { display: flex; align-items: center; gap: 8px; font-size: 13px; font-weight: 500; color: var(--pine-ink); }
        .permission-grid label.protected { color: var(--muted); }
        .protected-note { font-size: 11px; color: var(--muted); margin-top: 8px; }
    </style>

    <h2>Ubah Izin Role: {{ $role->name }}</h2>

    @if ($errors->any())
        <div class="error-text" style="margin-bottom: 12px;">
            <ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    @if (count($protectedPermissions))
        <p class="protected-note">Izin inti ({{ implode(', ', $protectedPermissions) }}) tidak bisa dicabut dari role ini — mencegah semua orang terkunci dari pengelolaan user/role.</p>
    @endif

    <div class="form-card">
        <form method="POST" action="{{ route('admin.role-permission.update', $role) }}">
            @csrf
            @method('PUT')

            @foreach ($permissionsByModule as $module => $permissions)
                <div class="module-block">
                    <h4>{{ $module }}</h4>
                    <div class="permission-grid">
                        @foreach ($permissions as $permission)
                            @php $isProtected = in_array($permission->name, $protectedPermissions, true); @endphp
                            <label class="{{ $isProtected ? 'protected' : '' }}">
                                <input type="checkbox" name="permissions[]" value="{{ $permission->name }}"
                                    @checked(in_array($permission->name, old('permissions', $assignedPermissions)))
                                    @disabled($isProtected)>
                                {{ $permission->name }}
                            </label>
                        @endforeach
                    </div>
                </div>
            @endforeach

            <button type="submit" class="btn-primary">Simpan Izin</button>
        </form>
    </div>
@endsection
