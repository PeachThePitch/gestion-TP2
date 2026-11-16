<?php

namespace App\Tests\Functional;

use App\Entity\User;
use App\Tests\Support\PwnedClientFactory;
use App\Tests\Support\WebSecurityTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class RegistrationTest extends WebSecurityTestCase
{
    // PDF 9, 10, 12 : chaque cas vérifie également l'absence d'écriture en base.
    #[DataProvider('invalidRegistrations')]
    public function testInvalidRegistrationCreatesNoAccount(array $values): void
    {
        $this->register($values);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorExists('.invalid-feedback, .form-error-message, ul li');
        $this->assertRegistrationAbsent();
    }

    public static function invalidRegistrations(): iterable
    {
        foreach (['nom', 'prenom', 'adresse', 'ville', 'codePostal', 'email'] as $field) {
            yield 'champ vide '.$field => [[$field => '']];
        }
        yield 'mot de passe vide' => [['plainPassword' => ['first' => '', 'second' => '']]];
        yield 'code postal lettres' => [['codePostal' => 'ABCDE']];
        yield 'email invalide' => [['email' => 'not-an-email']];
        yield 'nom trop long' => [['nom' => str_repeat('N', 256)]];
        yield 'mot de passe court' => [['plainPassword' => ['first' => 'Ab1!', 'second' => 'Ab1!']]];
        yield 'mot de passe faible' => [['plainPassword' => ['first' => 'aaaaaaaaaaaa', 'second' => 'aaaaaaaaaaaa']]];
        yield 'mot de passe compromis' => [['plainPassword' => ['first' => PwnedClientFactory::COMPROMISED, 'second' => PwnedClientFactory::COMPROMISED]]];
        yield 'confirmation différente' => [['plainPassword' => ['first' => 'UniqueRegistration42!Word', 'second' => 'DifferentRegistration42!Word']]];
        yield 'confirmation absente' => [['plainPassword' => ['first' => 'UniqueRegistration42!Word', 'second' => '']]];
        yield 'conditions refusées' => [['agreeTerms' => null]];
        yield 'tentative role admin' => [['roles' => ['ROLE_ADMIN']]];
    }

    public function testCompromisedPasswordShowsSpecificError(): void
    {
        $this->register(['plainPassword' => ['first' => PwnedClientFactory::COMPROMISED, 'second' => PwnedClientFactory::COMPROMISED]]);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('body', 'Ce mot de passe a été divulgué');
        $this->assertRegistrationAbsent();
    }

    // PDF 11 : compte créé, hash, rôle minimal et connexion avec les nouveaux identifiants.
    public function testValidRegistrationCanAuthenticate(): void
    {
        $this->register();
        self::assertResponseRedirects('/login');
        $user = $this->em()->getRepository(User::class)->findOneBy(['email' => 'new@example.test']);
        self::assertNotNull($user);
        self::assertSame(['ROLE_USER'], $user->getRoles());
        self::assertTrue(password_verify('UniqueRegistration42!Word', $user->getPassword()));
        self::assertSame(1, (int) $this->em()->getConnection()->fetchOne('SELECT COUNT(*) FROM password_history WHERE user_id = ?', [$user->getId()]));
        $this->login('new', 'UniqueRegistration42!Word');
        self::assertResponseRedirects();
        $this->client->request('GET', '/change-password');
        self::assertResponseIsSuccessful();
    }

    // PDF 12 : correction puis double soumission => un seul compte.
    public function testCorrectedRegistrationCreatesExactlyOneAccount(): void
    {
        $this->register(['codePostal' => 'ABCDE']);
        $this->assertRegistrationAbsent();
        $this->register();
        self::assertResponseRedirects('/login');
        $this->register();
        self::assertResponseStatusCodeSame(422);
        self::assertSame(1, $this->em()->getRepository(User::class)->count(['email' => 'new@example.test']));
    }
}
