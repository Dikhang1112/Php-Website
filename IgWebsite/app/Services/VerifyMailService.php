<?php
declare(strict_types=1);

namespace App\Services;
use App\Repositories\TokenMailRepository;
use App\Repositories\EmailRepository;
use App\Repositories\UserRepository;
use MongoDB\BSON\UTCDateTime;
use App\Entity\Email;
final class VerifyMailService
{
    public function __construct(
        private TokenMailRepository $tokens,
        private UserRepository $users,
        private EmailService $mailer,
        private ?string $baseUrl = null,
        private int $ttlSeconds = 86400 // 24h
    ) {
        // Lấy baseURL từ .env nếu chưa truyền
        $this->baseUrl ??= rtrim((string) env('app.baseURL', ''), '/');
    }

    /**
     * Phát hành token xác minh cho user + gửi email verify.
     */
    public function issueForUser(string $userId, string $email, string $name = ''): array
    {
        $email = trim(strtolower($email));
        if ($userId === '' || $email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'error' => 'Thiếu userId hoặc email không hợp lệ'];
        }

        // 1) Sinh token
        $rawToken = $this->generateRawToken(32);
        $tokenHash = $this->hashToken($rawToken);

        // 2) Vô hiệu token cũ (nếu có)
        try {
            $this->tokens->invalidateAllForUser($userId, 'verify_email');
        } catch (\Throwable $e) {
        }

        // 3) Lưu token
        $ins = $this->tokens->create($userId, $tokenHash, 'verify_email', $this->ttlSeconds);
        if (empty($ins['ok'])) {
            return ['ok' => false, 'error' => $ins['error'] ?? 'Không tạo được token'];
        }

        // 4) Build VERIFY URL & gửi mail
        $verifyUrl = $this->buildVerifyUrl($rawToken);

        $subject = 'Xác minh email - ST Group';
        // Nếu bạn đã có view 'emails/verify_mail', cứ render; nếu không sẽ dùng fallback HTML
        $htmlBody = view('email/verify_mail.php', ['name' => $name, 'verifyUrl' => $verifyUrl]);
        if (!is_string($htmlBody) || $htmlBody === '') {
            $esc = fn(string $s) => htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $htmlBody = '<p>Xin chào <b>' . $esc($name ?: 'bạn') . '</b>,</p>'
                . '<p>Vui lòng bấm liên kết để xác minh email:</p>'
                . '<p><a href="' . $esc($verifyUrl) . '" target="_blank" rel="noopener">Xác minh email</a></p>';
        }

        try {
            // CHỮ KÝ ĐÚNG: (email, subject, html, name?)
            $send = $this->mailer->sendVerifyMail($email, $subject, $htmlBody, $name);
            if (empty($send['ok'])) {
                // EmailService trả 'reason', không phải 'error'
                log_message('error', 'Send verify mail failed: ' . ($send['reason'] ?? 'unknown'));
            }
        } catch (\Throwable $e) {
            log_message('error', 'Send verify mail exception: ' . $e->getMessage());
        }

