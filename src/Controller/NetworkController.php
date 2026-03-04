<?php declare(strict_types=1);

namespace App\Controller;

use App\Entity\Network;
use App\Form\NetworkType;
use App\Repository\NetworkRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/networks')]
class NetworkController extends AbstractController
{
    #[Route('', name: 'app_network_index')]
    public function index(NetworkRepository $networkRepository): Response
    {
        return $this->render('network/index.html.twig', [
            'networks' => $networkRepository->findBy([], ['name' => 'ASC']),
        ]);
    }

    #[Route('/new', name: 'app_network_new')]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $network = new Network();

        $form = $this->createForm(NetworkType::class, $network);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($network);
            $em->flush();

            $this->addFlash('success', 'Netzwerk wurde erstellt.');

            return $this->redirectToRoute('app_network_show', ['id' => $network->getId()]);
        }

        return $this->render('network/new.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_network_show', requirements: ['id' => '\d+'])]
    public function show(Network $network): Response
    {
        return $this->render('network/show.html.twig', [
            'network' => $network,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_network_edit', requirements: ['id' => '\d+'])]
    public function edit(Request $request, Network $network, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(NetworkType::class, $network);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();

            $this->addFlash('success', 'Netzwerk wurde aktualisiert.');

            return $this->redirectToRoute('app_network_show', ['id' => $network->getId()]);
        }

        return $this->render('network/edit.html.twig', [
            'network' => $network,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/delete', name: 'app_network_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(Request $request, Network $network, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete-network-' . $network->getId(), $request->request->getString('_token'))) {
            $em->remove($network);
            $em->flush();

            $this->addFlash('success', 'Netzwerk wurde gelöscht.');
        }

        return $this->redirectToRoute('app_network_index');
    }
}
