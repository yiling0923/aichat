<?php

namespace App\Http\Controllers;

use App\Support\Backup;
use App\Support\BusinessExport;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** The owner downloads their own data as spreadsheets, and sees whether the server keeps daily backups. */
class BackupController extends Controller
{
    public function index(Request $request): View
    {
        $business = $request->user()->business;
        $dir = app(Backup::class)->directory();
        $files = is_dir($dir) ? collect(glob($dir.'/aichat-*') ?: [])->sortDesc()->values() : collect();
        $last = $files->first();

        return view('admin.backup', [
            'business' => $business,
            'active' => 'manage',
            'sets' => BusinessExport::SETS,
            'counts' => (new BusinessExport($business))->counts(),
            'lastBackup' => $last ? ['at' => \Carbon\Carbon::createFromTimestamp(filemtime($last), $business->timezone ?: 'UTC'), 'size' => round(filesize($last) / 1024).' KB', 'count' => $files->count()] : null,
        ]);
    }

    public function csv(Request $request, string $set): StreamedResponse
    {
        abort_unless(array_key_exists($set, BusinessExport::SETS), 404);
        $export = new BusinessExport($request->user()->business);

        return response()->streamDownload(function () use ($export, $set): void {
            $out = fopen('php://output', 'w');
            $export->writeCsv($set, $out);
            fclose($out);
        }, $set.'-'.now()->format('Ymd').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function zip(Request $request): BinaryFileResponse
    {
        $business = $request->user()->business;
        $path = (new BusinessExport($business))->zip();

        return response()->download($path, str($business->slug)->slug().'-export-'.now()->format('Ymd').'.zip', ['Content-Type' => 'application/zip'])->deleteFileAfterSend(true);
    }
}
