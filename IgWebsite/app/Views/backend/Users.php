<?php // app/Views/Users.php ?>
<!doctype html>
<html lang="vi">

<head>
    <meta charset="utf-8">
    <title>Users</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- jQuery + Kendo UI R3 2021 -->
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <link rel="stylesheet" href="https://kendo.cdn.telerik.com/2021.3.1109/styles/kendo.default-v2.min.css" />
    <script src="https://kendo.cdn.telerik.com/2021.3.1109/js/kendo.all.min.js"></script>

    <style>
        body {
            background: #f6f7fb;
        }

        .wrap {
            max-width: 1280px;
            margin: 32px auto;
            padding: 0 16px;
        }

        .cards {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
            margin-bottom: 16px;
        }

        @media (max-width: 900px) {
            .cards {
                grid-template-columns: 1fr;
            }
        }

        .k-card {
            border-radius: 14px;
            position: relative;
        }

        .k-card-body {
            padding: 16px;
        }

        .k-grid {
            border-radius: 12px;
        }

        .nowrap {
            white-space: nowrap;
        }

        .muted {
            color: #888;
        }

        .badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 999px;
            font-size: .85rem;
        }

        .badge-yes {
            background: #e6f4ea;
            color: #0b7a2a;
            border: 1px solid #c7e7cf;
        }

        .badge-no {
            background: #fdecea;
            color: #a00;
            border: 1px solid #f6c6c3;
        }
    </style>
</head>

