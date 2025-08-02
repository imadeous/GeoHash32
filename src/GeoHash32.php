<?php

namespace GeoHash32;

use InvalidArgumentException;
use RuntimeException;

/**
 * GeoHash32 class for encoding and decoding geographic coordinates into Base32 geohashes.
 * 
 * This library provides efficient encoding and decoding of latitude/longitude coordinates
 * into compact, URL-safe geohash strings using Base32 encoding. Features include:
 * - Variable precision (1-12 characters)
 * - Bounding box calculation
 * - URL generation for sharing
 * - QR code generation (requires GD extension)
 * - GeoJSON export for mapping applications
 * - Performance benchmarking
 * 
 * @package GeoHash32
 * @author Mohamed Imad <email2emad@gmail.com>
 * @license MIT
 * @version 1.0.0
 * 
 * @example
 * ```php
 * $geo = new GeoHash32();
 * $geo->setHashLength(7);
 * 
 * // Encode coordinates
 * $hash = $geo->encode(1.3521, 103.8198); // Singapore
 * 
 * // Decode back to coordinates with bounding box
 * $decoded = $geo->decode($hash);
 * echo "Lat: {$decoded['lat']}, Lng: {$decoded['lng']}";
 * ```
 */

class GeoHash32
{
    /** @var int Default hash length for encoding operations */
    private int $hashLength = 5;

    /** @var string Base32 alphabet used for geohash encoding (Crockford's Base32) */
    private const BASE32 = '0123456789bcdefghjkmnpqrstuvwxyz';

    /** @var int Base32 radix (32) */
    private const BASE = 32;

    // --- Configuration ---

    /**
     * Set the default hash length for encoding operations.
     *
     * @param int $length The desired hash length (1-12 characters)
     * @return self Returns the instance for method chaining
     */
    public function setHashLength(int $length): self
    {
        $this->hashLength = max(1, min(12, $length));
        return $this;
    }

    // --- Encode/Decode ---

    /**
     * Encode latitude and longitude coordinates into a geohash string.
     *
     * @param float $lat Latitude coordinate (-90 to 90)
     * @param float $lng Longitude coordinate (-180 to 180)
     * @param int|null $len Optional hash length override (1-12 characters)
     * @return string Base32 encoded geohash
     */
    public function encode(float $lat, float $lng, ?int $len = null): string
    {
        $len = $len ?? $this->hashLength;

        // Clamp coordinates to valid ranges
        $lat = max(-90.0, min(90.0, $lat));
        $lng = max(-180.0, min(180.0, $lng));

        $latMin = -90.0;
        $latMax = 90.0;
        $lngMin = -180.0;
        $lngMax = 180.0;

        $bits = 0;
        $isEven = true; // Start with longitude (even)

        for ($i = 0; $i < $len * 5; $i++) {
            $bits <<= 1;

            if ($isEven) { // Longitude
                $mid = ($lngMin + $lngMax) / 2;
                if ($lng >= $mid) {
                    $bits |= 1;
                    $lngMin = $mid;
                } else {
                    $lngMax = $mid;
                }
            } else { // Latitude
                $mid = ($latMin + $latMax) / 2;
                if ($lat >= $mid) {
                    $bits |= 1;
                    $latMin = $mid;
                } else {
                    $latMax = $mid;
                }
            }
            $isEven = !$isEven;
        }

        return $this->base32Encode($bits, $len);
    }

