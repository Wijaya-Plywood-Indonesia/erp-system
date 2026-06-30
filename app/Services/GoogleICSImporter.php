<?php

namespace App\Services;

use App\Models\HariLibur;
use Illuminate\Support\Facades\Http;
use Carbon\Carbon;

class GoogleICSImporter
{
    public static function import(int $year)
    {
        $url = "https://calendar.google.com/calendar/ical/en.indonesian%23holiday%40group.v.calendar.google.com/public/basic.ics";

        try {
            $response = Http::timeout(10)->get($url);

            if ($response->failed()) {
                throw new \Exception("Gagal download ICS Google Calendar");
            }

            $ics = $response->body();

            $events = self::parseICS($ics);

            $saved = 0;

            foreach ($events as $event) {

                // Filter hanya event yang sesuai tahun
                if (Carbon::parse($event['date'])->format('Y') != $year) {
                    continue;
                }

                HariLibur::updateOrCreate(
                    [
                        'date' => $event['date'],
                    ],
                    [
                        'name' => $event['name'],
                        'type' => 'national',
                        'is_repeat_yearly' => false,
                        'source' => 'Google Calendar ICS',
                    ]
                );

                $saved++;
            }

            return $saved;

        } catch (\Exception $e) {
            return [
                'error' => true,
                'message' => $e->getMessage()
            ];
        }
    }

    private static function parseICS($ics)
    {
        $lines = explode("\n", $ics);
        $events = [];
        $event = [];

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === "BEGIN:VEVENT") {
                $event = [];
            }

            if (str_starts_with($line, "DTSTART;")) {
                $date = substr($line, strpos($line, ":") + 1);
                $event['date'] = Carbon::createFromFormat('Ymd', $date)->format('Y-m-d');
            }

            if (str_starts_with($line, "SUMMARY:")) {
                $event['name'] = substr($line, 8);
            }

            if ($line === "END:VEVENT") {
                if (isset($event['date']) && isset($event['name'])) {
                    $events[] = $event;
                }
            }
        }

        return $events;
    }
}
