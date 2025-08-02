<?php

namespace GeoHash32;

use InvalidArgumentException;
use RuntimeException;

/**
 * GeoHash32 class for encoding and decoding geographic coordinates into a 32-bit geohash.
 * Supports encoding, decoding, URL generation, QR code generation, and GeoJSON output.
 */

class GeoHash32
{
    private int $hashLength = 5;
    private const BASE32 = '0123456789bcdefghjkmnpqrstuvwxyz';
    private const BASE = 32;

    // --- Configuration ---

    public function setHashLength(int $length): self
    {
        $this->hashLength = max(1, min(12, $length));
        return $this;
    }

    // --- Encode/Decode ---

    public function encode(float $lat, float $lng, ?int $len = null): string
    {
        $len = $len ?? $this->hashLength;
        $totalBits = $len * 5;

        $latBits = (int) floor($totalBits / 2);
        $lngBits = $totalBits - $latBits;

        $latNorm = $this->normalize($lat, -90, 90, $latBits);
        $lngNorm = $this->normalize($lng, -180, 180, $lngBits);

        $bits = $this->interleaveBits($latNorm, $lngNorm, $latBits, $lngBits);

        return $this->base32Encode($bits, $len);
    }

    public function decode(string $hash): array
    {
        $totalBits = strlen($hash) * 5;
        $latBits = (int) floor($totalBits / 2);
        $lngBits = $totalBits - $latBits;

        $bits = $this->base32Decode($hash);
        [$latBitsVal, $lngBitsVal] = $this->splitBits($bits, $latBits, $lngBits);

        $lat = $this->denormalize($latBitsVal, -90, 90, $latBits);
        $lng = $this->denormalize($lngBitsVal, -180, 180, $lngBits);

        return ['lat' => round($lat, 6), 'lng' => round($lng, 6)];
    }

    // --- Utilities ---

    public function toURL(string $hash, string $base = ''): string
    {
        $base = rtrim($base, '/');
        return $base . '/h/' . $hash;
    }

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

    public function suggestLengthForPrecision(float $targetMeters): int
    {
        for ($len = 1; $len <= 12; $len++) {
            if ($this->getPrecisionMeters($len) <= $targetMeters) {
                return $len;
            }
        }
        return 12;
    }

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

    private function normalize(float $value, float $min, float $max, int $bits): int
    {
        return (int) round((($value - $min) / ($max - $min)) * ((1 << $bits) - 1));
    }

    private function denormalize(int $value, float $min, float $max, int $bits): float
    {
        return $min + ($value / ((1 << $bits) - 1)) * ($max - $min);
    }

    private function interleaveBits(int $latBits, int $lngBits, int $latLen, int $lngLen): int
    {
        $result = 0;
        $maxLen = max($latLen, $lngLen);

        for ($i = 0; $i < $maxLen; $i++) {
            $result <<= 1;
            if ($i < $latLen) $result |= ($latBits >> ($latLen - $i - 1)) & 1;
            $result <<= 1;
            if ($i < $lngLen) $result |= ($lngBits >> ($lngLen - $i - 1)) & 1;
        }

        return $result;
    }

    private function splitBits(int $bits, int $latLen, int $lngLen): array
    {
        $lat = 0;
        $lng = 0;
        $totalLen = max($latLen, $lngLen);

        for ($i = 0; $i < $totalLen; $i++) {
            if ($i < $latLen)
                $lat = ($lat << 1) | (($bits >> (($totalLen - $i - 1) * 2 + 1)) & 1);
            if ($i < $lngLen)
                $lng = ($lng << 1) | (($bits >> (($totalLen - $i - 1) * 2)) & 1);
        }

        return [$lat, $lng];
    }

    private function base32Encode(int $bits, int $length): string
    {
        $str = '';
        for ($i = $length - 1; $i >= 0; $i--) {
            $chunk = ($bits >> ($i * 5)) & 0b11111;
            $str .= self::BASE32[$chunk];
        }
        return $str;
    }

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