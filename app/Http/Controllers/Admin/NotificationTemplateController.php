<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateNotificationTemplateRequest;
use App\Models\NotificationTemplate;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class NotificationTemplateController extends Controller
{
    public function index(): View
    {
        $this->authorize('notifikasi_template.manage');

        return view('admin.notifikasi.template.index', [
            'templates' => NotificationTemplate::query()->orderBy('trigger_type')->orderBy('channel')->get(),
        ]);
    }

    public function edit(NotificationTemplate $template): View
    {
        $this->authorize('notifikasi_template.manage');

        return view('admin.notifikasi.template.edit', ['template' => $template]);
    }

    public function update(UpdateNotificationTemplateRequest $request, NotificationTemplate $template): RedirectResponse
    {
        $template->update([
            'subject' => $request->validated('subject'),
            'body' => $request->validated('body'),
            'is_active' => $request->boolean('is_active'),
        ]);

        return redirect()
            ->route('admin.notifikasi.template.index')
            ->with('status', "Template \"{$template->trigger_type}\" ({$template->channel}) berhasil diperbarui.");
    }
}