    /**
     * Decode a geohash string back into latitude, longitude, and bounding box.
     *
     * @param string $hash The geohash string to decode
     * @return array{lat: float, lng: float, bbox: array{sw: array{lat: float, lng: float}, ne: array{lat: float, lng: float}}}
     * @throws InvalidArgumentException If the hash contains invalid characters
     */
    public function decode(string $hash): array
    {
        $bits = $this->base32Decode($hash);
        $totalBits = strlen($hash) * 5;

        $latMin = -90.0;
        $latMax = 90.0;
        $lngMin = -180.0;
        $lngMax = 180.0;

        $isEven = true; // Start with longitude (even)

        for ($i = $totalBits - 1; $i >= 0; $i--) {
            $bit = ($bits >> $i) & 1;

            if ($isEven) { // Longitude
                $mid = ($lngMin + $lngMax) / 2;
                if ($bit === 1) {
                    $lngMin = $mid;
                } else {
                    $lngMax = $mid;
                }
            } else { // Latitude
                $mid = ($latMin + $latMax) / 2;
                if ($bit === 1) {
                    $latMin = $mid;
                } else {
                    $latMax = $mid;
                }
            }
            $isEven = !$isEven;
        }

        $lat = ($latMin + $latMax) / 2;
        $lng = ($lngMin + $lngMax) / 2;

        $bbox = [
            'sw' => [
                'lat' => $latMin,
                'lng' => $lngMin
            ],
            'ne' => [
                'lat' => $latMax,
                'lng' => $lngMax
            ]
        ];

        return [
            'lat' => round($lat, 6),
            'lng' => round($lng, 6),
            'bbox' => $bbox
        ];
    }    // --- Utilities ---

    /**
     * Generate a URL with an embedded geohash.
     *
     * @param string $hash The geohash to embed in the URL
     * @param string $base The base URL (without trailing slash)
     * @return string Complete URL with embedded geohash
     */
    public function toURL(string $hash, string $base = ''): string
    {
        $base = rtrim($base, '/');
        return $base . '/h/' . $hash;
    }

    /**
     * Generate a QR code image for a geohash (requires GD extension).
     *
     * @param string $hash The geohash to encode in the QR code
     * @return string Base64 encoded PNG image data URI
     * @throws RuntimeException If GD extension is not available
     */
    public function toQR(string $hash): string
    {
        if (!extension_loaded('gd')) {
            throw new RuntimeException("GD extension is not enabled.");
        }

        $url = $this->toURL($hash, 'https://geo.local');
        $size = 200;

        $img = imagecreate($size, $size);
        $bg = imagecolorallocate($img, 255, 255, 255);
        $black = imagecolorallocate($img, 0, 0, 0);

        for ($i = 0; $i < strlen($hash); $i++) {
            $char = strpos(self::BASE32, $hash[$i]);
            for ($b = 0; $b < 5; $b++) {
                if (($char >> $b) & 1) {
                    imagefilledrectangle(
                        $img,
                        10 + $i * 30,
                        10 + $b * 30,
                        35 + $i * 30,
                        35 + $b * 30,
                        $black
                    );
                }
            }
        }

        ob_start();
        imagepng($img);
        $imageData = ob_get_clean();
        imagedestroy($img);

        return 'data:image/png;base64,' . base64_encode($imageData);
    }

    /**
     * Benchmark the encoding and decoding performance for given coordinates.
     *
     * @param float $lat Latitude coordinate to benchmark
     * @param float $lng Longitude coordinate to benchmark  
     * @param int|null $len Optional hash length override
     * @return array{length: int, hash: string, decoded: array, encode_ms: float, decode_ms: float}
     */
    public function benchmark(float $lat, float $lng, ?int $len = null): array
    {
        $len = $len ?? $this->hashLength;

        $start = microtime(true);
        $hash = $this->encode($lat, $lng, $len);
        $encodeTime = microtime(true) - $start;

        $start = microtime(true);
        $decoded = $this->decode($hash);
        $decodeTime = microtime(true) - $start;

        return [
            'length' => $len,
            'hash' => $hash,
            'decoded' => $decoded,
            'encode_ms' => round($encodeTime * 1000, 4),
            'decode_ms' => round($decodeTime * 1000, 4),
        ];
    }

    /**
     * Get the approximate precision in meters for a given geohash length.
     *
     * @param int $length The geohash length (1-12 characters)
     * @return float Approximate precision in meters
     */
    public function getPrecisionMeters(int $length): float
    {
        $bits = $length * 5;
        $latBits = floor($bits / 2);
        $lngBits = $bits - $latBits;

        $latErr = 180 / (1 << $latBits);
        $lngErr = 360 / (1 << $lngBits);

        $latMeters = $latErr * 111_320;
        $lngMeters = $lngErr * 111_320;

        return round(max($latMeters, $lngMeters), 2);
    }

