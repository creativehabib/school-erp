<?php

declare(strict_types=1);

namespace App\Http\Controllers\Hrm;

use App\Http\Controllers\Controller;
use App\Models\Hrm\Payslip;
use App\Services\Documents\Payloads\PayslipPayload;
use App\Services\Pdf\DompdfRenderer;
use App\Support\PageSetup;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DownloadPayslipController extends Controller
{
    public function __invoke(Request $request, Payslip $payslip, PayslipPayload $payloadBuilder, DompdfRenderer $renderer): StreamedResponse
    {
        Gate::authorize('hrm.payslip.print');
        abort_unless($payslip->employee()->where('user_id', $request->user()?->id)->exists(), 404);
        $html = View::make('pdf.salary-slip', ['data' => $payloadBuilder->build($payslip)])->render();
        $bytes = $renderer->render($html, new PageSetup(title: 'Salary Slip'));

        return response()->streamDownload(static function () use ($bytes): void {
            echo $bytes;
        }, 'salary-slip-'.$payslip->slip_no.'.pdf', ['Content-Type' => 'application/pdf']);
    }
}
