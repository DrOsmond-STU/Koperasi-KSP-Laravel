<?php

namespace Tests\Feature\Payment;

use App\Exceptions\Payment\PaymentGatewayException;
use App\Services\Payment\XenditGateway;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class XenditGatewayTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Config::set('services.xendit.secret_key', 'xnd_development_test_key');
    }

    public function test_creates_an_invoice_and_returns_id_and_url(): void
    {
        Http::fake(['*/v2/invoices' => Http::response([
            'id' => 'inv_123',
            'invoice_url' => 'https://checkout.xendit.co/web/inv_123',
        ], 200)]);

        $result = app(XenditGateway::class)->createInvoice(
            externalId: 'SETOR-abc',
            amount: 150000,
            description: 'Setoran simpanan SA-001',
            payerEmail: 'anggota@ksp.test',
            successRedirectUrl: 'https://app.local/portal/pembayaran/selesai',
        );

        $this->assertEquals('inv_123', $result['id']);
        $this->assertEquals('https://checkout.xendit.co/web/inv_123', $result['invoice_url']);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.xendit.co/v2/invoices'
                && $request['external_id'] === 'SETOR-abc'
                && (float) $request['amount'] === 150000.0;
        });
    }

    public function test_throws_payment_gateway_exception_when_xendit_returns_an_error(): void
    {
        Http::fake(['*/v2/invoices' => Http::response(['message' => 'invalid request'], 400)]);

        $this->expectException(PaymentGatewayException::class);

        app(XenditGateway::class)->createInvoice(
            externalId: 'SETOR-abc',
            amount: 150000,
            description: 'Setoran simpanan SA-001',
            payerEmail: 'anggota@ksp.test',
            successRedirectUrl: 'https://app.local/portal/pembayaran/selesai',
        );
    }
}