    /**
     * Suggest the optimal geohash length for a target precision in meters.
     *
     * @param float $targetMeters The desired precision in meters
     * @return int Recommended geohash length (1-12 characters)
     */
    public function suggestLengthForPrecision(float $targetMeters): int
    {
        for ($len = 1; $len <= 12; $len++) {
            if ($this->getPrecisionMeters($len) <= $targetMeters) {
                return $len;
            }
        }
        return 12;
    }

    /**
     * Decode a geohash with additional bounding box information and precision metrics.
     *
     * @param string $hash The geohash string to decode
     * @return array{lat: float, lng: float, bbox: array, precision_m: float}
     */
    public function decodeWithBoundingBox(string $hash): array
    {
        $decoded = $this->decode($hash);

        $bits = strlen($hash) * 5;
        $latBits = (int) floor($bits / 2);
        $lngBits = $bits - $latBits;

        $latErr = 180 / (1 << $latBits);
        $lngErr = 360 / (1 << $lngBits);

        return [
            'lat' => $decoded['lat'],
            'lng' => $decoded['lng'],
            'bbox' => [
                'sw' => [
                    'lat' => round($decoded['lat'] - ($latErr / 2), 6),
                    'lng' => round($decoded['lng'] - ($lngErr / 2), 6)
                ],
                'ne' => [
                    'lat' => round($decoded['lat'] + ($latErr / 2), 6),
                    'lng' => round($decoded['lng'] + ($lngErr / 2), 6)
                ]
            ],
            'precision_m' => $this->getPrecisionMeters(strlen($hash))
        ];
    }

    /**
     * Export a geohash as GeoJSON with bounding box and optional center point.
     *
     * @param string $hash The geohash to export
     * @param bool $includeCenter Whether to include the center point feature
     * @param float $paddingMeters Optional padding around the bounding box in meters
     * @return string JSON-encoded GeoJSON FeatureCollection
     */
    public function toGeoJSON(string $hash, bool $includeCenter = true, float $paddingMeters = 0): string
    {
        $decoded = $this->decodeWithBoundingBox($hash);
        $bbox = $decoded['bbox'];

        if ($paddingMeters > 0) {
            $degPaddingLat = $paddingMeters / 111_320;
            $degPaddingLng = $paddingMeters / (111_320 * cos(deg2rad($decoded['lat'])));

            $bbox['sw']['lat'] -= $degPaddingLat;
            $bbox['sw']['lng'] -= $degPaddingLng;
            $bbox['ne']['lat'] += $degPaddingLat;
            $bbox['ne']['lng'] += $degPaddingLng;
        }

        $features = [];

        $features[] = [
            'type' => 'Feature',
            'properties' => ['type' => 'bbox', 'hash' => $hash],
            'geometry' => [
                'type' => 'Polygon',
                'coordinates' => [[
                    [$bbox['sw']['lng'], $bbox['sw']['lat']],
                    [$bbox['ne']['lng'], $bbox['sw']['lat']],
                    [$bbox['ne']['lng'], $bbox['ne']['lat']],
                    [$bbox['sw']['lng'], $bbox['ne']['lat']],
                    [$bbox['sw']['lng'], $bbox['sw']['lat']]
                ]]
            ]
        ];

        if ($includeCenter) {
            $features[] = [
                'type' => 'Feature',
                'properties' => ['type' => 'center', 'hash' => $hash],
                'geometry' => [
                    'type' => 'Point',
                    'coordinates' => [$decoded['lng'], $decoded['lat']]
                ]
            ];
        }

        return json_encode([
            'type' => 'FeatureCollection',
            'features' => $features
        ], JSON_PRETTY_PRINT);
    }

    // --- Internal Bit Operations ---

