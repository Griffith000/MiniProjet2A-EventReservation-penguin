<?php

namespace App\Security;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;

class LoginSuccessHandler implements AuthenticationSuccessHandlerInterface
{
    private RouterInterface $router;

    public function __construct(RouterInterface $router)
    {
        $this->router = $router;
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token): Response
    {
        $user = $token->getUser();
        
        if ($user && in_array('ROLE_ADMIN', $user->getRoles(), true)) {
            return new Response('', 302, ['Location' => $this->router->generate('app_admin_dashboard')]);
        }
        
        return new Response('', 302, ['Location' => $this->router->generate('app_event_list')]);
    }
}
