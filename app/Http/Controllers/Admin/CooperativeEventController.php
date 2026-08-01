<?php

namespace App\Http\Controllers\Admin;

use App\Exports\GenericListExport;
use App\Http\Controllers\Concerns\GeneratesPrintPdf;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCooperativeEventRequest;
use App\Models\Branch;
use App\Models\CooperativeEvent;
use App\Models\Member;
use App\Services\Calendar\CooperativeEventService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

class CooperativeEventController extends Controller
{
    use GeneratesPrintPdf;

    public function __construct(private readonly CooperativeEventService $eventService) {}

    public function index(): View
    {
        $this->authorize('kalender.read');

        return view('admin.kalender.kegiatan.index', [
            'events' => CooperativeEvent::query()->latest('start_at')->get(),
        ]);
    }

    public function exportPdf(): Response
    {
        $this->authorize('kalender.read');

        $pdf = $this->renderPrintPdf('prints.kalender.kegiatan', [
            'events' => CooperativeEvent::query()->latest('start_at')->get(),
            'generatedAt' => now(),
        ]);

        return $pdf->download('daftar-kegiatan-'.now()->format('Ymd-His').'.pdf');
    }

    public function exportExcel(): BinaryFileResponse
    {
        $this->authorize('kalender.read');

        $rows = CooperativeEvent::query()->latest('start_at')->get()->map(fn (CooperativeEvent $event) => [
            $event->title,
            $event->start_at->format('Y-m-d H:i'),
            $event->end_at->format('Y-m-d H:i'),
            $event->location,
            ucfirst($event->status),
        ]);

        return Excel::download(
            new GenericListExport(['Judul', 'Mulai', 'Selesai', 'Lokasi', 'Status'], $rows),
            'daftar-kegiatan-'.now()->format('Ymd-His').'.xlsx',
        );
    }

    public function create(): View
    {
        $this->authorize('kalender.create');

        return view('admin.kalender.kegiatan.create', [
            'branches' => Branch::query()->where('is_active', true)->get(),
            'members' => Member::query()->limit(100)->get(),
            'roles' => Role::query()->get(),
        ]);
    }

    public function store(StoreCooperativeEventRequest $request): RedirectResponse
    {
        $event = $this->eventService->create($request->validated(), $request->user()->id);

        return redirect()
            ->route('admin.kalender.kegiatan.index')
            ->with('status', "Kegiatan \"{$event->title}\" berhasil dijadwalkan.");
    }

    public function cancel(CooperativeEvent $event): RedirectResponse
    {
        $this->authorize('kalender.update');

        $this->eventService->cancel($event);

        return redirect()
            ->route('admin.kalender.kegiatan.index')
            ->with('status', "Kegiatan \"{$event->title}\" dibatalkan.");
    }
}
