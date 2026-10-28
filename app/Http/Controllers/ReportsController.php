<?php

namespace App\Http\Controllers;

use App\Bot\FlowRepository;
use App\Models\Practitioner;
use App\Models\Service;
use App\Reports\ReportData;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Numbers for the team: chats, customers, bookings, handovers, reply time, surveys, orders. Filters and CSV export.
 */
class ReportsController extends Controller
{
    public function index(Request $request, FlowRepository $flows): View
    {
        $business = $request->user()->business;
        $data = new ReportData($business, $request, $flows);

        return view('admin.reports', [
            'business' => $business,
            'active' => 'reports',
            'r' => $data,
            's' => $data->summary(),
            'periods' => ReportData::PERIODS,
            'channels' => ReportData::CHANNELS,
            'services' => Service::query()->orderBy('sort')->get(),
            'practitioners' => Practitioner::query()->orderBy('name')->get(),
            'exports' => ReportData::EXPORTS,
            'query' => array_filter($request->only(['period', 'from', 'to', 'channel', 'service', 'practitioner']), fn ($v) => $v !== null && $v !== ''),
        ]);
    }

    public function export(Request $request, FlowRepository $flows): StreamedResponse
    {
        $business = $request->user()->business;
        $data = new ReportData($business, $request, $flows);
        $section = array_key_exists((string) $request->query('section'), ReportData::EXPORTS) ? (string) $request->query('section') : 'summary';
        [$header, $rows] = $data->export($section);

        $name = 'report-'.$section.'-'.$data->from->format('Ymd').'-'.$data->to->format('Ymd').'.csv';

        return response()->streamDownload(function () use ($header, $rows): void {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");   // so Excel shows Chinese names correctly
            fputcsv($out, $header);
            foreach ($rows as $row) {
                fputcsv($out, array_map([self::class, 'safe'], $row));
            }
            fclose($out);
        }, $name, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** A customer can type "=HYPERLINK(...)" as a name; a spreadsheet would run it. A leading apostrophe makes it plain text. */
    public static function safe(mixed $value): mixed
    {
        if (is_string($value) && $value !== '' && preg_match('/^[=+\-@\t\r]/', $value) && ! is_numeric($value)) {
            return "'".$value;
        }

        return $value;
    }
}
