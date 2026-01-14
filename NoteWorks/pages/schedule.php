<?php
// assets/pages/schedule.php

// 1. CHỐNG CACHE TRÌNH DUYỆT
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

$uid = $_SESSION['user_id'];
$view_mode = $_GET['view'] ?? 'list'; // 'list' hoặc 'grid'

// ============================================================
// 2. CẤU HÌNH KHUNG GIỜ (TIẾT HỌC)
// ============================================================
// Cấu hình này dùng để:
// a) Hiển thị cột giờ bên trái Grid
// b) Mapping từ giờ trong DB (VD: 07:00) sang vị trí ô Grid (Tiết 1)
$periods_config = [
        1 => ['start' => '07:00', 'end' => '07:50'],
        2 => ['start' => '07:50', 'end' => '08:40'],
        3 => ['start' => '08:40', 'end' => '09:35'],
        4 => ['start' => '09:35', 'end' => '10:25'],
        5 => ['start' => '10:25', 'end' => '11:15'], // Sáng
        6 => ['start' => '13:00', 'end' => '13:50'],
        7 => ['start' => '13:50', 'end' => '14:40'],
        8 => ['start' => '14:40', 'end' => '15:35'],
        9 => ['start' => '15:35', 'end' => '16:25'],
        10=> ['start' => '16:25', 'end' => '17:15'], // Chiều
        11=> ['start' => '17:30', 'end' => '18:15'],
        12=> ['start' => '18:15', 'end' => '19:00'],
        13=> ['start' => '19:00', 'end' => '19:45'], // Tối
];

// ============================================================
// 3. XỬ LÝ THỜI GIAN (TUẦN & NGÀY)
// ============================================================
// Xử lý nhảy đến ngày cụ thể
if (isset($_GET['jump_date']) && !empty($_GET['jump_date'])) {
    $target = new DateTime($_GET['jump_date']);
    $now = new DateTime();
    // Tính khoảng cách tuần
    $target->setISODate($target->format('o'), $target->format('W'));
    $now->setISODate($now->format('o'), $now->format('W'));
    $interval = $now->diff($target);
    $weeks_diff = (int)$interval->format('%r%a') / 7;
    header("Location: index.php?page=schedule&view=$view_mode&week=" . round($weeks_diff));
    exit();
}

$week_offset = isset($_GET['week']) ? intval($_GET['week']) : 0;

// Xác định ngày Thứ 2 đầu tuần và CN cuối tuần dựa trên offset
$dt = new DateTime();
$dt->setISODate($dt->format('o'), $dt->format('W')); // Về thứ 2 tuần hiện tại
if ($week_offset != 0) {
    $dt->modify(($week_offset > 0 ? '+' : '') . $week_offset . ' weeks');
}

$start_week_date = $dt->format('Y-m-d'); // Thứ 2
$start_week_ts = $dt->getTimestamp();

$dt_end = clone $dt;
$dt_end->modify('+6 days');
$end_week_date = $dt_end->format('Y-m-d'); // Chủ nhật

// Tạo Map ngày trong tuần (Mon => 2025-12-29)
$week_dates_map = [];
$days_label_map = ['Mon'=>'Thứ 2','Tue'=>'Thứ 3','Wed'=>'Thứ 4','Thu'=>'Thứ 5','Fri'=>'Thứ 6','Sat'=>'Thứ 7','Sun'=>'CN'];
$temp_d = clone $dt;
foreach ($days_label_map as $code => $label) {
    $week_dates_map[$code] = $temp_d->format('Y-m-d');
    $temp_d->modify('+1 day');
}

// ============================================================
// ============================================================
$error_msg = "";

