<?php

namespace App\Http\Controllers\Admin;

use App\Exports\ListExport;
use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Support\AdminExports;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;

class ExportController extends Controller
{
    /**
     * Download an admin list as PDF or Excel. The page's own query string
     * (?status=, ?tab=) is honoured so the file matches what is on screen.
     */
    public function download(Request $request, string $list, string $format)
    {
        $export = AdminExports::build($list, $request);
        abort_if($export === null, 404);

        $filename = Str::slug($export['title'] . ' ' . ($export['filter'] ?? '')) . '-' . now()->format('Ymd-His');

        if ($format === 'xlsx') {
            return Excel::download(
                new ListExport($export['title'], $export['headings'], $export['rows']),
                $filename . '.xlsx'
            );
        }

        return Pdf::loadView('admin.pdf.list', $export + [
            'siteName' => Setting::get('site_name', config('app.name')),
        ])->setPaper('a4', count($export['headings']) > 6 ? 'landscape' : 'portrait')
            ->setOption('isFontSubsettingEnabled', true)
            ->download($filename . '.pdf');
    }
}
