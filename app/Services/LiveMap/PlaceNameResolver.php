<?php

namespace App\Services\LiveMap;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class PlaceNameResolver
{
    public function resolve(float $latitude, float $longitude): string
    {
        $fallback = sprintf('%.4f, %.4f', $latitude, $longitude);
        $cacheKey = 'live-map:place:'.round($latitude, 4).','.round($longitude, 4);

        return Cache::remember($cacheKey, now()->addDay(), function () use ($latitude, $longitude, $fallback): string {
            try {
                $response = Http::withHeaders([
                    'User-Agent' => 'FieldTrackerAdmin/1.0 (live map place lookup)',
                    'Accept-Language' => 'en',
                ])->timeout(3)->get('https://nominatim.openstreetmap.org/reverse', [
                    'format' => 'jsonv2',
                    'lat' => $latitude,
                    'lon' => $longitude,
                    'zoom' => 16,
                    'addressdetails' => 1,
                ]);

                if ($response->failed()) {
                    return $fallback;
                }

                return $response->json('display_name') ?: $fallback;
            } catch (\Throwable) {
                return $fallback;
            }
        });
    }
}
