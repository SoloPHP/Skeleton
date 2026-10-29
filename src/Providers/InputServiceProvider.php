<?php

declare(strict_types=1);

namespace App\Providers;

use App\Validation\UniqueRule;
use Doctrine\DBAL\Connection;
use Solo\Contracts\Container\WritableContainerInterface;
use Solo\Contracts\Validator\ValidatorInterface;
use Solo\Input\InputFactory;
use Solo\Validator\Validator;

/**
 * Request DTOs: solophp/input builds them, solophp/validator checks them.
 *
 * The application's own rules are registered here, by the name a rule string
 * uses; the validator is built once, so a rule may take a service of its own.
 */
final class InputServiceProvider
{
    public function register(WritableContainerInterface $container): void
    {
        // `#[Validate('required|string|max:255|unique:tasks,title')]` — see src/Validation
        $container->set(ValidatorInterface::class, fn(WritableContainerInterface $c) => new Validator([
            'unique' => new UniqueRule($c->get(Connection::class)),
        ]));

        // The container builds casters and processors that have dependencies of their own
        $container->set(InputFactory::class, fn(WritableContainerInterface $c) => new InputFactory(
            $c->get(ValidatorInterface::class),
            container: $c,
        ));
    }
}
