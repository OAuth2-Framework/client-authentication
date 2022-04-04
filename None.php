<?php

declare(strict_types=1);

namespace OAuth2Framework\Component\ClientAuthentication;

use function array_key_exists;
use OAuth2Framework\Component\Core\Client\Client;
use OAuth2Framework\Component\Core\Client\ClientId;
use OAuth2Framework\Component\Core\DataBag\DataBag;
use OAuth2Framework\Component\Core\Util\RequestBodyParser;
use Psr\Http\Message\ServerRequestInterface;

final class None implements AuthenticationMethod
{
    public static function create(): static
    {
        return new self();
    }

    public function getSchemesParameters(): array
    {
        return [];
    }

    /**
     * @param mixed|null $clientCredentials
     */
    public function findClientIdAndCredentials(ServerRequestInterface $request, &$clientCredentials = null): ?ClientId
    {
        $parameters = RequestBodyParser::parseFormUrlEncoded($request);
        if (array_key_exists('client_id', $parameters)) {
            return new ClientId($parameters['client_id']);
        }

        return null;
    }

    public function checkClientConfiguration(DataBag $command_parameters, DataBag $validated_parameters): DataBag
    {
        return $validated_parameters;
    }

    /**
     * @param mixed|null $clientCredentials
     */
    public function isClientAuthenticated(Client $client, $clientCredentials, ServerRequestInterface $request): bool
    {
        return true;
    }

    public function getSupportedMethods(): array
    {
        return ['none'];
    }
}
