<?php
// src/Mailer.php – email helpers (uses PHPMailer via SMTP)

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

class Mailer
{
    private static function send(string $to, string $subject, string $bodyHtml): void
    {
        $mail = new PHPMailer(true);

        try {
            $mail->isSMTP();
            $mail->Host       = SMTP_HOST;
            $mail->SMTPAuth   = true;
            $mail->Username   = SMTP_USER;
            $mail->Password   = SMTP_PASS;
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = SMTP_PORT;
            $mail->CharSet    = 'UTF-8';

            $mail->setFrom(SMTP_FROM, SMTP_FROM_NAME);
            $mail->addAddress($to);

            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $bodyHtml;

            $mail->send();
        } catch (PHPMailerException $e) {
            error_log('Mailer error – could not send to ' . $to . ': ' . $e->getMessage());
        }
    }

    public static function sendBookingConfirmationToAttendee(
        array $user,
        array $session
    ): void {
        $subject = 'Confirmation de réservation – ' . $session['title'];
        $body    = self::bookingConfirmationBody($user, $session);
        self::send($user['email'], $subject, $body);
    }

    public static function sendBookingNotificationToAdmin(
        array $user,
        array $session
    ): void {
        $subject = '[Admin] Nouvelle réservation – ' . $session['title'];
        $body    = self::adminNotificationBody($user, $session);
        self::send(ADMIN_EMAIL, $subject, $body);
    }

    public static function sendCancellationConfirmationToAttendee(
        array $user,
        array $session
    ): void {
        $subject = 'Annulation de réservation – ' . $session['title'];
        $body    = self::cancellationConfirmationBody($user, $session);
        self::send($user['email'], $subject, $body);
    }

    public static function sendCancellationNotificationToAdmin(
        array $user,
        array $session
    ): void {
        $subject = '[Admin] Annulation de réservation – ' . $session['title'];
        $body    = self::adminCancellationBody($user, $session);
        self::send(ADMIN_EMAIL, $subject, $body);
    }

    public static function sendSessionConfirmationToAttendee(
        array $user,
        array $session
    ): void {
        $subject = 'Séance confirmée – ' . $session['title'];
        $body    = self::sessionConfirmationBody($user, $session);
        self::send($user['email'], $subject, $body);
    }

    public static function sendSessionCancellationToAttendee(
        array $user,
        array $session
    ): void {
        $subject = 'Séance annulée – ' . $session['title'];
        $body    = self::sessionCancellationBody($user, $session);
        self::send($user['email'], $subject, $body);
    }

    public static function sendAttendanceConfirmationToAttendee(
        array $user,
        array $session
    ): void {
        $subject = 'Merci pour votre participation – ' . $session['title'];
        $body    = self::attendanceConfirmationBody($user, $session);
        self::send($user['email'], $subject, $body);
    }

    public static function sendRegistrationNotificationToAdmin(array $user): void
    {
        $safeName = str_replace(["\r", "\n"], '', $user['first_name'] . ' ' . $user['last_name']);
        $subject  = '[Admin] Nouvelle inscription – ' . $safeName;
        $body     = self::adminRegistrationBody($user);
        self::send(ADMIN_EMAIL, $subject, $body);
    }

    public static function sendPrivateSessionInvitation(
        array $user,
        array $session
    ): void {
        $subject = 'Invitation – ' . $session['title'];
        $body    = self::privateSessionInvitationBody($user, $session);
        self::send($user['email'], $subject, $body);
    }

    public static function sendUpcomingSessionsAnnouncement(
        array $user,
        array $sessions,
        string $fromDate,
        string $toDate
    ): void {
        $safeFrom = str_replace(["\r", "\n"], '', $fromDate);
        $safeTo   = str_replace(["\r", "\n"], '', $toDate);
        $subject = 'Prochaines séances – du ' . $safeFrom . ' au ' . $safeTo;
        $body    = self::upcomingSessionsAnnouncementBody($user, $sessions, $fromDate, $toDate);
        self::send($user['email'], $subject, $body);
    }

    public static function sendGroupBookingRequestToUser(
        array $user,
        array $request
    ): void {
        $subject = 'Demande de séance anniversaire reçue';
        $body    = self::groupBookingRequestUserBody($user, $request);
        self::send($user['email'], $subject, $body);
    }

