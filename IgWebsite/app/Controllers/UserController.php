<?php
namespace App\Controllers;
use App\Repositories\UserRepository;
use App\Repositories\EmailEventRepository;
use App\Repositories\TokenMailRepository;
use App\Services\UserService;
use App\Services\EmailService;
use App\Services\VerifyMailService;
use MongoDB\Client;


class UserController extends BaseController
{
    public function showUsers()
    {
        $page = (int) ($this->request->getGet('page') ?? 1);
        $size = (int) ($this->request->getGet('size') ?? 6);

        // Init
        $repo = new UserRepository();
        $serv = new UserService($repo);

        // --- Filter isVerified (giữ nguyên) ---
        $isVerifiedParam = $this->request->getGet('isVerified'); // "0"/"1"/""/null
        $filter = [];
        if ($isVerifiedParam !== null && $isVerifiedParam !== '') {
            $v = strtolower((string) $isVerifiedParam);
            $truthy = ['1', 'true', 'yes', 'y'];
            $falsey = ['0', 'false', 'no', 'n'];
            if (in_array($v, $truthy, true))
                $filter['isVerified'] = true;
            elseif (in_array($v, $falsey, true))
                $filter['isVerified'] = false;
        }

        // --- Parse isOpen từ URL, chỉ dùng để lọc sau ---
        $isOpenParam = $this->request->getGet('isOpen'); // "0"/"1"/""/null
        $wantOpen = null; // null = không lọc
        if ($isOpenParam !== null && $isOpenParam !== '') {
            $v = strtolower((string) $isOpenParam);
            $truthy = ['1', 'true', 'yes', 'y'];
            $falsey = ['0', 'false', 'no', 'n'];
            if (in_array($v, $truthy, true))
                $wantOpen = true;
            elseif (in_array($v, $falsey, true))
                $wantOpen = false;
        }

        // --- Parse isDownload từ URL, chỉ dùng để lọc sau ---
        $isDownloadParam = $this->request->getGet('isDownload'); // "0"/"1"/""/null
        $wantDownload = null; // null = không lọc
        if ($isDownloadParam !== null && $isDownloadParam !== '') {
            $v = strtolower((string) $isDownloadParam);
            $truthy = ['1', 'true', 'yes', 'y'];
            $falsey = ['0', 'false', 'no', 'n'];
            if (in_array($v, $truthy, true))
                $wantDownload = true;
            elseif (in_array($v, $falsey, true))
                $wantDownload = false;
        }

        // Lấy users theo trang + isVerified
        $result = $serv->listUser($page, $size, $filter);
        $users = $result['items'] ?? [];

        // ===== Tính thống kê "đã mở" + "đã download" cho users trong trang =====
        $userOpens = [];
        $userDownloads = [];
        if (!empty($users)) {
            // gom id dạng string
            $userIds = [];
            foreach ($users as $u) {
                $raw = $u['_id'] ?? ($u['id'] ?? null);
                if ($raw instanceof ObjectId) {
                    $userIds[] = (string) $raw;
                } elseif (is_string($raw)) {
                    $userIds[] = $raw;
                }
            }

            /** @var \MongoDB\Database $db */
            $db = service('mongoDB');
            $eventRepo = new EmailEventRepository($db);

            // repo trả về: $map[userIdString] = ['openCount'=>int, 'lastOpenAt'=>?DateTime]
            $userOpens = $eventRepo->userOpenStatsByUserIds($userIds);

            // tương tự cho download
            $userDownloads = $eventRepo->userDownloadStatsByUserIds($userIds);

        }

        // --- Áp lọc isOpen (nếu có) ---
        if ($wantOpen !== null && !empty($users)) {
            $users = array_values(array_filter($users, function ($u) use ($userOpens, $wantOpen) {
                $uid = isset($u['_id']) ? (string) $u['_id'] : (string) ($u['id'] ?? '');
                $hasOpen = !empty($userOpens[$uid]) && (($userOpens[$uid]['openCount'] ?? 0) > 0);
                return $wantOpen ? $hasOpen : !$hasOpen;
            }));
        }

        // --- Áp lọc isDownload (nếu có) ---
        if ($wantDownload !== null && !empty($users)) {
            $users = array_values(array_filter($users, function ($u) use ($userDownloads, $wantDownload) {
                $uid = isset($u['_id']) ? (string) $u['_id'] : (string) ($u['id'] ?? '');
                $hasDownload = !empty($userDownloads[$uid]) && (($userDownloads[$uid]['downloadCount'] ?? 0) > 0);
                return $wantDownload ? $hasDownload : !$hasDownload;
            }));
        }

        // Truyền xuống view
        return view('backend/Users', [
            'page' => $result['page'],
            'size' => $result['size'],
            'items' => $users,
            'users' => $users,
            'isVerified' => $isVerifiedParam,
            'isOpen' => $isOpenParam,
            'isDownload' => $isDownloadParam,   // ✅ thêm biến này
            'userOpens' => $userOpens,
            'userDownloads' => $userDownloads,  // ✅ thêm biến này
        ]);
    }

    //Post User
    public function addUser()
    {
        $mongo = new Client(env('mongo.uri', 'mongodb://localhost:27017'));
        $db = $mongo->selectDatabase(env('mongo.db', 'PhpWeb'));
        $col = $db->selectCollection('email_tokens');
        $tokenRepo = new TokenMailRepository($col);
        $payload = $this->request->getPost();

        $repo = new UserRepository();
        $res = (new UserService($repo))->createUser($payload);

        $email = (string) ($payload['email'] ?? '');
        $name = (string) ($payload['fullName'] ?? ($payload['name'] ?? ''));

        if (!empty($res['ok'])) {
            // ===== GỬI EMAIL XÁC MINH (flow mới) =====
            if ($email !== '') {
                try {
                    // Khởi tạo VerifyMailService với repo + mailer
                    $verifySvc = new VerifyMailService(
                        tokens: $tokenRepo,
                        users: $repo,
                        mailer: new EmailService()
                    );
                    // Sinh token + lưu DB + gửi mail xác minh
                    $iss = $verifySvc->issueForUser((string) $res['id'], $email, $name);
                    if (empty($iss['ok'])) {
                        log_message('error', 'Issue  verification mail failed: ' . ($iss['error'] ?? 'unknown'));
                    }
                } catch (\Throwable $e) {
                    log_message('error', 'VerifyMailService exception: ' . $e->getMessage());
                }
            }

            return redirect()->to('/')
                ->with('success', 'Tạo user thành công! Vui lòng kiểm tra email để xác minh.');
        }

        return redirect()->back()
            ->withInput()
            ->with('error', $res['error'] ?? 'Không thể tạo user');
    }
}
?>