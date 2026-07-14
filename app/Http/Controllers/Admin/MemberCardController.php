<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Member;
use App\Services\MemberCard\MemberCardRenderer;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class MemberCardController extends Controller
{
    public function __construct(private readonly MemberCardRenderer $renderer) {}

    public function show(Member $member): View
    {
        $this->authorize('member_card.print');

        return view('admin.anggota.kartu', [
            'member' => $member,
            'cardHtml' => $this->renderer->renderHtml($member),
        ]);
    }

    public function downloadPdf(Member $member): Response
    {
        $this->authorize('member_card.print');

        $template = $this->renderer->resolveTemplate();
        $html = $this->renderer->renderHtml($member, $template);

        $pdf = Pdf::loadHTML('<html><body style="margin:0;">'.$html.'</body></html>')
            ->setPaper([0, 0, $this->mmToPoints((float) $template->width_mm), $this->mmToPoints((float) $template->height_mm)]);

        return $pdf->download("kartu-anggota-{$member->member_number}.pdf");
    }

    private function mmToPoints(float $mm): float
    {
        return $mm * 2.8346;
    }
}