if (isset($_POST['save_sch'])) {
    $title = trim($_POST['subject']);
    $day_code = $_POST['day']; // Mon, Tue...
    $specific_date = $week_dates_map[$day_code]; // Lấy ngày cụ thể của tuần đang xem

    // Tính giờ bắt đầu/kết thúc
    if (isset($_POST['start_period']) && $_POST['start_period'] != '') {
        // Nếu chọn theo Tiết -> Chuyển sang Giờ
        $s_p = intval($_POST['start_period']);
        $e_p = intval($_POST['end_period']);
        $start_time = $periods_config[$s_p]['start'] . ":00";
        $end_time   = $periods_config[$e_p]['end'] . ":00";
        $type = 'class'; // Mặc định từ Grid là Lớp học
    } else {
        // Nếu nhập giờ thủ công
        $start_time = $_POST['start'] . ":00";
        $end_time   = $_POST['end'] . ":00";
        $type = 'event';
    }

    // Xử lý Insert/Update
    try {
        if (isset($_POST['sch_id']) && $_POST['sch_id'] != '') {
            $sid = intval($_POST['sch_id']);
            $stmt = $conn->prepare("UPDATE schedule SET title=?, event_date=?, start_time=?, end_time=?, type=? WHERE id=? AND user_id=?");
            $stmt->bind_param("sssssii", $title, $specific_date, $start_time, $end_time, $type, $sid, $uid);
        } else {
            $stmt = $conn->prepare("INSERT INTO schedule (user_id, title, event_date, start_time, end_time, type) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("isssss", $uid, $title, $specific_date, $start_time, $end_time, $type);
        }

        if(!$stmt->execute()) {
            // Bắt lỗi trùng giờ (Duplicate entry)
            if ($conn->errno == 1062) $error_msg = "Lỗi: Giờ này đã có lịch rồi!";
            else $error_msg = "Lỗi hệ thống: " . $conn->error;
        } else {
            // Refresh trang
            header("Location: index.php?page=schedule&view=$view_mode&week=$week_offset"); exit();
        }
    } catch (Exception $e) {
        $error_msg = "Lỗi: " . $e->getMessage();
    }
}

if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $conn->query("DELETE FROM schedule WHERE id=$id AND user_id=$uid");
    header("Location: index.php?page=schedule&view=$view_mode&week=$week_offset"); exit();
}

// Lấy dữ liệu EDIT
$edit_data = ['day'=>'Mon', 'subject'=>'', 'start_p'=>'', 'end_p'=>'', 'start_t'=>'', 'end_t'=>'', 'id'=>''];
$edit_mode = false;
if (isset($_GET['edit'])) {
    $edit_mode = true;
    $eid = intval($_GET['edit']);
    $res = $conn->query("SELECT * FROM schedule WHERE id=$eid AND user_id=$uid");
    if($res && $row = $res->fetch_assoc()) {
        $edit_data['id'] = $row['id'];
        $edit_data['subject'] = $row['title'];
        $edit_data['day'] = date('D', strtotime($row['event_date']));
        $edit_data['start_t'] = substr($row['start_time'], 0, 5);
        $edit_data['end_t'] = substr($row['end_time'], 0, 5);

        // Cố gắng map lại về Tiết (nếu khớp giờ)
        foreach($periods_config as $p => $time) {
            if(substr($row['start_time'], 0, 5) == $time['start']) $edit_data['start_p'] = $p;
            if(substr($row['end_time'], 0, 5) == $time['end']) $edit_data['end_p'] = $p;
        }
    }
}

// ============================================================
// 5. LẤY DỮ LIỆU HIỂN THỊ
// ============================================================
$schedule_data = []; // Mảng chứa dữ liệu đã phân loại theo ngày

// Query duy nhất: Lấy dữ liệu trong khoảng ngày của tuần
$sql = "SELECT * FROM schedule 
        WHERE user_id = $uid 
        AND event_date BETWEEN '$start_week_date' AND '$end_week_date' 
        ORDER BY event_date ASC, start_time ASC";
