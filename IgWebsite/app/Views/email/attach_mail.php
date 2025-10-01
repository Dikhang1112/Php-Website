<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8" />
    <title>Tài liệu của bạn đã sẵn sàng - ST Group</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f8f9fa;
            margin: 0;
            padding: 0
        }

        .wrapper {
            max-width: 640px;
            margin: 0 auto;
            background: #fff;
            padding: 24px;
            border-radius: 8px
        }

        h1 {
            color: #0d6efd;
            font-size: 22px;
            margin: 0 0 12px
        }

        p {
            font-size: 15px;
            line-height: 1.5;
            color: #333;
            margin: 0 0 12px
        }

        .highlight {
            font-weight: bold
        }

        .footer {
            margin-top: 24px;
            font-size: 13px;
            color: #6c757d
        }

        .link {
            font-size: 15px;
            word-break: break-all
        }

        a {
            color: #0d6efd;
            text-decoration: underline
        }
    </style>
    <?php
    helper('url');
    $rawDownload = trim((string) ($downloadUrl ?? ''));
    $rawPixel = trim((string) ($pixelUrl ?? ''));
    $isAbs = static function (string $u): bool {
        return (bool) filter_var($u, FILTER_VALIDATE_URL);
    };
    // $absDownload = $isAbs($rawDownload) ? $rawDownload : site_url(ltrim($rawDownload, '/'));
    $absPixel = $isAbs($rawPixel) ? $rawPixel : '';
    ?>
</head>

<body>
    <div class="wrapper">
        <h1>🎉 Xác minh email thành công!</h1>

        <p>Xin chào <span class="highlight">
                <?= esc($name ?? 'bạn') ?>
            </span>,</p>

        <p>
            Tài liệu <span class="highlight">
                <?= esc($fileLabel ?? 'tài liệu') ?>
            </span> của bạn đã sẵn sàng.
            Nhấn vào liên kết bên dưới để tải:
        </p>

        <?php if (!empty($downloadUrl)): ?>
            <p style="text-align:center;">
                <a href="<?= esc($downloadUrl) ?>" target="_blank" rel="noopener" ...>Tải tài liệu</a>
            </p>
        <?php endif; ?>


        <div class="footer">
            <p>Trân trọng,<br><strong>Đội ngũ ST Group</strong></p>
            <p><small>Email này được gửi tự động, vui lòng không trả lời.</small></p>
        </div>
    </div>

    <?php if (!empty($pixelUrl)): ?>
        <img src="<?= esc($pixelUrl) ?>" alt="" width="1" height="1" style="display:none;border:0;outline:0" />
    <?php endif; ?>
</body>

</html>