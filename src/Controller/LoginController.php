<?php declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class LoginController extends AbstractController
{
    #[Route('/login', name: 'app_login')]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        if ($this->getUser() instanceof \App\Entity\Client) {
            return $this->redirectToRoute('app_group_index');
        }
        if ($this->getUser()) {
            return $this->redirectToRoute('app_dashboard');
        }

        return $this->render('login/login.html.twig', [
            'last_username' => $authenticationUtils->getLastUsername(),
            'error' => $authenticationUtils->getLastAuthenticationError(),
        ]);
    }

    #[Route('/login/client-token', name: 'app_login_client_token', methods: ['POST'])]
    public function clientTokenLogin(): never
    {
        // Intercepted by WebTokenAuthenticator. This action is unreachable in
        // practice and only exists so the route shows up in debug:router and
        // returns 405-on-GET instead of a stray 404.
        throw new \LogicException('This method is intercepted by WebTokenAuthenticator.');
    }

    #[Route('/logout', name: 'app_logout')]
    public function logout(): never
    {
        throw new \LogicException('This method is intercepted by the logout firewall handler.');
    }
}
