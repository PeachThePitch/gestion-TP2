<?php

namespace App\Controller;

use App\Service\PasswordHistoryManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')] // Seul un utilisateur connecté a accès
class ChangePasswordController extends AbstractController
{
    #[Route('/change-password', name: 'app_change_password')]
    public function changePassword(
        Request $request,
        UserPasswordHasherInterface $hasher,
        PasswordHistoryManager $historyManager,
        EntityManagerInterface $em
    ): Response {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        if ($request->isMethod('POST')) {
            $newPassword = $request->request->get('new_password');

            // 1. On vérifie si le nouveau mot de passe a déjà été utilisé
            if ($historyManager->isPasswordAlreadyUsed($user, $newPassword)) {
                $this->addFlash('danger', 'Vous ne pouvez pas réutiliser un ancien mot de passe !');
                return $this->redirectToRoute('app_change_password');
            }

            // 2. On enregistre le mot de passe actuel dans l'historique
            $historyManager->logOldPassword($user);

            // 3. On applique et hache le nouveau mot de passe
            $hashedPassword = $hasher->hashPassword($user, $newPassword);
            $user->setPassword($hashedPassword);

            $em->flush();

            $this->addFlash('success', 'Votre mot de passe a bien été modifié !');
            return $this->redirectToRoute('app_change_password');
        }

        return $this->render('change_password/index.html.twig');
    }
}