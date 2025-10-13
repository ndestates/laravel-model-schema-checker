# Contributing

Thank you for considering contributing to the Laravel Model-Database Schema Checker! We welcome contributions from everyone.

## How to Contribute

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add some amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

## Development Setup

1. Clone the repository:
   ```bash
   git clone https://github.com/ndestates/laravel-model-schema-checker.git
   cd laravel-model-schema-checker
   ```

2. Install dependencies:
   ```bash
   composer install
   ```

3. Run tests:
   ```bash
   composer test
   ```

4. Create a test Laravel project for integration testing:
   ```bash
   composer create-project laravel/laravel test-project
   cd test-project
   composer config repositories.local path ../
   composer require ndestates/laravel-model-schema-checker *@dev --dev
   ```

## Code Standards

- Follow PSR-12 coding standards
- Write tests for new features
- Update documentation as needed
- Ensure all tests pass before submitting PR

## Testing

Run the test suite:
```bash
composer test
```

For integration testing with a real Laravel project:
```bash
cd test-project
php check.php --check-all
```

## Reporting Issues

When reporting issues, please include:
- Laravel version
- PHP version
- Database type and version
- Steps to reproduce
- Expected vs actual behavior
- Any relevant error messages or logs

## Feature Requests

Feature requests are welcome! Please provide:
- Clear description of the feature
- Use case and benefits
- Any relevant examples or mockups