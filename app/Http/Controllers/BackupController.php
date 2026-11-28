<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * A backup of everything the application holds, as one SQLite file (a consistent snapshot taken with VACUUM INTO, safe
 * while the application is running). The saved KSeF tokens in it are encrypted with APP_KEY: keep that key somewhere
 * safe too, or the backup cannot be used to sign in to KSeF again.
 */
class BackupController extends Controller
{
    public function download(): BinaryFileResponse|RedirectResponse
    {
        if (DB::connection()->getDriverName() !== 'sqlite') {
            return back()->withErrors(['backup' => __('Backups from here need the SQLite database. Use your database\'s own dump tool (pg_dump, mysqldump).')]);
        }

        $file = storage_path('app/backup-'.bin2hex(random_bytes(6)).'.sqlite');
        DB::statement('VACUUM INTO '.DB::connection()->getPdo()->quote($file));

        return response()->download($file, 'ksef-app-backup-'.now()->format('Ymd-His').'.sqlite', ['Content-Type' => 'application/vnd.sqlite3'])->deleteFileAfterSend(true);
    }
}
