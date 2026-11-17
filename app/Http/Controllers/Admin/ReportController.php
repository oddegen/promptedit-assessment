<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Pixelforge\PixelforgeClient;
use App\Services\UsageReport;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReportController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request, PixelforgeClient $pixelforge): Response
    {
        $range = $request->string('range')->toString();
        $today = CarbonImmutable::today();

        [$range, $from] = match ($range) {
            '7' => ['7', $today->subDays(6)],
            'month' => ['month', $today->startOfMonth()],
            default => ['30', $today->subDays(29)],
        };

        $report = new UsageReport($from, $today);

        return Inertia::render('admin/Report', [
            'range' => $range,
            'from' => $from->toDateString(),
            'to' => $today->toDateString(),
            'summary' => $report->summary(),
            'days' => $report->days()->values(),
            'users' => $report->users(),
            'provider' => Inertia::defer(fn () => $report->reconcile($pixelforge)),
        ]);
    }
}