$result = $conn->query($sql);

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $d_code = date('D', strtotime($row['event_date'])); // Mon, Tue...

        // Tính toán Tiết bắt đầu/Kết thúc để vẽ lên Grid
        $row['grid_start'] = null;
        $row['grid_span'] = 1;

        // Logic tìm tiết gần đúng nhất dựa trên giờ bắt đầu
        $s_time = substr($row['start_time'], 0, 5);
        foreach ($periods_config as $p => $cfg) {
            if ($s_time >= $cfg['start'] && $s_time < $cfg['end']) {
                $row['grid_start'] = $p;
                break;
            }
        }

        // Tính độ dài (số tiết)
        // --- ĐOẠN CODE ĐÃ SỬA LỖI ---

        // 1. Tìm Tiết Bắt Đầu (grid_start)
        $s_time = substr($row['start_time'], 0, 5);
        foreach ($periods_config as $p => $cfg) {
            // So sánh giờ bắt đầu của sự kiện với khung giờ
            if ($s_time >= $cfg['start'] && $s_time < $cfg['end']) {
                $row['grid_start'] = $p;
                break;
            }
        }

        // 2. Tìm Tiết Kết Thúc để tính Span chính xác
        if ($row['grid_start']) {
            $e_time = substr($row['end_time'], 0, 5);
            $end_p = $row['grid_start']; // Mặc định ít nhất là bằng tiết bắt đầu

            // Duyệt từ tiết bắt đầu trở đi để tìm tiết kết thúc
            foreach ($periods_config as $p => $cfg) {
                if ($p < $row['grid_start']) continue;

                // Nếu giờ kết thúc sự kiện <= giờ kết thúc của tiết này => Đây là tiết cuối
                // Lưu ý: So sánh chuỗi thời gian (String comparison) hoạt động tốt với format HH:MM
                if ($e_time <= $cfg['end']) {
                    $end_p = $p;
                    break;
                }
            }

            // Tính số ô cần vẽ (Span) = Tiết cuối - Tiết đầu + 1
            $row['grid_span'] = ($end_p - $row['grid_start']) + 1;
        } else {
            // Trường hợp không khớp tiết nào (VD: sự kiện nhập tay giờ lẻ), mặc định span=1
            $row['grid_span'] = 1;
        }

        // --- HẾT ĐOẠN SỬA ---

        $schedule_data[$d_code][] = $row;
    }
}
?>

<link rel="stylesheet" href="assets/css/pages/schedule.css">

<div class="header-welcome">
    <div>
        <h1>Thời khóa biểu</h1>
        <p>Tuần <?= $dt->format('W') ?> / Tháng <?= $dt->format('m') ?></p>
    </div>

    <div style="display: flex; gap: 10px; align-items: center;">
        <div class="week-navigator">
            <a href="index.php?page=schedule&view=<?= $view_mode ?>&week=<?= $week_offset - 1 ?>" class="week-btn"><i class="ri-arrow-left-s-line"></i></a>

            <div class="week-info">
                <span class="week-highlight">
                    <?= date('d/m', $start_week_ts) ?> - <?= date('d/m', strtotime($end_week_date)) ?>
                </span>
            </div>

            <a href="index.php?page=schedule&view=<?= $view_mode ?>&week=<?= $week_offset + 1 ?>" class="week-btn"><i class="ri-arrow-right-s-line"></i></a>

            <div style="position: relative; margin-left: 5px;">
                <button class="week-btn" onclick="document.getElementById('jump-menu').classList.toggle('show')" type="button"><i class="ri-calendar-line"></i></button>
                <div id="jump-menu" class="jump-dropdown">
                    <label style="font-size: 12px; font-weight: bold; color: #666;">Đến ngày:</label>
                    <input type="date" class="jump-input" onchange="window.location.href='index.php?page=schedule&view=<?= $view_mode ?>&jump_date='+this.value">
                    <div style="margin-top: 10px; border-top: 1px solid #eee; padding-top: 5px;">
                        <a href="index.php?page=schedule&view=<?= $view_mode ?>&week=0" style="font-size: 12px; color: var(--primary); text-decoration: none;">Về tuần hiện tại</a>
                    </div>
                </div>
            </div>
        </div>

        <div class="view-toggle">
            <a href="index.php?page=schedule&view=list&week=<?= $week_offset ?>" class="view-btn <?= $view_mode=='list'?'active':'' ?>"><i class="ri-list-check"></i></a>
            <a href="index.php?page=schedule&view=grid&week=<?= $week_offset ?>" class="view-btn <?= $view_mode=='grid'?'active':'' ?>"><i class="ri-grid-fill"></i></a>
        </div>
    </div>
