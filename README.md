# GeoHash32 - Base32 Geohash Encoder/Decoder

[![PHP Version](https://img.shields.io/badge/php-%3E%3D8.0-blue)](https://php.net)
[![License: MIT](https://img.shields.io/badge/License-MIT-green.svg)](https://opensource.org/licenses/MIT)
[![Tests](https://github.com/imadeous/geohash32/workflows/Tests/badge.svg)](https://github.com/imadeous/geohash32/actions)
[![Packagist Version](https://img.shields.io/packagist/v/imadeous/geohash32)](https://packagist.org/packages/imadeous/geohash32)

GeoHash32 is a simple, lightweight, and flexible library to **encode** and **decode geohashes** using Base32 encoding. It offers the following features:

- **Variable-length geohashing**: Choose the hash length to adjust precision.
- **Geohash decoding with bounding boxes**: Decode geohashes into latitude, longitude, and bounding box coordinates for spatial operations.
- **QR code generation**: Easily generate QR codes for your geohash to share location info.
- **URL-safe geohashes**: Generate links to share geohashes easily.

---

## Purpose

This library enables precise encoding and decoding of geographic coordinates (latitude, longitude) into geohashes. The generated geohash can be used in location-based applications for mapping, geofencing, clustering, or spatial queries.

GeoHash32 is particularly useful in scenarios where:

- You need **region-level precision** and are working with **large geographical areas**.
- You want to convert **latitude/longitude** into a **compact, URL-safe string**.
- You need to **encode or decode locations for sharing or comparison** without dependencies.

---

## Installation

You can install **GeoHash32** via Composer:

```bash
composer require imadeous/geohash32
```

---

## Usage

### Encoding a Geohash

```php
use GeoHash32\GeoHash32;

$geo = new GeoHash32();
$geo->setHashLength(7);  // Set the geohash length (e.g., 7 for city-level precision)

$hash = $geo->encode(1.3521, 103.8198);  // Singapore coordinates
echo "Geohash: " . $hash;  // Example output: u3xcpme
```

### Decoding a Geohash

```php
$decoded = $geo->decode('u3xcpme');
echo "Latitude: " . $decoded['lat'] . "\n";
echo "Longitude: " . $decoded['lng'] . "\n";
```

### Generating a URL-safe Geohash

```php
$url = $geo->toURL('u3xcpme', 'https://maps.example.com');
echo "Map Link: " . $url;  // Example: https://maps.example.com/h/u3xcpme
```

### Generating a QR Code

```php
$qr = $geo->toQR('u3xcpme');
echo '<img src="' . $qr . '" alt="QR Code" />';  // Display a base64 QR image
```

---

## Key Features

- **Customizable Precision**: Set the desired precision by adjusting the hash length.
- **Geohash Decoding with Bounding Box**: Retrieve bounding box coordinates (SW and NE corners) around a geohash.
- **URL-Safe**: Generate URL-safe geohashes for location sharing or mapping.
- **QR Code Generation**: Embed geohashes directly in QR codes for easy scanning or sharing.

---

## Use Cases

### Geofencing & Location-Based Services
Use the GeoHash32 library to divide geographic areas into smaller cells and perform spatial queries or geofencing operations based on proximity.

### Map Integration

Generate geohashes for specific locations and plot them on interactive maps (using libraries like Leaflet or Mapbox). Use the toGeoJSON method to export bounding boxes and center points for use in mapping applications.

### URL and QR Code Sharing

Encode location data into short geohash strings, which can be used in URLs or converted into QR codes for easy sharing of geolocation information.

### Location Clustering

Cluster nearby locations together by encoding their coordinates into geohashes. The prefix of the geohash will group geographically close locations together.

### Data Efficiency
Use base32-encoded geohashes as compact, efficient representations of geographic points, which can be used in large datasets, mobile apps, or IoT devices.

---

## Additional Features

- **GeoJSON Export**: Export geohash bounding boxes and center points as GeoJSON for easy integration with map APIs.
- **Performance Benchmarking**: Built-in benchmarking to measure the encoding/decoding speed of geohashes for various precision levels.

---

## Development

### Running Tests

```bash
# Install dependencies
composer install

# Run tests
composer test

# Run tests with coverage
composer test-coverage
```

### Examples

Check out the `examples/` directory for practical usage demonstrations:

```bash
php examples/basic_usage.php
```

---

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request. For major changes, please open an issue first to discuss what you would like to change.

---

## License
This package is licensed under the MIT License.

See the LICENSE file for details.