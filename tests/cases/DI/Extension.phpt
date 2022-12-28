<?php declare(strict_types = 1);

use Nette\Bridges\HttpDI\HttpExtension;
use Nette\Bridges\HttpDI\SessionExtension;
use Nette\Bridges\SecurityDI\SecurityExtension;
use Nette\DI\Compiler;
use Nette\DI\Container;
use Nette\DI\ContainerLoader;
use Nette\Http\Session;
use Nette\Security\IIdentity;
use Nette\Security\SimpleIdentity;
use Nette\Security\User;
use Rootpd\NetteSentry\DI\SentryExtension;
use Rootpd\NetteSentry\SentryLogger;
use Tester\Assert;

require __DIR__ . '/../../bootstrap.php';

// simple config
test(function (): void {
    $config = [
        'dsn' => 'https://123abc123abc123abc123abc123abc12@sentry.io/3',
    ];

    $loader = new ContainerLoader(TEMP_DIR, true);
    $class = $loader->load(function (Compiler $compiler) use ($config): void {
        $compiler->addExtension('sentry', new SentryExtension())
            ->addConfig([
                'sentry' => $config,
            ]);
    }, 1);

    /** @var Container $container */
    $container = new $class();

    /** @var SentryLogger $logger */
    $logger = $container->getService('sentry.logger');

    Assert::type(SentryLogger::class, $logger);

    Assert::with($logger, function () use ($config): void {
        Assert::same($config['dsn'], $this->getDsn());

        Assert::null($this->session);

        Assert::null($this->user);
        Assert::null($this->getIdentity());

        Assert::same([], $this->userFields);

        Assert::same([], $this->priorityMapping);
    });
});

// complex config
test(function (): void {
    $config = [
        'dsn' => 'https://123abc123abc123abc123abc123abc12@sentry.io/3',
        'environment' => 'test',
        'user_fields' => [
            'email',
        ],
        'session_sections' => [
            'my_section',
        ],
        'priority_mapping' => [
            'mypriority' => 'warning',
        ],
        'traces_sample_rate' => 0.5,
    ];

    $loader = new ContainerLoader(TEMP_DIR, true);
    $class = $loader->load(function (Compiler $compiler) use ($config): void {
        $compiler->addExtension('http', new HttpExtension());
        $compiler->addExtension('security', new SecurityExtension());
        $compiler->addExtension('session', new SessionExtension());

        $compiler->addExtension('sentry', new SentryExtension())
            ->addConfig([
                'sentry' => $config,
            ]);
    }, 2);

    /** @var Container $container */
    $container = new $class();

    $user = $container->getByType(User::class);
    $identity = new SimpleIdentity(1, null, ['email' => '']);
    $user->login($identity);

    /** @var SentryLogger $logger */
    $logger = $container->getService('sentry.logger');

    Assert::type(SentryLogger::class, $logger);

    Assert::with($logger, function () use ($config, $user, $identity): void {
        Assert::same($config['dsn'], $this->getDsn());
        Assert::same($config['environment'], $this->getEnvironment());

        Assert::type(Session::class, $this->session);

        Assert::type(User::class, $this->user);
        Assert::same($user, $this->user);
        Assert::type(IIdentity::class, $this->getIdentity());
        Assert::same($identity, $this->getIdentity());

        Assert::same($config['user_fields'], $this->userFields);
        Assert::same($config['session_sections'], $this->sessionSections);
        Assert::same($config['priority_mapping'], $this->priorityMapping);
        Assert::same($config['traces_sample_rate'], $this->tracesSampleRate);
    });
});
