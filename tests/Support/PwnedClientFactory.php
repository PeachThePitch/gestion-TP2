<?php

namespace App\Tests\Support;

use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class PwnedClientFactory
{
    public const COMPROMISED = 'CyberKnownPwned42!';

    public static function create(): MockHttpClient
    {
        // Simulation du protocole k-anonymity, sans désactiver le vrai validateur Symfony.
        return new MockHttpClient(static function (string $method, string $url): MockResponse {
            $hash = strtoupper(sha1(self::COMPROMISED));
            if ($method !== 'GET' || !preg_match('~^https://api\.pwnedpasswords\.com/range/[A-F0-9]{5}$~D', $url)) {
                throw new \LogicException('Requête externe inattendue pendant les tests.');
            }
            return new MockResponse(str_ends_with($url, substr($hash, 0, 5)) ? substr($hash, 5).":42\r\n" : str_repeat('0', 35).":0\r\n");
        });
    }
}
