<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\NotificationLog;
use Illuminate\View\View;

class NotificationLogController extends Controller
{
    public function index(): View
    {
        $this->authorize('notifikasi_log.read');

        return view('admin.notifikasi.log.index', [
            'logs' => NotificationLog::query()->latest()->limit(100)->get(),
            'stats' => [
                'terkirim' => NotificationLog::query()->where('status', 'terkirim')->count(),
                'gagal' => NotificationLog::query()->where('status', 'gagal')->count(),
                'tanpa_consent' => NotificationLog::query()->where('status', 'tidak_dikirim_tanpa_consent')->count(),
            ],
        ]);
    }
}
