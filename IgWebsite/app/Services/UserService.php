<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\UserRepository;
use App\Entity\User;

final class UserService
{
    private UserRepository $repo;

    public function __construct(?UserRepository $repo = null)
    {
        $this->repo = $repo ?? new UserRepository();
    }

    /**
     * Trả về danh sách dưới dạng Entity<User>[].
     */
    public function listUserEntities(int $page = 1, int $size = 5, array $filter = []): array
    {
        $page = max(1, $page);
        $size = max(1, $size);

        $options = [
            'filter' => $filter,
            'limit' => $size,
            'skip' => ($page - 1) * $size,
            'sort' => ['createdAt' => -1],
        ];

        $items = $this->repo->getAll($options); // User[]
        return [
            'page' => $page,
            'size' => $size,
            'items' => $items,                   // << Entity[]
        ];
    }

    /**
     * Bản “compat” cho view/JSON hiện tại: map Entity -> array.
     */
    public function listUser(int $page = 1, int $size = 5, array $filter = []): array
    {
        $res = $this->listUserEntities($page, $size, $filter);
        $res['items'] = array_map(fn(User $u) => [
            'id' => $u->getId(),
            'email' => $u->getEmail(),
            'password' => $u->getPassword(),
            'fullName' => $u->getFullName(),
            'sex' => $u->getSex(),
            'age' => $u->getAge(),
            'career' => $u->getCareer(),
            'isVerified' => (bool) ($u->getIsVerified()),
            'createdAt' => $u->getCreatedAt()?->format('Y-m-d H:i:sP'),
            'updatedAt' => $u->getUpdatedAt()?->format('Y-m-d H:i:sP'),
        ], $res['items']);
        return $res;
    }

    /**
     * Tạo user mới: build Entity từ input rồi gọi Repository.
     * (Chưa làm validate theo yêu cầu của bạn – sẽ thêm sau.)
     *
     * @return array { ok: bool, id?: string, error?: string }
     */
    public function createUser(array $input): array
    {
        $u = new User();
        // ID (tuỳ bạn có cho client gửi trước hay không)
        if (!empty($input['id'])) {
            $u->setId((string) $input['id']);
        }

        // Email
        $email = strtolower(trim((string) ($input['email'] ?? '')));
        $exitMail = $this->repo->exitByEmail($email);
        if ($exitMail) {
            return ['ok' => false, 'error' => 'Email đã tồn tại trong hệ thống'];
        }
        $u->setEmail($email);
        //Password
        if (isset($input['password'])) {
            $password_hash = password_hash($input['password'], PASSWORD_DEFAULT);
            $u->setPassword($password_hash);
        }

        // Tên: nhận fullName hoặc name → chuẩn về fullName
        if (!empty($input['fullName'])) {
            $u->setFullName((string) $input['fullName']);
        } elseif (!empty($input['name'])) {
            $u->setFullName((string) $input['name']);
        }

        // Giới tính
        if (array_key_exists('sex', $input)) {
            $u->setSex($input['sex'] !== '' ? (string) $input['sex'] : null);
        }

        // Tuổi
        if (array_key_exists('age', $input)) {
            $age = (int) $input['age'];
            if ($age < 18 || $age > 100) {
                return ['ok' => false, 'error' => 'Tuổi phải từ 18-100'];
            }
            $u->setAge((int) $age);
        }
        // Nghề nghiệp: career hoặc job -> career
        if (!empty($input['career'])) {
            $u->setCareer((string) $input['career']);
        } elseif (!empty($input['job'])) {
            $u->setCareer((string) $input['job']);
        }

        // Timestamps nếu client có gửi (Repo sẽ tự set nếu thiếu)
        if (isset($input['createdAt']) && ($dt = $this->parseDate($input['createdAt']))) {
            $u->setCreatedAt(createdAt: $dt);
        }
        if (isset($input['updatedAt']) && ($dt = $this->parseDate($input['updatedAt']))) {
            $u->setUpdatedAt($dt);
        }

        try {
            return $this->repo->create($u);
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    public function login(string $email, string $password): array
    {
        //Validate input
        $email = strtolower(trim($email));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'error' => 'Email không hợp lệ'];
        }
        if ($password === '') {
            return ['ok' => false, 'error' => 'Mật khẩu không được để trống'];
        }
        $user = $this->repo->findByEmail($email, [
            'email' => 1,
            'role' => 1,
            'password' => 1,
            '_id' => 1,
        ]);
        // So khớp mật khẩu
        $hash = (string) ($user['password'] ?? '');
        if ($hash === '' || !password_verify($password, $hash)) {
            return ['ok' => false, 'error' => 'Email hoặc mật khẩu không đúng'];
        }
        // 5) Ghi nhận đăng nhập (không chặn luồng nếu lỗi)
        try {
            $this->repo->updateLastLogin((string) $user['_id'], $_SERVER['REMOTE_ADDR'] ?? null);
        } catch (\Throwable $e) {
            log_message('debug', 'Lỗi ghi nhận đăng nhập');
        }
        // Trả user “an toàn” cho Controller set session
        return [
            'ok' => true,
            'user' => [
                'id' => (string) ($user['_id'] ?? ''),
                'email' => (string) ($user['email'] ?? ''),
                'role' => (string) ($user['role'] ?? ''),
                'fullName' => (string) ($user['fullName'] ?? ''),
            ],
        ];
    }


    /**
     * Hỗ trợ parse datetime từ nhiều định dạng đầu vào (string/epoch/int/\DateTimeInterface).
     */
    private function parseDate(mixed $value): ?\DateTimeImmutable
    {
        if ($value instanceof \DateTimeImmutable)
            return $value;
        if ($value instanceof \DateTimeInterface)
            return \DateTimeImmutable::createFromMutable(
                $value instanceof \DateTime ? $value : new \DateTime($value->format('c'))
            );
        if (is_numeric($value)) {
            $sec = (int) $value;
            if ($sec > 2_000_000_000) { // epoch ms
                return (new \DateTimeImmutable('@' . (int) floor($sec / 1000)))->setTimezone(new \DateTimeZone(date_default_timezone_get()));
            }
            return (new \DateTimeImmutable('@' . $sec))->setTimezone(new \DateTimeZone(date_default_timezone_get()));
        }
        if (is_string($value) && $value !== '') {
            try {
                return new \DateTimeImmutable($value);
            } catch (\Throwable) {
            }
        }
        return null;
    }
}
