<?php

namespace App\Tests\Functional;

use App\Tests\Support\WebSecurityTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class AuthorizationTest extends WebSecurityTestCase
{
    // PDF 5 et 6.
    public function testAdminCanOpenAdministrationAndUserList(): void
    {
        $this->login('admin');
        foreach (['/admin', '/admin/users'] as $path) {
            $this->client->request('GET', $path);
            self::assertResponseIsSuccessful();
            self::assertSelectorTextContains('table', 'bob@example.test');
        }
    }

    public function testOrdinaryUserCannotOpenAdministration(): void
    {
        $this->login();
        foreach (['/admin', '/admin/users'] as $path) {
            $this->client->request('GET', $path);
            self::assertResponseStatusCodeSame(403);
        }
    }

    #[DataProvider('adminOperations')]
    public function testUserCannotPerformAdminOperations(string $action): void
    {
        $this->login();
        $this->client->request('POST', '/admin/user/'.$this->ids['bob'].'/'.$action, ['_token' => 'forged']);
        self::assertResponseStatusCodeSame(403);
        self::assertFalse($this->user('bob')->isSuspended());
    }

    public static function adminOperations(): iterable
    {
        yield ['toggle-suspend'];
        yield ['delete'];
    }

    // PDF 7 : lecture et écriture directes avec l'identifiant de Bob.
    public function testUserCanModifyOwnProfile(): void
    {
        $this->login();
        $crawler = $this->client->request('GET', $this->profileUrl('alice'));
        $this->client->submit($crawler->selectButton('Enregistrer')->form(['profile_form[prenom]' => 'Alicia']));
        self::assertResponseRedirects($this->profileUrl('alice'));
        self::assertSame('Alicia', $this->user('alice')->getPrenom());
    }

    public function testUserCannotModifyOtherProfileEvenWithForgedRequest(): void
    {
        $this->login();
        $own = $this->client->request('GET', $this->profileUrl('alice'));
        $form = $own->selectButton('Enregistrer')->form(['profile_form[prenom]' => 'Intrusion']);
        $this->client->request('POST', $this->profileUrl('bob'), $form->getPhpValues());
        self::assertResponseStatusCodeSame(403);
        self::assertSame('Bob', $this->user('bob')->getPrenom());
        $this->client->request('GET', $this->profileUrl('bob'));
        self::assertResponseStatusCodeSame(403);
    }

    // PDF 8 : ajout d'un champ roles absent du formulaire autorisé.
    public function testUserCannotGrantThemselvesAdmin(): void
    {
        $this->login();
        $crawler = $this->client->request('GET', $this->profileUrl('alice'));
        $values = $crawler->selectButton('Enregistrer')->form()->getPhpValues();
        $values['profile_form']['roles'] = ['ROLE_ADMIN'];
        $this->client->request('POST', $this->profileUrl('alice'), $values);
        self::assertResponseStatusCodeSame(422);
        self::assertSame(['ROLE_USER'], $this->user('alice')->getRoles());
        $this->client->request('POST', $this->profileUrl('bob'), $values);
        self::assertResponseStatusCodeSame(403);
        self::assertSame(['ROLE_USER'], $this->user('bob')->getRoles());
    }

    public function testAdminCanModifyBobAndAssignAllowedRole(): void
    {
        $this->login('admin');
        $crawler = $this->client->request('GET', $this->profileUrl('bob'));
        $form = $crawler->selectButton('Enregistrer')->form();
        $values = $form->getPhpValues();
        $values['profile_form']['prenom'] = 'Robert';
        $values['profile_form']['roles'] = ['ROLE_USER', 'ROLE_ADMIN'];
        $this->client->request('POST', $this->profileUrl('bob'), $values);
        self::assertResponseRedirects($this->profileUrl('bob'));
        self::assertSame('Robert', $this->user('bob')->getPrenom());
        self::assertContains('ROLE_ADMIN', $this->user('bob')->getRoles());
    }

    public function testAdminCannotAssignUnknownRole(): void
    {
        $this->login('admin');
        $crawler = $this->client->request('GET', $this->profileUrl('bob'));
        $values = $crawler->selectButton('Enregistrer')->form()->getPhpValues();
        $values['profile_form']['roles'] = ['ROLE_SUPER_ADMIN'];
        $this->client->request('POST', $this->profileUrl('bob'), $values);
        self::assertResponseStatusCodeSame(422);
        self::assertSame(['ROLE_USER'], $this->user('bob')->getRoles());
    }

    public function testAdminCanDeleteBobAndRelatedRecords(): void
    {
        // Bob possède déjà une trace de connexion : la suppression doit aussi fonctionner dans ce cas.
        $this->login('bob');
        $this->client->restart();
        $this->login('admin');
        $crawler = $this->client->request('GET', '/admin/users');
        $form = $crawler->filter('form[action="/admin/user/'.$this->ids['bob'].'/delete"]')->form();
        $this->client->submit($form);
        self::assertResponseRedirects('/admin/users');
        self::assertNull($this->em()->getRepository(\App\Entity\User::class)->find($this->ids['bob']));
        self::assertSame(0, (int) $this->em()->getConnection()->fetchOne('SELECT COUNT(*) FROM login_log WHERE user_id = ?', [$this->ids['bob']]));
    }

    public function testSensitiveAdminActionsRequirePostAndCsrf(): void
    {
        $this->login('admin');
        foreach (['delete', 'toggle-suspend'] as $action) {
            $path = '/admin/user/'.$this->ids['bob'].'/'.$action;
            $this->client->request('GET', $path);
            self::assertResponseStatusCodeSame(405);
            $this->client->request('POST', $path, ['_token' => 'invalid']);
            self::assertResponseStatusCodeSame(403);
            self::assertFalse($this->user('bob')->isSuspended());
        }
    }
}
