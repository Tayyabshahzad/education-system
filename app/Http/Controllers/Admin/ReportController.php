<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Transactions & reporting: pick a date range (this month by default), see the totals
 * that must tally, browse the records behind each total and export them as CSV.
 */
class ReportController extends Controller
{
    public function __construct(private ReportService $reports) {}

    public function index(Request $request): View
    {
        [$from, $to, $type] = $this->filters($request);

        return view('admin.reports.index', [
            'from' => $from,
            'to' => $to,
            'type' => $type,
            'types' => ReportService::TYPES,
            'presets' => $this->presets(),
            'summary' => $this->reports->summary($from, $to),
            'records' => $this->reports->query($type, $from, $to)->paginate(25)->withQueryString(),
        ]);
    }

    /** CSV of every record in the range, opened directly by Excel (UTF-8 with BOM). */
    public function export(Request $request): StreamedResponse
    {
        [$from, $to, $type] = $this->filters($request);
        $columns = $this->reports->csvColumns($type);
        $query = $this->reports->query($type, $from, $to);
        $filename = sprintf('%s-%s_to_%s.csv', $type, $from->format('Y-m-d'), $to->format('Y-m-d'));

        return response()->streamDownload(function () use ($columns, $query) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, array_keys($columns));
            $query->chunk(500, function ($rows) use ($out, $columns) {
                foreach ($rows as $row) {
                    fputcsv($out, array_map(fn ($get) => $get($row), array_values($columns)));
                }
            });
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** @return array{0: Carbon, 1: Carbon, 2: string} */
    private function filters(Request $request): array
    {
        $data = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'type' => ['nullable', Rule::in(array_keys(ReportService::TYPES))],
        ]);

        return [
            isset($data['from']) ? Carbon::parse($data['from'])->startOfDay() : now()->startOfMonth(),
            isset($data['to']) ? Carbon::parse($data['to'])->endOfDay() : now()->endOfDay(),
            $data['type'] ?? 'members',
        ];
    }

    /** Quick ranges shown as buttons. */
    private function presets(): array
    {
        $today = now();

        return [
            'Today' => [$today->copy(), $today->copy()],
            'This month' => [$today->copy()->startOfMonth(), $today->copy()],
            'Last month' => [$today->copy()->subMonthNoOverflow()->startOfMonth(), $today->copy()->subMonthNoOverflow()->endOfMonth()],
            'Last 3 months' => [$today->copy()->subMonthsNoOverflow(2)->startOfMonth(), $today->copy()],
            'This year' => [$today->copy()->startOfYear(), $today->copy()],
        ];
    }
}
