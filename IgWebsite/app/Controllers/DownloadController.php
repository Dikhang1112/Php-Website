<?php

namespace App\Controllers;
use App\Repositories\EmailEventRepository;
use App\Repositories\UserRepository;
use App\Entity\Email;
use App\Repositories\EmailRepository;
use CodeIgniter\Controller;
use App\Services\EmailService;

class DownloadController extends Controller
{
    protected EmailEventRepository $emailEvents;

    public function __construct()
    {
        $this->emailEvents = new EmailEventRepository();
    }
    public function download(string $name)
    {
        // Chỉ cho phép tên file đơn giản (tránh ../)
        $name = basename($name);

        // Nếu người gửi link không có .pdf thì thêm vào
        if (!str_ends_with(strtolower($name), '.pdf')) {
            $name .= '.pdf';
        }

        // Đường dẫn thật trong writable/uploads
        $absPath = WRITEPATH . 'uploads' . DIRECTORY_SEPARATOR . $name;

        if (!is_file($absPath)) {
            return $this->response->setStatusCode(404)->setBody('File không tồn tại');
        }
        try {
            // ở đây bạn cần emailId để log
            // ví dụ nếu emailId được truyền kèm theo query ?e=<id>
            $emailId = (string) $this->request->getGet('e');
            if ($emailId) {
                $this->emailEvents->logDownload($emailId);
            }
        } catch (\Throwable $e) {

        }

        // Trả file cho trình duyệt tải
        return $this->response->download($absPath, null);
    }
    public function resendDownload()
    {
        // ----- Nhận JSON hoặc form (an toàn) -----
        $ct = $this->request->getHeaderLine('Content-Type');
        if (stripos($ct, 'application/json') !== false) {
            try {
                $data = $this->request->getJSON(true) ?? [];
            } catch (\Throwable $__) {
                $data = $this->request->getPost() ?? [];
            }
        } else {
            $data = $this->request->getPost() ?? [];
        }

        $userIdIn = trim((string) ($data['userId'] ?? ''));
        $emailIn = trim((string) ($data['email'] ?? ''));

        // ----- Repo & Service -----
        $db = service('mongoDB'); // (nếu không dùng, có thể bỏ dòng này)
        $usersRepo = new UserRepository();
        $emailsRepo = new EmailRepository();
        $mailer = new EmailService();

        // ----- Tìm user theo userId/email -----
        $user = null;
        if ($userIdIn !== '') {
            $user = $usersRepo->findById($userIdIn);
        } elseif ($emailIn !== '') {
            $user = $usersRepo->findByEmail($emailIn);
        }

        if (!$user) {
            return redirect()->back()
                ->with('download_error', 'Không tìm thấy người dùng.')
                ->with('form', 'download')
                ->withInput();
        }

        // Chỉ cho phép resend download NẾU user đã verified
        if (empty($user['isVerified'])) {
            return redirect()->back()
                ->with('download_error', 'Tài khoản chưa xác minh, không thể gửi mail tải tài liệu.')
                ->with('form', 'download')
                ->withInput();
        }

        // ----- Lấy dữ liệu cần thiết -----
        $recipientEmail = (string) ($user['email'] ?? '');
        $recipientName = (string) ($user['fullName'] ?? $user['name'] ?? '');
        if ($recipientEmail === '') {
            return redirect()->back()
                ->with('download_error', 'Người dùng chưa có email hợp lệ.')
                ->with('form', 'download')
                ->withInput();
        }

        try {
            // 1) Sinh openToken MỚI (URL-safe)
            $openToken = rtrim(strtr(base64_encode(random_bytes(24)), '+/', '-_'), '=');

            // 2) TẠO Email MỚI type=download
            $emailEntity = new Email(
                userId: (string) ($user['_id'] ?? $user['id'] ?? ''),
                recipientEmail: $recipientEmail,
                type: 'download',
                openToken: $openToken,
                createdAt: new \DateTimeImmutable()
            );
            $emailsRepo->create($emailEntity);
            $emailId = $emailEntity->getId(); // id MỚI cho lần resend

            // 3) Build URLs từ token/id MỚI
            $pixelUrl = base_url('log/open/' . rawurlencode($openToken) . '.gif');
            $downloadUrl = base_url('download/cv_backend.pdf?e=' . rawurlencode((string) $emailId));

            // 4) Gửi mail tải tài liệu
            $subject = 'Tài liệu sau khi xác minh - ST Group';
            $send = $mailer->sendDownloadLink(
                recipientEmail: $recipientEmail,
                subject: $subject,
                name: $recipientName ?: null,
                pixelUrl: $pixelUrl,
                openToken: $openToken,
                downloadUrl: $downloadUrl
            );

            if (empty($send['ok'])) {
                // chuẩn hóa key lỗi theo kênh download
                $why = $send['download_error'] ?? $send['error'] ?? 'Gửi email thất bại.';
                return redirect()->back()
                    ->with('download_error', $why)
                    ->with('form', 'download')
                    ->withInput();
            }

            return redirect()->back()
                ->with('download_success', 'Đã gửi lại email tải về.')
                ->with('form', 'download');

        } catch (\Throwable $e) {
            return redirect()->back()
                ->with('download_error', 'Lỗi: ' . $e->getMessage())
                ->with('form', 'download')
                ->withInput();
        }
    }
}
