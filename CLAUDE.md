# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is `xibosignage/support`, a PHP utility library that provides reusable support classes for the Xibo Digital Signage Platform. It covers input sanitization, database abstraction, CSRF/nonce management, logging integrations, and exception handling with HTTP-aware responses.

## Common Commands

```bash
# Install dependencies
composer install

# Run tests
composer test

# Run tests with coverage report (requires Xdebug)
composer test:coverage

# Run code style checks (PSR-2 based, with Xibo customisations)
composer lint
```

## Architecture

### Module Breakdown (`src/Xibo/Support/`)

**Database/**
`PdoStorageService` implements `StorageServiceInterface` providing a PDO/MySQL wrapper with named connection pooling, transaction management, and deadlock retry logic (max 2 retries via `DeadLockException`).

**Exception/**
Seventeen custom exceptions all extend `GeneralException`. Each maps to an HTTP status (401, 403, 404, etc.) and can render a JSON HTTP response via `generateHttpResponse(ResponseInterface $response)`. This is the primary error-propagation mechanism across the Xibo platform.

**Nonce/**
CSRF protection via bcrypt-hashed nonces stored through `StorageServiceInterface`. `CsrfMiddleware` is a PSR-7 middleware that validates nonces on incoming requests. `Nonce` entities support expiration, metadata, and JSON serialisation.

**Sanitizer/**
`RespectSanitizer` wraps Respect\Validation and Symfony HtmlSanitizer to provide typed, validated input access (`getInt`, `getString`, `getDate`, `getHtml`, etc.). Callers pass an associative array of raw input; the sanitizer enforces types and optionally throws on validation failure.

**Validator/**
Thin wrapper (`RespectValidator`) around Respect\Validation for standalone rule checks separate from sanitization.

**Monolog/**
Two Monolog integrations: `RocketChatHandler` (webhook log posting) and `ProxyIpProcessor` (real-IP extraction from proxy headers).

### Coding Standards

The custom PHPCS ruleset at `src/Standards/xibo_ruleset.xml` extends PSR-2 with:
- Double-quoted strings enforced (Squiz rule)
- Forbidden functions: `delete`, `print`, `create_function`
- Line length checks ignore comments
- `ElseIfDeclaration` rule excluded

PHP platform target is **8.1+**; CI runs against **8.4**.

### Test Suite

Tests live under `tests/` and mirror the `src/Xibo/Support/` module structure. Key notes:

- `tests/Database/PdoStorageServiceSqliteTest.php` uses SQLite in-memory via a `connect()` override — no MySQL needed for the happy-path tests.
- `tests/Database/PdoStorageServiceMockTest.php` mocks PDO to exercise reconnect (error 2006) and deadlock retry (errors 1213/1205) paths.
- `tests/Nonce/CsrfMiddlewareTest.php` saves and restores `$_SESSION` in setUp/tearDown rather than using process isolation.
- `RespectSanitizer::getString` uses `strip_tags` (removes tags, does not encode entities). `getHtml` uses Symfony HtmlSanitizer (preserves safe tags). These behave differently and have separate tests.
- `getCheckbox` never throws and uses loose `!= null` comparison for the `default` option — see test comments.
- `getDate` uses loose `== null` for the missing-value check (so empty string is treated as missing), unlike the other getters which use strict `===`.
