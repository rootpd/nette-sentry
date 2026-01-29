# Nette Sentry

[![Build Status](https://app.travis-ci.com/rootpd/nette-sentry.svg?branch=master)](https://app.travis-ci.com/rootpd/nette-sentry)

Tracy logger extension capable of logging messages and errors to Sentry.

*Note*: If you have debug mode enabled in your application, logger will only send `\Tracy\Debugger::log()` messages to sentry. Any exception ending with Tracy's blue screen is not going to be logged as you can see the exception details directly.

## Installation

Install package via Composer:

```
composer require rootpd/nette-sentry
```

## Configuration

Enable and configure the extension in Nette config file:

```neon
extensions:
	# ...
	sentry: Rootpd\NetteSentry\DI\SentryExtension

sentry:
    dsn: https://123abc123abc123abc123abc123abc12@sentry.io/3 # required
    environment: production # optional, defaults to "local"
    user_fields: # optional, defaults to empty array; Nette's identity ID is being sent automatically
        - email
    session_sections: # optional, list of session sections to track
        - mySection
    priority_mapping: # optional, mapping of custom log levels to ones that's Sentry aware of
        mypriority: warning
    ignore_exceptions: # optional, list of exception class names to ignore
        - Tomaj\Hermes\Shutdown\ShutdownException
        - App\MyCustomException

    db_tracing: true # optional, defaults to "false", wraps DB connection so it adds span for every query
```

### Priority-Severity mapping

Sometimes you might need to use custom *priority* when logging the error in Nette:

```php
\Tracy\Debugger::log('foo', 'mypriority');
```

Sentry only allows strict set of severities. By default any message with unknown (non-standard) severity is not being logged.

You can map your custom *priority* to Sentry's *severity* in config by using `priority_mapping` as shown in the example.

The allowed set of Sentry severities can be checked in [Sentry's PHP repository](https://github.com/getsentry/sentry-php/blob/master/src/Severity.php).

### Ignoring exceptions

In some cases, you might want to prevent certain exceptions from being sent to Sentry while still logging them locally via Tracy. This is useful for expected exceptions like graceful shutdown signals or business logic exceptions that don't require monitoring.

Use `ignore_exceptions` config option to specify exception class names that should be ignored:

```neon
sentry:
    ignore_exceptions:
        - Tomaj\Hermes\Shutdown\ShutdownException
        - App\MyCustomException
```

Ignored exceptions will still be logged to Tracy's local log files but won't be sent to Sentry.

## Usage

Once enabled as extension, you can continue to throw exceptions without any change. To log message please use `\Tracy\Debugger::log()` method.