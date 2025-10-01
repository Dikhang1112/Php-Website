<?php // app/Views/Login.php ?>
<!doctype html>
<html lang="vi">

<head>
    <meta charset="utf-8">
    <title>Login</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- jQuery (Google CDN) -->
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>

    <!-- Kendo UI R3 2021 (CSS + JS) -->
    <link rel="stylesheet" href="https://kendo.cdn.telerik.com/2021.3.1109/styles/kendo.default-v2.min.css" />
    <script src="https://kendo.cdn.telerik.com/2021.3.1109/js/kendo.all.min.js"></script>

    <style>
        body {
            background: #f6f7fb;
        }

        /* 1) Tăng chiều cao vùng form (card) */
        #formWrap .k-card-body {
            padding: 24px;
        }

        #formWrap.form-tall .k-card-body {
            min-height: 480px;
            max-width: 200px;
        }

        /* đổi 480px theo ý */

        /* 2) Tăng chiều cao các ô Kendo TextBox */
        .k-input .k-input-inner {
            min-height: 44px;
        }

        /* 40–48px là hợp lý */
        .k-input {
            font-size: 16px;
        }

        /* tăng cỡ chữ nếu muốn */

        /* 3) Tăng chiều cao button Kendo */
        .k-button {
            min-height: 44px;
            padding: 0 16px;
        }

        /* 4) Khoảng cách dọc giữa các field */
        .k-mb-4 {
            margin-bottom: 16px !important;
        }


        .actions {
            display: flex;
            gap: 8px;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
        }
    </style>
</head>

<body>
    <div class="auth-wrap">
        <h1 class="k-h1 k-text-center k-mb-4">Đăng nhập</h1>

        <!-- Notification host -->
        <div id="notif"></div>

        <div id="formWrap" class="k-card k-shadow-md form-tall">
            <div class="k-card-body k-p-6">
                <form id="loginForm" method="post" action="<?= site_url('/login') ?>" autocomplete="off">
                    <?= csrf_field() ?>

                    <div class="k-mb-4">
                        <label for="email" class="k-label k-mb-2">Email <span class="k-required"> </span></label>
                        <input id="email" name="email" type="email" value="<?= esc(old('email')) ?>" required>
                    </div>

                    <div class="k-mb-2">
                        <label for="password" class="k-label k-mb-2">Mật khẩu <span class="k-required"> </span></label>
                        <input id="password" name="password" type="password" required>
                    </div>

                    <div class="k-mb-4">
                        <label class="k-checkbox-label">
                            <input type="checkbox" id="remember" name="remember" class="k-checkbox" <?= old('remember') ? 'checked' : '' ?>>
                            <span class="k-checkbox-label">Ghi nhớ đăng nhập</span>
                        </label>
                    </div>

                    <div class="actions k-mt-4">
                        <button type="submit" class="k-button k-button-solid k-button-solid-primary">
                            Đăng nhập
                        </button>
                        <a class="k-link" href="<?= site_url('') ?>">Đăng ký tài khoản</a>
                    </div>
                </form>
            </div>
        </div>

    </div>

    <script>
        $(function () {
            // 1) Notification neo vào khung form
            var notif = $("#notif").kendoNotification({
                appendTo: "#formWrap",
                position: { pinned: false, top: 8, right: 8 }, // góc trên-phải trong form
                stacking: "down",
                autoHideAfter: 3000,
                hideOnClick: true
            }).data("kendoNotification");

            // 2) Flash message từ server (nếu có)
            <?php if (session()->getFlashdata('success')): ?>
                notif.show("<?= esc(session('success')) ?>", "success");
            <?php endif; ?>
            <?php if (session()->getFlashdata('error')): ?>
                notif.show("<?= esc(session('error')) ?>", "error");
            <?php endif; ?>

            // 3) Nâng cấp input -> Kendo widgets
            $("#email").kendoTextBox({ placeholder: "email@domain.com", size: "large" });
            $("#password").kendoTextBox(); // vẫn là type="password"
            // Checkbox dùng class k-checkbox là đủ để có style của Kendo

            // 4) Kendo Validator: yêu cầu email hợp lệ + password >= 6
            var validator = $("#loginForm").kendoValidator({
                rules: {
                    minPass: function (input) {
                        if (input.is("[name=password]")) {
                            return (input.val() || "").length >= 6;
                        }
                        return true;
                    }
                },
                messages: {
                    required: "Trường này là bắt buộc.",
                    email: "Email không hợp lệ.",
                    minPass: "Mật khẩu tối thiểu 6 ký tự."
                }
            }).data("kendoValidator");

            // 5) Chặn submit nếu chưa hợp lệ
            $("#loginForm").on("submit", function (e) {
                if (!validator.validate()) {
                    e.preventDefault();
                    notif.show("Vui lòng kiểm tra lại Email/Mật khẩu.", "warning");
                }
            });
        });
    </script>
</body>

</html>