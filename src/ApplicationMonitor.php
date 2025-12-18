<?php

/* https://github.com/contributte/sentry/blob/master/src/Performance/NetteApplicationMonitor.php */

declare(strict_types=1);

namespace Rootpd\NetteSentry;

use Nette\Application\Application;
use Nette\Application\IPresenter;
use Nette\Application\Request;
use Nette\Application\Response;
use Nette\Application\UI\Presenter;
use Nette\Http\IRequest;
use Sentry\SentrySdk;
use Sentry\Tracing\Span;
use Sentry\Tracing\SpanContext;
use Sentry\Tracing\Transaction;
use Sentry\Tracing\TransactionContext;

class ApplicationMonitor
{
    private ?Transaction $transaction = null;
    private array $spans = [];

    public function __construct(private IRequest $request)
    {
    }

    public function onRequest(Application $application, Request $request): void
    {
        if ($request->isMethod(Request::FORWARD)) {
            return;
        }

        $hub = SentrySdk::getCurrentHub();

        if ($hub->getTransaction() !== null) {
            throw new Exception('Transaction already started');
        }

        $context = TransactionContext::fromHeaders($this->request->getHeader('sentry-trace') ?? '', '');
        $context->setOp('nette.request');
        $context->setName(sprintf(
            '%s %s %s',
            $request->getPresenterName(),
            $request->getParameter('action') ?? 'unknown', // @phpstan-ignore-line
            $request->getParameter('do') ?? '' // @phpstan-ignore-line
        ));
        $context->setStartTimestamp(microtime(true));
        $context->setTags([
            'http.method' => $this->request->getMethod(),
            'http.url' => $this->request->getUrl()->getAbsoluteUrl(),
        ]);

        $context->setData([
            'http.parameters' => $request->getParameters(),
        ]);

        $this->transaction = $hub->startTransaction($context);
        $hub->setSpan($this->transaction);
    }

    public function onPresenter(Application $application, IPresenter $presenter): void
    {
        if (!$this->transaction || !$presenter instanceof Presenter) {
            return;
        }

        $className = get_class($presenter);

        $presenterActionSpan = null;
        $presenter->onStartup[] = function (Presenter $p) use ($className, &$presenterActionSpan) {
            $action = $p->getAction();
            $presenterActionSpan = $this->startSpan('presenter.action', "{$className}::action{$action}");
            SentrySdk::getCurrentHub()->setSpan($presenterActionSpan);
        };

        $renderSpan = null;
        $presenter->onRender[] = function (Presenter $p) use ($className, &$presenterActionSpan, &$renderSpan) {
            $presenterActionSpan?->finish();
            SentrySdk::getCurrentHub()->setSpan($this->transaction);

            $view = $p->getView();
            $renderSpan = $this->startSpan('template.render', "{$className}::render{$view}");
            SentrySdk::getCurrentHub()->setSpan($renderSpan);
        };

        $presenter->onShutdown[] = function () use (&$renderSpan) {
            $renderSpan?->finish();
            SentrySdk::getCurrentHub()->setSpan($this->transaction);
        };
    }

    public function onResponse(Application $application, Response $response): void
    {
        $responseSpan = $this->startSpan('response.send', get_class($response));
        if ($responseSpan) {
            SentrySdk::getCurrentHub()->setSpan($responseSpan);
        }
    }

    public function onShutdown(): void
    {
        foreach ($this->spans as $span) {
            if ($span && !$span->getEndTimestamp()) {
                $span->finish();
            }
        }
        $this->spans = [];

        $hub = SentrySdk::getCurrentHub();

        if ($hub->getTransaction() !== null) {
            $hub->getTransaction()->finish();
        }

        $this->transaction = null;
    }

    public function hook(Application $application): void
    {
        $application->onRequest[] = function (Application $application, Request $request): void {
            $this->onRequest($application, $request);
        };

        $application->onPresenter[] = function (Application $application, IPresenter $presenter): void {
            $this->onPresenter($application, $presenter);
        };

        $application->onResponse[] = function (Application $application, Response $response): void {
            $this->onResponse($application, $response);
        };

        $application->onShutdown[] = function (Application $application): void {
            $this->onShutdown();
        };
    }

    private function startSpan(string $op, string $description): ?Span
    {
        if (!$this->transaction) {
            return null;
        }

        $context = new SpanContext();
        $context->setOp($op);
        $context->setDescription($description);

        $span = $this->transaction->startChild($context);
        $this->spans[] = $span;

        return $span;
    }
}
