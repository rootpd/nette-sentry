<?php

declare(strict_types=1);

namespace Rootpd\NetteSentry;

use Nette\Database\Connection;
use Sentry\SentrySdk;
use Sentry\Tracing\SpanContext;
use Sentry\Tracing\SpanStatus;

class DatabaseConnection extends Connection
{
    public function query(string $sql, ...$params): \Nette\Database\ResultSet
    {
        return $this->executeWithSpan($sql, fn() => parent::query($sql, ...$params));
    }

    private function executeWithSpan(string $sql, callable $callback): mixed
    {
        $hub = SentrySdk::getCurrentHub();
        $span = $hub->getSpan();

        if ($span === null) {
            // No active transaction, execute without tracing
            return $callback();
        }

        $spanContext = new SpanContext();
        $spanContext->setOp('db.query');
        $spanContext->setDescription($this->formatQuery($sql));
        $spanContext->setData([
            'db.system' => 'mysql',
        ]);

        $childSpan = $span->startChild($spanContext);
        $startTime = microtime(true);

        try {
            $result = $callback();
            $childSpan->setStatus(SpanStatus::ok());
            return $result;
        } catch (\Throwable $e) {
            $childSpan->setStatus(SpanStatus::internalError());
            $childSpan->setData(['error' => $e->getMessage()]);
            throw $e;
        } finally {
            $duration = microtime(true) - $startTime;
            $childSpan->setData(['duration_ms' => round($duration * 1000, 2)]);
            $childSpan->finish();
        }
    }

    private function formatQuery(string $sql, int $maxLength = 1000): string
    {
        // Normalize whitespace
        $sql = preg_replace('/\s+/', ' ', trim($sql));

        if (strlen($sql) <= $maxLength) {
            return $sql;
        }

        return substr($sql, 0, $maxLength) . '...';
    }
}
