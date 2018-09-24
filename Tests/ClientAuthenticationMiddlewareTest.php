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

namespace OAuth2Framework\Component\ClientAuthentication\Tests;

use OAuth2Framework\Component\ClientAuthentication\AuthenticationMethod;
use OAuth2Framework\Component\ClientAuthentication\AuthenticationMethodManager;
use OAuth2Framework\Component\ClientAuthentication\ClientAuthenticationMiddleware;
use OAuth2Framework\Component\ClientAuthentication\ClientSecretBasic;
use OAuth2Framework\Component\Core\Client\Client;
use OAuth2Framework\Component\Core\Client\ClientId;
use OAuth2Framework\Component\Core\Client\ClientRepository;
use OAuth2Framework\Component\Core\DataBag\DataBag;
use OAuth2Framework\Component\Core\Message\OAuth2Error;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * @group TokenEndpoint
 * @group ClientAuthenticationMiddleware
 */
final class ClientAuthenticationMiddlewareTest extends TestCase
{
    /**
     * @test
     */
    public function noClientIsFoundInTheRequest()
    {
        $response = $this->prophesize(ResponseInterface::class);
        $request = $this->prophesize(ServerRequestInterface::class);
        $request->getHeader('Authorization')->willReturn([])->shouldBeCalled();
        $handler = $this->prophesize(RequestHandlerInterface::class);
        $clientRepository = $this->prophesize(ClientRepository::class);
        $handler->handle(Argument::type(ServerRequestInterface::class))
            ->shouldBeCalled()
            ->willReturn($response->reveal())
        ;

        $this->getClientAuthenticationMiddleware($clientRepository->reveal())->process($request->reveal(), $handler->reveal());
    }

    /**
     * @test
     */
    public function aClientIdIsSetButTheClientDoesNotExist()
    {
        $request = $this->prophesize(ServerRequestInterface::class);
        $request->getHeader('Authorization')
            ->willReturn([
                'Basic '.\base64_encode('FOO:BAR'),
            ])
            ->shouldBeCalled();
        $handler = $this->prophesize(RequestHandlerInterface::class);
        $clientRepository = $this->prophesize(ClientRepository::class);
        $clientRepository->find(Argument::type(ClientId::class))->willReturn(null)->shouldBeCalled();
        $handler->handle(Argument::type(ServerRequestInterface::class))
            ->shouldNotBeCalled()
        ;

        try {
            $this->getClientAuthenticationMiddleware($clientRepository->reveal())->process($request->reveal(), $handler->reveal());
            static::fail('An OAuth2 exception should be thrown.');
        } catch (OAuth2Error $e) {
            static::assertEquals(401, $e->getCode());
            static::assertEquals([
                'error' => 'invalid_client',
                'error_description' => 'Client authentication failed.',
            ], $e->getData());
        }
    }

    /**
     * @test
     */
    public function aClientIdIsSetButTheClientIsDeleted()
    {
        $client = new Client(
            new ClientId('FOO'),
            new DataBag([]),
            null
        );
        $client = $client->markAsDeleted();

        $request = $this->prophesize(ServerRequestInterface::class);
        $request->getHeader('Authorization')
            ->willReturn([
                'Basic '.\base64_encode('FOO:BAR'),
            ])
            ->shouldBeCalled();
        $handler = $this->prophesize(RequestHandlerInterface::class);
        $clientRepository = $this->prophesize(ClientRepository::class);
        $clientRepository->find(Argument::type(ClientId::class))->willReturn($client)->shouldBeCalled();
        $handler->handle(Argument::type(ServerRequestInterface::class))
            ->shouldNotBeCalled()
        ;

        try {
            $this->getClientAuthenticationMiddleware($clientRepository->reveal())->process($request->reveal(), $handler->reveal());
            static::fail('An OAuth2 exception should be thrown.');
        } catch (OAuth2Error $e) {
            static::assertEquals(401, $e->getCode());
            static::assertEquals([
                'error' => 'invalid_client',
                'error_description' => 'Client authentication failed.',
            ], $e->getData());
        }
    }

    /**
     * @test
     */
    public function aClientIdIsSetButTheClientCredentialsExpired()
    {
        $client = new Client(
            new ClientId('FOO'),
            new DataBag([
                'token_endpoint_auth_method' => 'client_secret_basic',
                'client_secret' => 'BAR',
                'client_secret_expires_at' => \time() - 1,
            ]),
            null
        );

        $request = $this->prophesize(ServerRequestInterface::class);
        $request->getHeader('Authorization')
            ->willReturn([
                'Basic '.\base64_encode('FOO:BAR'),
            ])
            ->shouldBeCalled();
        $handler = $this->prophesize(RequestHandlerInterface::class);
        $clientRepository = $this->prophesize(ClientRepository::class);
        $clientRepository->find(Argument::type(ClientId::class))->willReturn($client)->shouldBeCalled();
        $handler->handle(Argument::type(ServerRequestInterface::class))
            ->shouldNotBeCalled()
        ;

        try {
            $this->getClientAuthenticationMiddleware($clientRepository->reveal())->process($request->reveal(), $handler->reveal());
            static::fail('An OAuth2 exception should be thrown.');
        } catch (OAuth2Error $e) {
            static::assertEquals(401, $e->getCode());
            static::assertEquals([
                'error' => 'invalid_client',
                'error_description' => 'Client credentials expired.',
            ], $e->getData());
        }
    }