</div>

<?php if($error_msg): ?>
    <div class="error-box"><i class="ri-error-warning-fill"></i> <?= $error_msg ?></div>
<?php endif; ?>

<div class="card <?= $edit_mode ? 'editing' : '' ?>">
    <h3 style="margin-bottom: 15px; font-size: 16px;">
        <?= $edit_mode ? 'Cập nhật lịch' : 'Thêm vào lịch tuần này' ?>
    </h3>
    <form method="POST" class="flex-form">
        <input type="hidden" name="sch_id" value="<?= $edit_data['id'] ?>">

        <select name="day" class="form-input" style="width: 140px;">
            <?php foreach($days_label_map as $code => $name): ?>
                <option value="<?= $code ?>" <?= $code==$edit_data['day'] ? 'selected' : '' ?>>
                    <?= $name ?> (<?= date('d/m', strtotime($week_dates_map[$code])) ?>)
                </option>
            <?php endforeach; ?>
        </select>

        <div class="grow">
            <input type="text" name="subject" value="<?= htmlspecialchars($edit_data['subject']) ?>" placeholder="Tên môn học / Sự kiện" required class="form-input">
        </div>

        <?php if ($view_mode == 'grid'): ?>
            <div class="period-selector">
                <span style="font-size: 12px; color: #666;">Tiết:</span>
                <select name="start_period" class="period-input" required>
                    <option value="">-</option>
                    <?php foreach($periods_config as $p => $t): ?>
                        <option value="<?= $p ?>" <?= $edit_data['start_p'] == $p ? 'selected' : '' ?>><?= $p ?></option>
                    <?php endforeach; ?>
                </select>
                <span>-</span>
                <select name="end_period" class="period-input" required>
                    <option value="">-</option>
                    <?php foreach($periods_config as $p => $t): ?>
                        <option value="<?= $p ?>" <?= $edit_data['end_p'] == $p ? 'selected' : '' ?>><?= $p ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php else: ?>
            <div style="display: flex; align-items: center; gap: 5px;">
                <input type="time" name="start" value="<?= $edit_data['start_t'] ?>" required class="form-input" style="width: 110px;">
                <span>-</span>
                <input type="time" name="end" value="<?= $edit_data['end_t'] ?>" required class="form-input" style="width: 110px;">
            </div>
        <?php endif; ?>

        <button type="submit" name="save_sch" class="btn-primary"><?= $edit_mode ? 'Lưu' : 'Thêm' ?></button>
        <?php if($edit_mode): ?><a href="index.php?page=schedule&view=<?= $view_mode ?>&week=<?= $week_offset ?>" class="btn-cancel">Hủy</a><?php endif; ?>
    </form>
</div>

