<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Infrastructure\Bitrix24;

use Bitrix24\CLI\Application\Failure;
use Bitrix24\CLI\Application\RuntimeState;
use Bitrix24\CLI\Infrastructure\Connection\B24ClientProvider;
use Bitrix24\SDK\Core\Contracts\ApiVersion;
use Bitrix24\SDK\Core\Exceptions\ValidationException;

final readonly class SdkApiTransport implements ApiTransport
{
    public function __construct(private B24ClientProvider $provider, private RuntimeState $state)
    {
    }

    public function call(string $method, int $version, array $parameters, string $effect = 'read'): ApiResponse
    {
        $this->state->checkCancellation();
        $builder = $this->provider->get();
        $this->state->calls[] = ['method' => $method, 'apiVersion' => $version . '.0', 'effect' => $effect];
        try {
            $data = $builder->core->call($method, $parameters, ApiVersion::from($version))->getResponseData();
            return new ApiResponse($data->getResult(), $data->getPagination()->getNextItem(), $data->getPagination()->getTotal());
        } catch (\Throwable $exception) {
            for ($cause = $exception; $cause instanceof \Throwable; $cause = $cause->getPrevious()) {
                if ($cause instanceof ValidationException) {
                    $validation = array_map(fn ($error): array => ['field' => $error->field, 'message' => $this->provider->redact($error->message), 'option' => $error->field === 'task.responsible.id' ? '--responsible' : null], $cause->getValidationErrors());
                    throw new Failure('api-validation-error', 'Bitrix24 rejected the supplied fields.', 1, ['validation' => $validation]);
                }

                $name = $cause::class;
                $message = strtolower($cause->getMessage());
                if (str_contains($name, 'AccessDenied') || str_contains($name, 'AuthForbidden') || array_any(['access denied', 'access_denied', 'accessdeniedexception', 'insufficient_scope', 'insufficientscopeexception'], static fn (string $code): bool => str_contains($message, $code))) {
                    throw new Failure('permission-denied', 'Bitrix24 denied this operation; check the connection permissions.');
                }

                if (str_contains($name, 'Transport') || str_contains($name, 'Timeout')) {
                    throw new Failure('transport-error', 'Request failed; inspect the resource before retrying a write.', 1, [], $effect !== 'read');
                }
            }

            throw new Failure('api-error', 'Bitrix24 rejected the request; verify the target, fields and permissions.');
        }
    }
}
