<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
#[Route('/admin')]
class AdminController extends AbstractController
{
    #[Route('/users', name: 'app_admin_users')]
    public function index(UserRepository $userRepository): Response
    {
        return $this->render('admin/index.html.twig', [
            'users' => $userRepository->findAll(),
        ]);
    }

    #[Route('/user/{id}/toggle-suspend', name: 'app_admin_toggle_suspend')]
    public function toggleSuspend(User $user, EntityManagerInterface $em): Response
    {
        $user->setIsSuspended(!$user->isSuspended());
        $em->flush();

        $status = $user->isSuspended() ? 'suspendu' : 'réactivé';
        $this->addFlash('success', "Le compte de {$user->getNom()} {$user->getPrenom()} a été {$status}.");

        return $this->redirectToRoute('app_admin_users');
    }
}