<?php

namespace App\Http\Controllers\Anggota;

use App\Http\Controllers\Concerns\GeneratesPrintPdf;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cetak mandiri data simpanan sendiri dari Portal Anggota — sama seperti
 * halaman lain di Anggota\*, cakupan datanya SELALU $request->user()->member,
 * tidak pernah dari input, jadi tidak butuh gate permission (data sendiri).
 */
class PrintSavingsController extends Controller
{
    use GeneratesPrintPdf;

    public function index(Request $request): Response
    {
        $member = $request->user()->member->load('savingsAccounts.savingsProduct');

        $pdf = $this->renderPrintPdf('prints.savings.list', [
            'members' => collect([$member]),
            'filterDescription' => 'Anggota: '.$member->name,
            'generatedAt' => now(),
        ]);

        return $pdf->download('simpanan-saya-'.now()->format('Ymd-His').'.pdf');
    }
}
