<?php

declare(strict_types=1);

namespace Rootpd\NetteSentry\DI;

use Nette\DI\CompilerExtension;
use Nette\PhpGenerator\ClassType;
use Nette\Schema\Expect;
use Nette\Schema\Schema;
use Rootpd\NetteSentry\SentryLogger;
use Tracy\Debugger;
use Tracy\ILogger;

class SentryExtension extends CompilerExtension
{
    private bool $enabled = false;

    public function loadConfiguration()
    {
        if (!$this->config->dsn) {
            Debugger::log('Unable to initialize SentryExtension, dsn config option is missing', ILogger::WARNING);
            return;
        }
        $this->enabled = true;

        $this->getContainerBuilder()
            ->addDefinition($this->prefix('logger'))
            ->setFactory(SentryLogger::class, [Debugger::$logDirectory])
            ->addSetup(
                'register',
                [
                    $this->config->dsn,
                    $this->config->environment,
                ]
            )->addSetup(
                'setUserFields',
                [
                    $this->config->user_fields,
                ]
            )->addSetup(
                'setSessionSections',
                [
                    $this->config->session_sections,
                ]
            )->addSetup(
                'setPriorityMapping',
                [
                    $this->config->priority_mapping,
                ]
            );
    }

    public function getConfigSchema(): Schema
    {
        return Expect::structure([
            'dsn' => Expect::string()->dynamic()->default(null),
            'environment' => Expect::string()->dynamic()->default('local'),
            'user_fields' => Expect::listOf(Expect::string())->default([]),
            'session_sections' => Expect::listOf(Expect::string())->default([]),
            'priority_mapping' => Expect::arrayOf(Expect::string(), Expect::string())->default([]),
        ]);
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

    public function afterCompile(ClassType $class)
    {
        if (!$this->enabled) {
            return;
        }

        $class->getMethod('initialize')
            ->addBody('Tracy\Debugger::setLogger($this->getService(?));', [ $this->prefix('logger') ]);
    }
}
