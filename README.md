# Nette Sentry

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
    priority_mapping:
        mypriority: warning
```

### Priority-Severity mapping

Sometimes you might need to use custom *priority* when logging the error in Nette:

```php
\Tracy\Debugger::log('foo', 'mypriority');
```

Sentry only allows strict set of severities. By default any message with unknown (non-standard) severity is not being logged.

You can map your custom *priority* to Sentry's *severity* in config by using `priority_mapping` as shown in the example.

The allowed set of Sentry severities can be checked in [Sentry's PHP repository](https://github.com/getsentry/sentry-php/blob/master/src/Severity.php).  

## Usage

Once enabled as extension, you can continue to throw exceptions without any change. To log message please use `\Tracy\Debugger::log()` method.