<?php

declare(strict_types=1);

namespace Rootpd\NetteSentry;

use Nette\Http\Session;
use Nette\Security\IIdentity;
use Nette\Security\User;
use Sentry\Integration\RequestIntegration;
use Sentry\Severity;
use Sentry\State\Scope;
use Throwable;
use Tracy\Debugger;
use Tracy\Dumper;
use Tracy\ILogger;
use Tracy\Logger;
use function Sentry\captureException;
use function Sentry\captureMessage;
use function Sentry\configureScope;
use function Sentry\init;

class SentryLogger extends Logger
{
    /** @var IIdentity */
    private $identity;

    /** @var Session */
    private $session;

    /** @var array */
    private $userFields = [];

    /** @var array */
    private $priorityMapping = [];

    public function register(string $dsn, string $environment)
    {
        init([
            'dsn' => $dsn,
            'environment' => $environment,
            'default_integrations' => false,
            'integrations' => [
                new RequestIntegration(),
            ],
        ]);

        $this->email = & Debugger::$email;
        $this->directory = Debugger::$logDirectory;
    }

    public function setUser(User $user)
    {
        if ($user->isLoggedIn()) {
            $this->identity = $user->getIdentity();
        }
    }

    public function setUserFields(array $userFields)
    {
        $this->userFields = $userFields;
    }

    public function setPriorityMapping(array $priorityMapping)
    {
        $this->priorityMapping = $priorityMapping;
    }

    public function setSession(Session $session)
    {
        $this->session = $session;
    }

    public function log($value, $priority = ILogger::INFO)
    {
        $response = parent::log($value, $priority);
        $severity = $this->tracyPriorityToSentrySeverity($priority);

        // if it's non-default severity, let's try configurable mapping
        if (!$severity) {
            $mappedSeverity = $this->priorityMapping[$priority] ?? null;
            if ($mappedSeverity) {
                $severity = new Severity((string) $mappedSeverity);
            }
        }
        // if we still don't have severity, don't log anything
        if (!$severity) {
            return $response;
        }

        configureScope(function (Scope $scope) use ($severity) {
            if (!$severity) {
                return;
            }
            $scope->setLevel($severity);
            if ($this->identity) {
                $userFields = [
                    'id' => $this->identity->getId(),
                ];
                foreach ($this->userFields as $name) {
                    $userFields[$name] = $this->identity->{$name} ?? null;
                }
                $scope->setUser($userFields);
            }
            if ($this->session) {
                $data = [];
                foreach ($this->session->getIterator() as $section) {
                    foreach ($this->session->getSection($section)->getIterator() as $key => $val) {
                        $data[$section][$key] = $val;
                    }
                }
                $scope->setExtra('session', $data);
            }
        });

        if ($value instanceof Throwable) {
            captureException($value);
        } else {
            captureMessage(is_string($value) ? $value : Dumper::toText($value));
        }

        return $response;
    }

    private function tracyPriorityToSentrySeverity(string $priority): ?Severity
    {
        switch ($priority) {
            case ILogger::DEBUG:
                return Severity::debug();
            case ILogger::INFO:
                return Severity::info();
            case ILogger::WARNING:
                return Severity::warning();
            case ILogger::ERROR:
            case ILogger::EXCEPTION:
                return Severity::error();
            case ILogger::CRITICAL:
                return Severity::fatal();
            default:
                return null;
        }
    }
}
