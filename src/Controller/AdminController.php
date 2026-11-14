<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
#[Route('/admin')]
class AdminController extends AbstractController
{
    #[Route('', name: 'app_admin', methods: ['GET'])]
    #[Route('/users', name: 'app_admin_users', methods: ['GET'])]
    public function index(UserRepository $userRepository): Response
    {
        return $this->render('admin/index.html.twig', [
            'users' => $userRepository->findAll(),
        ]);
    }

    #[Route('/user/{id}/toggle-suspend', name: 'app_admin_toggle_suspend', methods: ['POST'])]
    public function toggleSuspend(User $user, Request $request, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('suspend_'.$user->getId(), $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $user->setIsSuspended(!$user->isSuspended());
        $em->flush();

        $status = $user->isSuspended() ? 'suspendu' : 'réactivé';
        $this->addFlash('success', "Le compte de {$user->getNom()} {$user->getPrenom()} a été {$status}.");

        return $this->redirectToRoute('app_admin_users');
    }
    #[Route('/user/{id}/delete', name: 'app_admin_delete', methods: ['POST'])]
    public function delete(User $user, Request $request, EntityManagerInterface $em): Response
    {
        if ($user->getId() === $this->getUser()->getId()) {
            throw $this->createAccessDeniedException('Vous ne pouvez pas supprimer votre propre compte administrateur.');
        }
        if (!$this->isCsrfTokenValid('delete_'.$user->getId(), $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }
        $em->remove($user);
        $em->flush();
        $this->addFlash('success', 'Le compte a été supprimé.');
        return $this->redirectToRoute('app_admin_users');
    }
}