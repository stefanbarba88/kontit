<?php

namespace App\Controller;

use App\Form\NotificationPreferencesType;
use App\Form\OwnPasswordType;
use App\Form\ProfileType;
use App\Repository\ActivityLogRepository;
use App\Repository\TeamMemberRepository;
use App\Repository\TeamRepository;
use App\Service\UserService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/profile')]
class ProfileController extends AbstractController
{
    #[Route('', name: 'app_profile', methods: ['GET', 'POST'])]
    public function index(
        Request $request,
        EntityManagerInterface $em,
        UserService $users,
        TeamMemberRepository $members,
        TeamRepository $teams,
        ActivityLogRepository $activity,
    ): Response {
        $user = $this->getUser();

        $profileForm = $this->createForm(ProfileType::class, $user);
        $profileForm->handleRequest($request);
        if ($profileForm->isSubmitted() && $profileForm->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Profil je sačuvan.');

            return $this->redirectToRoute('app_profile');
        }

        $prefsForm = $this->createForm(NotificationPreferencesType::class, $user);
        $prefsForm->handleRequest($request);
        if ($prefsForm->isSubmitted() && $prefsForm->isValid()) {
            $user->setEmailNotifications(array_values($user->getEmailNotifications()));
            $em->flush();
            $this->addFlash('success', 'Podešavanja obaveštenja su sačuvana.');

            return $this->redirectToRoute('app_profile');
        }

        $passwordForm = $this->createForm(OwnPasswordType::class);
        $passwordForm->handleRequest($request);
        if ($passwordForm->isSubmitted() && $passwordForm->isValid()) {
            $users->changePassword($user, $passwordForm->get('plainPassword')->getData());
            $this->addFlash('success', 'Lozinka je promenjena.');

            return $this->redirectToRoute('app_profile');
        }

        return $this->render('profile/index.html.twig', [
            'profileForm' => $profileForm,
            'passwordForm' => $passwordForm,
            'prefsForm' => $prefsForm,
            'membership' => $members->findActiveByUser($user),
            'ledTeam' => $teams->findActiveLedBy($user),
            'activity' => $activity->findUserPage($user),
        ]);
    }
}