        return ['ok' => true, 'verifyUrl' => $verifyUrl];
    }

    /**
     * Xác minh token người dùng click từ email.
     * - Hợp lệ: mark user verified + mark token used + gửi mail "đã verify".
     */
    public function verifyByToken(string $rawToken, ?string $attachmentPath = null): array
    {
        $rawToken = trim($rawToken);
        if ($rawToken === '') {
            return ['ok' => false, 'error' => 'Token rỗng'];
        }

        // 1) Tìm token
        $hash = $this->hashToken($rawToken);
        $tok = $this->tokens->findValidByHash($hash);
        if (!$tok) {
            return ['ok' => false, 'error' => 'Token không hợp lệ hoặc đã dùng'];
        }
        $userId = (string) ($tok['userId'] ?? '');
        if ($userId === '') {
            return ['ok' => false, 'error' => 'Token không gắn user'];
        }

        // 2) Lấy user
        $user = $this->users->findById($userId);
        if (!$user) {
            return ['ok' => false, 'error' => 'Không tìm thấy user'];
        }
        $already = !empty($user['isVerified']);

        // 3) Đánh dấu verified nếu chưa
        if (!$already) {
            try {
                $now = new UTCDateTime((int) (microtime(true) * 1000));
                $ok = $this->users->markVerified($userId, $now);
                if (!$ok)
                    return ['ok' => false, 'error' => 'Không thể cập nhật trạng thái xác minh'];
            } catch (\Throwable $e) {
                return ['ok' => false, 'error' => 'Lỗi cập nhật xác minh: ' . $e->getMessage()];
            }
        }

        // 4) Mark token đã dùng (best-effort)
        try {
            $this->tokens->markUsed((string) $tok['_id']);
        } catch (\Throwable $e) {
        }

        // 5) Gửi mail "đã xác minh" lần 2 (type: download) + tạo bản ghi email qua EmailRepository::create
        $email = (string) ($user['email'] ?? '');
        $name = (string) ($user['fullName'] ?? '');
        if ($email !== '') {
            try {
                // a) Sinh open token
                $openToken = $this->generateRawToken();

                // b) Lưu bản ghi email (chỉ các trường cần thiết)
                $emailsRepo = new EmailRepository();
                $emailEntity = new Email(
                    userId: $userId,
                    recipientEmail: $email,
                    type: 'download',
                    openToken: $openToken,
                    createdAt: new \DateTimeImmutable()
                );
                $emailsRepo->create($emailEntity);
                $emailId = $emailEntity->getId();

                // c) Pixel URL cho tracking open
                $pixelUrl = $this->buildPixelUrl($openToken); // trả dạng /log/open/{token}.gif

                // d) Gửi email (CHỈ: email, subject, name, pixelUrl, openToken)
                $downloadUrl = base_url('download/cv_backend.pdf?e=' . $emailId);
                $subject = 'Tài liệu sau khi xác minh - ST Group';
                $send = $this->mailer->sendDownloadLink(
                    $email,
                    $subject,
                    $name ?: null,
                    $pixelUrl,
                    $openToken,
                    $downloadUrl
                );
                if (empty($send['ok'])) {
                    log_message('error', 'Send verified notice failed: ' . ($send['error'] ?? 'unknown'));
                }
            } catch (\Throwable $e) {
                log_message('error', 'Send verified notice exception: ' . $e->getMessage());
            }
        }

        return ['ok' => true, 'userId' => $userId, 'email' => $email ?: null];
    }


    public function resendVerificationForUser(array $user): array
    {
        $userId = (string) ($user['_id'] ?? $user['id'] ?? '');
        $email = (string) ($user['email'] ?? '');
        $name = (string) ($user['fullName'] ?? $user['name'] ?? '');

        if ($userId === '' || $email === '') {
            return ['ok' => false, 'error' => 'Thiếu userId hoặc email'];
        }

        try {
            // 1) Tạo token verify mới
            $rawToken = $this->generateRawToken();
            $tokenHash = $this->hashToken($rawToken);
            $ins = $this->tokens->create($userId, $tokenHash, 'verify_email', $this->ttlSeconds);
            // 2) Lưu token
            $nowMs = (int) (microtime(true) * 1000);
            $expMs = $nowMs + 24 * 60 * 60 * 1000;
            // 3) Build verify URL
            $verifyUrl = $this->buildVerifyUrl($rawToken);

            // 4) Gọi lại EmailService::sendVerifyMail (nó sẽ load view verify_mail)
            $subject = 'Xác minh email của bạn - ST Group';
            $res = $this->mailer->sendVerifyMail(
                recipientEmail: $email,
                subject: $subject,
                htmlBody: view('email/verify_mail.php', [
                    'name' => $name,
                    'verifyUrl' => $verifyUrl,
                ]),
                recipientName: $name ?: null
            );

            return $res;
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /* ---------------- helpers ---------------- */

    private function generateRawToken(int $bytes = 32): string
    {
        // base64url: an toàn trong URL (không có + / =)
        return rtrim(strtr(base64_encode(random_bytes($bytes)), '+/', '-_'), '=');
    }

    private function hashToken(string $rawToken): string
    {
        // sha256 hex, không thêm prefix
        return hash('sha256', $rawToken);
    }

    private function buildVerifyUrl(string $rawToken): string
    {
        // link dạng /verify-email?token=...
        $base = rtrim(trim($this->baseUrl ?: (string) base_url()), '/');
        return $base . '/verify-email?token=' . rawurldecode($rawToken);
    }

    // VerifyMailService.php
    private function buildPixelUrl(string $openToken): string
    {
        $base = rtrim((string) base_url(), '/');
        return $base . '/log/open/' . rawurlencode($openToken) . '.gif?ts=' . time();
    }


    private function getBaseUrl(): string
    {
        $base = (string) (env('app.baseURL') ?? env('app.baseUrl') ?? base_url());
        return rtrim($base, '/');
    }
}
?>