<?php
declare(strict_types=1);

namespace App\Services;

use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as MailException;

final class EmailService
{
    /** Cho phép inject base URL public để build pixel; nếu null sẽ fallback base_url() */
    public function __construct(private ?string $baseUrl = null)
    {
    }

    /* =====================================================================
     *  GỬI EMAIL LẦN 1: VERIFY (sendVerifyMail)
     * ===================================================================== */
    /**
     * Gửi email xác minh (lần 1) bằng PHPMailer.
     * - Bạn truyền sẵn $htmlBody (đã render token/verify link trong View riêng của bạn).
     * - Hàm này chỉ lo chuyện gửi SMTP, đồng bộ config với verify flow cũ.
     *
     * @return array{ok:bool, reason?:string}
     */
    public function sendVerifyMail(
        string $recipientEmail,
        string $subject,
        string $htmlBody,
        ?string $recipientName = null
    ): array {
        $email = \Config\Services::email();

        // lấy from từ .env (fallback mặc định)
        $fromEmail = env('email.fromEmail') ?? 'no-reply@yourdomain.com';
        $fromName = env('email.fromName') ?? 'ST Group';
        $email->setFrom($fromEmail, $fromName);
        $email->setTo($recipientEmail);
        $email->setSubject($subject);
        $email->setMailType('html');
        $email->setMessage($htmlBody);
        $email->setAltMessage(strip_tags($htmlBody));

        try {
            if ($email->send()) {
                return ['ok' => true];
            }
            $debug = method_exists($email, 'printDebugger')
                ? $email->printDebugger(['headers', 'subject'])
                : 'send() returned false';
            log_message('error', 'Send verify mail failed: ' . $debug);
            return ['ok' => false, 'reason' => $debug];
        } catch (\Throwable $e) {
            log_message('error', 'Send verify mail exception: ' . $e->getMessage());
            return ['ok' => false, 'reason' => $e->getMessage()];
        }
    }
    /* =====================================================================
     *  GỬI EMAIL LẦN 2: LINK + PIXEL (sendEmailDownloadLink)
     * ===================================================================== */
    /**
     * Gửi email lần 2 (dạng LINK), kèm tracking pixel để đo "open" cho CHÍNH email này.
     * - Tạo record "emails" (type='download') + open_token (Mongo)
     * - Render view 'emails/attach_mail' với {name, fileLabel, downloadUrl, pixelUrl}
     * - Gửi bằng PHPMailer (SMTP)
     *
     * @return array{ok:bool, email_id?:string, open_token?:string, reason?:string}
     */
    public function sendDownloadLink(
        string $recipientEmail,
        string $subject,
        ?string $name = null,
        ?string $pixelUrl = null,
        ?string $openToken = null,
        ?string $downloadUrl = null
    ): array {
        try {
            // Chuẩn hoá pixel URL (nếu có)
            $absPixel = $pixelUrl ? $this->absUrl($pixelUrl) : '';
            $absDown = $downloadUrl ? $this->absUrl($downloadUrl) : null;
            // Render HTML email: chỉ cần name + pixel
            $html = view('email/attach_mail', [
                'name' => $name,
                'pixelUrl' => $absPixel,
                'downloadUrl' => $absDown,
            ]);

            // Gửi
            $ok = $this->sendHtml($recipientEmail, $subject, $html);
            if (!$ok) {
                return ['ok' => false, 'error' => 'Mailer trả về thất bại'];
            }

            // (Tuỳ chọn) log nội bộ nếu cần openToken để trace
            if ($openToken) {
                log_message('debug', 'Email sent with openToken=' . $openToken . ' to=' . $recipientEmail);
            }

            return ['ok' => true];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /* ====================== MAILER CONFIG ====================== */

    /**
     * Tạo PHPMailer đã cấu hình SMTP từ .env:
     * email.SMTPHost, email.SMTPPort, email.SMTPUser, email.SMTPPass, email.SMTPCrypto
     */
    private function makeMailerFromEnv(): PHPMailer
    {
        $host = (string) (env('email.SMTPHost') ?? '');
        $port = (int) (env('email.SMTPPort') ?? 587);
        $user = (string) (env('email.SMTPUser') ?? '');
        $pass = (string) (env('email.SMTPPass') ?? '');
        $secure = (string) (env('email.SMTPCrypto') ?? 'tls'); // 'tls' | 'ssl' | ''

        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = $host;
        $mail->Port = $port;
        $mail->SMTPAuth = ($user !== '' || $pass !== '');
        $mail->Username = $user;
        $mail->Password = $pass;
        if ($secure !== '') {
            $mail->SMTPSecure = $secure;
        }
        return $mail;
    }

    /** Lấy cấu hình From từ .env: email.fromEmail, email.fromName */
    private function getFromConfig(): array
    {
        $fromEmail = (string) (env('email.fromEmail') ?? '');
        $fromName = (string) (env('email.fromName') ?? 'ST Group');

        $fromEmail = trim($fromEmail);
        $fromName = trim($fromName);

        if ($fromEmail === '' || !filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'error' => 'Missing or invalid "email.fromEmail"'];
        }
        return ['ok' => true, 'email' => $fromEmail, 'name' => ($fromName !== '' ? $fromName : 'ST Group')];
    }

    /* ====================== HELPERS ====================== */

    /** Sinh token 32 hex (256-bit ngẫu nhiên) */
    private function absUrl(string $url): string
    {
        helper('url');
        $u = trim($url);
        if ($u === '')
            return '';
        if (filter_var($u, FILTER_VALIDATE_URL))
            return $u;
        return site_url(ltrim($u, '/'));
    }
    private function generateOpenToken(): string
    {
        return bin2hex(random_bytes(16));
    }



    /** Fallback HTML nếu view không render được (để không chặn luồng gửi) */
    public function sendHtml(string $to, string $subject, string $html): bool
    {
        try {
            $mail = $this->makeMailerFromEnv();
            $mail->setFrom(
                env('email.fromAddress') ?? 'no-reply@example.com',
                env('email.fromName') ?? 'System'
            );
            $mail->addAddress($to);
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $html;

            return $mail->send();
        } catch (\Throwable $e) {
            log_message('error', 'sendHtml error: ' . $e->getMessage());
            return false;
        }
    }

}

?>