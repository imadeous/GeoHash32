# Contributing to GeoHash32

Thank you for considering contributing to GeoHash32! We welcome contributions from everyone.

## How to Contribute

### Reporting Bugs

If you find a bug, please create an issue with:
- A clear description of the problem
- Steps to reproduce the issue
- Expected vs actual behavior
- PHP version and OS information

### Suggesting Features

Feature requests are welcome! Please create an issue with:
- A clear description of the feature
- Why you think it would be useful
- Any implementation ideas you might have

### Code Contributions

1. **Fork the repository**
2. **Create a feature branch**: `git checkout -b my-new-feature`
3. **Make your changes**
4. **Add tests** for your changes
5. **Run the test suite**: `composer test`
6. **Commit your changes**: `git commit -am 'Add some feature'`
7. **Push to the branch**: `git push origin my-new-feature`
8. **Create a Pull Request**

## Development Setup

```bash
# Clone your fork
git clone https://github.com/your-username/geohash32.git
cd geohash32

# Install dependencies
composer install

# Run tests
composer test
```

## Code Style

- Follow PSR-12 coding standards
- Add PHPDoc comments for all public methods
- Include tests for new functionality
- Keep backwards compatibility when possible

## Testing

- All new features must include tests
- Ensure existing tests pass
- Aim for high test coverage
- Test edge cases and error conditions

## Questions?

Feel free to create an issue for any questions about contributing!
