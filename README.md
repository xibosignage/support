# Xibo Support Library

A PHP utility library providing foundational support classes for the [Xibo Digital Signage Platform](https://xibosignage.com). Consumed by Xibo CMS and related services as a Composer package.

## Modules

### Sanitizer
Type-safe, validated input access. Pass an associative array of raw input (e.g. from a HTTP request) and retrieve values as the expected type:

```php
$san = (new RespectSanitizer())->setCollection($request->getParams());

$id      = $san->getInt('id');
$name    = $san->getString('name');
$body    = $san->getHtml('body');       // Symfony HtmlSanitizer — preserves safe tags
$enabled = $san->getCheckbox('active');
$date    = $san->getDate('from', ['dateFormat' => 'Y-m-d']);
```

All getters accept an `$options` array for defaults, custom Respect\Validation rules, and configurable exception throwing (`throw`, `throwClass`, `throwMessage`).

**Note:** `getString` uses `strip_tags` (removes all tags, does not encode entities). `getHtml` uses Symfony HtmlSanitizer and is the correct choice when HTML content must be preserved safely.

### Validator
Standalone boolean validation using Respect\Validation, separate from sanitization:

```php
$v = new RespectValidator();
$v->int('42');            // true
$v->double('3.14');       // true
$v->string('hello', ['Length' => [1, 100]]); // true
```

### Exception
Seventeen domain exceptions extending `GeneralException`, each with a fixed HTTP status code and a `generateHttpResponse(ResponseInterface)` method that writes a JSON error body:

| Exception | Status |
|-----------|--------|
| `AuthenticationRequiredException` | 401 |
| `AccessDeniedException` | 403 |
| `NotFoundException` | 404 |
| `DuplicateEntityException` | 409 |
| `InvalidArgumentException` | 422 |
| Everything else | 500 |

### Nonce
CSRF protection built on bcrypt-hashed nonces stored via `StorageServiceInterface`:

```php
// Create and persist
$nonce = $nonceService->create($entityId, 'upload', 300);
$nonceService->persist($nonce);
$token = $nonce->getCompleteNonce(); // "plaintextNonce:::lookup"

// Verify later
$verified = $nonceService->getSplitVerified($token, 'upload');
```

`CsrfMiddleware` is a PSR-7 middleware that validates `X-XSRF-TOKEN` headers (or a body parameter) on POST/PUT/DELETE requests against a session-stored token.

### Database
`PdoStorageService` is a PDO/MySQL wrapper with named connection pooling, automatic transaction management on writes, reconnect handling (MySQL error 2006 and `ErrorException` "QUERY packet" failures), and deadlock retry logic (errors 1213/1205, max 2 retries).

Connections are opened with native (non-emulated) prepared statements and the `utf8mb4` charset declared in the DSN. Bound parameter values are never written to logs — the SQL string is logged with placeholders intact and parameter keys appear in the log context only.

```php
$db = new PdoStorageService($logger, [
    'host' => 'db.internal:3306',
    'user' => 'app',
    'pass' => $secret,
    'name' => 'app_db',
    'ssl'  => '/etc/ssl/certs/ca-bundle.crt', // optional, omit or 'none' to disable
    'sslVerify' => true,                       // optional, default true
]);

$db->insert('INSERT INTO t (name) VALUES (:name)', [':name' => 'x']);
$db->commitIfNecessary();

$rows = $db->select('SELECT * FROM t WHERE id = :id', [':id' => 1]);

// Deadlock-safe write with automatic retry
$db->updateWithDeadlockLoop('UPDATE t SET val = :v WHERE id = :id', [...]);

// Per-call flags: $reconnect (retry on 2006), $transaction (begin txn if none),
// $close (release the connection after the call). Useful for long-running scripts.
$db->insert($sql, $params, 'default', false, true, true);

// setTimeZone validates against an allow-list (numeric offsets like '-08:00'
// or IANA-style names like 'Europe/London'). Anything else throws InvalidArgumentException.
$db->setTimeZone('Europe/London');
```

### Monolog
- **`RocketChatHandler`** — posts log records to a Rocket.Chat inbound webhook. Colour-coded by level (red ≥ ERROR, yellow = WARNING, green ≥ INFO, grey = DEBUG).
- **`ProxyIpProcessor`** — adds the real client IP to each log record by inspecting `X_FORWARDED_FOR`, `HTTP_X_FORWARDED_FOR`, `CLIENT_IP`, and `REMOTE_ADDR` in that order.

## Installation

```bash
composer require xibosignage/support
```

Optional dependencies (required for specific modules):

```bash
composer require nesbot/carbon          # RespectSanitizer::getDate()
composer require respect/validation     # RespectSanitizer and RespectValidator
composer require monolog/monolog        # RocketChatHandler and ProxyIpProcessor
```

## Development

```bash
composer install
composer test          # run PHPUnit test suite
composer test:coverage # run with Clover coverage report (requires Xdebug)
composer lint          # PHPCS code style check
```
