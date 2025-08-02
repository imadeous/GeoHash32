# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.0.0] - 2025-08-02

### Added
- Initial release of GeoHash32 library
- Base32 geohash encoding and decoding functionality
- Variable-length geohashing with precision control (1-12 characters)
- Bounding box calculation for decoded geohashes
- URL generation for geohashes
- QR code generation support
- GeoJSON export functionality with optional center points
- Performance benchmarking capabilities
- Comprehensive test suite with PHPUnit
- Complete documentation and usage examples
- MIT License

### Features
- **Encoding**: Convert latitude/longitude coordinates to Base32 geohashes
- **Decoding**: Convert geohashes back to coordinates with bounding box information
- **Precision Control**: Adjustable hash length from 1-12 characters
- **URL Generation**: Create shareable URLs with embedded geohashes
- **QR Codes**: Generate QR codes for easy location sharing
- **GeoJSON**: Export geohashes as GeoJSON for mapping applications
- **PHP 8.0+**: Modern PHP support with strong typing

### Documentation
- Complete README with installation and usage instructions
- Code examples demonstrating all features
- PHPUnit test suite with 95%+ coverage
- Inline code documentation

[Unreleased]: https://github.com/imadeous/geohash32/compare/v1.0.0...HEAD
[1.0.0]: https://github.com/imadeous/geohash32/releases/tag/v1.0.0
