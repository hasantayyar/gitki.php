<?php


namespace Net\Dontdrinkandroot\Gitki\BaseBundle\Security;


use HWI\Bundle\OAuthBundle\OAuth\Response\UserResponseInterface;
use HWI\Bundle\OAuthBundle\Security\Core\User\OAuthAwareUserProviderInterface;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UsernameNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

class UserProvider implements OAuthAwareUserProviderInterface, UserProviderInterface {


    public function loadUserByOAuthUserResponse(UserResponseInterface $response)
    {
        $user = new User();
        $user->setId($response->getResponse()['id']);
        $user->setLogin($response->getResponse()['login']);
        $user->setRealName($response->getResponse()['name']);
        $user->setEMail($response->getResponse()['email']);

        return $user;
    }

    public function loadUserByUsername($username)
    {
        throw new \Exception('Not possible to load user by name');
    }

    public function refreshUser(UserInterface $user)
    {
        if (!$this->supportsClass(get_class($user))) {
            throw new UnsupportedUserException(sprintf('Unsupported user class "%s"', get_class($user)));
        }

        return $user;
    }

    public function supportsClass($class)
    {
        return $class === 'Net\\Dontdrinkandroot\\Gitki\\BaseBundle\\Security\\User';
    }


} 