<?php

declare(strict_types=1);

namespace App\Auth;

interface OidcClientInterface
{
    public function authorizationUrl(string $state, string $redirectUri): string;

    public function exchangeCode(string $code, string $redirectUri): AdminIdentity;
}
