<?php

namespace App\Controller;

use App\Entity\Agents;
use App\Entity\Logs;
use App\Entity\Users;
use App\Form\RegistrationFormType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;

class RegistrationController extends AbstractController
{
    #[Route('/register', name: 'app_register')]
    public function register(Request $request, UserPasswordHasherInterface $userPasswordHasher, EntityManagerInterface $entityManager): Response
    {

        $user = new Users();
        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            // encode the plain password
            $user->setPassword(
                $userPasswordHasher->hashPassword(
                    $user,
                    $form->get('plainPassword')->getData()
                )
            );
            $user->setDateCreated(new \DateTime());
            $user->setLoginTime(new \DateTime());


            $entityManager->persist($user);
            $entityManager->flush();

            if($form->get('user_type')->getData()=='agent') {

                $agent = new Agents();
                $agent->setUserId($user->getId());
                $agent->setUsername($form->get('username')->getData());
                $agent->setRole((array)$form->get('agent_role')->getData());
                $entityManager->persist($agent);
                $entityManager->flush();
            }

            // do anything else you need here, like send an email

            $log = new Logs();
            $log->setUserId($user->getId());
            $log->setActionName('register');
            $log->setDateCreated(new \DateTime());
            $entityManager->persist($log);
            $entityManager->flush();

            return $this->redirectToRoute('home_page');
        }




        return $this->render('registration/register.html.twig', [
            'registrationForm' => $form->createView(),$form->getErrors()
        ]);
    }
}
