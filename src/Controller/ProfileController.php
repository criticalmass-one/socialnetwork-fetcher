<?php declare(strict_types=1);

namespace App\Controller;

use App\Entity\Profile;
use App\Form\ProfileType;
use App\Repository\ItemRepository;
use App\Repository\ProfileRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/profiles')]
class ProfileController extends AbstractController
{
    #[Route('', name: 'app_profile_index')]
    public function index(ProfileRepository $profileRepository): Response
    {
        return $this->render('profile/index.html.twig', [
            'profiles' => $profileRepository->findBy([], ['identifier' => 'ASC']),
        ]);
    }

    #[Route('/new', name: 'app_profile_new')]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $profile = new Profile();
        $profile->setCreatedAt(new \DateTimeImmutable());

        $form = $this->createForm(ProfileType::class, $profile, ['is_new' => true]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($profile);
            $em->flush();

            $this->addFlash('success', 'Profil wurde erstellt.');

            return $this->redirectToRoute('app_profile_show', ['id' => $profile->getId()]);
        }

        return $this->render('profile/new.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_profile_show', requirements: ['id' => '\d+'])]
    public function show(Profile $profile, ItemRepository $itemRepository): Response
    {
        $itemCount = $itemRepository->count(['profile' => $profile]);

        return $this->render('profile/show.html.twig', [
            'profile' => $profile,
            'itemCount' => $itemCount,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_profile_edit', requirements: ['id' => '\d+'])]
    public function edit(Request $request, Profile $profile, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(ProfileType::class, $profile);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();

            $this->addFlash('success', 'Profil wurde aktualisiert.');

            return $this->redirectToRoute('app_profile_show', ['id' => $profile->getId()]);
        }

        return $this->render('profile/edit.html.twig', [
            'profile' => $profile,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/delete', name: 'app_profile_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(Request $request, Profile $profile, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete-profile-' . $profile->getId(), $request->request->getString('_token'))) {
            $em->remove($profile);
            $em->flush();

            $this->addFlash('success', 'Profil wurde gelöscht.');
        }

        return $this->redirectToRoute('app_profile_index');
    }
}
