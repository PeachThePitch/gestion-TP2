<?php

namespace App\Tests\Functional;

use App\Tests\Support\WebSecurityTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class AccountSecurityTest extends WebSecurityTestCase
{
    // PDF 13 : l'état suspendu est vérifié lors d'une vraie authentification HTTP.
    public function testSuspendedAccountCannotLogin(): void
    {
        $this->login('suspended');
        self::assertResponseRedirects('/login');
        $this->client->followRedirect();
        self::assertSelectorTextContains('.alert-danger', 'suspendu');
        $this->client->request('GET', '/change-password');
        self::assertResponseRedirects('/login');
    }

    public function testAdminSuspensionAndReactivation(): void
    {
        $this->login();
        $this->client->request('GET', '/change-password');
        self::assertResponseIsSuccessful();
        foreach ([true, false] as $suspended) {
            $this->client->restart();
            $this->login('admin');
            $crawler = $this->client->request('GET', '/admin/users');
            $this->client->submit($crawler->filter('form[action="/admin/user/'.$this->ids['alice'].'/toggle-suspend"]')->form());
            self::assertResponseRedirects('/admin/users');
            self::assertSame($suspended, $this->user('alice')->isSuspended());
            $this->client->restart();
            $this->login();
            if ($suspended) {
                self::assertResponseRedirects('/login');
            } else {
                $this->client->request('GET', '/change-password');
                self::assertResponseIsSuccessful();
            }
        }
    }

    // PDF 14 : A -> B -> C puis refus de A et de B.
    public function testOldPasswordsCannotBeReused(): void
    {
        $this->login();
        $this->changePassword(self::ALICE_PASSWORD, 'SecondSecure42!Word');
        self::assertResponseRedirects('/change-password');
        // Le changement du hash invalide la session précédente : nouvelle connexion nécessaire.
        $this->client->restart();
        $this->login('alice', 'SecondSecure42!Word');
        $this->changePassword('SecondSecure42!Word', 'ThirdSecure84!Word');
        self::assertResponseRedirects('/change-password');
        $this->client->restart();
        $this->login('alice', 'ThirdSecure84!Word');
        foreach ([self::ALICE_PASSWORD, 'SecondSecure42!Word'] as $oldPassword) {
            $this->changePassword('ThirdSecure84!Word', $oldPassword);
            self::assertResponseRedirects('/change-password');
            $this->client->followRedirect();
            self::assertSelectorTextContains('.alert-danger', 'ancien mot de passe');
            self::assertTrue(password_verify('ThirdSecure84!Word', $this->user('alice')->getPassword()));
        }
        self::assertSame(2, (int) $this->em()->getConnection()->fetchOne('SELECT COUNT(*) FROM password_history WHERE user_id = ?', [$this->ids['alice']]));
    }

    // PDF 15 : aucune modification du hash/historique après refus.
    #[DataProvider('invalidChanges')]
    public function testInvalidPasswordChangeDoesNotWrite(string $old, string $new, string $message): void
    {
        $this->login();
        $hash = $this->user('alice')->getPassword();
        $this->changePassword($old, $new);
        self::assertResponseRedirects('/change-password');
        $this->client->followRedirect();
        self::assertSelectorTextContains('.alert-danger', $message);
        self::assertSame($hash, $this->user('alice')->getPassword());
        self::assertSame(0, (int) $this->em()->getConnection()->fetchOne('SELECT COUNT(*) FROM password_history WHERE user_id = ?', [$this->ids['alice']]));
    }

    public static function invalidChanges(): iterable
    {
        yield ['wrong', 'NextSecure84!Word', 'actuel est incorrect'];
        yield ['', 'NextSecure84!Word', 'actuel est incorrect'];
        yield [self::ALICE_PASSWORD, 'short', 'au moins 12 caractères'];
    }

    public function testNewPasswordWorksAndPreviousPasswordFails(): void
    {
        $this->login();
        $this->changePassword(self::ALICE_PASSWORD, 'NextSecure84!Word');
        self::assertResponseRedirects('/change-password');
        self::assertTrue(password_verify('NextSecure84!Word', $this->user('alice')->getPassword()));
        $this->client->restart();
        $this->login('alice', self::ALICE_PASSWORD);
        self::assertResponseRedirects('/login');
        $this->login('alice', 'NextSecure84!Word');
        $this->client->request('GET', '/change-password');
        self::assertResponseIsSuccessful();
    }

    #[DataProvider('invalidConfirmations')]
    public function testNewPasswordConfirmationIsRequired(?string $confirmation): void
    {
        $this->login();
        $hash = $this->user('alice')->getPassword();
        $crawler = $this->client->request('GET', '/change-password');
        self::assertSelectorExists('input[name="confirm_new_password"][required]');
        $values = $crawler->selectButton('Valider')->form([
            'current_password' => self::ALICE_PASSWORD,
            'new_password' => 'NextSecure84!Word',
        ])->getPhpValues();
        if ($confirmation === null) {
            unset($values['confirm_new_password']);
        } else {
            $values['confirm_new_password'] = $confirmation;
        }
        $this->client->request('POST', '/change-password', $values);
        self::assertResponseRedirects('/change-password');
        $this->client->followRedirect();
        self::assertSelectorTextContains('.alert-danger', 'Les deux nouveaux mots de passe doivent être identiques.');
        self::assertSame($hash, $this->user('alice')->getPassword());
        self::assertSame(0, (int) $this->em()->getConnection()->fetchOne('SELECT COUNT(*) FROM password_history WHERE user_id = ?', [$this->ids['alice']]));
    }

    public static function invalidConfirmations(): iterable
    {
        yield 'différente' => ['DifferentSecure42!Word'];
        yield 'vide' => [''];
        yield 'absente de la requête' => [null];
    }

    public function testPasswordChangeRequiresCsrf(): void
    {
        $this->login();
        $hash = $this->user('alice')->getPassword();
        $this->client->request('POST', '/change-password', ['current_password' => self::ALICE_PASSWORD, 'new_password' => 'NextSecure84!Word', '_token' => 'invalid']);
        self::assertResponseStatusCodeSame(403);
        self::assertSame($hash, $this->user('alice')->getPassword());
    }
}
