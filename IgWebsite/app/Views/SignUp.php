<?php // app/Views/SignUp.php ?>
<!doctype html>
<html lang="vi">

<head>
  <meta charset="utf-8">
  <title>Sign Up</title>
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

    .auth-wrap {
      max-width: 720px;
      height: 600px;
      margin: 40px auto;
      padding: 0 16px;
    }

    .k-card {
      border-radius: 16px;
    }

    .form-row {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 16px;
    }

    @media (max-width: 640px) {
      .form-row {
        grid-template-columns: 1fr;
      }
    }

    /* Container để neo thông báo */
    #formWrap {
      position: relative;
    }
  </style>
</head>

<body>
  <div class="auth-wrap">
    <h1 class="k-h1 k-text-center k-mb-4">Sign Up</h1>

    <!-- Notification host -->
    <div id="notif"></div>

    <!-- Container form để append Notification vào -->
    <div id="formWrap" class="k-card k-shadow-md">
      <div class="k-card-body k-p-6">
        <form id="signupForm" method="post" action="<?= site_url('/users/add') ?>" autocomplete="off" class="k-mt-2">
          <?= csrf_field() ?>

          <div class="k-mb-4">
            <label for="fullName" class="k-label k-mb-2">Họ tên <span class="k-required">*</span></label>
            <input id="fullName" name="fullName" type="text" value="<?= esc(old('fullName')) ?>" required>
          </div>

          <div class="k-mb-4">
            <label for="email" class="k-label k-mb-2">Email <span class="k-required">*</span></label>
            <input id="email" name="email" type="email" value="<?= esc(old('email')) ?>" required>
          </div>

          <div class="k-mb-4">
            <label for="password" class="k-label k-mb-2">Mật khẩu <span class="k-required">*</span></label>
            <input id="password" name="password" type="password" required>
            <div class="k-text-secondary k-text-sm k-mt-1">Tối thiểu 6 ký tự.</div>
          </div>

          <div class="form-row k-mb-4">
            <div>
              <label for="sex" class="k-label k-mb-2">Giới tính</label>
              <select id="sex" name="sex">
                <option value="">-- Chọn --</option>
                <option value="Male" <?= old('sex') === 'Male' ? 'selected' : '' ?>>Nam</option>
                <option value="Female" <?= old('sex') === 'Female' ? 'selected' : '' ?>>Nữ</option>
                <option value="Other" <?= old('sex') === 'Other' ? 'selected' : '' ?>>Khác</option>
              </select>
            </div>
            <div>
              <label for="age" class="k-label k-mb-2">Tuổi</label>
              <input id="age" name="age" type="number" min="0" value="<?= esc(old('age')) ?>">
            </div>
          </div>

          <div class="k-mb-4">
            <label for="career" class="k-label k-mb-2">Nghề nghiệp</label>
            <input id="career" name="career" type="text" value="<?= esc(old('career')) ?>">
          </div>

          <div class="k-mt-6 k-d-flex k-gap-2">
            <button type="submit" class="k-button k-button-solid k-button-solid-primary">
              Đăng ký
            </button>
            <a href="<?= site_url('login') ?>" class="k-button k-button-outline k-button-outline-base">
              Đăng nhập để xem danh sách Users
            </a>
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

      // 2) Hiển thị flash message (nếu có) bằng cùng instance
      <?php if (session()->getFlashdata('success')): ?>
        notif.show("<?= esc(session('success')) ?>", "success");
      <?php endif; ?>
      <?php if (session()->getFlashdata('error')): ?>
        notif.show("<?= esc(session('error')) ?>", "error");
      <?php endif; ?>

      // 3) Nâng cấp input thành Kendo widgets
      $("#fullName").kendoTextBox({ placeholder: "Nhập họ tên" });
      $("#email").kendoTextBox({ placeholder: "email@domain.com" });
      $("#password").kendoTextBox(); // vẫn là <input type="password">
      $("#career").kendoTextBox({ placeholder: "VD: Sinh viên, DevOps..." });

      $("#sex").kendoDropDownList({
        optionLabel: "-- Chọn --",
        valuePrimitive: true
      });

      $("#age").kendoNumericTextBox({
        format: "n0",
        min: 0,
        step: 1
      });

      // 4) Kendo Validator
      var validator = $("#signupForm").kendoValidator({
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

      // 5) Chặn submit nếu form chưa hợp lệ
      $("#signupForm").on("submit", function (e) {
        if (!validator.validate()) {
          e.preventDefault();
          notif.show("Vui lòng kiểm tra lại các trường bắt buộc.", "warning");
        }
      });
    });
  </script>
</body>

</html>