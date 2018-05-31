<?php

declare(strict_types=1);

/*
 * The MIT License (MIT)
 *
 * Copyright (c) 2014-2018 Spomky-Labs
 *
 * This software may be modified and distributed under the terms
 * of the MIT license.  See the LICENSE file for details.
 */

namespace OAuth2Framework\Component\ClientAuthentication;

use OAuth2Framework\Component\Core\Client\Client;
use OAuth2Framework\Component\Core\Client\ClientId;
use OAuth2Framework\Component\Core\Message\OAuth2Message;
use Psr\Http\Message\ServerRequestInterface;

class AuthenticationMethodManager
{
    /**
     * @var AuthenticationMethod[]
     */
    private $methods = [];

    /**
     * @var string[]
     */
    private $names = [];

    /**
     * @param AuthenticationMethod $method
     */
    public function add(AuthenticationMethod $method): void
    {
        $class = get_class($method);
        $this->methods[$class] = $method;
        foreach ($method->getSupportedMethods() as $name) {
            $this->names[$name] = $class;
        }
    }

    /**
     * @return string[]
     */
    public function list(): array
    {
        return array_keys($this->names);
    }

    /**
     * @param string $name
     *
     * @return bool
     */
    public function has(string $name): bool
    {
        return array_key_exists($name, $this->names);
    }

    /**
     * @param string $name
     *
     * @throws \InvalidArgumentException
     *
     * @return AuthenticationMethod
     */
    public function get(string $name): AuthenticationMethod
    {
        if (!$this->has($name)) {
            throw new \InvalidArgumentException(sprintf('The token endpoint authentication method "%s" is not supported. Please use one of the following values: %s', $name, implode(', ', $this->list())));
        }
        $class = $this->names[$name];

        return $this->methods[$class];
    }

    /**
     * @return AuthenticationMethod[]
     */
    public function all(): array
    {
        return array_values($this->methods);
    }

    /**
     * @param ServerRequestInterface $request
     * @param AuthenticationMethod   $authenticationMethod
     * @param mixed                  $clientCredentials    The client credentials found in the request
     *
     * @throws OAuth2Message
     *
     * @return null|ClientId
     */
    public function findClientIdAndCredentials(ServerRequestInterface $request, AuthenticationMethod &$authenticationMethod = null, &$clientCredentials = null)
    {
        $clientId = null;
        $clientCredentials = null;
        foreach ($this->methods as $method) {
            $tempClientId = $method->findClientIdAndCredentials($request, $clientCredentials);
            if (null === $tempClientId) {
                continue;
            }
            if (null === $clientId) {
                $clientId = $tempClientId;
                $authenticationMethod = $method;

                continue;
            }
            if (!$method instanceof None && !$authenticationMethod instanceof None) {
                throw new OAuth2Message(400, OAuth2Message::ERROR_INVALID_REQUEST, 'Only one authentication method may be used to authenticate the client.');
            }
            if (!$method instanceof None) {
                $authenticationMethod = $method;
            }
        }

        return $clientId;
    }

    /**
     * @param ServerRequestInterface $request
     * @param Client                 $client
     * @param AuthenticationMethod   $authenticationMethod
     * @param mixed                  $clientCredentials
     *
     * @return bool
     */
    public function isClientAuthenticated(ServerRequestInterface $request, Client $client, AuthenticationMethod $authenticationMethod, $clientCredentials): bool
    {
        if (in_array($client->get('token_endpoint_auth_method'), $authenticationMethod->getSupportedMethods())) {
            if (false === $client->areClientCredentialsExpired()) {
                return $authenticationMethod->isClientAuthenticated($client, $clientCredentials, $request);
            }
        }

        return false;
    }

    /**
     * @return string[]
     */
    public function getSchemesParameters(): array
    {
        $schemes = [];
        foreach ($this->all() as $method) {
            $schemes = array_merge(
                $schemes,
                $method->getSchemesParameters()
            );
        }

        return $schemes;
    }

    /**
     * @param array $additionalAuthenticationParameters
     *
     * @return array
     */
    public function getSchemes(array $additionalAuthenticationParameters = []): array
    {
        $schemes = [];
        foreach ($this->all() as $method) {
            $schemes = array_merge(
                $schemes,
                $method->getSchemesParameters()
            );
        }
        foreach ($schemes as $k => $scheme) {
            $schemes[$k] = $this->appendParameters($scheme, $additionalAuthenticationParameters);
        }

        return $schemes;
    }

    /**
     * @param string $scheme
     * @param array  $parameters
     *
     * @return string
     */
    private function appendParameters(string $scheme, array $parameters): string
    {
        $position = mb_strpos($scheme, ' ', 0, 'utf-8');
        $add_comma = false === $position ? false : true;

        foreach ($parameters as $key => $value) {
            $value = is_string($value) ? sprintf('"%s"', $value) : $value;
            if (false === $add_comma) {
                $add_comma = true;
                $scheme = sprintf('%s %s=%s', $scheme, $key, $value);
            } else {
                $scheme = sprintf('%s,%s=%s', $scheme, $key, $value);
            }
        }

        return $scheme;
    }
}
