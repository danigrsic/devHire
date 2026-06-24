<?php
declare(strict_types=1);

namespace DevHire;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;


class Mailer
{
    private array $config;

    public function __construct()
    {
        $configFile = __DIR__ . '/../mail_config.php';
        if (file_exists($configFile)) {
            $this->config = require $configFile;
        } else {
            $this->config = [
                'host' => 'smtp.example.com',
                'username' => '',
                'password' => '',
                'port' => 587,
                'encryption' => 'tls',
                'from_email' => 'noreply@devhire.local',
                'from_name' => 'devHire',
                'app_url' => 'http://localhost/',
                
            ];
        }
    }

    /**
     * Send an email via SMTP
     * @param string $to Recipient email – this is the USER's email, can be anything
     * @param string $subject
     * @param string $htmlBody HTML content
     */
    public function send(string $to, string $subject, string $htmlBody, string $altBody = ''): bool
    {
        $logFile = __DIR__ . '/../assets/uploads/mail_log.txt';
        @is_dir(dirname($logFile)) || @mkdir(dirname($logFile), 0777, true);

        // If no SMTP configured, log only (dev mode)
        if (empty($this->config['username'])) {
            $log = date('c') . " | DEV-LOG | TO: $to | SUBJECT: $subject\n$htmlBody\n---\n\n";
            @file_put_contents($logFile, $log, FILE_APPEND);
            return false;
        }

        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host = $this->config['host'];
            $mail->SMTPAuth = true;
            $mail->Username = $this->config['username'];
            $mail->Password = $this->config['password'];
            
            $enc = strtolower($this->config['encryption'] ?? 'tls');
            $mail->SMTPSecure = ($enc === 'ssl') ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = (int)($this->config['port'] ?? 587);
            $mail->CharSet = 'UTF-8';

            // Sender = your SMTP account (fixed)
            $mail->setFrom($this->config['from_email'], $this->config['from_name']);
            // Recipient = the actual user (any email)
            $mail->addAddress($to);
            // No-reply
            $mail->addReplyTo($this->config['from_email'], $this->config['from_name']);

            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $this->wrapHtml($subject, $htmlBody);
            $mail->AltBody = $altBody ?: strip_tags(str_replace(['<br>', '</p>'], "\n", $htmlBody));

            $mail->send();
            @file_put_contents($logFile, date('c') . " | SENT | TO: $to | SUBJECT: $subject\n", FILE_APPEND);
            return true;
        } catch (Exception $e) {
            @file_put_contents($logFile, date('c') . " | FAILED | TO: $to | SUBJECT: $subject | ERR: " . $mail->ErrorInfo . "\n", FILE_APPEND);
            error_log('Mailer Error: ' . $mail->ErrorInfo);
            return false;
        }
    }

    private function wrapHtml(string $title, string $innerHtml): string
    {
        $fromName = htmlspecialchars($this->config['from_name'] ?? 'devHire');
        return '<!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body style="margin:0;background:#f6f5fa;font-family:Inter,Arial,sans-serif;color:#222">
<div style="max-width:560px;margin:32px auto;background:#fff;border:1px solid #e5e2ef;border-radius:16px;padding:28px 26px">
<div style="font-weight:800;font-size:20px;color:#221b3a;margin-bottom:14px">devHire</div>
'.$innerHtml.'
<hr style="border:none;border-top:1px solid #eee;margin:22px 0">
<p style="font-size:12px;color:#888">Sent by '.$fromName.' – IT Job Marketplace<br>This is an automated message, please do not reply.</p>
</div></body></html>';
    }

    public function sendActivationEmail(string $toEmail, string $firstName, string $verifyLink): bool
    {
        $fn = htmlspecialchars($firstName);
        $vl = htmlspecialchars($verifyLink);
        $subject = 'Activate your devHire account';
        $body = "<h2 style='margin:0 0 12px;color:#221b3a'>Welcome to devHire, {$fn}!</h2>
<p>Thank you for registering at devHire – IT Job Marketplace.</p>
<p>Please activate your account by clicking the button below:</p>
<p><a href='{$vl}' style='background:#221b3a;color:#fff;padding:12px 22px;border-radius:10px;text-decoration:none;display:inline-block;font-weight:600'>Activate Account</a></p>
<p style='font-size:13px;color:#555'>Or copy this link into your browser:<br><a href='{$vl}'>{$vl}</a></p>
<p style='font-size:12px;color:#888'>If you didn't register, just ignore this email.</p>";
        return $this->send($toEmail, $subject, $body);
    }

    public function sendLoginNotification(string $toEmail, string $firstName): bool
    {
        $fn = htmlspecialchars($firstName);
        $ip = htmlspecialchars($_SERVER['REMOTE_ADDR'] ?? 'unknown');
        $time = date('Y-m-d H:i:s');
        $ua = htmlspecialchars($_SERVER['HTTP_USER_AGENT'] ?? '');
        $subject = 'New login to your devHire account';
        $body = "<p>Hi {$fn},</p>
<p>We detected a new login to your devHire account.</p>
<p><strong>Time:</strong> {$time}<br><strong>IP:</strong> {$ip}<br><strong>Browser:</strong> {$ua}</p>
<p style='color:#555'>If this wasn't you, please change your password immediately in Security settings.</p>";
        return $this->send($toEmail, $subject, $body);
    }

    /** Optional: notify user when they get a new message in devHire */
    public function sendMessageNotification(string $toEmail, string $firstName, string $fromName, string $jobTitle): bool
    {
        if (empty($this->config['notify_on_message'])) {
            return false;
        }
        $fn = htmlspecialchars($firstName);
        $from = htmlspecialchars($fromName);
        $job = htmlspecialchars($jobTitle);
        $inboxUrl = rtrim($this->config['app_url'] ?? '', '/') . '/public/dashboard/messages.php';
        $subject = 'New message on devHire – ' . $jobTitle;
        $body = "<p>Hi {$fn},</p>
<p>You received a new message from <strong>{$from}</strong> regarding <strong>{$job}</strong>.</p>
<p><a href='{$inboxUrl}' style='background:#221b3a;color:#fff;padding:11px 20px;border-radius:10px;text-decoration:none;display:inline-block;font-weight:600'>Open Messages</a></p>
<p style='font-size:12px;color:#888'>You can turn off these notifications in your profile.</p>";
        return $this->send($toEmail, $subject, $body);
    }
}
