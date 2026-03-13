<?php

namespace App\Controller;

use App\Controller\App\DashboardAppController;
use App\Controller\App\UserAppCrudController;
use App\Entity\Order;
use App\Entity\Plan;
use App\Entity\Purchase;
use App\Entity\Subscription;
use App\Entity\User;
use App\Event\UserFirstLoginEvent;
use App\Event\UserFirstSubscriptionEvent;
use App\Form\ReCaptchaType;
use App\Form\RegistrationType;
use App\Form\ResetPasswordFormType;
use App\Form\ResetPasswordRequestFormType;
use App\Repository\UserRepository;
use App\Security\LoginAuthenticator;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Csrf\TokenGenerator\TokenGeneratorInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\Security\Http\Authentication\UserAuthenticatorInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;


class SecurityController extends AbstractController
{
    /** Home page default */
    public function default(Request $request) 
    {
        return $this->redirectToRoute('app');
    }

    /**
     * Registration
     */
    public function registration(SessionInterface $session, Request $request, EntityManagerInterface $manager, MailerInterface $mailer, UserPasswordHasherInterface $passwordHasher, EventDispatcherInterface $eventDispatcher,  LoginAuthenticator $loginAuthenticator, UserAuthenticatorInterface $userAuthenticator, AdminUrlGenerator $adminUrlGenerator,) 
    {
        if ($this->isGranted('IS_AUTHENTICATED_FULLY')) {
            return $this->redirectToRoute('app');
        }
        
        $user = new User();
        $user->setLastLogin(new \DateTime());
        
        $form = $this->createForm(RegistrationType::class, $user);
        $form->handleRequest($request);
  
        if($form->isSubmitted() && $form->isValid()) {
         
            $user->setEmail($user->getEmail());
            $user->setRoles(["ROLE_USER"]);
            $hash = $passwordHasher->hashPassword($user, $user->getPassword());
            $user->setPassword($hash);
            $manager->persist($user);
            $manager->flush();

            //auto subscription
            $eventDispatcher->dispatch(new UserFirstSubscriptionEvent($user));
                
            //email de bienvenue
            $eventDispatcher->dispatch(new UserFirstLoginEvent($user));

            //Redirect to home and autologin after registration
            $session->set('registration', true);
            //$this->addFlash('success', "Votre compte avec l'abonnement ". $session->get('subscriptionPlan')->getName() ." a bien été créé");

            return $userAuthenticator->authenticateUser(
                $user,
                $loginAuthenticator,
                $request
            );

        }    

        return $this->render('security/register.html.twig', [
            'form' => $form->createView(),
        ]);

    }

    /**
    * Login
    */
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        if ($this->isGranted('IS_AUTHENTICATED_FULLY')) {
            return $this->redirectToRoute('app');
        }

        // get the login error if there is one
        $error = $authenticationUtils->getLastAuthenticationError();
        // last username entered by the user
        $lastUsername = $authenticationUtils->getLastUsername();

        return $this->render('security/login.html.twig', ['last_username' => $lastUsername, 'error' => $error]);
    }

    /**
     * Logout
     */
    public function logout(): void
    {
        throw new \LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }

    #[Route('/reset-password', name:'forgotten_password')]
    public function forgottenPassword(Request $request,UserRepository $usersRepository,TokenGeneratorInterface $tokenGenerator,EntityManagerInterface $entityManager,MailerInterface $mail): Response
    {
        $form = $this->createForm(ResetPasswordRequestFormType::class);

        $form->handleRequest($request);

        if($form->isSubmitted() && $form->isValid()){
            //On va chercher l'utilisateur par son email
            $user = $usersRepository->findOneBy(['email' => $form->get('email')->getData()]);

            // On vérifie si on a un utilisateur
            if($user){
                // On génère un token de réinitialisation
                $token = $tokenGenerator->generateToken();
                $user->setResetToken($token);
                $entityManager->persist($user);
                $entityManager->flush();

                // On génère un lien de réinitialisation du mot de passe
                $url = $this->generateUrl('reset_pass', ['token' => $token], UrlGeneratorInterface::ABSOLUTE_URL);
                
                $email = (new TemplatedEmail())
                    ->to(new Address($user->getEmail()))
                    ->subject('Réinitialisation de mot de passe')
                    ->htmlTemplate('security/email.html.twig')
                    ->context([
                        'user' => $user,
                        'url' => $url
                    ]);
            
                $mail->send($email);

                $this->addFlash('success', 'Un email avec les instructions pour réinitialiser votre mot de passe vient de vous être envoyé.');
                return $this->redirectToRoute('security_login');
            }
            // $user est null
            $this->addFlash('danger', 'Adresse email non reconnue');
            
            return $this->redirectToRoute('security_login');
        }

        return $this->render('security/reset_password_request.html.twig', [
            'requestPassForm' => $form->createView()
        ]);
    }

    #[Route('/reset-password/{token}', name:'reset_pass')]
    public function resetPass(
        string $token,
        Request $request,
        UserRepository $usersRepository,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher
    ): Response
    {
        // On vérifie si on a ce token dans la base
        $user = $usersRepository->findOneBy(['resetToken' => $token]);
        
        // On vérifie si l'utilisateur existe

        if($user){
            $form = $this->createForm(ResetPasswordFormType::class);

            $form->handleRequest($request);

            if($form->isSubmitted() && $form->isValid()){
                // On efface le token
                $user->setResetToken('');
                
                
        // On enregistre le nouveau mot de passe en le hashant
                $user->setPassword(
                    $passwordHasher->hashPassword(
                        $user,
                        $form->get('password')->getData()
                    )
                );
                $entityManager->persist($user);
                $entityManager->flush();

                $this->addFlash('success', 'Mot de passe changé avec succès');
                return $this->redirectToRoute('security_login');
            }

            return $this->render('security/reset_password.html.twig', [
                'passForm' => $form->createView()
            ]);
        }
        
        // Si le token est invalide on redirige vers le login
        $this->addFlash('danger', 'Lien invalide - Invalid token');
        return $this->redirectToRoute('security_login');
    }

    #[Route('/connect/google', name: 'connect_google_start')]
    public function connectGoogle(ClientRegistry $clientRegistry): RedirectResponse
    {
        return $clientRegistry
            ->getClient('google')
            ->redirect(['openid', 'profile', 'email'],
            [
                'prompt' => 'consent select_account', // <- force l’écran de choix de compte
                // 'access_type' => 'offline', // optionnel
                // 'include_granted_scopes' => 'true', // optionnel
            ]);
    }
}    