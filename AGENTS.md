# AGENTS.md - Development Guidelines for Chat Forum Project

This file contains comprehensive guidelines for agentic coding agents working on this PHP chat forum application.

## Project Overview

This is a PHP-based chat forum application built with a custom MVC architecture. It features user authentication, blog functionality, and private messaging between users. The project uses Composer for dependency management and follows PSR-4 autoloading standards.

## Essential Commands

### Testing
```bash
# Run all tests
composer test
./vendor/bin/phpunit

# Run specific test file
./vendor/bin/phpunit tests/Feature/AuthTest.php

# Run tests with coverage
./vendor/bin/phpunit --coverage-html coverage
```

### Development
```bash
# Install dependencies
composer install

# Update dependencies
composer update

# Start development server (if using PHP built-in server)
php -S localhost:8000 -t public/
```

## Code Structure & Architecture

### Directory Layout
- `src/` - Application source code (PSR-4 namespace: App\)
  - `controllers/` - Request handling and business logic
  - `models/` - Data models and database interactions
  - `views/` - Presentation templates
- `public/` - Web-accessible files
  - Entry point files (index.php, blog.php, etc.)
  - Static assets (css/, js/, uploads/)
- `config/` - Configuration files
- `tests/` - PHPUnit test files
- `scripts/` - Database migration scripts

### MVC Pattern
- **Controllers**: Handle HTTP requests, validate input, manage sessions
- **Models**: Interact with database, perform data operations
- **Views**: Render HTML output with embedded PHP

## Code Style Guidelines

### PHP Standards
- Use PSR-4 autoloading with `App\` namespace prefix
- PHP version compatibility: 7.4+ / 8.0+
- Use PDO for all database operations with prepared statements
- Implement proper error handling with try-catch blocks
- Use type hints where appropriate (PHP 7.4+ compatibility)

### Naming Conventions
- **Classes**: PascalCase (e.g., `AuthController`, `UserModel`)
- **Methods**: camelCase (e.g., `registerUser()`, `getPostById()`)
- **Variables**: camelCase (e.g., `$userId`, `$postTitle`)
- **Constants**: UPPER_SNAKE_CASE (e.g., `DB_HOST`, `APP_NAME`)
- **Database tables**: snake_case (e.g., `users`, `blog_posts`)

### File Organization
- One class per file
- Class name matches filename (e.g., `AuthController.php`)
- Keep controllers focused on request handling
- Models handle data operations only
- Views contain minimal PHP logic

## Security Practices

### Input Validation
- Always validate user inputs in controllers before processing
- Use filter functions for sanitization
- Validate required fields before database operations

### Password Security
- Always hash passwords using `password_hash()` with bcrypt
- Use cost factor of 12 for bcrypt (see User.php)
- Verify passwords with `password_verify()`

### Database Security
- Use PDO prepared statements for all queries
- Never concatenate user input into SQL queries
- Set `PDO::ERRMODE_EXCEPTION` for database connections

### Session Management
- Use `$_SESSION` for user authentication state
- Store minimal user data in session (id, name, admin status)
- Properly destroy sessions on logout

## Error Handling

### Database Errors
- Wrap database operations in try-catch blocks
- Use PDO exception mode for detailed error reporting
- Log errors appropriately without exposing sensitive data

### User-Facing Errors
- Provide meaningful error messages to users
- Use boolean returns for simple operations
- Return appropriate HTTP status codes when applicable

## Database Guidelines

### Connection Setup
- Define database constants in `config/config.php`
- Use PDO with UTF-8 encoding
- Enable exception mode for proper error handling

### Query Patterns
- Use prepared statements for all queries
- Fetch results with `PDO::FETCH_ASSOC`
- Always bind parameters to prevent SQL injection
- Close statements when complete

## Testing Guidelines

### Test Structure
- Place tests in `tests/` directory mirroring src/ structure
- Use PSR-4 autoloading for test classes
- Name test classes with `Test` suffix
- Use descriptive test method names

### Test Types
- **Unit Tests**: Test individual methods in isolation
- **Integration Tests**: Test controller-model interactions
- **Feature Tests**: Test complete user workflows

### Database Testing
- Use separate test database configuration
- Reset database state between tests
- Use fixtures or migrations for test data

## Frontend Standards

### CSS Organization
- Use BEM methodology for class naming
- Organize styles by component/section
- Use CSS custom properties for consistent theming
- Mobile-first responsive design

### JavaScript
- Use modern ES6+ features when appropriate
- Keep scripts modular and focused
- Handle errors gracefully
- Use semantic HTML5 elements

## Configuration Management

### Environment Variables
- Use `.env` files for environment-specific settings
- Load environment variables with `vlucas/phpdotenv`
- Never commit sensitive configuration to version control

### Constants
- Define application-wide constants in `config/config.php`
- Use descriptive constant names
- Group related constants together

## Commit Guidelines

### Commit Messages
- Use present tense ("Add feature" not "Added feature")
- Keep messages under 50 characters for title
- Provide detailed body when necessary
- Reference issue numbers when applicable

## Performance Considerations

### Database Optimization
- Use appropriate indexes for frequently queried columns
- Limit query results with LIMIT clauses
- Avoid N+1 query problems
- Use prepared statements consistently

### Caching
- Consider implementing simple caching for frequently accessed data
- Optimize database queries for large datasets
- Use appropriate HTTP caching headers

This file should be updated as the project evolves and new patterns emerge.