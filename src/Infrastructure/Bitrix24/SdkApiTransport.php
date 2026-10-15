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
            $response = $builder->core->call($method, $parameters, ApiVersion::from($version));
            $data = $response->getResponseData();
            if ($method === 'scope' && $version === 1) {
                // SDK normalizes scalars and empty objects to arrays; scope must be an actual JSON list.
                $payload = json_decode($response->getHttpResponse()->getContent(), false, 32, JSON_THROW_ON_ERROR);
                if (!$payload instanceof \stdClass || !is_array($payload->result ?? null) || !array_is_list($payload->result)) {
                    throw new Failure('scope-check-unavailable', 'The scope method did not return a JSON list of permission codes.');
                }
            }

            return new ApiResponse($data->getResult(), $data->getPagination()->getNextItem(), $data->getPagination()->getTotal());
        } catch (\Throwable $exception) {
            if ($exception instanceof Failure) {
                throw $exception;
            }

            if ($this->state->cancelled) {
                throw new Failure('interrupted', 'Interrupted while awaiting the API response; inspect a sent write before retrying.', 130, [], $effect !== 'read');
            }

            for ($cause = $exception; $cause instanceof \Throwable; $cause = $cause->getPrevious()) {
                if ($cause instanceof ValidationException) {
                    $validation = array_map(fn ($error): array => ['field' => $error->field, 'message' => $this->provider->redact($error->message), 'option' => $error->field === 'task.responsible.id' ? '--responsible' : null], $cause->getValidationErrors());
                    throw new Failure('api-validation-error', 'Bitrix24 rejected the supplied fields.', 1, ['validation' => $validation]);
                }

                $name = $cause::class;
                $message = strtolower($cause->getMessage());
                if (str_contains($name, 'InsufficientScope') || str_starts_with($message, 'insufficient_scope -')) {
                    throw new Failure('permission-denied', 'Bitrix24 denied the requested scope; update the incoming webhook permissions and run connection:check.', 1, ['apiErrorCode' => 'insufficient_scope']);
                }

                if (str_starts_with($message, 'internal_server_error -')) {
                    throw new Failure('api-error', 'Bitrix24 returned an internal server error; check the selected fields and portal API support.', 1, ['apiErrorCode' => 'INTERNAL_SERVER_ERROR'], $effect !== 'read');
                }

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
