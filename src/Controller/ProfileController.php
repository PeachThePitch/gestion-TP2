<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\ProfileFormType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
final class ProfileController extends AbstractController
{
    #[Route('/profile', name: 'app_profile', methods: ['GET'])]
    public function ownProfile(): Response
    {
        return $this->redirectToRoute('app_profile_edit', ['id' => $this->getUser()->getId()]);
    }

    #[Route('/profile/{id}/edit', name: 'app_profile_edit', methods: ['GET', 'POST'])]
    public function edit(User $user, Request $request, EntityManagerInterface $em): Response
    {
        $isAdmin = $this->isGranted('ROLE_ADMIN');
        if (!$isAdmin && $user->getId() !== $this->getUser()->getId()) {
            throw $this->createAccessDeniedException('Vous ne pouvez modifier que votre propre profil.');
        }

        $canManageRoles = $this->isGranted('ROLE_ADMIN') && $user->getId() !== $this->getUser()->getId();

        $form = $this->createForm(ProfileFormType::class, $user, ['can_manage_roles' => $canManageRoles]);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Le profil a été modifié.');
            return $this->redirectToRoute('app_profile_edit', ['id' => $user->getId()]);
        }

        return $this->render('profile/edit.html.twig', ['profileForm' => $form, 'profileUser' => $user]);
    }
}
