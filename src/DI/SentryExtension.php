<?php

declare(strict_types=1);

namespace Rootpd\NetteSentry\DI;

use Nette\DI\CompilerExtension;
use Nette\DI\Definitions\ServiceDefinition;
use Nette\DI\Definitions\Statement;
use Nette\PhpGenerator\ClassType;
use Nette\Schema\Expect;
use Nette\Schema\Schema;
use Rootpd\NetteSentry\ApplicationMonitor;
use Rootpd\NetteSentry\SentryLogger;
use Sentry\Integration\EnvironmentIntegration;
use Sentry\Integration\TransactionIntegration;
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

        $logger = $this->getContainerBuilder()
            ->addDefinition($this->prefix('logger'))
            ->setFactory(SentryLogger::class, [Debugger::$logDirectory]);

        // configure logger before registering the Sentry SDK

        $logger
            ->addSetup('setUserFields', [$this->config->user_fields])
            ->addSetup('setSessionSections', [$this->config->session_sections])
            ->addSetup('setPriorityMapping', [$this->config->priority_mapping]);

        if ($this->config->traces_sample_rate !== null) {
            $logger->addSetup('setTracesSampleRate', [$this->config->traces_sample_rate]);

            $this->getContainerBuilder()
                ->addDefinition($this->prefix('applicationMonitor'))
                ->setFactory(ApplicationMonitor::class);

            /** @var ServiceDefinition $application */
            $application = $this->getContainerBuilder()->getDefinition('application.application');
            $application->addSetup('@Rootpd\NetteSentry\ApplicationMonitor::hook', ['@self']);

            if ($this->config->profiles_sample_rate !== null) {
                $logger->addSetup('setProfilesSampleRate', [$this->config->profiles_sample_rate]);
            }

            $integrations[] = new Statement(TransactionIntegration::class);
            $integrations[] = new Statement(EnvironmentIntegration::class);
        }

        // register Sentry SDK

        $logger->addSetup('register', [
            $this->config->dsn,
            $this->config->environment,
            $integrations,
        ]);
    }

    public function getConfigSchema(): Schema
    {
        return Expect::structure([
            'dsn' => Expect::string()->dynamic(),
            'environment' => Expect::string('local')->dynamic(),
            'user_fields' => Expect::listOf(Expect::string())->default([]),
            'session_sections' => Expect::listOf(Expect::string())->default([]),
            'priority_mapping' => Expect::arrayOf(Expect::string(), Expect::string())->default([]),
            'traces_sample_rate' => Expect::float()->dynamic(),
            'profiles_sample_rate' => Expect::float()->dynamic(),
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
