<?php

declare(strict_types=1);

namespace OCA\AdPlaner\Tests;

use function OCA\LocalBase\Tests\Support\assertSameValue as supportAssertSameValue;
use function OCA\LocalBase\Tests\Support\assertThrows as supportAssertThrows;

function assertSameValue(mixed $expected, mixed $actual, string $message): void {
    supportAssertSameValue($expected, $actual, $message);
}

function assertDomainException(callable $callback, string $message): void {
    supportAssertThrows($callback, \DomainException::class, $message);
}
