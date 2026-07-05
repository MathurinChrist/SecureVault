<?php

namespace App\Controller;

use App\Entity\ContactMessage;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Routing\Annotation\Route;

class ContactController extends AbstractController
{
    #[Route('/contact', name: 'app_contact', methods: ['GET', 'POST'])]
    public function index(
        Request $request,
        MailerInterface $mailer,
        EntityManagerInterface $em,
        LoggerInterface $logger
    ): Response {
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('contact', $request->request->get('_token'))) {
                $this->addFlash('error', 'Session expirée. Veuillez réessayer.');
                return $this->redirectToRoute('app_contact');
            }

            $name    = trim($request->request->get('name', ''));
            $email   = trim($request->request->get('email', ''));
            $subject = trim($request->request->get('subject', ''));
            $message = trim($request->request->get('message', ''));

            if (!$name || !$email || !$subject || !$message) {
                $this->addFlash('error', 'Tous les champs sont obligatoires.');
                return $this->redirectToRoute('app_contact');
            }

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $this->addFlash('error', 'Adresse e-mail invalide.');
                return $this->redirectToRoute('app_contact');
            }

            if (\strlen($message) < 10) {
                $this->addFlash('error', 'Le message est trop court.');
                return $this->redirectToRoute('app_contact');
            }

            $contact = (new ContactMessage())
                ->setName($name)
                ->setEmail($email)
                ->setSubject($subject)
                ->setMessage($message);

            $em->persist($contact);
            $em->flush();

            try {
                $adminEmail = (new TemplatedEmail())
                    ->from('security@securevault.com')
                    ->to('contact@securevault.com')
                    ->replyTo($email)
                    ->subject('[Contact] ' . $subject)
                    ->htmlTemplate('emails/contact_admin.html.twig')
                    ->context([
                        'sender_name'  => $name,
                        'sender_email' => $email,
                        'subject'      => $subject,
                        'message'      => $message,
                    ]);

                $mailer->send($adminEmail);

                $confirmEmail = (new TemplatedEmail())
                    ->from('security@securevault.com')
                    ->to($email)
                    ->subject('Nous avons bien reçu votre message — SecureVault')
                    ->htmlTemplate('emails/contact_confirmation.html.twig')
                    ->context([
                        'sender_name' => $name,
                        'subject'     => $subject,
                    ]);

                $mailer->send($confirmEmail);
            } catch (\Exception $e) {
                $logger->error('Contact email failed: {error}', ['error' => $e->getMessage()]);
            }

            $logger->info('Contact message saved from {email}', ['email' => $email]);
            $this->addFlash('success', 'Votre message a été envoyé. Nous vous répondrons sous 48 h.');

            return $this->redirectToRoute('app_contact');
        }

        return $this->render('home/contact.html.twig');
    }
}