    public static function sendGroupBookingRequestToAdmin(
        array $user,
        array $request
    ): void {
        $subject = '[Admin] Nouvelle demande de séance anniversaire';
        $body    = self::groupBookingRequestAdminBody($user, $request);
        self::send(ADMIN_EMAIL, $subject, $body);
    }

    public static function sendGroupBookingStatusUpdate(
        array $user,
        array $request
    ): void {
        $statusLabel = $request['status'] === 'confirmed' ? 'confirmée' : 'annulée';
        $subject     = 'Votre demande de séance anniversaire a été ' . $statusLabel;
        $body        = self::groupBookingStatusUpdateBody($user, $request);
        self::send($user['email'], $subject, $body);
    }

    private static function groupBookingRequestUserBody(array $user, array $request): string
    {
        $name        = htmlspecialchars($user['first_name'] . ' ' . $user['last_name']);
        $date        = htmlspecialchars($request['preferred_date']);
        $nbChildren  = (int) $request['nb_children'];
        $location    = $request['location_type'] === 'home' ? 'à votre domicile' : 'aux Escales Culinaires';
        $unitCents   = $request['location_type'] === 'home' ? 3000 : 3500;
        $unitPrice   = number_format($unitCents / 100, 2, ',', ' ') . ' €';
        $totalCents  = $nbChildren * $unitCents;
        $totalPrice  = number_format($totalCents / 100, 2, ',', ' ') . ' €';
        $baseUrl     = APP_BASE_URL;

        return <<<HTML
        <p>Bonjour {$name},</p>
        <p>Nous avons bien reçu votre demande de séance anniversaire. Voici un récapitulatif :</p>
        <ul>
            <li>📅 Date souhaitée : <strong>{$date}</strong></li>
            <li>👧 Nombre d'enfants : <strong>{$nbChildren}</strong></li>
            <li>📍 Lieu : <strong>{$location}</strong></li>
            <li>💶 Tarif estimé : {$unitPrice}/enfant → <strong>{$totalPrice} au total</strong></li>
        </ul>
        <p>Nous vous contacterons dans les plus brefs délais pour confirmer la disponibilité et finaliser les détails.</p>
        <p>À très bientôt aux Escales Culinaires !</p>
        <p><a href="{$baseUrl}/my-group-bookings.php">Voir ma demande</a></p>
        HTML;
    }

    private static function groupBookingRequestAdminBody(array $user, array $request): string
    {
        $name        = htmlspecialchars($user['first_name'] . ' ' . $user['last_name']);
        $email       = htmlspecialchars($user['email']);
        $phone       = htmlspecialchars($request['contact_phone'] ?? '–');
        $date        = htmlspecialchars($request['preferred_date']);
        $nbChildren  = (int) $request['nb_children'];
        $location    = $request['location_type'] === 'home'
            ? 'Domicile : ' . htmlspecialchars($request['location_address'] ?? '–')
            : 'Escales Culinaires';
        $allergies   = htmlspecialchars($request['allergies'] ?? '–');
        $info        = htmlspecialchars($request['additional_info'] ?? '–');
        $baseUrl     = APP_BASE_URL;
        $adminUrl    = $baseUrl . '/admin/group-booking-view.php?id=' . (int) $request['id'];

        return <<<HTML
        <p>Nouvelle demande de séance anniversaire soumise :</p>
        <ul>
            <li>👤 Contact : <strong>{$name}</strong> ({$email}) – tél. {$phone}</li>
            <li>📅 Date souhaitée : <strong>{$date}</strong></li>
            <li>👧 Nombre d'enfants : <strong>{$nbChildren}</strong></li>
            <li>📍 Lieu : {$location}</li>
            <li>🥜 Allergies : {$allergies}</li>
            <li>📝 Informations complémentaires : {$info}</li>
        </ul>
        <p><a href="{$adminUrl}">→ Traiter la demande dans l'administration</a></p>
        HTML;
    }