    /**
     * Normalize a coordinate value to an integer within the specified bit range.
     *
     * @param float $value The coordinate value to normalize
     * @param float $min The minimum value of the coordinate range
     * @param float $max The maximum value of the coordinate range
     * @param int $bits The number of bits to use for the normalized value
     * @return int Normalized integer value
     */
    private function normalize(float $value, float $min, float $max, int $bits): int
    {
        return (int) round((($value - $min) / ($max - $min)) * ((1 << $bits) - 1));
    }

    /**
     * Denormalize an integer value back to a coordinate within the specified range.
     *
     * @param int $value The normalized integer value
     * @param float $min The minimum value of the coordinate range
     * @param float $max The maximum value of the coordinate range
     * @param int $bits The number of bits used for normalization
     * @return float Denormalized coordinate value
     */
    private function denormalize(int $value, float $min, float $max, int $bits): float
    {
        return $min + ($value / ((1 << $bits) - 1)) * ($max - $min);
    }

    /**
     * Interleave latitude and longitude bits to create the geohash bit pattern.
     *
     * @param int $latBits Normalized latitude bits
     * @param int $lngBits Normalized longitude bits
     * @param int $latLen Number of latitude bits
     * @param int $lngLen Number of longitude bits
     * @return int Interleaved bit pattern
     */
    private function interleaveBits(int $latBits, int $lngBits, int $latLen, int $lngLen): int
    {
        $result = 0;
        $maxLen = max($latLen, $lngLen);

        for ($i = 0; $i < $maxLen; $i++) {
            // Longitude bit first (even positions)
            $result <<= 1;
            if ($i < $lngLen) $result |= ($lngBits >> ($lngLen - $i - 1)) & 1;

            // Latitude bit second (odd positions)
            $result <<= 1;
            if ($i < $latLen) $result |= ($latBits >> ($latLen - $i - 1)) & 1;
        }

        return $result;
    }

    /**
     * Split interleaved bits back into separate latitude and longitude components.
     *
     * @param int $bits The interleaved bit pattern
     * @param int $latLen Number of latitude bits expected
     * @param int $lngLen Number of longitude bits expected
     * @return array{0: int, 1: int} Array containing [latitude bits, longitude bits]
     */
    private function splitBits(int $bits, int $latLen, int $lngLen): array
    {
        $lat = 0;
        $lng = 0;
        $totalLen = max($latLen, $lngLen);

        for ($i = 0; $i < $totalLen; $i++) {
            // Extract longitude bit (even positions - rightmost bit of each pair)
            if ($i < $lngLen)
                $lng = ($lng << 1) | (($bits >> (($totalLen - $i - 1) * 2)) & 1);

            // Extract latitude bit (odd positions - second rightmost bit of each pair)
            if ($i < $latLen)
                $lat = ($lat << 1) | (($bits >> (($totalLen - $i - 1) * 2 + 1)) & 1);
        }

        return [$lat, $lng];
    }

    /**
     * Encode an integer bit pattern into a Base32 string.
     *
     * @param int $bits The bit pattern to encode
     * @param int $length The desired length of the resulting string
     * @return string Base32 encoded string
     */
    private function base32Encode(int $bits, int $length): string
    {
        $str = '';
        for ($i = $length - 1; $i >= 0; $i--) {
            $chunk = ($bits >> ($i * 5)) & 0b11111;
            $str .= self::BASE32[$chunk];
        }
        return $str;
    }

    /**
     * Decode a Base32 string back into an integer bit pattern.
     *
     * @param string $hash The Base32 string to decode
     * @return int Decoded bit pattern
     * @throws InvalidArgumentException If the hash contains invalid Base32 characters
     */
    private function base32Decode(string $hash): int
    {
        $val = 0;
        foreach (str_split($hash) as $char) {
            $index = strpos(self::BASE32, $char);
            if ($index === false) {
                throw new InvalidArgumentException("Invalid base32 character: $char");
            }
            $val = ($val << 5) | $index;
        }
        return $val;
    }
}
// --- End of GeoHash32 class ---