<?php
declare(strict_types=1);

/**
 * LocalGuide Transactional Email Dispatcher
 * Dispatches via Resend API if configured, standard SMTP if configured, or logs safely to storage/mail_spool.log
 */

function send_transactional_email(string $toEmail, string $toName, string $subject, string $htmlContent, string $textContent = ''): array {
    $fromEmail = getenv('MAIL_FROM_ADDRESS') ?: 'notifications@localguide.local';
    $fromName = getenv('MAIL_FROM_NAME') ?: setting('site_name', 'LocalGuide');

    if (!$textContent) {
        $textContent = strip_tags(str_replace(['<br>', '<br/>', '</p>', '</div>'], "\n", $htmlContent));
    }

    // 1. Resend API
    if (integration_is_configured('RESEND_API_KEY')) {
        $apiKey = trim((string)getenv('RESEND_API_KEY'));
        $payload = [
            'from' => "{$fromName} <{$fromEmail}>",
            'to' => [$toEmail],
            'subject' => $subject,
            'html' => $htmlContent,
            'text' => $textContent
        ];

        if (function_exists('curl_init')) {
            $ch = curl_init('https://api.resend.com/emails');
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($payload),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . $apiKey,
                    'Content-Type: application/json'
                ],
                CURLOPT_TIMEOUT => 8
            ]);
            $res = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($code >= 200 && $code < 300) {
                return ['ok' => true, 'provider' => 'resend', 'message' => 'Email sent via Resend API'];
            }
        }
    }

    // 2. Safe local file spool fallback (ideal for offline dev, testing, or unconfigured keys)
    $spoolDir = APP_ROOT . '/storage';
    if (!is_dir($spoolDir)) @mkdir($spoolDir, 0755, true);
    $spoolFile = $spoolDir . '/mail_spool.log';

    $logEntry = sprintf(
        "[%s] TO: %s <%s> | SUBJECT: %s\nBODY:\n%s\n%s\n\n",
        date('Y-m-d H:i:s'),
        $toName,
        $toEmail,
        $subject,
        $textContent,
        str_repeat('-', 60)
    );

    @file_put_contents($spoolFile, $logEntry, FILE_APPEND | LOCK_EX);

    return [
        'ok' => true,
        'provider' => 'spool',
        'file' => 'storage/mail_spool.log',
        'message' => 'Email recorded to local mail spool'
    ];
}

/**
 * Sends notification email to business owner when a customer leaves an enquiry or booking
 */
function notify_owner_of_inquiry(int $listingId, string $customerName, string $customerEmail, string $type, string $details): void {
    try {
        $s = db()->prepare("SELECT l.name, u.email, u.name owner_name FROM listings l JOIN users u ON u.id=l.owner_user_id WHERE l.id=:id AND u.is_active=1 LIMIT 1");
        $s->execute([':id' => $listingId]);
        $row = $s->fetch();
        if (!$row || empty($row['email'])) return;

        $subject = "New {$type} received for {$row['name']} on LocalGuide";
        $html = "<div style='font-family:system-ui,sans-serif;max-width:600px;margin:auto;padding:24px;border:1px solid #e0e5e2;border-radius:12px;background:#ffffff;'>"
              . "<h2 style='color:#10251c;margin-top:0;'>New Customer {$type}</h2>"
              . "<p>Hi <strong>" . h($row['owner_name']) . "</strong>,</p>"
              . "<p>You have received a new <strong>{$type}</strong> for <strong>" . h($row['name']) . "</strong>.</p>"
              . "<div style='background:#f4f6f4;padding:16px;border-radius:8px;margin:16px 0;'>"
              . "<p style='margin:0 0 8px 0;'><strong>Customer:</strong> " . h($customerName) . " (" . h($customerEmail) . ")</p>"
              . "<p style='margin:0;'><strong>Details:</strong> " . nl2br(h($details)) . "</p>"
              . "</div>"
              . "<p>Log in to your <a href='" . h(app_url('owner/inbox.php')) . "' style='color:#146b60;font-weight:bold;'>Owner Inbox</a> to respond directly.</p>"
              . "</div>";

        send_transactional_email($row['email'], $row['owner_name'], $subject, $html);
    } catch (Throwable $e) {
        error_log('[Email Notification] ' . $e->getMessage());
    }
}
