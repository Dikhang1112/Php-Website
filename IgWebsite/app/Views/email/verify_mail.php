<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Xác minh tài khoản - ST. Group</title>
    <link rel="stylesheet" href="/asset/css/verify_mail.css">
</head>

<body>
    <div class="email-wrapper">
        <div class="email-header">
            <h1>🎉 Chào mừng đến với ST Group!</h1>
            <p class="subtitle">Email xác nhận người dùng</p>
        </div>

        <div class="email-content">
            <p>Xin chào <strong>
                    <?= esc($name ?? 'bạn') ?>
                </strong>,</p>

            <p>Cảm ơn bạn đã đăng ký tài khoản tại <strong>ST Group</strong>! Vui lòng nhấn vào nút bên dưới để xác nhận
                email:</p>

            <div class="verify-section">
                <a href="<?= esc($verifyUrl) ?>" class="verify-button" target="_blank" rel="noopener">
                    ✅ XÁC NHẬN TÀI KHOẢN NGAY
                </a>
            </div>

            <div class="alert-box">
                <h4>📌 Lưu ý:</h4>
                <ul>
                    <li>Liên kết chỉ có hiệu lực trong <strong>24 giờ</strong>.</li>
                    <li>Nếu bạn không đăng ký, vui lòng bỏ qua email này.</li>
                </ul>
            </div>
        </div>

        <div class="email-footer">
            <p>Trân trọng,<br><strong>Đội ngũ ST Group</strong></p>
            <p><small>
                    Cần hỗ trợ? Liên hệ:
                    <a href="mailto:<?= esc($email ?? 'support@example.com') ?>" class="contact-link">
                        <?= esc($email ?? 'support@example.com') ?>
                    </a>
                </small></p>
            <div class="copyright">©
                <?= date('Y') ?> ST Group. All rights reserved.
            </div>
        </div>
    </div>
</body>

</html>