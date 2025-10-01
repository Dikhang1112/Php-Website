<?php
declare(strict_types=1);

namespace App\Repositories;

use MongoDB\Collection;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;

final class TokenMailRepository
{
    public function __construct(private Collection $collection)
    {
        // Chỉ giữ tham chiếu Collection, KHÔNG tạo ObjectId ở đây.
    }

    /**
     * Tạo 1 token (đÃ HASH) cho user.
     * - $userId: chuỗi 24-hex ObjectId của user
     * - $tokenHash: chuỗi hash sha256(rawToken) dạng hex (64 kí tự)
     * - $type: 'verify_email' | 'reset_password' ...
     * - $ttlSeconds: thời hạn token (mặc định 24h)
     */
    public function create(
        string $userId,
        string $tokenHash,
        string $type = 'verify_email',
        int $ttlSeconds = 86400
    ): array {
        try {
            // Bọc new ObjectId để nếu $userId không hợp lệ sẽ throw ngay (sẽ vào catch)
            $oid = new ObjectId($userId);

            $now = $this->nowUtcMs();
            $expiresAt = $this->utcMsAfter($ttlSeconds);

            $doc = [
                'userId' => $oid,
                'tokenHash' => $tokenHash,
                'type' => $type,
                'used' => false,
                'createdAt' => $now,
                'expiresAt' => $expiresAt,
            ];

            $res = $this->collection->insertOne($doc);
            return ['ok' => true, 'id' => (string) $res->getInsertedId()];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Tìm token hợp lệ theo tokenHash + type.
     * Điều kiện:
     *  - used = false
     *  - expiresAt > now
     */
    public function findValidByHash(string $tokenHash, string $type = 'verify_email'): ?array
    {
        $now = $this->nowUtcMs();

        $doc = $this->collection->findOne([
            'tokenHash' => $tokenHash,
            'type' => $type,
            'used' => false,
            'expiresAt' => ['$gt' => $now],
        ]);

        if (!$doc) {
            return null;
        }

        $arr = (array) $doc;
        if (isset($arr['_id']) && $arr['_id'] instanceof ObjectId) {
            $arr['_id'] = (string) $arr['_id'];
        }
        if (isset($arr['userId']) && $arr['userId'] instanceof ObjectId) {
            $arr['userId'] = (string) $arr['userId'];
        }
        return $arr;
    }

    /**
     * Đánh dấu token đã dùng (single-use).
     */
    public function markUsed(string $id): bool
    {
        try {
            $res = $this->collection->updateOne(
                ['_id' => new ObjectId($id)],
                ['$set' => ['used' => true]]
            );
            return $res->getModifiedCount() > 0;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * (Tuỳ chọn) Vô hiệu hoá toàn bộ token chưa dùng của 1 user cho 1 type.
     * Hữu ích khi "resend verify" để tránh nhiều link cùng lúc.
     */
    public function invalidateAllForUser(string $userId, string $type = 'verify_email'): int
    {
        try {
            $res = $this->collection->updateMany(
                [
                    'userId' => new ObjectId($userId),
                    'type' => $type,
                    'used' => false,
                ],
                ['$set' => ['used' => true]]
            );
            return (int) $res->getModifiedCount();
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /**
     * (Tuỳ chọn) Xoá 1 token theo id (nếu muốn xoá thay vì mark used).
     */
    public function deleteById(string $id): bool
    {
        try {
            $res = $this->collection->deleteOne(['_id' => new ObjectId($id)]);
            return $res->getDeletedCount() > 0;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /* -------------- helpers -------------- */

    private function nowUtcMs(): UTCDateTime
    {
        return new UTCDateTime((int) (microtime(true) * 1000));
    }

    private function utcMsAfter(int $seconds): UTCDateTime
    {
        return new UTCDateTime((int) ((microtime(true) + $seconds) * 1000));
    }
}
?>