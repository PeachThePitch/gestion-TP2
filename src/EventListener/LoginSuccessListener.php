<?php
namespace App\EventListener;

use App\Entity\LoginLog;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;
use DateTimeImmutable;
use DateTimeZone;

#[AsEventListener(event: LoginSuccessEvent::class)]
class LoginSuccessListener
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {}

    public function __invoke(LoginSuccessEvent $event): void
    {
        $user = $event->getUser();

        if (!$user instanceof User) {
            return;
        }

        $request = $event->getRequest();

        $log = new LoginLog();
        $log->setUser($user);
        $log->setLoggedAt(new DateTimeImmutable('now', new DateTimeZone('Europe/Paris')));
        $log->setIpAddress($request->getClientIp() ?? '127.0.0.1');

        $this->entityManager->persist($log);
        $this->entityManager->flush();
    }
}
