<?php
/**
 * GeoHash32 Library - Usage Examples
 * 
 * This file demonstrates how to use the GeoHash32 library for encoding
 * and decoding geographic coordinates into geohashes.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use GeoHash32\GeoHash32;

echo "=== GeoHash32 Library Examples ===\n\n";

// Initialize the library
$geo = new GeoHash32();

// Example 1: Basic Encoding and Decoding
echo "1. Basic Encoding and Decoding\n";
echo "------------------------------\n";

// Singapore coordinates
$lat = 1.3521;
$lng = 103.8198;

$geo->setHashLength(7);
$hash = $geo->encode($lat, $lng);

echo "Original coordinates: $lat, $lng\n";
echo "Encoded geohash: $hash\n";

$decoded = $geo->decode($hash);
echo "Decoded coordinates: {$decoded['lat']}, {$decoded['lng']}\n";
echo "Bounding box: SW({$decoded['bbox']['sw']['lat']}, {$decoded['bbox']['sw']['lng']}) to NE({$decoded['bbox']['ne']['lat']}, {$decoded['bbox']['ne']['lng']})\n\n";

// Example 2: Different Precision Levels
echo "2. Different Precision Levels\n";
echo "-----------------------------\n";

$coordinates = [
    'New York' => [40.7128, -74.0060],
    'London' => [51.5074, -0.1278],
    'Tokyo' => [35.6762, 139.6503],
    'Sydney' => [-33.8688, 151.2093]
];

foreach ([3, 5, 7, 9] as $precision) {
    echo "Precision level $precision:\n";
    $geo->setHashLength($precision);
    
    foreach ($coordinates as $city => $coords) {
        $hash = $geo->encode($coords[0], $coords[1]);
        echo "  $city: $hash\n";
    }
    echo "\n";
}

// Example 3: URL Generation
echo "3. URL Generation\n";
echo "----------------\n";

$geo->setHashLength(6);
$hash = $geo->encode(48.8566, 2.3522); // Paris coordinates

$url = $geo->toURL($hash, 'https://maps.google.com');
echo "Generated URL: $url\n\n";

// Example 4: QR Code Generation
echo "4. QR Code Generation\n";
echo "--------------------\n";

try {
    $qrCode = $geo->toQR($hash);
    echo "QR Code (base64): " . substr($qrCode, 0, 50) . "...\n";
    echo "You can use this in HTML: <img src=\"$qrCode\" alt=\"QR Code\" />\n\n";
} catch (Exception $e) {
    echo "QR Code generation requires additional dependencies: " . $e->getMessage() . "\n\n";
}

// Example 5: GeoJSON Export
echo "5. GeoJSON Export\n";
echo "----------------\n";

$geoJson = $geo->toGeoJSON($hash, true); // Include center point
echo "GeoJSON output:\n";
echo $geoJson . "\n\n";

// Example 6: Clustering Example
echo "6. Location Clustering Example\n";
echo "------------------------------\n";

$locations = [
    ['name' => 'Central Park', 'lat' => 40.7829, 'lng' => -73.9654],
    ['name' => 'Times Square', 'lat' => 40.7580, 'lng' => -73.9855],
    ['name' => 'Brooklyn Bridge', 'lat' => 40.7061, 'lng' => -73.9969],
    ['name' => 'Statue of Liberty', 'lat' => 40.6892, 'lng' => -74.0445],
];

$geo->setHashLength(5); // City-level clustering

echo "NYC Locations with Geohashes (length 5):\n";
$clusters = [];

foreach ($locations as $location) {
    $hash = $geo->encode($location['lat'], $location['lng']);
    $clusters[$hash][] = $location['name'];
    echo "  {$location['name']}: $hash\n";
}

echo "\nClusters (locations with same geohash prefix):\n";
foreach ($clusters as $hash => $places) {
    if (count($places) > 1) {
        echo "  $hash: " . implode(', ', $places) . "\n";
    }
}

echo "\n=== Examples Complete ===\n";