<body>
    <?php
    // ===== Chuẩn hoá dữ liệu PHP -> JSON cho Kendo Grid =====
    /** @var array $users */
    /** @var array $userOpens */
    /** @var array $userDownloads */
    $tz = new \DateTimeZone('Asia/Ho_Chi_Minh');
    $rows = [];

    foreach ($users as $i => $u) {
        $uid = isset($u['_id']) ? (string) $u['_id'] : (string) ($u['id'] ?? '');

        $openStat = $userOpens[$uid] ?? null;
        $downloadStat = $userDownloads[$uid] ?? null;

        $openCount = (int) ($openStat['openCount'] ?? 0);
        $lastOpen = $openStat['lastOpenAt'] ?? null;

        $dlCount = (int) ($downloadStat['downloadCount'] ?? 0);
        $lastDl = $downloadStat['lastDownloadAt'] ?? null;

        $rows[] = [
            'idx' => (int) $i + 1,
            'email' => (string) ($u['email'] ?? ''),
            'isVerified' => !empty($u['isVerified']),
            'hasOpened' => $openCount > 0,
            'lastOpenAt' => $lastOpen instanceof \DateTimeInterface
                ? $lastOpen->setTimezone($tz)->format(\DateTime::ATOM)
                : null,
            'hasDownloaded' => $dlCount > 0,
            'lastDownloadAt' => $lastDl instanceof \DateTimeInterface
                ? $lastDl->setTimezone($tz)->format(\DateTime::ATOM)
                : null,
            'id' => isset($u['_id']) ? (string) $u['_id'] : ($u['id'] ?? null),
        ];
    }

    // Lấy toàn bộ flashdata một lần để đẩy xuống JS
    $flash = session()->getFlashdata() ?? [];
    ?>
    <div class="wrap">
        <h1 class="k-h1 k-mb-3">Quản lý Users</h1>
        <a href="/logout"> Đăng xuất</a>

        <!-- ======= Hai form resend ======= -->
        <div class="cards">
            <!-- Resend verify-email -->
            <div id="verifyWrap" class="k-card k-shadow-md">
                <div class="k-card-body">
                    <h3 class="k-h3 k-mb-3">Resend verify-email</h3>
                    <div id="notifVerify"></div>

                    <form id="verifyForm" method="post" action="/verify-email/resend" autocomplete="off">
                        <?php if (function_exists('csrf_field'))
                            echo csrf_field(); ?>
                        <label for="verifyEmail" class="k-label k-mb-1">
                            Nhập email để resend <span class="k-required">*</span>
                        </label>
                        <input type="email" id="verifyEmail" name="email" required placeholder="user@example.com"
                            value="<?= esc(old('email') ?? '') ?>">
                        <div class="k-mt-3">
                            <button id="btnVerify" type="submit" class="k-button k-button-solid k-button-solid-primary">
                                Gửi lại verify-email
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Resend download-mail -->
            <div id="downloadWrap" class="k-card k-shadow-md">
                <div class="k-card-body">
                    <h3 class="k-h3 k-mb-3">Resend download-mail</h3>
                    <div id="notifDownload"></div>

                    <form id="downloadForm" method="post" action="/download-email/resend" autocomplete="off">
                        <?php if (function_exists('csrf_field'))
                            echo csrf_field(); ?>
                        <label for="downloadEmail" class="k-label k-mb-1">
                            Nhập email để resend <span class="k-required">*</span>
                        </label>
                        <input type="email" id="downloadEmail" name="email" required placeholder="user@example.com">
                        <div class="k-mt-3">
                            <button id="btnDownload" type="submit" class="k-button k-button-solid">
                                Gửi lại download-mail
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- ======= Kendo Grid ======= -->
        <div id="usersGrid"></div>
    </div>

    <script>
        $(function () {
            // ========== Kendo hóa controls form ==========
            $("#verifyEmail, #downloadEmail").kendoTextBox();
            $("#btnVerify, #btnDownload").kendoButton();

            // ========== Notifications cho từng form (neo trong card) ==========
            var notifVerify = $("#notifVerify").kendoNotification({
                appendTo: "#verifyWrap",
                position: { pinned: false, top: 8, right: 8 },
                stacking: "down",
                autoHideAfter: 3000,
                hideOnClick: true
            }).data("kendoNotification");

            var notifDownload = $("#notifDownload").kendoNotification({
                appendTo: "#downloadWrap",
                position: { pinned: false, top: 8, right: 8 },
                stacking: "down",
                autoHideAfter: 3000,
                hideOnClick: true
            }).data("kendoNotification");

            // ========== Validators ==========
            var verifyValidator = $("#verifyForm").kendoValidator({
                messages: { required: "Email là bắt buộc.", email: "Email không hợp lệ." }
            }).data("kendoValidator");

            var downloadValidator = $("#downloadForm").kendoValidator({
                messages: { required: "Email là bắt buộc.", email: "Email không hợp lệ." }
            }).data("kendoValidator");

            $("#verifyForm").on("submit", function (e) {
                if (!verifyValidator.validate()) {
                    e.preventDefault();
                    notifVerify.show("Vui lòng nhập email hợp lệ.", "warning");
                    return;
                }
                $("#btnVerify").data("kendoButton").enable(false);
            });

            $("#downloadForm").on("submit", function (e) {
                if (!downloadValidator.validate()) {
                    e.preventDefault();
                    notifDownload.show("Vui lòng nhập email hợp lệ.", "warning");
                    return;
                }
                $("#btnDownload").data("kendoButton").enable(false);
            });

            // ========== Hiển thị FLASH (đặt NGAY SAU khi có notif*) ==========
            var flash = <?= json_encode($flash, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?> || {};

            function show(notif, type, text) {
                if (!text) return;
                try { notif.show(String(text), type); } catch (e) { }
            }
            function flatten(x) {
                if (!x) return "";
                if (Array.isArray(x)) return x.join("<br>");
                if (typeof x === "object") return Object.values(x).join("<br>");
                return String(x);
            }

            // VERIFY form
            show(notifVerify, "success", flash.verify_success || flash.msg || flash.success);
            show(notifVerify, "warning", flash.verify_warning || flash.warning);

            var verifyErr = flash.verify_error || flash.err || flash.error;
            if (verifyErr && (!flash.form || flash.form === 'verify')) {
                show(notifVerify, "error", verifyErr);
            }
            if (flash.verify_errors) {
                show(notifVerify, "error", flatten(flash.verify_errors));
            }

            // DOWNLOAD form
            show(notifDownload, "success", flash.download_success || flash.success_download);

            var dlErr = flash.download_error || flash.err_download;
            if (dlErr) {
                show(notifDownload, "error", dlErr);
            }
            if ((flash.err || flash.error) && flash.form === 'download') {
                show(notifDownload, "error", flash.err || flash.error);
            }
            if (flash.download_errors) {
                show(notifDownload, "error", flatten(flash.download_errors));
            }

            // Fallback chung (nếu controller chỉ bắn generic không kèm form)
            if (!verifyErr && !dlErr) {
                show(notifVerify, "success", flash.success);
                show(notifVerify, "error", flash.error);
            }

            // ========== Kendo Grid ==========
            var data = <?= json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

            function yesNoBadge(cond, yesText, noText) {
                return cond
                    ? '<span class="badge badge-yes">' + kendo.htmlEncode(yesText) + '</span>'
                    : '<span class="badge badge-no">' + kendo.htmlEncode(noText) + '</span>';
            }

            $("#usersGrid").kendoGrid({
                dataSource: {
                    data: data,
                    schema: {
                        model: {
                            fields: {
                                idx: { type: "number" },
                                email: { type: "string" },
                                isVerified: { type: "boolean" },
                                hasOpened: { type: "boolean" },
                                lastOpenAt: { type: "date" },
                                hasDownloaded: { type: "boolean" },
                                lastDownloadAt: { type: "date" },
                                id: { type: "string" }
                            }
                        }
                    },
                    pageSize: 20
                },
                height: 600,
                sortable: true,
                resizable: true,
                columnMenu: true,
                filterable: {
                    mode: "row",
                    messages: { info: "Lọc:", clear: "Xoá", filter: "Lọc", checkAll: "Chọn tất cả" },
                    operators: {
                        string: { contains: "Chứa", startswith: "Bắt đầu bằng", eq: "Bằng" },
                        boolean: { eq: "Bằng" },
                        date: { gte: "Từ ngày", lte: "Đến ngày", eq: "Bằng ngày" }
                    }
                },
                pageable: {
                    refresh: true, pageSizes: [10, 20, 50, 100],
                    messages: {
                        display: "{0}-{1} của {2} mục",
                        empty: "Không có dữ liệu",
                        itemsPerPage: "mục/trang",
                        first: "Trang đầu", previous: "Trước", next: "Sau", last: "Trang cuối", refresh: "Tải lại"
                    }
                },
                columns: [
                    { field: "idx", title: "#", width: 80, attributes: { "class": "nowrap k-text-end" } },
                    { field: "email", title: "Email" },
                    {
                        field: "isVerified", title: "Xác minh", width: 130, filterable: { multi: true },
                        template: function (d) { return yesNoBadge(d.isVerified, "Đã xác minh", "Chưa xác minh"); }
                    },
                    {
                        field: "hasOpened", title: "Đã mở email", width: 140, filterable: { multi: true },
                        template: function (d) { return yesNoBadge(d.hasOpened, "Đã mở", "Chưa mở"); }
                    },
                    {
                        field: "lastOpenAt", title: "Thời gian mở email", width: 190, attributes: { "class": "nowrap" },
                        template: function (d) { return d.lastOpenAt ? kendo.toString(d.lastOpenAt, "yyyy-MM-dd HH:mm:ss") : "<span class='muted'>-</span>"; },
                        filterable: { ui: "datetimepicker" }
                    },
                    {
                        field: "hasDownloaded", title: "Đã tải", width: 110, filterable: { multi: true },
                        template: function (d) { return yesNoBadge(d.hasDownloaded, "Đã tải", "Chưa tải"); }
                    },
                    {
                        field: "lastDownloadAt", title: "Thời gian tải", width: 180, attributes: { "class": "nowrap" },
                        template: function (d) { return d.lastDownloadAt ? kendo.toString(d.lastDownloadAt, "yyyy-MM-dd HH:mm:ss") : "<span class='muted'>-</span>"; },
                        filterable: { ui: "datetimepicker" }
                    }
                ]
            });
        });
    </script>
</body>

</html>