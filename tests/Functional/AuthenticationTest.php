<?php

namespace App\Tests\Functional;

use App\Tests\Support\WebSecurityTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class AuthenticationTest extends WebSecurityTestCase
{
    // PDF 1 : une URL protégée reste protégée même saisie directement.
    #[DataProvider('protectedPages')]
    public function testVisitorCannotOpenProtectedPage(string $path): void
    {
        $this->client->request('GET', $path === '/profile/alice/edit' ? $this->profileUrl('alice') : $path);
        self::assertResponseRedirects('/login');
    }

    public static function protectedPages(): iterable
    {
        yield ['/profile'];
        yield ['/profile/alice/edit'];
        yield ['/change-password'];
        yield ['/admin'];
    }

    // PDF 1 et 2 : vraie connexion HTTP, puis plusieurs pages et persistance de session.
    public function testValidLoginAndSessionPersistence(): void
    {
        $this->login();
        self::assertResponseRedirects();
        foreach ([$this->profileUrl('alice'), '/change-password'] as $path) {
            $this->client->request('GET', $path);
            self::assertResponseIsSuccessful();
            self::assertSelectorExists('a[href="/profile"]');
        }
        self::assertSame(1, (int) $this->em()->getConnection()->fetchOne('SELECT COUNT(*) FROM login_log WHERE user_id = ?', [$this->ids['alice']]));
    }

    // PDF 3 : mauvais mot de passe, celui de Bob, compte inexistant.
    #[DataProvider('invalidCredentials')]
    public function testInvalidCredentials(string $name, string $password): void
    {
        $this->login($name, $password);
        self::assertResponseRedirects('/login');
        $this->client->followRedirect();
        self::assertSelectorExists('.alert-danger');
        $this->client->request('GET', '/change-password');
        self::assertResponseRedirects('/login');
    }

    public static function invalidCredentials(): iterable
    {
        yield ['alice', 'wrong'];
        yield ['alice', self::BOB_PASSWORD];
        yield ['unknown', self::ALICE_PASSWORD];
    }

    // PDF 4 : le cookie précédent ne doit plus ouvrir une session authentifiée.
    public function testLogoutInvalidatesPreviousSession(): void
    {
        $this->login();
        $crawler = $this->client->request('GET', $this->profileUrl('alice'));
        self::assertResponseIsSuccessful();
        $cookies = clone $this->client->getCookieJar();
        $link = $crawler->filter('a')->reduce(static fn ($node) => str_contains($node->attr('href') ?? '', '/logout'))->first()->link();
        $this->client->click($link);
        self::assertResponseRedirects();
        $this->client->request('GET', '/change-password');
        self::assertResponseRedirects('/login');
        $this->client->getCookieJar()->clear();
        foreach ($cookies->all() as $cookie) {
            $this->client->getCookieJar()->set($cookie);
        }
        $this->client->request('GET', $this->profileUrl('alice'));
        self::assertResponseRedirects('/login');
    }
}