    private static function groupBookingStatusUpdateBody(array $user, array $request): string
    {
        $name        = htmlspecialchars($user['first_name'] . ' ' . $user['last_name']);
        $date        = htmlspecialchars($request['preferred_date']);
        $statusLabel = $request['status'] === 'confirmed' ? 'confirmée ✅' : 'annulée ❌';
        $adminNotes  = $request['admin_notes'] ? htmlspecialchars($request['admin_notes']) : null;
        $baseUrl     = APP_BASE_URL;

        $notesBlock = $adminNotes
            ? "<p>💬 Message de notre équipe : <em>{$adminNotes}</em></p>"
            : '';

        return <<<HTML
        <p>Bonjour {$name},</p>
        <p>Votre demande de séance anniversaire pour le <strong>{$date}</strong> a été <strong>{$statusLabel}</strong>.</p>
        {$notesBlock}
        <p>Pour toute question, répondez simplement à cet e-mail.</p>
        <p>À bientôt aux Escales Culinaires !</p>
        <p><a href="{$baseUrl}/my-group-bookings.php">Voir ma demande</a></p>
        HTML;
    }

    public static function sendRatingReminder(
        array $user,
        array $session
    ): void {
        $subject = 'Votre avis nous intéresse – ' . $session['title'];
        $body    = self::ratingReminderBody($user, $session);
        self::send($user['email'], $subject, $body);
    }

    private static function ratingReminderBody(array $user, array $session): string
    {
        $name      = htmlspecialchars($user['first_name'] . ' ' . $user['last_name']);
        $title     = htmlspecialchars($session['title']);
        $date      = htmlspecialchars($session['session_date']);
        $baseUrl   = APP_BASE_URL;
        $ratingUrl = $baseUrl . '/rate-session.php?session_id=' . (int) $session['id'];

        return <<<HTML
        <p>Bonjour {$name},</p>
        <p>Nous espérons que la séance <strong>{$title}</strong> du {$date} vous a plu !</p>
        <p>Votre avis est précieux pour nous aider à améliorer nos ateliers.
           Cela ne prend qu'une minute :</p>
        <p><a href="{$ratingUrl}">⭐ Donner mon avis sur la séance</a></p>
        <p>Merci d'avance, et à bientôt aux Escales Culinaires !</p>
        HTML;
    }

    private static function sessionConfirmationBody(array $user, array $session): string
    {
        $name    = htmlspecialchars($user['first_name'] . ' ' . $user['last_name']);
        $title   = htmlspecialchars($session['title']);
        $date    = htmlspecialchars($session['session_date']);
        $start   = htmlspecialchars($session['start_time']);
        $end     = htmlspecialchars($session['end_time']);
        $baseUrl = APP_BASE_URL;

        return <<<HTML
        <p>Bonjour {$name},</p>
        <p>Bonne nouvelle ! La séance <strong>{$title}</strong> est confirmée et aura bien lieu.</p>
        <p>📅 Date : {$date}<br>🕐 Horaires : {$start} – {$end}</p>
        <p>Nous avons hâte de vous accueillir !</p>
        <p>À bientôt aux Escales Culinaires !</p>
        <p><a href="{$baseUrl}/my-sessions.php">Voir mes réservations</a></p>
        HTML;
    }

    private static function sessionCancellationBody(array $user, array $session): string
    {
        $name    = htmlspecialchars($user['first_name'] . ' ' . $user['last_name']);
        $title   = htmlspecialchars($session['title']);
        $date    = htmlspecialchars($session['session_date']);
        $start   = htmlspecialchars($session['start_time']);
        $end     = htmlspecialchars($session['end_time']);
        $baseUrl = APP_BASE_URL;

        return <<<HTML
        <p>Bonjour {$name},</p>
        <p>Nous sommes désolés de vous informer que la séance <strong>{$title}</strong> est annulée faute d'un nombre suffisant de participants.</p>
        <p>📅 Date : {$date}<br>🕐 Horaires : {$start} – {$end}</p>
        <p>Votre paiement sera intégralement remboursé sous quelques jours ouvrés.</p>
        <p>À bientôt aux Escales Culinaires !</p>
        <p><a href="{$baseUrl}/my-sessions.php">Voir mes réservations</a></p>
        HTML;
    }

