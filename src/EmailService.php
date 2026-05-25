<?php
declare(strict_types=1);

namespace UnidetApi;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class EmailService
{
    private static function env(string $key, string $default = ''): string
    {
        return getenv($key) ?: ($_ENV[$key] ?? $default);
    }

    public static function isEnabled(): bool
    {
        return filter_var(
            self::env('MAIL_ENABLED', 'false'),
            FILTER_VALIDATE_BOOLEAN
        );
    }

    public static function sendAdminVerificationCode(
        string $toEmail,
        string $toName,
        string $code,
        string $role
    ): void {
        if (!self::isEnabled()) {
            return;
        }

        $host = self::env('MAIL_HOST', 'smtp.gmail.com');
        $port = (int) self::env('MAIL_PORT', '587');
        $username = self::env('MAIL_USERNAME');
        $password = self::env('MAIL_PASSWORD');
        $fromEmail = self::env('MAIL_FROM_EMAIL', $username);
        $fromName = self::env('MAIL_FROM_NAME', 'Portal UNIDET');

        if ($username === '' || $password === '') {
            throw new \RuntimeException('Faltan credenciales SMTP en .env');
        }

        $safeName = htmlspecialchars($toName, ENT_QUOTES, 'UTF-8');
        $safeEmail = htmlspecialchars($toEmail, ENT_QUOTES, 'UTF-8');
        $safeRole = htmlspecialchars($role, ENT_QUOTES, 'UTF-8');

        $mail = new PHPMailer(true);

        try {
            $mail->CharSet = 'UTF-8';

            $mail->isSMTP();
            $mail->Host = $host;
            $mail->SMTPAuth = true;
            $mail->Username = $username;
            $mail->Password = $password;
            $mail->Port = $port;
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;

            $mail->setFrom($fromEmail, $fromName);
            $mail->addAddress($toEmail, $toName);

            $mail->isHTML(true);
            $mail->Subject = 'Código de verificación - Portal UNIDET';

            $mail->Body = "
                <div style='font-family: Arial, sans-serif; color: #0f172a; line-height: 1.5;'>
                    <h2 style='color:#005f73;'>Código de verificación UNIDET</h2>

                    <p>Hola <strong>{$safeName}</strong>,</p>

                    <p>
                        Se solicitó la creación de una cuenta administrativa en el
                        <strong>Portal UNIDET</strong>.
                    </p>

                    <p>
                        Correo: <strong>{$safeEmail}</strong><br>
                        Rol solicitado: <strong>{$safeRole}</strong>
                    </p>

                    <div style='margin: 24px 0; padding: 18px; border-radius: 12px; background: #ecfeff; border: 1px solid #67e8f9; text-align:center;'>
                        <p style='margin:0 0 8px; color:#475569;'>Tu código de verificación es:</p>
                        <p style='font-size: 32px; font-weight: 800; letter-spacing: 6px; margin:0; color:#003f5c;'>{$code}</p>
                    </div>

                    <p>Este código expira en aproximadamente <strong>10 minutos</strong>.</p>

                    <p>Si no esperabas este correo, puedes ignorarlo.</p>
                </div>
            ";

            $mail->AltBody =
                "Código de verificación UNIDET\n\n" .
                "Hola {$toName},\n\n" .
                "Tu código de verificación es: {$code}\n" .
                "Este código expira en aproximadamente 10 minutos.\n\n" .
                "Correo: {$toEmail}\n" .
                "Rol solicitado: {$role}\n";

            $mail->send();
        } catch (Exception $e) {
            throw new \RuntimeException('Error al enviar correo: ' . $mail->ErrorInfo);
        }
    }
}