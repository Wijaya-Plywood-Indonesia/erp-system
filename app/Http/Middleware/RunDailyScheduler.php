<?php

namespace App\Http\Middleware;

use Closure;
use App\Services\DailyScheduler;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\DB;

class RunDailyScheduler
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // --- AUTO UPDATE DURASI ---
        DailyScheduler::checkAndRun('update_durasi_kontrak', '06:00', function () {
            DB::statement("
                UPDATE kontrak_kerja
                SET durasi_kontrak = 
                    CASE
                        WHEN kontrak_mulai IS NULL OR kontrak_selesai IS NULL
                            THEN 0
                        ELSE DATEDIFF(kontrak_selesai, kontrak_mulai)
                    END
            ");
        });

        // --- AUTO UPDATE STATUS ---
        DailyScheduler::checkAndRun('update_status_kontrak', '06:00', function () {
            DB::statement("
                UPDATE kontrak_kerja
                SET status_kontrak = 
                    CASE
                        WHEN kontrak_mulai IS NULL OR kontrak_selesai IS NULL
                            THEN 'expired'
                        WHEN CURDATE() > kontrak_selesai
                            THEN 'expired'
                        WHEN DATEDIFF(kontrak_selesai, CURDATE()) <= 30
                            THEN 'soon'
                        ELSE 'active'
                    END
            ");
        });

        return $next($request);
    }
}