    private static function bookingConfirmationBody(array $user, array $session): string
    {
        $name    = htmlspecialchars($user['first_name'] . ' ' . $user['last_name']);
        $title   = htmlspecialchars($session['title']);
        $date    = htmlspecialchars($session['session_date']);
        $start   = htmlspecialchars($session['start_time']);
        $end     = htmlspecialchars($session['end_time']);
        $baseUrl = APP_BASE_URL;

        return <<<HTML
        <p>Bonjour {$name},</p>
        <p>Votre réservation pour la session <strong>{$title}</strong> est confirmée.</p>
        <p>📅 Date : {$date}<br>🕐 Horaires : {$start} – {$end}</p>
        <p>📍 L'atelier se déroule au :<br>
           <strong>Les Escales Culinaires</strong><br>
           36 rue Boieldieu, 31300 Toulouse</p>
        <p>⏰ Merci d'amener votre enfant <strong>10 minutes avant le début</strong> de la séance
           et de le récupérer dans les <strong>10 minutes suivant la fin</strong>.</p>
        <p>📋 Quelques consignes pratiques :</p>
        <ul>
          <li>Votre enfant peut apporter <strong>son propre tablier</strong> ; un tablier est fourni par l'organisatrice si besoin.</li>
          <li>Merci de <strong>retirer les chaussures</strong> en entrant dans l'atelier.</li>
          <li>Pensez à apporter <strong>une boîte et un sac</strong> pour ramener la préparation à la maison.</li>
        </ul>
        <p>Vous recevrez le contenu détaillé après la session.</p>
        <p>À bientôt aux Escales Culinaires !</p>
        <p><a href="{$baseUrl}/my-sessions.php">Voir mes réservations</a></p>
        HTML;
    }

    private static function adminNotificationBody(array $user, array $session): string
    {
        $name    = htmlspecialchars($user['first_name'] . ' ' . $user['last_name']);
        $email   = htmlspecialchars($user['email']);
        $title   = htmlspecialchars($session['title']);
        $date    = htmlspecialchars($session['session_date']);
        $baseUrl = APP_BASE_URL;

        return <<<HTML
        <p>Nouvelle réservation reçue :</p>
        <ul>
          <li>Session : <strong>{$title}</strong> ({$date})</li>
          <li>Participant : {$name} ({$email})</li>
        </ul>
        <p><a href="{$baseUrl}/admin/attendees.php?session_id={$session['id']}">Voir les participants</a></p>
        HTML;
    }

    private static function cancellationConfirmationBody(array $user, array $session): string
    {
        $name    = htmlspecialchars($user['first_name'] . ' ' . $user['last_name']);
        $title   = htmlspecialchars($session['title']);
        $date    = htmlspecialchars($session['session_date']);
        $start   = htmlspecialchars($session['start_time']);
        $end     = htmlspecialchars($session['end_time']);
        $baseUrl = APP_BASE_URL;

        return <<<HTML
        <p>Bonjour {$name},</p>
        <p>Votre réservation pour la session <strong>{$title}</strong> a bien été annulée.</p>
        <p>📅 Date : {$date}<br>🕐 Horaires : {$start} – {$end}</p>
        <p>Le remboursement sera traité sous quelques jours ouvrés.</p>
        <p>À bientôt aux Escales Culinaires !</p>
        <p><a href="{$baseUrl}/my-sessions.php">Voir mes réservations</a></p>
        HTML;
    }

    private static function adminCancellationBody(array $user, array $session): string
    {
        $name    = htmlspecialchars($user['first_name'] . ' ' . $user['last_name']);
        $email   = htmlspecialchars($user['email']);
        $title   = htmlspecialchars($session['title']);
        $date    = htmlspecialchars($session['session_date']);
        $baseUrl = APP_BASE_URL;

        return <<<HTML
        <p>Annulation de réservation reçue :</p>
        <ul>
          <li>Session : <strong>{$title}</strong> ({$date})</li>
          <li>Participant : {$name} ({$email})</li>
        </ul>
        <p><a href="{$baseUrl}/admin/attendees.php?session_id={$session['id']}">Voir les participants</a></p>
        HTML;
    }

    private static function adminRegistrationBody(array $user): string
    {
        $name    = htmlspecialchars($user['first_name'] . ' ' . $user['last_name']);
        $email   = htmlspecialchars($user['email']);
        $baseUrl = htmlspecialchars(APP_BASE_URL);

        return <<<HTML
        <p>Un nouveau compte vient d'être créé sur le site :</p>
        <ul>
          <li>Nom : <strong>{$name}</strong></li>
          <li>E-mail : {$email}</li>
        </ul>
        <p><a href="{$baseUrl}/admin/index.php">Accéder à l'administration</a></p>
        HTML;
    }

