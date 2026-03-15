<?php declare(strict_types=1);

namespace App\Controller;

use App\Entity\Client;
use App\Repository\ClientRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/clients')]
class ClientController extends AbstractController
{
    #[Route('', name: 'app_client_index')]
    public function index(ClientRepository $clientRepository): Response
    {
        return $this->render('client/index.html.twig', [
            'clients' => $clientRepository->findBy([], ['name' => 'ASC']),
        ]);
    }

    #[Route('/new', name: 'app_client_new')]
    public function new(Request $request, ClientRepository $clientRepository, EntityManagerInterface $em): Response
    {
        if ($request->isMethod('POST')) {
            $name = trim($request->request->getString('name'));

            if ($name === '') {
                $this->addFlash('danger', 'Name darf nicht leer sein.');
                return $this->redirectToRoute('app_client_new');
            }

            if ($clientRepository->findOneByName($name)) {
                $this->addFlash('danger', sprintf('Client "%s" existiert bereits.', $name));
                return $this->redirectToRoute('app_client_new');
            }

            $client = new Client();
            $client->setName($name);
            $client->setToken(Client::generateToken());

            $em->persist($client);
            $em->flush();

            $this->addFlash('success', sprintf('Client "%s" erstellt. Token: %s', $name, $client->getToken()));

            return $this->redirectToRoute('app_client_show', ['id' => $client->getId()]);
        }

        return $this->render('client/new.html.twig');
    }

    #[Route('/{id}', name: 'app_client_show', requirements: ['id' => '\d+'])]
    public function show(Client $client): Response
    {
        return $this->render('client/show.html.twig', [
            'client' => $client,
        ]);
    }

    #[Route('/{id}/regenerate-token', name: 'app_client_regenerate_token', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function regenerateToken(Request $request, Client $client, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('regenerate-token-' . $client->getId(), $request->request->getString('_token'))) {
            $client->setToken(Client::generateToken());
            $em->flush();

            $this->addFlash('success', sprintf('Token neu generiert: %s', $client->getToken()));
        }

        return $this->redirectToRoute('app_client_show', ['id' => $client->getId()]);
    }

    #[Route('/{id}/toggle-enabled', name: 'app_client_toggle_enabled', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function toggleEnabled(Request $request, Client $client, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('toggle-client-' . $client->getId(), $request->request->getString('_token'))) {
            $client->setEnabled(!$client->isEnabled());
            $em->flush();

            $this->addFlash('success', sprintf('Client "%s" %s.', $client->getName(), $client->isEnabled() ? 'aktiviert' : 'deaktiviert'));
        }

        return $this->redirectToRoute('app_client_show', ['id' => $client->getId()]);
    }

    #[Route('/{id}/delete', name: 'app_client_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(Request $request, Client $client, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete-client-' . $client->getId(), $request->request->getString('_token'))) {
            $em->remove($client);
            $em->flush();

            $this->addFlash('success', sprintf('Client "%s" gelöscht.', $client->getName()));
        }

        return $this->redirectToRoute('app_client_index');
    }
}