    /**
     * @test
     */
    public function aClientIdIsSetButTheAuthenticationMethodIsNotSupportedByTheClient()
    {
        $client = new Client(
            new ClientId('FOO'),
            new DataBag([
                'token_endpoint_auth_method' => 'none',
            ]),
            null
        );

        $request = $this->prophesize(ServerRequestInterface::class);
        $request->getHeader('Authorization')
            ->willReturn([
                'Basic '.\base64_encode('FOO:BAR'),
            ])
            ->shouldBeCalled();
        $handler = $this->prophesize(RequestHandlerInterface::class);
        $clientRepository = $this->prophesize(ClientRepository::class);
        $clientRepository->find(Argument::type(ClientId::class))->willReturn($client)->shouldBeCalled();
        $handler->handle(Argument::type(ServerRequestInterface::class))
            ->shouldNotBeCalled()
        ;

        try {
            $this->getClientAuthenticationMiddleware($clientRepository->reveal())->process($request->reveal(), $handler->reveal());
            static::fail('An OAuth2 exception should be thrown.');
        } catch (OAuth2Error $e) {
            static::assertEquals(401, $e->getCode());
            static::assertEquals([
                'error' => 'invalid_client',
                'error_description' => 'Client authentication failed.',
            ], $e->getData());
        }
    }

    /**
     * @test
     */
    public function aClientIdIsSetButTheClientIsNotAuthenticated()
    {
        $client = new Client(
            new ClientId('FOO'),
            new DataBag([
                'token_endpoint_auth_method' => 'client_secret_basic',
                'client_secret' => 'BAR',
            ]),
            null
        );

        $request = $this->prophesize(ServerRequestInterface::class);
        $request->getHeader('Authorization')
            ->willReturn([
                'Basic '.\base64_encode('FOO:BAD_SECRET'),
            ])
            ->shouldBeCalled();
        $handler = $this->prophesize(RequestHandlerInterface::class);
        $clientRepository = $this->prophesize(ClientRepository::class);
        $clientRepository->find(Argument::type(ClientId::class))->willReturn($client)->shouldBeCalled();
        $handler->handle(Argument::type(ServerRequestInterface::class))
            ->shouldNotBeCalled()
        ;

        try {
            $this->getClientAuthenticationMiddleware($clientRepository->reveal())->process($request->reveal(), $handler->reveal());
            static::fail('An OAuth2 exception should be thrown.');
        } catch (OAuth2Error $e) {
            static::assertEquals(401, $e->getCode());
            static::assertEquals([
                'error' => 'invalid_client',
                'error_description' => 'Client authentication failed.',
            ], $e->getData());
        }
    }

    /**
     * @test
     */
    public function aClientIsFullyAuthenticated()
    {
        $client = new Client(
            new ClientId('FOO'),
            new DataBag([
                'token_endpoint_auth_method' => 'client_secret_basic',
                'client_secret' => 'BAR',
            ]),
            null
        );

        $response = $this->prophesize(ResponseInterface::class);
        $request = $this->prophesize(ServerRequestInterface::class);
        $request->getHeader('Authorization')
            ->willReturn([
                'Basic '.\base64_encode('FOO:BAR'),
            ])
            ->shouldBeCalled();
        $request->withAttribute('client', $client)->shouldBeCalled()->willReturn($request->reveal());
        $request->withAttribute('client_authentication_method', Argument::type(AuthenticationMethod::class))->shouldBeCalled()->willReturn($request->reveal());
        $request->withAttribute('client_credentials', 'BAR')->shouldBeCalled()->willReturn($request->reveal());
        $handler = $this->prophesize(RequestHandlerInterface::class);
        $clientRepository = $this->prophesize(ClientRepository::class);
        $clientRepository->find(Argument::type(ClientId::class))->willReturn($client)->shouldBeCalled();
        $handler->handle(Argument::type(ServerRequestInterface::class))
            ->shouldBeCalled()
            ->willReturn($response->reveal())
        ;

        $this->getClientAuthenticationMiddleware($clientRepository->reveal())->process($request->reveal(), $handler->reveal());
    }

    private function getClientAuthenticationMiddleware(ClientRepository $clientRepository): ClientAuthenticationMiddleware
    {
        $authenticationMethodManager = new AuthenticationMethodManager();
        $authenticationMethodManager->add(new ClientSecretBasic('Real'));

        $clientAuthenticationMiddleware = new ClientAuthenticationMiddleware(
            $clientRepository,
            $authenticationMethodManager
        );

        return $clientAuthenticationMiddleware;
    }
}
