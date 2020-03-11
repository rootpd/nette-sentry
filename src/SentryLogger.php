<?php

declare(strict_types=1);

namespace Rootpd\NetteSentry;

use Nette\Http\Session;
use Nette\Security\IIdentity;
use Nette\Security\User;
use Sentry\ClientBuilder;
use Sentry\Integration\RequestIntegration;
use Sentry\Severity;
use Sentry\State\Hub;
use Tracy\Debugger;
use Tracy\ILogger;
use Tracy\Logger;
use function Sentry\captureException;
use function Sentry\captureMessage;
use function Sentry\configureScope;

class SentryLogger extends Logger
{
    /** @var IIdentity */
    private $identity;

    /** @var Session */
    private $session;

    /** @var array */
    private $userFields = [];

    public function register(string $dsn, string $environment)
    {
        $options = new \Sentry\Options([
            'dsn' => $dsn,
            'environment' => $environment,
            'default_integrations' => false,
        ]);

        $options->setIntegrations([
            new RequestIntegration($options),
        ]);

        $builder = new ClientBuilder($options);
        $client = $builder->getClient();
        Hub::setCurrent(new Hub($client));

        $this->email = & Debugger::$email;
        $this->directory = Debugger::$logDirectory;
    }

    public function setUser(User $user)
    {
        $this->identity = $user->getIdentity();
    }

    public function setUserFields(array $userFields)
    {
        $this->userFields = $userFields;
    }

    public function setSession(Session $session)
    {
        $this->session = $session;
    }

    public function log($value, $priority = ILogger::INFO)
    {
        $response = parent::log($value, $priority);

        configureScope(function (\Sentry\State\Scope $scope) use ($priority) {
            $severity = $this->tracyPriorityToSentrySeverity($priority);
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

        if ($value instanceof \Exception) {
            captureException($value);
        } else if(is_object($value)) {
            captureException($value);
        } else {
            captureMessage($value);
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
