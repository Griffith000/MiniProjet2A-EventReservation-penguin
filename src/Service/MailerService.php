<?php

namespace App\Service;

use App\Entity\Event;
use App\Entity\Reservation;
use Resend\Client;
use Resend\Resend;

class MailerService
{
    private ?Client $client = null;
    private string $apiKey;

    public function __construct(string $resendApiKey)
    {
        $this->apiKey = $resendApiKey;
    }

    private function getClient(): Client
    {
        if ($this->client === null) {
            $this->client = Resend::client($this->apiKey);
        }

        return $this->client;
    }

    public function sendReservationConfirmation(Reservation $reservation): void
    {
        $event = $reservation->getEvent();
        $user = $reservation->getUser();

        $this->getClient()->emails->send([
            'from' => 'EventHub <onboarding@resend.dev>',
            'to' => [$user->getEmail()],
            'subject' => 'Confirmation de votre réservation - ' . $event->getTitle(),
            'html' => $this->getReservationEmailHtml($reservation, $event, $user->getEmail()),
        ]);
    }

    private function getReservationEmailHtml(Reservation $reservation, Event $event, string $email): string
    {
        $eventDate = $event->getDate()->format('d F Y à H:i');
        
        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirmation de réservation</title>
</head>
<body style="font-family: 'DM Sans', Arial, sans-serif; margin: 0; padding: 0; background-color: #faf9f7;">
    <table width="100%" cellpadding="0" cellspacing="0" style="max-width: 600px; margin: 0 auto; background-color: #ffffff;">
        <tr>
            <td style="padding: 40px 30px; text-align: center; background-color: #1a1a1a;">
                <h1 style="color: #ffffff; margin: 0; font-size: 28px;">Event<span style="color: #d4a574;">Hub</span></h1>
            </td>
        </tr>
        <tr>
            <td style="padding: 40px 30px;">
                <h2 style="color: #1a1a1a; margin: 0 0 20px 0; font-size: 24px;">Confirmation de réservation</h2>
                <p style="color: #666666; margin: 0 0 30px 0; font-size: 16px; line-height: 1.6;">
                    Bonjour,
                    <br><br>
                    Votre réservation a été confirmée avec succès !
                </p>
                
                <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #faf9f7; border-radius: 12px; margin-bottom: 30px;">
                    <tr>
                        <td style="padding: 20px;">
                            <p style="margin: 0 0 10px 0;"><strong style="color: #1a1a1a;">Événement</strong></p>
                            <p style="margin: 0 0 20px 0; color: #d4a574; font-size: 18px; font-weight: 600;">{$event->getTitle()}</p>
                            
                            <p style="margin: 0 0 8px 0;"><strong style="color: #1a1a1a;">Date</strong></p>
                            <p style="margin: 0 0 20px 0; color: #666666;">{$eventDate}</p>
                            
                            <p style="margin: 0 0 8px 0;"><strong style="color: #1a1a1a;">Lieu</strong></p>
                            <p style="margin: 0 0 20px 0; color: #666666;">{$event->getLocation()}</p>
                            
                            <p style="margin: 0 0 8px 0;"><strong style="color: #1a1a1a;">Numéro de réservation</strong></p>
                            <p style="margin: 0; color: #666666;">{$reservation->getId()}</p>
                        </td>
                    </tr>
                </table>
                
                <p style="color: #666666; margin: 0; font-size: 14px; line-height: 1.6;">
                    Merci d'avoir choisi EventHub. Nous vous retrouvons avec impatience !
                </p>
            </td>
        </tr>
        <tr>
            <td style="padding: 20px 30px; background-color: #f5f5f5; text-align: center;">
                <p style="margin: 0; color: #999999; font-size: 12px;">
                    © 2026 EventHub. Tous droits réservés.
                </p>
            </td>
        </tr>
    </table>
</body>
</html>
HTML;
    }
}
