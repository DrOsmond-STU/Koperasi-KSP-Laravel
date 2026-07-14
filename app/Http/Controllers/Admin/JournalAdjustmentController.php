<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\Accounting\AdjustmentException;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreJournalAdjustmentRequest;
use App\Models\JournalEntry;
use App\Services\Accounting\AdjustmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class JournalAdjustmentController extends Controller
{
    public function __construct(private readonly AdjustmentService $adjustmentService) {}

    public function create(Request $request): View
    {
        $this->authorize('jurnal.adjust');

        $selectedId = $request->integer('entry_id') ?: null;

        return view('admin.jurnal-penyesuaian', [
            'entries' => JournalEntry::query()->whereNull('reversal_of_entry_id')->latest()->limit(50)->get(),
            'selectedEntry' => $selectedId !== null
                ? JournalEntry::query()->with('lines.account')->find($selectedId)
                : null,
        ]);
    }

    public function store(StoreJournalAdjustmentRequest $request, JournalEntry $entry): RedirectResponse
    {
        try {
            $this->adjustmentService->reverse(
                $entry,
                $request->validated('reason'),
                $request->user(),
                $request->validated('password'),
            );
        } catch (AdjustmentException $exception) {
            return redirect()->route('admin.jurnal-penyesuaian.create')->with('error', $exception->getMessage());
        }

        return redirect()
            ->route('admin.jurnal-penyesuaian.create')
            ->with('status', "Jurnal penyesuaian atas entri #{$entry->id} berhasil diposting.");
    }
}
