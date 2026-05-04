<?php
declare(strict_types=1);

namespace App\Support;

class GeocodingService
{
    /**
     * Mapbox token for server-side geocoding (ecommerce root `.env`).
     * Public pk.* tokens work for Geocoding API within Mapbox quotas; prefer a dedicated secret when possible.
     */
    private static function mapboxToken(): ?string
    {
        $fromEnv = $_ENV['MAPBOX_ACCESS_TOKEN'] ?? $_ENV['MAPBOX_SECRET_TOKEN'] ?? null;
        if (is_string($fromEnv) && trim($fromEnv) !== '') {
            return trim($fromEnv);
        }
        $g1 = getenv('MAPBOX_ACCESS_TOKEN');
        if (is_string($g1) && trim($g1) !== '') {
            return trim($g1);
        }
        $g2 = getenv('MAPBOX_SECRET_TOKEN');
        if (is_string($g2) && trim($g2) !== '') {
            return trim($g2);
        }
        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function httpGetJson(string $url, array $extraHeaderLines = []): ?array
    {
        $header = "Accept: application/json\r\n";
        foreach ($extraHeaderLines as $line) {
            $header .= $line . "\r\n";
        }
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 10,
                'header' => $header,
            ],
        ]);
        $raw = @file_get_contents($url, false, $context);
        if ($raw === false || $raw === '') {
            return null;
        }
        $parsed = json_decode($raw, true);
        return is_array($parsed) ? $parsed : null;
    }

    /**
     * Forward geocode via Mapbox Places API.
     *
     * @return array{lat: float, lng: float}|null
     */
    private static function resolveWithMapbox(string $query): ?array
    {
        $token = self::mapboxToken();
        if ($token === null) {
            return null;
        }
        $pathQuery = rawurlencode($query);
        $url = 'https://api.mapbox.com/geocoding/v5/mapbox.places/' . $pathQuery . '.json?' . http_build_query([
            'access_token' => $token,
            'country' => 'vn',
            'limit' => 1,
            'language' => 'vi',
        ]);

        $parsed = self::httpGetJson($url);
        if ($parsed === null) {
            return null;
        }
        $features = $parsed['features'] ?? null;
        if (!is_array($features) || !isset($features[0]['center']) || !is_array($features[0]['center'])) {
            return null;
        }
        $center = $features[0]['center'];
        // GeoJSON: [longitude, latitude]
        $lng = (float) ($center[0] ?? 0);
        $lat = (float) ($center[1] ?? 0);
        if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
            return null;
        }
        if ($lat === 0.0 && $lng === 0.0) {
            return null;
        }
        return ['lat' => $lat, 'lng' => $lng];
    }

    /**
     * Fallback: OpenStreetMap Nominatim.
     *
     * @return array{lat: float, lng: float}|null
     */
    private static function resolveWithNominatim(string $query): ?array
    {
        $url = 'https://nominatim.openstreetmap.org/search?' . http_build_query([
            'q' => $query,
            'format' => 'jsonv2',
            'limit' => 1,
            'countrycodes' => 'vn',
            'addressdetails' => 0,
        ]);

        $parsed = self::httpGetJson($url, [
            'User-Agent: ecommerce-geocoder/1.0',
        ]);
        if ($parsed === null) {
            return null;
        }
        if (empty($parsed[0]['lat']) || empty($parsed[0]['lon'])) {
            return null;
        }

        $lat = (float) $parsed[0]['lat'];
        $lng = (float) $parsed[0]['lon'];

        if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
            return null;
        }

        return ['lat' => $lat, 'lng' => $lng];
    }

    /**
     * Resolve full address to coordinates: Mapbox first (if token set), else Nominatim.
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

        $coords = self::resolveWithMapbox($query);
        if ($coords !== null) {
            return $coords;
        }

        return self::resolveWithNominatim($query);
    }
}
