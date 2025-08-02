<?php

namespace GeoHash32\Tests;

use GeoHash32\GeoHash32;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class GeoHash32Test extends TestCase
{
    private GeoHash32 $geo;

    protected function setUp(): void
    {
        $this->geo = new GeoHash32();
    }

    public function testBasicEncoding(): void
    {
        $this->geo->setHashLength(5);
        
        // Test Singapore coordinates
        $hash = $this->geo->encode(1.3521, 103.8198);
        $this->assertIsString($hash);
        $this->assertEquals(5, strlen($hash));
        
        // Test that encoding is deterministic
        $hash2 = $this->geo->encode(1.3521, 103.8198);
        $this->assertEquals($hash, $hash2);
    }

    public function testBasicDecoding(): void
    {
        $this->geo->setHashLength(7);
        
        // Encode and then decode
        $originalLat = 1.3521;
        $originalLng = 103.8198;
        
        $hash = $this->geo->encode($originalLat, $originalLng);
        $decoded = $this->geo->decode($hash);
        
        $this->assertIsArray($decoded);
        $this->assertArrayHasKey('lat', $decoded);
        $this->assertArrayHasKey('lng', $decoded);
        $this->assertArrayHasKey('bbox', $decoded);
        
        // Check that decoded coordinates are close to original (within precision)
        $this->assertEqualsWithDelta($originalLat, $decoded['lat'], 0.01);
        $this->assertEqualsWithDelta($originalLng, $decoded['lng'], 0.01);
    }

    public function testBoundingBox(): void
    {
        $hash = $this->geo->encode(40.7128, -74.0060); // NYC
        $decoded = $this->geo->decode($hash);
        
        $bbox = $decoded['bbox'];
        $this->assertArrayHasKey('sw', $bbox);
        $this->assertArrayHasKey('ne', $bbox);
        
        // SW corner should have smaller lat/lng than NE corner
        $this->assertLessThan($bbox['ne']['lat'], $bbox['sw']['lat']);
        $this->assertLessThan($bbox['ne']['lng'], $bbox['sw']['lng']);
        
        // Decoded point should be within bounding box
        $this->assertGreaterThanOrEqual($bbox['sw']['lat'], $decoded['lat']);
        $this->assertLessThanOrEqual($bbox['ne']['lat'], $decoded['lat']);
        $this->assertGreaterThanOrEqual($bbox['sw']['lng'], $decoded['lng']);
        $this->assertLessThanOrEqual($bbox['ne']['lng'], $decoded['lng']);
    }

    public function testDifferentHashLengths(): void
    {
        $lat = 51.5074;
        $lng = -0.1278; // London
        
        foreach ([1, 3, 5, 7, 9, 12] as $length) {
            $this->geo->setHashLength($length);
            $hash = $this->geo->encode($lat, $lng);
            
            $this->assertEquals($length, strlen($hash));
            
            $decoded = $this->geo->decode($hash);
            $this->assertIsArray($decoded);
        }
    }

    public function testPrecisionImprovement(): void
    {
        $lat = 35.6762;
        $lng = 139.6503; // Tokyo
        
        $this->geo->setHashLength(3);
        $hash3 = $this->geo->encode($lat, $lng);
        $decoded3 = $this->geo->decode($hash3);
        
        $this->geo->setHashLength(7);
        $hash7 = $this->geo->encode($lat, $lng);
        $decoded7 = $this->geo->decode($hash7);
        
        // Higher precision should have smaller bounding box
        $bbox3Size = ($decoded3['bbox']['ne']['lat'] - $decoded3['bbox']['sw']['lat']) * 
                    ($decoded3['bbox']['ne']['lng'] - $decoded3['bbox']['sw']['lng']);
        $bbox7Size = ($decoded7['bbox']['ne']['lat'] - $decoded7['bbox']['sw']['lat']) * 
                    ($decoded7['bbox']['ne']['lng'] - $decoded7['bbox']['sw']['lng']);
        
        $this->assertLessThan($bbox3Size, $bbox7Size);
    }

    public function testExtremeCoordinates(): void
    {
        $coordinates = [
            [90, 180],    // North Pole, East
            [-90, -180],  // South Pole, West
            [0, 0],       // Equator, Prime Meridian
            [-33.8688, 151.2093], // Sydney (Southern Hemisphere)
        ];
        
        foreach ($coordinates as [$lat, $lng]) {
            $hash = $this->geo->encode($lat, $lng);
            $decoded = $this->geo->decode($hash);
            
            $this->assertEqualsWithDelta($lat, $decoded['lat'], 1.0);
            $this->assertEqualsWithDelta($lng, $decoded['lng'], 1.0);
        }
    }

    public function testURLGeneration(): void
    {
        $hash = $this->geo->encode(48.8566, 2.3522); // Paris
        $baseUrl = 'https://maps.example.com';
        
        $url = $this->geo->toURL($hash, $baseUrl);
        
        $this->assertStringStartsWith($baseUrl, $url);
        $this->assertStringContainsString($hash, $url);
    }

    public function testGeoJSONGeneration(): void
    {
        $hash = $this->geo->encode(40.7589, -73.9851); // Times Square
        
        // Test without center point
        $geoJson = $this->geo->toGeoJSON($hash, false);
        $data = json_decode($geoJson, true);
        
        $this->assertEquals('FeatureCollection', $data['type']);
        $this->assertCount(1, $data['features']); // Only bounding box
        
        // Test with center point
        $geoJsonWithCenter = $this->geo->toGeoJSON($hash, true);
        $dataWithCenter = json_decode($geoJsonWithCenter, true);
        
        $this->assertEquals('FeatureCollection', $dataWithCenter['type']);
        $this->assertCount(2, $dataWithCenter['features']); // Bounding box + center
    }

    public function testInvalidHashDecoding(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->geo->decode('invalid@hash'); // Contains invalid character
    }

    public function testHashLengthBounds(): void
    {
        // Test minimum bound
        $this->geo->setHashLength(0);
        $hash = $this->geo->encode(0, 0);
        $this->assertEquals(1, strlen($hash)); // Should be clamped to 1
        
        // Test maximum bound
        $this->geo->setHashLength(20);
        $hash = $this->geo->encode(0, 0);
        $this->assertEquals(12, strlen($hash)); // Should be clamped to 12
    }

    public function testEncodingWithCustomLength(): void
    {
        // Test encoding with length parameter override
        $lat = 1.3521;
        $lng = 103.8198;
        
        $this->geo->setHashLength(5);
        
        $hash3 = $this->geo->encode($lat, $lng, 3);
        $hash7 = $this->geo->encode($lat, $lng, 7);
        
        $this->assertEquals(3, strlen($hash3));
        $this->assertEquals(7, strlen($hash7));
    }

    public function testBase32Alphabet(): void
    {
        // Test that generated hashes only contain valid base32 characters
        $validChars = '0123456789bcdefghjkmnpqrstuvwxyz';
        
        $coordinates = [
            [1.3521, 103.8198],   // Singapore
            [40.7128, -74.0060],  // NYC
            [51.5074, -0.1278],   // London
            [35.6762, 139.6503],  // Tokyo
        ];
        
        foreach ($coordinates as [$lat, $lng]) {
            $hash = $this->geo->encode($lat, $lng);
            
            for ($i = 0; $i < strlen($hash); $i++) {
                $char = $hash[$i];
                $this->assertStringContainsString($char, $validChars, 
                    "Invalid character '$char' found in hash '$hash'");
            }
        }
    }

    public function testConsistentRoundTrip(): void
    {
        // Test multiple round trips for consistency
        $coordinates = [
            [37.7749, -122.4194], // San Francisco
            [-33.4489, -70.6693], // Santiago
            [55.7558, 37.6176],   // Moscow
            [-26.2041, 28.0473],  // Johannesburg
        ];
        
        foreach ([3, 5, 7, 9] as $length) {
            $this->geo->setHashLength($length);
            
            foreach ($coordinates as [$lat, $lng]) {
                $hash1 = $this->geo->encode($lat, $lng);
                $decoded1 = $this->geo->decode($hash1);
                $hash2 = $this->geo->encode($decoded1['lat'], $decoded1['lng']);
                
                $this->assertEquals($hash1, $hash2, 
                    "Round trip failed for coordinates ($lat, $lng) with length $length");
            }
        }
    }
}