<?php if ($view_mode == 'grid'): ?>
    <div class="tkb-wrapper">
        <table class="tkb-table">
            <thead>
            <tr>
                <th class="tkb-period-col">Tiết</th>
                <?php foreach($days_label_map as $code => $name): ?>
                    <th><?= $name ?><br><span class="th-date"><?= date('d/m', strtotime($week_dates_map[$code])) ?></span></th>
                <?php endforeach; ?>
            </tr>
            </thead>
            <tbody>
            <?php foreach($periods_config as $p => $time):
                // Phân cách buổi
                if($p == 1) echo "<tr class='session-row'><td colspan='8'>Sáng</td></tr>";
                if($p == 6) echo "<tr class='session-row'><td colspan='8'>Chiều</td></tr>";
                if($p == 11) echo "<tr class='session-row'><td colspan='8'>Tối</td></tr>";
                ?>
                <tr>
                    <td class="tkb-period-col">
                        <div class="p-num"><?= $p ?></div>
                        <div class="p-time"><?= $time['start'] ?>-<?= $time['end'] ?></div>
                    </td>
                    <?php foreach($days_label_map as $day_code => $day_name): ?>
                        <td class="tkb-cell">
                            <?php
                            if(isset($schedule_data[$day_code])) {
                                foreach($schedule_data[$day_code] as $evt) {

                                    // Chỉ hiển thị lớp học
                                    if ($evt['type'] !== 'class') continue;

                                    // Kiểm tra xem sự kiện này có thuộc tiết này không
                                    // Nếu grid_start khớp tiết này, hoặc nó kéo dài qua tiết này
                                    $p_start = $evt['grid_start'];
                                    $p_end = $evt['grid_start'] + $evt['grid_span'] - 1;

                                    // Logic đơn giản: Chỉ hiển thị ở ô bắt đầu, set rowspan (nếu muốn) hoặc lặp lại
                                    // Ở đây dùng cách vẽ vào tất cả các ô thuộc phạm vi để đơn giản hóa grid
                                    if ($p >= $p_start && $p <= $p_end) {
                                        $cls = ($evt['type'] == 'class') ? 'type-timetable' : 'type-event';
                                        echo "<div class='grid-event $cls'>
                                            <div class='evt-title'>".htmlspecialchars($evt['title'])."</div>
                                            <div class='grid-actions'>
                                                <a href='index.php?page=schedule&edit={$evt['id']}&view=grid&week=$week_offset'><i class='ri-pencil-line'></i></a>
                                                <a href='index.php?page=schedule&delete={$evt['id']}&view=grid&week=$week_offset' onclick='return confirm(\"Xóa?\")'><i class='ri-delete-bin-line'></i></a>
                                            </div>
                                        </div>";
                                    }
                                }
                            }
                            ?>
                        </td>
                    <?php endforeach; ?>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

<?php else: ?>
    <div class="schedule-grid">
        <?php foreach($days_label_map as $code => $name): ?>
            <div class="day-col">
                <h4><?= $name ?> <span class="col-date"><?= date('d/m', strtotime($week_dates_map[$code])) ?></span></h4>
                <?php if(isset($schedule_data[$code]) && count($schedule_data[$code]) > 0):
                    foreach($schedule_data[$code] as $s): ?>
                        <div class="sch-item <?= ($s['type']=='class') ? 'item-timetable' : 'item-event' ?>">
                            <div class="sch-content">
                                <strong class="sch-title"><?= htmlspecialchars($s['title']) ?></strong>
                                <div class="sch-time">
                                    <?= substr($s['start_time'], 0, 5) ?> - <?= substr($s['end_time'], 0, 5) ?>
                                </div>
                            </div>
                            <div class="sch-actions">
                                <a href="index.php?page=schedule&edit=<?= $s['id'] ?>&view=list&week=<?= $week_offset ?>"><i class="ri-pencil-line"></i></a>
                                <a href="index.php?page=schedule&delete=<?= $s['id'] ?>&view=list&week=<?= $week_offset ?>" onclick="return confirm('Xóa?')"><i class="ri-delete-bin-line"></i></a>
                            </div>
                        </div>
                    <?php endforeach;
                else: ?>
                    <div style="padding: 10px; color: #ccc; font-size: 12px; font-style: italic; text-align: center;">Trống</div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<script>
    // Đóng dropdown khi click ra ngoài
    window.onclick = function(event) {
        if (!event.target.closest('.week-btn') && !event.target.closest('.jump-dropdown')) {
            var dropdowns = document.getElementsByClassName("jump-dropdown");
            for (var i = 0; i < dropdowns.length; i++) {
                dropdowns[i].classList.remove('show');
            }
        }
    }
</script>