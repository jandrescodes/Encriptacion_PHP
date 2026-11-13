<?php

declare(strict_types=1);

namespace App\Service;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

class MailerService
{
    public function sendResetEmail(string $to, string $resetUrl): bool
    {
        $mail = new PHPMailer(true);

        try {
            $mail->SMTPDebug = SMTP::DEBUG_OFF;
            $mail->isSMTP();
            $mail->Host       = env('SMTP_HOST');
            $mail->SMTPAuth   = true;
            $mail->Username   = env('SMTP_USERNAME');
            $mail->Password   = env('SMTP_PASSWORD');
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = (int) env('SMTP_PORT', 587);
            $mail->CharSet    = 'UTF-8';

            $mail->setFrom(env('SMTP_USERNAME'), 'Password Recovery');
            $mail->addAddress($to);

            $mail->isHTML(true);
            $mail->Subject = 'Password Recovery';
            $mail->Body    = $this->buildResetEmailBody($resetUrl);

            $mail->send();
            return true;
        } catch (Exception) {
            return false;
        }
    }

    public function lastError(PHPMailer $mail): string
    {
        return $mail->ErrorInfo;
    }

    private function buildResetEmailBody(string $resetUrl): string
    {
        $year = date('Y');
        return "
            <!DOCTYPE html>
            <html>
            <head>
                <style>
                    body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                    .container { max-width: 600px; margin: 0 auto; padding: 20px; background: #f9f9f9; border-radius: 10px; }
                    .header { background: #2d3748; color: white; padding: 20px; text-align: center; border-radius: 10px 10px 0 0; }
                    .content { background: white; padding: 30px; border-radius: 0 0 10px 10px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
                    .button { display: inline-block; padding: 12px 24px; background: #4CAF50; color: white; text-decoration: none; border-radius: 5px; margin: 20px 0; font-weight: bold; }
                    .warning { background: #fff3cd; border: 1px solid #ffeeba; color: #856404; padding: 10px; border-radius: 5px; margin-top: 20px; font-size: 13px; }
                    .footer { text-align: center; margin-top: 20px; font-size: 12px; color: #666; }
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='header'><h1 style='margin:0;'>Password Recovery</h1></div>
                    <div class='content'>
                        <p>Hello,</p>
                        <p>We received a request to reset your account password. If you did not make this request, you can ignore this email.</p>
                        <div style='text-align:center;'>
                            <a href='{$resetUrl}' class='button' style='color:white;'>Reset Password</a>
                        </div>
                        <p>Or copy and paste the following link in your browser:</p>
                        <p style='word-break:break-all; font-size:14px; color:#666;'>{$resetUrl}</p>
                        <div class='warning'>
                            <strong>Important!</strong>
                            <p style='margin:5px 0 0 0;'>This link will expire in 1 hour for security reasons.</p>
                        </div>
                    </div>
                    <div class='footer'>
                        <p>This is an automated email, please do not reply.</p>
                        <p>&copy; {$year} Your System. All rights reserved.</p>
                    </div>
                </div>
            </body>
            </html>";
    }
}
