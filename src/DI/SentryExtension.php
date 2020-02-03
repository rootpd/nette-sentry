<?php

declare(strict_types=1);

namespace Rootpd\NetteSentry\DI;

use Nette\DI\Compiler;
use Nette\DI\CompilerExtension;

class SentryExtension extends CompilerExtension
{
    const PARAM_DSN = 'dsn';
    const PARAM_ENVIRONMENT = 'environment';
    const PARAM_USER_FIELDS = 'user_fields';

    private $defaults = [
        self::PARAM_DSN => null,
        self::PARAM_ENVIRONMENT => 'local',
        self::PARAM_USER_FIELDS => [],
    ];

    public function loadConfiguration()
    {
        // load services from config and register them to Nette\DI Container
        Compiler::loadDefinitions(
            $this->getContainerBuilder(),
            $this->loadFromFile(__DIR__.'/../config/config.neon')['services']
        );

        $this->validateConfig($this->defaults);

        $this->getContainerBuilder()
            ->getDefinition($this->prefix('logger'))
            ->addSetup(
                'register',
                [
                    $this->config[self::PARAM_DSN],
                    $this->config[self::PARAM_ENVIRONMENT],
                ]
            )->addSetup(
                'setUserFields',
                [
                    $this->config[self::PARAM_USER_FIELDS],
                ]
            );
    }

    public function beforeCompile()
    {
        $builder = $this->getContainerBuilder();
        if ($builder->hasDefinition('tracy.logger')) {
            $builder->getDefinition('tracy.logger')->setAutowired(false);
        }
        if ($builder->hasDefinition('security.user')) {
            $builder->getDefinition($this->prefix('logger'))
                ->addSetup('setUser', [$builder->getDefinition('security.user')]);
        }
        if ($builder->hasDefinition('session.session')) {
            $builder->getDefinition($this->prefix('logger'))
                ->addSetup('setSession', [$builder->getDefinition('session.session')]);
        }
    }

    public function afterCompile(\Nette\PhpGenerator\ClassType $class)
    {
        $class->getMethod('initialize')
            ->addBody('Tracy\Debugger::setLogger($this->getService(?));', [ $this->prefix('logger') ]);
    }
}
