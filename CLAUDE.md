# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is `xibosignage/support`, a PHP utility library that provides reusable support classes for the Xibo Digital Signage Platform. It covers input sanitization, database abstraction, CSRF/nonce management, logging integrations, and exception handling with HTTP-aware responses.

## Common Commands

```bash
# Install dependencies
composer install

# Run code style checks (PSR-2 based, with Xibo customisations)
vendor/bin/phpcs --standard=src/Standards/xibo_ruleset.xml src/
```

There is no automated test suite in this repository — correctness is validated by the consuming applications.

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

PHP platform target is **8.1+**.
