<?php

namespace App\Logging;

use App\Constants\AppConstants;
use Illuminate\Log\Logger;
use Monolog\Formatter\JsonFormatter;
use Monolog\Handler\FormattableHandlerInterface;

class JsonTap
{
    public function __invoke(Logger $logger): void
    {
        /*
         * Configure JSON formatter.
         */
        $formatter = new JsonFormatter;

        /*
         * Include exception stack traces.
         */
        $formatter->includeStacktraces(true);

        /*
         * Prevent deeply nested objects from exploding
         * the log payload.
         */
        $formatter->setMaxNormalizeDepth(20);

        /*
         * Apply JSON formatter to all handlers.
         */
        foreach ($logger->getHandlers() as $handler) {
            if ($handler instanceof FormattableHandlerInterface) {
                $handler->setFormatter($formatter);
            }
        }

        /*
         * Redact sensitive information.
         */
        $logger->pushProcessor(
            new RedactSensitiveData(
                AppConstants::LOG_MASK_KEYS
            )
        );

        /*
         * Add authenticated user information.
         */
        $logger->pushProcessor(
            new AddAuthenticatedUser
        );
    }
}
