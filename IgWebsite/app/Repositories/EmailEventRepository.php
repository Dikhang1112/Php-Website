<?php
namespace App\Repositories;
use App\Entity\EmailEvent;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Collection;


class EmailEventRepository
{
    private Collection $col;
    public function __construct()
    {
        $db = service('mongoDB');
        $this->col = $db->selectCollection('email_events');
        $this->ensureIndexes();
    }

    public function ensureIndexes(): void
    {
        // Tối ưu các truy vấn phổ biến: theo email_id + event, và sort theo thời gian
        $this->col->createIndex(['email_id' => 1, 'event' => 1]);
        $this->col->createIndex(['email_id' => 1, 'at' => -1]);

    }
    public function logOpen(string $emailId): array
    {
        $doc = [
            'email_id' => new ObjectId($emailId),
            'event' => 'open',
            'at' => new UTCDateTime(),
        ];
        $this->col->insertOne($doc);
        return ['ok' => true];
    }
    /** Log tải file */
    public function logDownload(string $emailId): array
    {

        $doc = [
            'email_id' => new ObjectId($emailId),
            'event' => 'download',
            'at' => new UTCDateTime(),
        ];
        $this->col->insertOne($doc);
        return ['ok' => true];
    }
    /**
     * Ghi nhận sự kiện CLICK (nếu bạn cần theo dõi bấm link).
     * Không idempotent vì 1 email có thể được bấm nhiều lần.
     */


    /* =========================================================
     *                         CRUD CƠ BẢN
     * ========================================================= */

    public function create(EmailEvent $event): ?string
    {
        $doc = $this->entityToDoc($event);
        log_message('info', 'Inserting document: ' . json_encode($doc));
        $res = $this->col->insertOne($doc);
        if ($res->getInsertedId()) {
            log_message('info', 'Inserted ID: ' . (string) $res->getInsertedId());
            return (string) $res->getInsertedId();
        }
        log_message('error', 'Insert failed');
        return null;
    }
    /**
     * Lấy danh sách event theo email_id (có thể lọc theo loại event).
     * @return EmailEvent[]
     */

    private function toObjectIdOrNull($id): ?ObjectId
    {
        if ($id instanceof ObjectId)
            return $id;
        if (is_string($id) && preg_match('/^[a-f\d]{24}$/i', $id)) {
            try {
                return new ObjectId($id);
            } catch (\Throwable $e) {
            }
        }
        return null;
    }

    /**
     * Lấy danh sách userId (string) đã có ít nhất 1 event 'open'.
     * - $userIdsFilter: (tùy chọn) chỉ xét trong tập userId này.
     * Trả về mảng userId (string, 24-hex) duy nhất.
     */
    public function userIdsOpened(?array $userIdsFilter = null): array
    {
        // Chuẩn hóa filter -> ObjectId[]
        $userOids = null;
        if (is_array($userIdsFilter) && $userIdsFilter) {
            $userOids = [];
            foreach ($userIdsFilter as $id) {
                if ($id instanceof ObjectId) {
                    $userOids[] = $id;
                } elseif (is_string($id) && preg_match('/^[a-f\d]{24}$/i', $id)) {
                    try {
                        $userOids[] = new ObjectId($id);
                    } catch (\Throwable $e) {
                    }
                }
            }
            if (!$userOids)
                return []; // toàn giá trị không hợp lệ
        }

        // Pipeline: events(event='open') -> lookup emails -> (optional) match user_id in $userOids -> group theo user_id
        $pipeline = [
            ['$match' => ['event' => 'open']],
            [
                '$lookup' => [
                    'from' => 'emails',
                    'localField' => 'email_id',
                    'foreignField' => '_id',
                    'as' => 'email',
                ]
            ],
            ['$unwind' => '$email'],
        ];
        if ($userOids !== null) {
            $pipeline[] = ['$match' => ['email.user_id' => ['$in' => $userOids]]];
        }
        $pipeline[] = ['$group' => ['_id' => '$email.user_id']];

        $out = [];
        foreach ($this->col->aggregate($pipeline) as $doc) {
            if (!empty($doc->_id)) {
                $out[] = (string) $doc->_id; // user_id
            }
        }
        return $out;
    }

    public function userOpenStatsByUserIds(array $userIds): array
    {
        // Ép về ObjectId hợp lệ
        $userOids = [];
        foreach ($userIds as $id) {
            $oid = $this->toObjectIdOrNull($id);
            if ($oid)
                $userOids[] = $oid;
        }
        if (!$userOids)
            return [];

        // Pipeline: lấy event 'open' -> join sang emails -> filter user_id in $userOids -> group theo user_id
        $pipeline = [
            ['$match' => ['event' => 'open']],
            [
                '$lookup' => [
                    'from' => 'emails',
                    'localField' => 'email_id',
                    'foreignField' => '_id',
                    'as' => 'email',
                ]
            ],
            ['$unwind' => '$email'],
            ['$match' => ['email.user_id' => ['$in' => $userOids]]],
            [
                '$group' => [
                    '_id' => '$email.user_id',
                    'openCount' => ['$sum' => 1],
                    'lastOpenAt' => ['$max' => '$at'],
                ]
            ],
        ];

        $map = [];
        foreach ($this->col->aggregate($pipeline) as $doc) {
            $uid = (string) $doc->_id;
            $last = null;
            if (isset($doc->lastOpenAt) && $doc->lastOpenAt instanceof UTCDateTime) {
                $last = $doc->lastOpenAt->toDateTime(); // DateTime (UTC)
            }
            $map[$uid] = [
                'openCount' => (int) ($doc->openCount ?? 0),
                'lastOpenAt' => $last,
            ];
        }
        return $map;
    }

