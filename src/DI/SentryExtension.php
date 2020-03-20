<?php

declare(strict_types=1);

namespace Rootpd\NetteSentry\DI;

use Nette\DI\CompilerExtension;
use Tracy\Debugger;
use Tracy\ILogger;

class SentryExtension extends CompilerExtension
{
    private const PARAM_DSN = 'dsn';
    private const PARAM_ENVIRONMENT = 'environment';
    private const PARAM_USER_FIELDS = 'user_fields';
    private const PARAM_PRIORITIES_MAPPING = 'priority_mapping';

    private $defaults = [
        self::PARAM_DSN => null,
        self::PARAM_ENVIRONMENT => 'local',
        self::PARAM_USER_FIELDS => [],
        self::PARAM_PRIORITIES_MAPPING => [],
    ];

    private $enabled = false;

    public function loadConfiguration()
    {
        $this->validateConfig($this->defaults);
        if (!$this->config[self::PARAM_DSN]) {
            Debugger::log('Unable to initialize SentryExtension, dsn config option is missing', ILogger::WARNING);
            return;
        }
        $this->enabled = true;

        $this->getContainerBuilder()
            ->addDefinition($this->prefix('logger'))
            ->setFactory(\Rootpd\NetteSentry\SentryLogger::class)
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
            )->addSetup(
                'setPriorityMapping',
                [
                    $this->config[self::PARAM_PRIORITIES_MAPPING],
                ]
            );
    }

    public function beforeCompile()
    {
        if (!$this->enabled) {
            return;
        }

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
        if (!$this->enabled) {
            return;
        }

        $class->getMethod('initialize')
            ->addBody('Tracy\Debugger::setLogger($this->getService(?));', [ $this->prefix('logger') ]);
    }
}
