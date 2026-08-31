# Task 10 Brief: Code Quality - CI/CD Pipeline

## Task Description
Add CI/CD pipeline configuration for automated testing and quality checks.

## Files to Create

### Create: `.github/workflows/ci.yml`
```yaml
name: CI

on: [push, pull_request]

jobs:
  php:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with:
          php-version: '8.2'
          extensions: pdo_mysql
      - name: PHPUnit
        run: php phpunit10.phar

  php-lint:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with:
          php-version: '8.2'
      - name: PHP Syntax Check
        run: find public -name "*.php" -exec php -l {} \; 2>&1 | grep -v "No syntax errors"

  js-lint:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - uses: actions/setup-node@v4
        with:
          node-version: '20'
      - name: ESLint
        run: npm run lint
```

### Create: `phpunit.xml` (already exists, verify it has correct config)

## Requirements
1. GitHub Actions workflow for CI
2. PHP syntax check
3. PHPUnit test execution
4. Optional JS linting (if package.json exists)

## Acceptance Criteria
1. CI workflow runs on push/PR
2. PHP files are checked for syntax errors
3. PHPUnit tests can be run via CLI
4. Configuration files are well-documented

## Notes
- Since this is XAMPP local development, GitHub Actions will only work if the repo is pushed to GitHub
- PHPUnit is already set up with phpunit10.phar
- phpunit.xml already exists in the project
