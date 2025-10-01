<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Entity\User;
use MongoDB\Collection;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;

final class UserRepository
{
    private Collection $col;

    public function __construct(?Collection $col = null)
    {
        if ($col instanceof Collection) {
            $this->col = $col;
        } else {
            $db = service('mongoDB'); // \MongoDB\Database đã cấu hình trong Services.php
            $this->col = $db->selectCollection('users');
        }
    }

    /** --------- Helpers map BSON <-> Entity --------- */

    /** 1 doc Mongo -> Entity */
    private function mapDocToEntity(array $doc): User
    {
        $u = new User();
        // id
        if (isset($doc['_id'])) {
            $u->setId((string) $doc['_id']);
        }
        // basic fields
        if (isset($doc['email']))
            $u->setEmail((string) $doc['email']);
        if (isset($doc['password']))
            $u->setPassword((string) $doc['password']);
        if (isset($doc['fullName']))
            $u->setFullName((string) $doc['fullName']);
        elseif (isset($doc['name']))
            $u->setFullName((string) $doc['name']);
        if (array_key_exists('sex', $doc))
            $u->setSex($doc['sex'] !== null ? (string) $doc['sex'] : null);
        if (array_key_exists('age', $doc))
            $u->setAge($doc['age'] !== null ? (int) $doc['age'] : null);
        if (isset($doc['career']))
            $u->setCareer((string) $doc['career']);
        elseif (isset($doc['job']))
            $u->setCareer((string) $doc['job']);
        // timestamps
        if (isset($doc['createdAt']) && $doc['createdAt'] instanceof UTCDateTime) {
            $u->setCreatedAt($doc['createdAt']->toDateTimeImmutable());
        }
        if (isset($doc['updatedAt']) && $doc['updatedAt'] instanceof UTCDateTime) {
            $u->setUpdatedAt($doc['updatedAt']->toDateTimeImmutable());
        }
        if (isset($doc['isVerified']) && (bool) $doc['isVerified']) {
            $u->setIsVerified($doc['isVerified']);
        }
        if (isset($doc['emailVerifiedAt']) && $doc['emailVerifiedAt'] instanceof UTCDateTime) {
            $u->setEmailVerifiedAt($doc['emailVerifiedAt']->toDateTimeImmutable());
        }

        return $u;
    }

    /** Entity -> document Mongo (chỉ những field cần lưu) */
    private function mapEntityToDoc(User $u): array
    {
        $doc = [];
        if ($u->getId()) {
            $doc['_id'] = new ObjectId($u->getId());
        }
        if ($u->getEmail() !== '')
            $doc['email'] = $u->getEmail();
        if ($u->getPassword() !== '')
            $doc['password'] = $u->getPassword();
        if ($u->getFullName() !== '')
            $doc['fullName'] = $u->getFullName();
        if ($u->getSex() !== null)
            $doc['sex'] = $u->getSex();
        if ($u->getAge() !== null)
            $doc['age'] = $u->getAge();
        if ($u->getCareer() !== null)
            $doc['career'] = $u->getCareer();

        // timestamps (nếu entity có sẵn thì dùng, không thì để create() tự set)
        if ($u->getCreatedAt() instanceof \DateTimeInterface) {
            $doc['createdAt'] = new UTCDateTime((int) $u->getCreatedAt()->format('Uv'));
        }
        if ($u->getUpdatedAt() instanceof \DateTimeInterface) {
            $doc['updatedAt'] = new UTCDateTime((int) $u->getUpdatedAt()->format('Uv'));
        }

        if ($u->getIsVerified() !== null) {
            $doc['isVerified'] = $u->getIsVerified();
        }
        if ($u->getEmailVerifiedAt() instanceof \DateTimeInterface) {
            $doc['emailVerified'] = new UTCDateTime((int) $u->getEmailVerifiedAt()->format('Uv'));
        }
        return $doc;
    }

    /** --------- APIs công khai --------- */
    /** Lấy danh sách users → trả về mảng Entity<User> */
    public function getAll(array $options = []): array
    {
        // mặc định sort theo createdAt desc
        $options = array_replace(['sort' => ['createdAt' => -1]], $options);

        // lấy filter từ options, nếu không có thì để mảng rỗng
        $filter = $options['filter'] ?? [];

        $out = [];
        foreach ($this->col->find($filter, $options) as $doc) {
            $out[] = $this->mapDocToEntity((array) $doc);
        }
        return $out;
    }


    //Nếu email đã tồn tại
    public function exitByEmail(string $email): bool
    {
        return $this->col->countDocuments(['email' => strtolower(trim($email))], ['limit' => 1]) > 0;
    }
    public function findByEmail(string $email, array $projection = []): ?array
    {
        $email = strtolower(trim($email));
        if ($email === '')
            return null;

        $opts = [];
        if ($projection)
            $opts['projection'] = $projection;

        $doc = $this->col->findOne(['email' => $email], $opts);
        if (!$doc)
            return null;

        $arr = (array) $doc;
        if (isset($arr['_id']) && $arr['_id'] instanceof ObjectId) {
            $arr['_id'] = (string) $arr['_id'];
        }
        return $arr;
    }

    //Ghi nhận thời điểm đăng nhập cuối cùng
    public function updateLastLogin(string $id, string $ip = null): void
    {
        if ($id === '')
            return;
        $set = ['lastLoginAt' => new UTCDateTime()];
        if ($ip)
            $set['lastLoginAt'] = $ip;
        $this->col->updateOne(
            ['_id' => new ObjectId($id)],
            ['$set' => $set]
        );
    }
    public function create(User $u): array
    {
        $doc = $this->mapEntityToDoc($u);

        // Set timestamps nếu entity chưa có
        $now = new UTCDateTime((int) (microtime(true) * 1000));
        $doc['createdAt'] = $doc['createdAt'] ?? $now;
        $doc['updatedAt'] = $doc['updatedAt'] ?? $now;

        try {
            $res = $this->col->insertOne($doc);
            $u->setId((string) $res->getInsertedId());
            return ['ok' => true, 'id' => (string) $res->getInsertedId()];
        } catch (\MongoDB\Driver\Exception\BulkWriteException $e) {
            // E11000 = duplicate key (trùng email)
            if ($e->getCode() === 11000) {
                return ['ok' => false, 'error' => 'Email đã tồn tại (unique index)'];
            }
            return ['ok' => false, 'error' => $e->getMessage()];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    //Find by id
    public function findById(string $id, array $projection = []): array
    {
        try {
            $opts = [];
            if (!empty($projection)) {
                $opts['projection'] = $projection;
            }

            $doc = $this->col->findOne(['_id' => new ObjectId($id)], $opts);

            if (!$doc) {
                return null;
            }

            $arr = (array) $doc;

            // convert ObjectId về string
            if (isset($arr['_id']) && $arr['_id'] instanceof ObjectId) {
                $arr['_id'] = (string) $arr['_id'];
            }

            return $arr;
        } catch (\Throwable $e) {
            return null;
        }
    }
    //Đánh dấu user đã được xác thực
    public function markVerified(string $id, UTCDateTime $when): bool
    {
        try {
            $res = $this->col->updateOne(
                ['_id' => new ObjectId($id)],
                [
                    '$set' => [
                        'isVerified' => true,
                        'emailVerifiedAt' => $when,
                    ],
                ]
            );

            return $res->getModifiedCount() > 0;
        } catch (\Throwable $e) {
            return false;
        }
    }
}
?>