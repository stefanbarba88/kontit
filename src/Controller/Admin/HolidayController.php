<?php

namespace App\Controller\Admin;

use App\Entity\Holiday;
use App\Form\HolidayType;
use App\Repository\HolidayRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/holidays')]
#[IsGranted('CATALOG_MANAGE')]
class HolidayController extends AbstractController
{
    #[Route('', name: 'admin_holiday_index', methods: ['GET', 'POST'])]
    public function index(Request $request, HolidayRepository $repository, EntityManagerInterface $em): Response
    {
        $holiday = new Holiday();
        $form = $this->createForm(HolidayType::class, $holiday);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($holiday);
            $em->flush();
            $this->addFlash('success', 'Praznik je dodat.');

            return $this->redirectToRoute('admin_holiday_index');
        }

        return $this->render('admin/holiday/index.html.twig', [
            'form' => $form,
            'holidays' => $repository->findBy([], ['date' => 'DESC']),
        ]);
    }

    #[Route('/{id}/delete', name: 'admin_holiday_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(Request $request, Holiday $holiday, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('holiday_delete_'.$holiday->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Neispravan CSRF token.');
        }
        $em->remove($holiday);
        $em->flush();
        $this->addFlash('success', 'Praznik je obrisan.');

        return $this->redirectToRoute('admin_holiday_index');
    }
}
