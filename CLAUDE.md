# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

ApiDog is a Swagger library for the Hyperf PHP framework that:
1. Automatically validates API parameters based on annotations
2. Automatically generates Swagger documentation from annotations

The package is designed for Hyperf 3.0+ and requires PHP 8.0+.

## Core Architecture

### Component Structure

**Annotation System** (`src/Annotation/`)
- API method annotations: `GetApi`, `PostApi`, `PutApi`, `DeleteApi`
- Parameter annotations: `Header`, `Query`, `Body`, `FormData`, `Path`
- Metadata annotations: `ApiController`, `ApiResponse`, `ApiVersion`, `ApiServer`, `ApiDefinition`, `ApiDefinitions`
- All parameters support Hyperf/Laravel validation rules

**Request Validation Flow**
1. `ApiValidationMiddleware` intercepts HTTP requests
2. Uses `ValidationApi` to validate against annotation rules
3. Returns validation errors or passes to controller
4. Validation leverages Hyperf's native validation system

**Swagger Generation Flow**
1. `BootAppConfListener` listens to `BootApplication` event
2. Scans all HTTP routes via `DispatcherFactory`
3. `SwaggerJson` builds swagger.json from controller annotations
4. Outputs to configured path (default: `public/swagger/swagger.json`)

### Key Files

- `src/ConfigProvider.php` - Hyperf service provider registration
- `src/BootAppConfListener.php` - Triggers swagger.json generation on app boot
- `src/Middleware/ApiValidationMiddleware.php` - Validates requests against annotations
- `src/Swagger/SwaggerJson.php` - Generates swagger.json structure
- `src/Validation/ValidationApi.php` - Validation logic
- `publish/apidog.php` - Configuration template

## Development Commands

### Testing
```bash
composer test           # Run PHPUnit tests
composer analyse        # Run PHPStan static analysis
composer cs-fix         # Run PHP-CS-Fixer for code style
```

### Swagger UI
```bash
# Start built-in Swagger UI server (for development)
php bin/hyperf.php apidog:ui
php bin/hyperf.php apidog:ui --port 8888
```

### Configuration Publishing (in Hyperf projects)
```bash
php bin/hyperf.php vendor:publish daodao97/apidog
php bin/hyperf.php vendor:publish hyperf/translation
php bin/hyperf.php vendor:publish hyperf/validation
```

## Configuration

Configuration file: `config/autoload/apidog.php` (published from `publish/apidog.php`)

Key settings:
- `enable` - Toggle swagger generation (default: disabled in production)
- `output_file` - Swagger JSON output path (supports `{server}` variable for multi-server setups)
- `swagger` - Swagger 2.0 base configuration (info, host, schemes)
- `global` - Global parameter rules applied to all endpoints
- `templates` - Response templates for reuse
- `exception_enable` - Toggle exception throwing vs JSON error response

## Validation Rules

Validation rules follow Hyperf/Laravel syntax:
- Standard rules: `required`, `integer`, `max:10`, `in:0,1`, `array`, `file`
- Nested rules: `a.b.c`, `a.*.e` for deep validation
- Custom callback: `cb_methodName` calls controller method for custom validation
- Return `true` for valid, return error string for invalid

## Annotation Patterns

Controller-level:
```php
@ApiController(tag="...", description="...")
@ApiVersion(version="v1")  // Adds /v1 prefix to routes
@ApiServer(name="http")    // For multi-server swagger generation
```

Method-level:
```php
@PostApi(path="/demo", description="...")
@Header(key="token|label", rule="required")
@FormData(key="name|label", rule="required|max:10")
@Body(rules={...})  // Note: rules is array
@ApiResponse(code="0", description="...", schema={...})
@ApiResponse(code="0", schema={"$ref": "DefinitionName"})
```

Definition reuse:
```php
@ApiDefinitions({
    @ApiDefinition(name="...", properties={...}),
    @ApiDefinition(name="...", properties={...})
})
```

## Testing Matrix

CI tests against:
- PHP: 8.0, 8.1, 8.2
- Engines: none, swoole, swow
- Swoole version: 5.0.1
- Platform: Ubuntu latest
