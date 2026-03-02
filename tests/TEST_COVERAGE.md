# Test Coverage Guide

This document explains how to generate and view test coverage reports for the BCPDO System.

## Test Coverage Tools

### PHPUnit with Xdebug

To generate code coverage reports, you'll need:

1. **Xdebug** extension installed
2. **PHPUnit** installed via Composer

#### Installation

```bash
# Install PHPUnit via Composer
composer require --dev phpunit/phpunit

# Verify Xdebug is installed
php -m | grep xdebug
```

#### Generate Coverage Report

```bash
# Generate HTML coverage report
vendor/bin/phpunit --coverage-html coverage/

# Generate text coverage report
vendor/bin/phpunit --coverage-text

# Generate Clover XML (for CI/CD)
vendor/bin/phpunit --coverage-clover coverage.xml
```

#### View Coverage Report

After generating HTML coverage:
- Open `coverage/index.html` in your browser
- Navigate through files to see line-by-line coverage
- Check overall coverage percentage

## Current Test Coverage

### Test Files Created

1. **tests/unit_test.php** - Unit tests for critical functions
   - Input sanitization
   - Password hashing
   - Environment variables
   - Debug helpers
   - Data validation

2. **tests/integration_test.php** - Integration tests
   - Database connections
   - Environment variables
   - Helper functions
   - File structure

3. **tests/security_test.php** - Security tests
   - SQL injection protection
   - CSRF protection
   - Input sanitization
   - Password hashing

4. **tests/performance_test.php** - Performance benchmarks
   - Database queries
   - String operations
   - Environment variables
   - Debug logging

5. **tests/e2e_test.php** - End-to-end tests
   - User flows
   - File structure
   - Data flow

## Coverage Goals

### Current Status
- **Unit Tests**: Basic coverage for critical functions
- **Integration Tests**: System interaction coverage
- **Security Tests**: Security function coverage
- **Performance Tests**: Critical path benchmarking
- **E2E Tests**: User flow structure validation

### Target Coverage
- **Critical Functions**: 80%+ coverage
- **Security Functions**: 100% coverage
- **Business Logic**: 70%+ coverage
- **Overall**: 60%+ coverage

## Running All Tests

Use the test runner to execute all test suites:

```bash
php tests/run_all_tests.php
```

This will:
- Run all test suites
- Provide a summary of results
- Exit with appropriate code (0 for success, 1 for failure)

## CI/CD Integration

### GitHub Actions Example

```yaml
name: Tests

on: [push, pull_request]

jobs:
  test:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v2
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.0'
          extensions: xdebug
      - name: Run Tests
        run: php tests/run_all_tests.php
      - name: Generate Coverage
        run: vendor/bin/phpunit --coverage-clover coverage.xml
```

## Notes

- Test coverage is a measure of code quality, not completeness
- 100% coverage doesn't mean bug-free code
- Focus on testing critical paths and edge cases
- Update tests when adding new features

---

**Last Updated:** 2025-01-XX  
**Status:** Test infrastructure in place, coverage reporting available