    public function userIdsDownload(?array $userIdsFilter = null): array
    {
        // Chuẩn hóa filter -> ObjectId[]
        $userOids = null;
        if (is_array($userIdsFilter) && $userIdsFilter) {
            $userOids = [];
            foreach ($userIdsFilter as $id) {
                if ($id instanceof ObjectId) {
                    $userOids[] = $id;
                } elseif (is_string($id) && preg_match('/^[a-f\d]{24}$/i', $id)) {
                    try {
                        $userOids[] = new ObjectId($id);
                    } catch (\Throwable $e) {
                    }
                }
            }
            if (!$userOids)
                return []; // toàn giá trị không hợp lệ
        }

        // Pipeline: events(event='open') -> lookup emails -> (optional) match user_id in $userOids -> group theo user_id
        $pipeline = [
            ['$match' => ['event' => 'download']],
            [
                '$lookup' => [
                    'from' => 'emails',
                    'localField' => 'email_id',
                    'foreignField' => '_id',
                    'as' => 'email',
                ]
            ],
            ['$unwind' => '$email'],
        ];
        if ($userOids !== null) {
            $pipeline[] = ['$match' => ['email.user_id' => ['$in' => $userOids]]];
        }
        $pipeline[] = ['$group' => ['_id' => '$email.user_id']];

        $out = [];
        foreach ($this->col->aggregate($pipeline) as $doc) {
            if (!empty($doc->_id)) {
                $out[] = (string) $doc->_id; // user_id
            }
        }
        return $out;
    }

    public function userDownloadStatsByUserIds(array $userIds): array
    {
        // Ép về ObjectId hợp lệ
        $userOids = [];
        foreach ($userIds as $id) {
            $oid = $this->toObjectIdOrNull($id);
            if ($oid)
                $userOids[] = $oid;
        }
        if (!$userOids)
            return [];

        // Pipeline: lấy event 'open' -> join sang emails -> filter user_id in $userOids -> group theo user_id
        $pipeline = [
            ['$match' => ['event' => 'download']],
            [
                '$lookup' => [
                    'from' => 'emails',
                    'localField' => 'email_id',
                    'foreignField' => '_id',
                    'as' => 'email',
                ]
            ],
            ['$unwind' => '$email'],
            ['$match' => ['email.user_id' => ['$in' => $userOids]]],
            [
                '$group' => [
                    '_id' => '$email.user_id',
                    'downloadCount' => ['$sum' => 1],
                    'lastDownloadAt' => ['$max' => '$at'],
                ]
            ],
        ];

        $map = [];
        foreach ($this->col->aggregate($pipeline) as $doc) {
            $uid = (string) $doc->_id;
            $last = null;
            if (isset($doc->lastDownloadAt) && $doc->lastDownloadAt instanceof UTCDateTime) {
                $last = $doc->lastDownloadAt->toDateTime(); // DateTime (UTC)
            }
            $map[$uid] = [
                'downloadCount' => (int) ($doc->downloadCount ?? 0),
                'lastDownloadAt' => $last,
            ];
        }
        return $map;
    }


    public function findById(string $id): array
    {
        $oid = $this->toObjectIdOrNull($id);
        if (!$oid)
            return [];

        $doc = $this->col->findOne(['_id' => $oid]);
        return $doc ? $this->docToEntity((array) $doc) : [];
    }

    public function findByEmailId(string $emailId, ?string $event = null, int $limit = 50, int $skip = 0): array
    {
        $oid = $this->toObjectIdOrNull($emailId);
        if (!$oid)
            return [];

        $filter = ['email_id' => $oid];
        if ($event !== null && $event !== '') {
            $filter['event'] = $event;
        }

        $cursor = $this->col->find(
            $filter,
            ['sort' => ['at' => -1], 'limit' => $limit, 'skip' => $skip]
        );

        $out = [];
        foreach ($cursor as $doc) {
            $out[] = $this->docToEntity((array) $doc);
        }
        return $out;
    }


    //CÂU HỎI NHANH (HELPERS)


    public function hasOpened(string $emailId): bool
    {
        return $this->hasEvent($emailId, 'open');
    }

    public function hasEvent(string $emailId, string $event): bool
    {
        return $this->col->countDocuments([
            'email_id' => new ObjectId($emailId),
            'event' => $event
        ]) > 0;
    }


    /* =========================================================
     *                     MAPPING ENTITY/DOC
     * ========================================================= */

    private function entityToDoc(EmailEvent $e): array
    {
        return [
            'email_id' => new ObjectId($e->getEmailId()),
            'event' => $e->getEvent(), // 'open' | 'click' | 'download'
            'at' => new UTCDateTime($e->getAt())
        ];
    }

    private function docToEntity(array $d): EmailEvent
    {
        return new EmailEvent(
            emailId: isset($d['email_id']) ? (string) $d['email_id'] : '',
            event: $d['event'] ?? 'open',
            at: isset($d['at'])
            ? $this->utcToImmutable($d['at'])
            : new \DateTimeImmutable()
        );
    }

    private function utcToImmutable(UTCDateTime $utc): \DateTimeImmutable
    {
        $dt = $utc->toDateTime();
        $dt->setTimezone(new \DateTimeZone(date_default_timezone_get()));
        return \DateTimeImmutable::createFromMutable($dt);
    }

}
?>