    private static function attendanceConfirmationBody(array $user, array $session): string
    {
        $name       = htmlspecialchars($user['first_name'] . ' ' . $user['last_name']);
        $title      = htmlspecialchars($session['title']);
        $date       = htmlspecialchars($session['session_date']);
        $baseUrl    = APP_BASE_URL;
        $contentUrl = $baseUrl . '/session-content.php?session_id=' . (int) $session['id'];
        $ratingUrl  = $baseUrl . '/rate-session.php?session_id=' . (int) $session['id'];

        return <<<HTML
        <p>Bonjour {$name},</p>
        <p>Merci d'avoir participé à la séance <strong>{$title}</strong> du {$date} !</p>
        <p>Vous pouvez maintenant :</p>
        <ul>
          <li>📚 <a href="{$contentUrl}">Accéder au contenu complet de la séance</a> (objectifs, recette, photos)</li>
          <li>⭐ <a href="{$ratingUrl}">Donner votre avis sur la séance</a></li>
        </ul>
        <p>À bientôt aux Escales Culinaires !</p>
        HTML;
    }

    private static function privateSessionInvitationBody(array $user, array $session): string
    {
        $name    = htmlspecialchars($user['first_name'] . ' ' . $user['last_name']);
        $title   = htmlspecialchars($session['title']);
        $date    = htmlspecialchars($session['session_date']);
        $start   = htmlspecialchars($session['start_time']);
        $end     = htmlspecialchars($session['end_time']);
        $baseUrl = APP_BASE_URL;
        $sessionUrl = $baseUrl . '/session.php?id=' . (int) $session['id'];

        return <<<HTML
        <p>Bonjour {$name},</p>
        <p>Vous avez été invité(e) à participer à la séance privée <strong>{$title}</strong>.</p>
        <p>📅 Date : {$date}<br>🕐 Horaires : {$start} – {$end}</p>
        <p>Pour réserver votre place, cliquez sur le lien ci-dessous :</p>
        <p><a href="{$sessionUrl}">Voir la séance et réserver</a></p>
        <p>À bientôt aux Escales Culinaires !</p>
        HTML;
    }

    private static function upcomingSessionsAnnouncementBody(
        array $user,
        array $sessions,
        string $fromDate,
        string $toDate
    ): string {
        $name       = htmlspecialchars(trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')));
        $safeFrom   = htmlspecialchars($fromDate);
        $safeTo     = htmlspecialchars($toDate);
        $baseUrl    = APP_BASE_URL;
        $sessionsUi = '';

        foreach ($sessions as $session) {
            $title      = htmlspecialchars((string) ($session['title'] ?? 'Séance'));
            $date       = htmlspecialchars((string) ($session['session_date'] ?? ''));
            $start      = htmlspecialchars((string) ($session['start_time'] ?? ''));
            $end        = htmlspecialchars((string) ($session['end_time'] ?? ''));
            $age        = htmlspecialchars((string) ($session['age_category'] ?? ''));
            $priceCents = isset($session['price_cents']) ? (int) $session['price_cents'] : 0;
            $price      = number_format($priceCents / 100, 2, ',', ' ') . ' €';
            $sessionUrl = $baseUrl . '/session.php?id=' . (int) ($session['id'] ?? 0);

            $sessionsUi .= <<<HTML
            <li style="margin-bottom:.75rem">
                <strong>{$title}</strong><br>
                📅 {$date} – 🕐 {$start} à {$end}<br>
                👧 Âge : {$age} – 💶 Tarif : {$price}<br>
                <a href="{$sessionUrl}">Voir la séance</a>
            </li>
            HTML;
        }

        if ($sessionsUi === '') {
            $sessionsUi = '<li>Aucune séance programmée sur cette période.</li>';
        }

        return <<<HTML
        <p>Bonjour {$name},</p>
        <p>Voici les séances prévues du <strong>{$safeFrom}</strong> au <strong>{$safeTo}</strong> :</p>
        <ul>
            {$sessionsUi}
        </ul>
        <p><a href="{$baseUrl}/">Voir toutes les séances</a></p>
        HTML;
    }
}
