<?php

namespace App\Controller\Api;

use App\Entity\ContactMessage;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Routing\Attribute\Route;

#[OA\Tag(name: 'Contact')]
#[Route('/api/v1/contact', name: 'api_contact_')]
class ContactController extends AbstractController
{
    #[Route('', name: 'submit', methods: ['POST'])]
    #[OA\RequestBody(required: true, content: new OA\JsonContent(
        required: ['name', 'email', 'subject', 'message'],
        properties: [
            new OA\Property(property: 'name', type: 'string'),
            new OA\Property(property: 'email', type: 'string', format: 'email'),
            new OA\Property(property: 'subject', type: 'string'),
            new OA\Property(property: 'message', type: 'string', minLength: 10),
        ],
    ))]
    #[OA\Response(response: 201, description: 'Message received. Public endpoint — no authentication required.')]
    #[OA\Response(response: 422, description: 'Validation error.')]
    public function submit(
        Request $request,
        MailerInterface $mailer,
        EntityManagerInterface $em,
        LoggerInterface $logger,
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);
        if (!is_array($data)) {
            return $this->json(['error' => 'Invalid JSON body.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $name    = trim((string) ($data['name'] ?? ''));
        $email   = trim((string) ($data['email'] ?? ''));
        $subject = trim((string) ($data['subject'] ?? ''));
        $message = trim((string) ($data['message'] ?? ''));

        if ($name === '' || $email === '' || $subject === '' || $message === '') {
            return $this->json(['error' => 'All fields are required.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->json(['error' => 'Invalid email address.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if (\strlen($message) < 10) {
            return $this->json(['error' => 'Message is too short.'], Response::HTTP_UNPROCESSABLE_ENTITY);
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

        return $this->json(['message' => 'Your message has been received.'], Response::HTTP_CREATED);
    }
}
