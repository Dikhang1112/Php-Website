<?php
declare(strict_types=1);

namespace App\Controllers;
use App\Repositories\EmailRepository;
use App\Repositories\EmailEventRepository;
use CodeIgniter\Controller;

class TrackEmailController extends Controller
{
    protected EmailEventRepository $emailEvents;
    protected EmailRepository $emails;

    public function __construct()
    {
        $this->emailEvents = new EmailEventRepository();
        $this->emails = new EmailRepository();
    }

    /**
     * Endpoint pixel: GET /log/open/{open_token} (hoặc {open_token}.gif)
     * - Tìm email theo open_token
     * - Ghi nhận event "open" (idempotent)
     * - Trả về ảnh GIF 1x1 (transparent) + header no-cache
     */
    public function open(string $token)
    {
        // tiny 1x1 gif + no-cache
        $gif = base64_decode('R0lGODlhAQABAPAAAP///wAAACwAAAAAAQABAAACAkQBADs=');
        $respond = function () use ($gif) {
            return $this->response
                ->setHeader('Content-Type', 'image/gif')
                ->setHeader('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
                ->setHeader('Pragma', 'no-cache')
                ->setBody($gif);
        };

        // Chuẩn hoá token (bỏ .gif nếu có)
        $raw = preg_replace('/\.gif$/i', '', rawurldecode($token));

        try {
            // 1) Tìm email theo open_token
            $emailId = $this->emails->getIdByOpenToken($raw);
            if (!$emailId) {
                // Không có email tương ứng → vẫn trả pixel
                return $respond();
            }
            try {
                if ($emailId) {
                    $this->emailEvents->logOpen($emailId);
                }
            } catch (\Exception $e) {
                // Optionally log the exception or handle it
            }
        } catch (\Exception $e) {

        }
        return $respond();
    }
}