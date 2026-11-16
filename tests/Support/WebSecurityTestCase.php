<?php

namespace App\Tests\Support;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

abstract class WebSecurityTestCase extends WebTestCase
{
    protected KernelBrowser $client;
    protected array $ids = [];
    protected const ALICE_PASSWORD = 'AliceSecure42!Word';
    protected const BOB_PASSWORD = 'BobSecure84!Word';

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = static::createClient([], ['HTTP_HOST' => 'localhost', 'HTTP_ORIGIN' => 'http://localhost', 'HTTP_REFERER' => 'http://localhost/']);
        $em = $this->em();
        TestDatabase::guard($em->getConnection(), static::$kernel->getEnvironment());
        foreach (['login_log', 'password_history', 'user'] as $table) {
            $em->getConnection()->executeStatement('DELETE FROM `'.$table.'`');
        }
        static::getContainer()->get('cache.rate_limiter')->clear();
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        foreach (['alice' => [], 'bob' => [], 'admin' => ['ROLE_ADMIN'], 'suspended' => []] as $name => $roles) {
            $user = (new User())->setEmail($name.'@example.test')->setRoles($roles)
                ->setNom(ucfirst($name))->setPrenom(ucfirst($name))->setAdresse('1 rue des Tests')->setVille('Paris')->setCodePostal('75001')
                ->setIsSuspended($name === 'suspended');
            $password = $name === 'bob' ? self::BOB_PASSWORD : self::ALICE_PASSWORD;
            $user->setPassword($hasher->hashPassword($user, $password));
            $em->persist($user);
            $em->flush();
            $this->ids[$name] = $user->getId();
        }
        $em->clear();
    }

    protected function em(): EntityManagerInterface
    {
        return static::getContainer()->get(EntityManagerInterface::class);
    }

    protected function user(string $name): User
    {
        $this->em()->clear();
        return $this->em()->getRepository(User::class)->find($this->ids[$name]);
    }

    protected function login(string $name = 'alice', ?string $password = null): void
    {
        $crawler = $this->client->request('GET', '/login');
        $this->client->submit($crawler->selectButton('Sign in')->form([
            '_username' => $name.'@example.test',
            '_password' => $password ?? ($name === 'bob' ? self::BOB_PASSWORD : self::ALICE_PASSWORD),
        ]));
    }

    protected function profileUrl(string $name): string
    {
        return '/profile/'.$this->ids[$name].'/edit';
    }

    protected function changePassword(string $old, string $new): void
    {
        $crawler = $this->client->request('GET', '/change-password');
        self::assertResponseIsSuccessful();
        $this->client->submit($crawler->selectButton('Valider')->form(['current_password' => $old, 'new_password' => $new, 'confirm_new_password' => $new]));
    }

    protected function register(array $overrides = []): void
    {
        $crawler = $this->client->request('GET', '/register');
        $values = array_replace([
            'email' => 'new@example.test', 'nom' => 'Dupont', 'prenom' => 'Alice', 'adresse' => '1 rue des Tests',
            'ville' => 'Paris', 'codePostal' => '75001', 'agreeTerms' => '1',
            'plainPassword' => ['first' => 'UniqueRegistration42!Word', 'second' => 'UniqueRegistration42!Word'],
        ], $overrides);
        // Requête HTTP directe : les validations HTML du navigateur ne doivent pas masquer un défaut serveur.
        $token = $crawler->filter('input[name="registration_form[_token]"]')->attr('value');
        $values['_token'] = $token;
        if ($values['agreeTerms'] === null) {
            unset($values['agreeTerms']); // Une case décochée est absente de la requête HTTP.
        }
        $this->client->request('POST', '/register', ['registration_form' => $values]);
    }

    protected function assertRegistrationAbsent(): void
    {
        self::assertNull($this->em()->getRepository(User::class)->findOneBy(['email' => 'new@example.test']));
        self::assertSame(4, $this->em()->getRepository(User::class)->count([]));
    }
}
