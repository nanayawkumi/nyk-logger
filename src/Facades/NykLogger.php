<?php

declare(strict_types=1);

namespace Nanayawkumi\NykLogger\Facades;

use Illuminate\Support\Facades\Facade;
use Nanayawkumi\NykLogger\AlertManager;

/**
 * @method static bool report(\Throwable $exception, string $level = 'error', array $context = [])
 * @method static bool alert(string $message, string $level = 'error', array $context = [])
 * @method static void fingerprintUsing(?callable $resolver)
 * @method static void dispatch(\Nanayawkumi\NykLogger\Support\AlertPayload $payload)
 *
 * @see \Nanayawkumi\NykLogger\AlertManager
 */
final class NykLogger extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return AlertManager::class;
    }
}
