<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\MemberCard\NoActiveCardTemplateException;
use App\Http\Controllers\Controller;
use App\Models\Member;
use App\Services\MemberCard\MemberCardRenderer;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class MemberCardController extends Controller
{
    public function __construct(private readonly MemberCardRenderer $renderer) {}

    public function show(Member $member): View|RedirectResponse
    {
        $this->authorize('member_card.print');

        try {
            $cardHtml = $this->renderer->renderHtml($member);
        } catch (NoActiveCardTemplateException $exception) {
            return $this->redirectToTemplateSetup($exception);
        }

        return view('admin.anggota.kartu', [
            'member' => $member,
            'cardHtml' => $cardHtml,
        ]);
    }

    public function downloadPdf(Member $member): Response|RedirectResponse
    {
        $this->authorize('member_card.print');

        try {
            $template = $this->renderer->resolveTemplate();
        } catch (NoActiveCardTemplateException $exception) {
            return $this->redirectToTemplateSetup($exception);
        }

        $html = $this->renderer->renderHtml($member, $template);

        $pdf = Pdf::loadHTML('<html><body style="margin:0;">'.$html.'</body></html>')
            ->setPaper([0, 0, $this->mmToPoints((float) $template->width_mm), $this->mmToPoints((float) $template->height_mm)]);

        return $pdf->download("kartu-anggota-{$member->member_number}.pdf");
    }

    /**
     * Cetak massal — beberapa anggota terpilih (?member_id[]=), difilter
     * jenis/cabang (?member_type_id=&branch_id=, pola sama seperti
     * MemberController::printList()), atau semua anggota bila tak ada
     * filter sama sekali. Satu kartu per halaman (page-break CSS), layout
     * kartu individual tidak berubah dari show()/downloadPdf().
     */
    public function printBulk(Request $request): Response|RedirectResponse
    {
        $this->authorize('member_card.print');

        try {
            $template = $this->renderer->resolveTemplate();
        } catch (NoActiveCardTemplateException $exception) {
            return $this->redirectToTemplateSetup($exception);
        }

        $memberIds = $request->input('member_id', []);
        $memberTypeId = $request->integer('member_type_id') ?: null;
        $branchId = $request->integer('branch_id') ?: null;

        $members = Member::query()
            ->when(! empty($memberIds), fn ($query) => $query->whereIn('id', $memberIds))
            ->when($memberTypeId, fn ($query) => $query->where('member_type_id', $memberTypeId))
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->orderBy('name')
            ->get();

        abort_if($members->isEmpty(), 404, 'Tidak ada anggota yang cocok dengan filter.');

        $cardsHtml = $members
            ->map(fn (Member $member) => '<div style="page-break-after: always;">'.$this->renderer->renderHtml($member, $template).'</div>')
            ->implode('');

        $pdf = Pdf::loadHTML('<html><body style="margin:0;">'.$cardsHtml.'</body></html>')
            ->setPaper([0, 0, $this->mmToPoints((float) $template->width_mm), $this->mmToPoints((float) $template->height_mm)]);

        return $pdf->download('kartu-anggota-massal-'.now()->format('Ymd-His').'.pdf');
    }

    private function redirectToTemplateSetup(NoActiveCardTemplateException $exception): RedirectResponse
    {
        if (request()->user()?->can('member_card.manage')) {
            return redirect()->route('admin.master.member-card-templates.index')->with('error', $exception->getMessage());
        }

        return redirect()->route('admin.members.index')->with('error', $exception->getMessage());
    }

    private function mmToPoints(float $mm): float
    {
        return $mm * 2.8346;
    }
}
