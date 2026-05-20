<?php
declare(strict_types=1);

namespace App\Support;

class GeocodingService
{
    /**
     * Resolve full address to coordinates (lat/lng).
     * Prefers Mapbox when MAPBOX_ACCESS_TOKEN is set, else OpenStreetMap Nominatim.
     *
     * @return array{lat: float, lng: float}|null
     */
    public static function resolveFromParts(string $addressLine, string $ward, string $district, string $province): ?array
    {
        $parts = array_filter([
            trim($addressLine),
            trim($ward),
            trim($district),
            trim($province),
            'Vietnam',
        ], static fn($v) => $v !== '');

        if (empty($parts)) {
            return null;
        }

        $query = implode(', ', $parts);

        $mapboxToken = $_ENV['MAPBOX_ACCESS_TOKEN'] ?? '';
        if ($mapboxToken !== '') {
            $fromMapbox = self::resolveViaMapbox($query, $mapboxToken);
            if ($fromMapbox !== null) {
                return $fromMapbox;
            }
        }

        return self::resolveViaNominatim($query);
    }

    /**
     * @return array{lat: float, lng: float}|null
     */
    private static function resolveViaMapbox(string $query, string $token): ?array
    {
        $encoded = rawurlencode($query);
        $url =
            'https://api.mapbox.com/geocoding/v5/mapbox.places/' . $encoded . '.json' .
            '?access_token=' . urlencode($token) .
            '&country=vn' .
            '&language=vi' .
            '&limit=1';

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 10,
                'header' => "Accept: application/json\r\n",
            ],
        ]);

        $raw = @file_get_contents($url, false, $context);
        if ($raw === false || $raw === '') {
            return null;
        }

        $parsed = json_decode($raw, true);
        if (!is_array($parsed) || empty($parsed['features'][0]['center'])) {
            return null;
        }

        $center = $parsed['features'][0]['center'];
        $lng = (float) ($center[0] ?? 0);
        $lat = (float) ($center[1] ?? 0);

        if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
            return null;
        }

        return ['lat' => $lat, 'lng' => $lng];
    }

    /**
     * @return array{lat: float, lng: float}|null
     */
    private static function resolveViaNominatim(string $query): ?array
    {
        $url = 'https://nominatim.openstreetmap.org/search?' . http_build_query([
            'q' => $query,
            'format' => 'jsonv2',
            'limit' => 1,
            'countrycodes' => 'vn',
            'addressdetails' => 0,
        ]);

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 8,
                'header' => [
                    'User-Agent: ecommerce-geocoder/1.0',
                    'Accept: application/json',
                ],
            ],
        ]);

        $raw = @file_get_contents($url, false, $context);
        if ($raw === false || $raw === '') {
            return null;
        }

        $parsed = json_decode($raw, true);
        if (!is_array($parsed) || empty($parsed[0]['lat']) || empty($parsed[0]['lon'])) {
            return null;
        }

        $lat = (float) $parsed[0]['lat'];
        $lng = (float) $parsed[0]['lon'];

        if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
            return null;
        }

        return ['lat' => $lat, 'lng' => $lng];
    }
}
