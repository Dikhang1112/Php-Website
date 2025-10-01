<?php
namespace App\Controllers;
use App\Repositories\UserRepository;
use App\Repositories\TokenMailRepository;
use App\Services\VerifyMailService;
use App\Services\EmailService;
use MongoDB\Client;

class VerifiedMailController extends BaseController
{
    protected UserRepository $users;

    public function __construct()
    {
        $this->users = new UserRepository();
    }
    public function verify()
    {
        $mongo = new Client(env('mongo.uri', 'mongodb://localhost:27017'));
        $db = $mongo->selectDatabase(env('mongo.db', 'PhpWeb'));
        $col = $db->selectCollection('email_tokens');


        $token = (string) $this->request->getGet('token');

        if ($token === '') {
            return redirect()->to('/login')->with('error', 'Thiếu token xác minh');
        }

        $repo = new UserRepository();
        $tokenRepo = new TokenMailRepository($col);
        $mailer = new EmailService();

        $svc = new VerifyMailService($tokenRepo, $repo, $mailer);
        $res = $svc->verifyByToken($token);

        if (!empty($res['ok'])) {
            // (tuỳ chọn) tự đăng nhập sau khi verify: set session ở đây nếu muốn
            return redirect()->to('/home')
                ->with('success', 'Email đã được xác minh thành công!');
        }

        // Token sai/hết hạn → có thể cho tới trang resend
        return redirect()->to('/')
            ->with('error', $res['error'] ?? 'Link xác minh không hợp lệ hoặc đã hết hạn');
    }
    public function resendEmail()
    {
        $db = service("mongoDB");
        $col = $db->selectCollection("email_tokens");
        $tokenRepo = new TokenMailRepository($col);
        $mailer = new EmailService();
        $svc = new VerifyMailService($tokenRepo, $this->users, $mailer);

        // Lấy dữ liệu POST hoặc JSON
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

        $userId = trim((string) ($data['userId'] ?? ''));
        $email = trim((string) ($data['email'] ?? ''));

        // Tìm user
        $user = $userId !== '' ? $this->users->findById($userId)
            : ($email !== '' ? $this->users->findByEmail($email) : null);

        if (!$user) {
            return redirect()->back()
                ->with('verify_error', 'Không tìm thấy người dùng.')
                ->with('form', 'verify') // (tuỳ chọn) giúp view biết đẩy vào notif form verify
                ->withInput();
        }

        if (!empty($user['isVerified'])) {
            return redirect()->back()
                ->with('verify_error', 'Email này đã được xác minh, không cần resend.')
                ->with('form', 'verify');
        }

        try {
            $res = $svc->resendVerificationForUser($user);
            if (!empty($res['ok'])) {
                return redirect()->back()
                    ->with('verify_success', 'Đã gửi lại email xác minh.')
                    ->with('form', 'verify');
            }
            $why = $res['error'] ?? $res['reason'] ?? 'Gửi email thất bại.';
            return redirect()->back()
                ->with('verify_error', $why)
                ->with('form', 'verify')
                ->withInput();
        } catch (\Throwable $e) {
            return redirect()->back()
                ->with('verify_error', 'Lỗi: ' . $e->getMessage())
                ->with('form', 'verify')
                ->withInput();
        }
    }
}
?>