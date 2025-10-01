<?php
namespace App\Repositories;
use App\Entity\Email;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Collection;

class EmailRepository
{
    private Collection $col;
    public function __construct()
    {
        $db = service('mongoDB');
        $this->col = $db->selectCollection('emails');
        $this->ensureIndexes();
    }
    private function ensureIndexes(): void
    {
        // Unique cho open_token
        $this->col->createIndex(['open_token' => 1], ['unique' => true]);
        // Phục vụ query theo user và theo thời gian
        $this->col->createIndex(['user_id' => 1, 'createdAt' => -1]);
        // type và createdAt để lọc nhanh (tuỳ chọn)
        $this->col->createIndex(['type' => 1, 'createdAt' => -1]);
    }
    // CRUD
    public function create(Email $email): string
    {
        // Bổ sung mặc định
        if (!$email->getOpenToken()) {
            $email->setOpenToken($this->generateOpenToken());
        }
        if (!$email->getCreatedAt()) {
            $email->setCreatedAt(new \DateTimeImmutable());
        }

        $doc = $this->entityToDoc($email);
        // Xoá _id null để Mongo tự sinh
        if (isset($doc['_id']) && $doc['_id'] === null) {
            unset($doc['_id']);
        }

        $res = $this->col->insertOne($doc);
        $id = (string) $res->getInsertedId();
        $email->setId($id);

        return $id;
    }

    /**
     * Upsert theo _id (nếu có) hoặc insert mới.
     */
    public function upsert(Email $email): string
    {
        if ($email->getId()) {
            $id = new ObjectId($email->getId());
            $doc = $this->entityToDoc($email);
            unset($doc['_id']); // không cập nhật _id

            $this->col->updateOne(
                ['_id' => $id],
                ['$set' => $doc],
                ['upsert' => true]
            );
            return (string) $id;
        }
        return $this->create($email);
    }

    public function findById(string $id): ?Email
    {
        if (!ObjectId::isValid($id))
            return null;
        $doc = $this->col->findOne(['_id' => new ObjectId($id)]);
        return $doc ? $this->docToEntity((array) $doc) : null;
    }
    public function getIdByOpenToken(string $token): ?string
    {
        $doc = $this->col->findOne(
            ['open_token' => $token],
            ['projection' => ['_id' => 1]]
        );
        return $doc && !empty($doc->_id) ? (string) $doc->_id : null;
    }
    /**
     * Tạo nhanh record Email từ recipient/type (dành cho lúc chuẩn bị gửi mail).
     * Trả về entity đã có id và openToken.
     */
    public function createForRecipient(
        string $recipientEmail,
        string $type = 'verified',
        ?string $userId = null
    ): Email {
        $email = new Email(
            userId: $userId,
            recipientEmail: $recipientEmail,
            type: $type,                       // 'verified' | 'download'
            openToken: $this->generateOpenToken(),
            createdAt: new \DateTimeImmutable()
        );
        $this->create($email);
        return $email;
    }

    /* ===================== Mapping ===================== */

    private function entityToDoc(Email $e): array
    {
        return [
            'user_id' => $e->getUserId() ? new ObjectId($e->getUserId()) : null,
            'recipient_email' => $e->getRecipientEmail(),
            'type' => $e->getType(),             // 'verified' | 'download'
            'open_token' => $e->getOpenToken(),
            'createdAt' => new UTCDateTime($e->getCreatedAt())
        ];
    }

    private function docToEntity(array $d): Email
    {
        return new Email(
            userId: isset($d['user_id']) ? (string) $d['user_id'] : null,
            recipientEmail: $d['recipient_email'] ?? '',
            type: $d['type'] ?? 'verified',
            openToken: $d['open_token'] ?? '',
            createdAt: isset($d['createdAt'])
            ? $this->utcToImmutable($d['createdAt'])
            : new \DateTimeImmutable()
        );
    }

    private function utcToImmutable(UTCDateTime $utc): \DateTimeImmutable
    {
        $dt = $utc->toDateTime();
        $dt->setTimezone(new \DateTimeZone(date_default_timezone_get()));
        return \DateTimeImmutable::createFromMutable($dt);
    }

    private function generateOpenToken(): string
    {
        // Random 32 hex chars (256-bit)
        return bin2hex(random_bytes(16));
    }
}
?>