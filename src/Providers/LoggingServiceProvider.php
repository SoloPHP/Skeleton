<?php

declare(strict_types=1);

namespace App\Providers;

use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Solo\Application\Config;
use Solo\Contracts\Container\WritableContainerInterface;
use Solo\Dotenv\Dotenv;
use Solo\Logger\Logger;
use Solo\Logger\Rotation\RotationOptions;

/**
 * A PSR-3 logger writing to one rotated file. Services ask for LoggerInterface,
 * never for the Logger class.
 */
final class LoggingServiceProvider
{
    public function register(WritableContainerInterface $container): void
    {
        $container->set(LoggerInterface::class, function (WritableContainerInterface $c) {
            $file = Dotenv::string('LOG_FILE', 'storage/logs/app.log');

            return Logger::file(
                path: str_starts_with($file, '/') ? $file : $c->get(Config::class)->basePath . '/' . $file,
                minLevel: Dotenv::string('LOG_LEVEL', LogLevel::WARNING),
                rotation: new RotationOptions(maxBytes: 5 * 1024 * 1024, maxFiles: 5),
                // Records carry the application's time; the logger itself defaults to UTC.
                timezone: Dotenv::string('APP_TIMEZONE', 'UTC'),
            );
        });
    }
}
