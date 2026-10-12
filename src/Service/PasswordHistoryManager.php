<?php
namespace App\Service;

use App\Entity\PasswordHistory;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class PasswordHistoryManager
{
    public function __construct(
        private UserPasswordHasherInterface $passwordHasher,
        private EntityManagerInterface $entityManager
    ) {}

    /**
     * Vérifie si le mot de passe proposé correspond au mot de passe actuel
     * ou à l'un des anciens mots de passe enregistrés dans l'historique.
     */
    public function isPasswordAlreadyUsed(User $user, string $newPlainPassword): bool
    {
        // 1. Vérification avec le mot de passe ACTUEL
        if ($user->getPassword() && $this->passwordHasher->isPasswordValid($user, $newPlainPassword)) {
            return true;
        }

        // 2. Vérification dans l'HISTORIQUE des anciens mots de passe
        foreach ($user->getPasswordHistories() as $history) {
            // On crée un objet temporaire avec le hash stocké pour utiliser isPasswordValid
            $tempUser = new User();
            $tempUser->setPassword($history->getPassword());

            if ($this->passwordHasher->isPasswordValid($tempUser, $newPlainPassword)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Archive le mot de passe actuel dans l'historique
     */
    public function logOldPassword(User $user): void
    {
        if ($user->getPassword()) {
            $history = new PasswordHistory();
            $history->setUser($user);
            $history->setPassword($user->getPassword());
            $history->setCreatedAt(new \DateTimeImmutable());

            $this->entityManager->persist($history);
        }
    }
}