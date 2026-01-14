<?php
// assets/pages/dashboard.php
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$uid = $_SESSION['user_id'];
$username = $_SESSION['username'] ?? 'Bạn';
date_default_timezone_set('Asia/Ho_Chi_Minh');
$today_date = date('Y-m-d');

// 1. CẤU HÌNH KHUNG GIỜ TIẾT HỌC
$tiet_config = [
        1 => ['start' => '07:00', 'end' => '07:50'], 2 => ['start' => '07:50', 'end' => '08:40'],
        3 => ['start' => '08:40', 'end' => '09:35'], 4 => ['start' => '09:35', 'end' => '10:25'],
        5 => ['start' => '10:25', 'end' => '11:15'], 6 => ['start' => '13:00', 'end' => '13:50'],
        7 => ['start' => '13:50', 'end' => '14:40'], 8 => ['start' => '14:40', 'end' => '15:35'],
        9 => ['start' => '15:35', 'end' => '16:25'], 10=> ['start' => '16:25', 'end' => '17:15'],
        11=> ['start' => '17:30', 'end' => '18:15'], 12=> ['start' => '18:15', 'end' => '19:00'],
        13=> ['start' => '19:00', 'end' => '19:45'], 14=> ['start' => '19:45', 'end' => '20:30'],
        15=> ['start' => '20:30', 'end' => '21:15']
];

$monday_date = date('Y-m-d', strtotime('monday this week'));
$sunday_date = date('Y-m-d', strtotime('sunday this week'));

// 2. TRUY VẤN DỮ LIỆU
// Thời khóa biểu
$sql_grid = "SELECT title as subject, event_date, start_time, end_time FROM schedule 
             WHERE user_id = $uid AND type = 'class' 
             AND event_date BETWEEN '$monday_date' AND '$sunday_date'";
$result_grid = $conn->query($sql_grid);
$grid_data = [];
if ($result_grid) {
    while ($row = $result_grid->fetch_assoc()) {
        $d_num = date('N', strtotime($row['event_date'])) + 1;
        $s_time = substr($row['start_time'], 0, 5);
        $e_time = substr($row['end_time'], 0, 5);
        $start_p = 0;
        foreach ($tiet_config as $tiet => $cfg) {
            if ($s_time >= $cfg['start'] && $s_time < $cfg['end']) { $start_p = $tiet; break; }
        }
        if ($start_p > 0) {
            $end_p = $start_p;
            foreach ($tiet_config as $tiet => $cfg) {
                if ($tiet < $start_p) continue;
                if ($e_time <= $cfg['end']) { $end_p = $tiet; break; }
            }
            for ($i = 0; $i <= ($end_p - $start_p); $i++) {
                $current_p = $start_p + $i;
                if ($current_p <= 15) $grid_data[$current_p][$d_num] = $row['subject'];
            }
        }
    }
}

// Sự kiện
$event_res = $conn->query("SELECT *, title as subject FROM schedule WHERE user_id = $uid AND type = 'event' AND event_date >= '$today_date' ORDER BY event_date ASC, start_time ASC");
$count_today_events = $conn->query("SELECT COUNT(*) as c FROM schedule WHERE user_id=$uid AND type='event' AND event_date='$today_date'")->fetch_assoc()['c'];

// Thống kê & Công việc
$total_tasks = $conn->query("SELECT COUNT(*) as c FROM tasks WHERE user_id=$uid AND parent_id IS NULL")->fetch_assoc()['c'];
$completed_tasks = $conn->query("SELECT COUNT(*) as c FROM tasks WHERE user_id=$uid AND status='completed' AND parent_id IS NULL")->fetch_assoc()['c'];
$progress_percent = ($total_tasks > 0) ? round(($completed_tasks / $total_tasks) * 100) : 0;
$total_files = $conn->query("SELECT COUNT(*) as c FROM files WHERE user_id=$uid")->fetch_assoc()['c'];
$urgent_tasks = $conn->query("SELECT * FROM tasks WHERE user_id=$uid AND status='pending' AND parent_id IS NULL ORDER BY due_date ASC");
?>
<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
    <div>
        <h1 style="font-size: 26px; font-weight: 800; color: var(--text); margin: 0;">Dashboard</h1>
        <p style="color: var(--sub); margin-top: 4px;">Chào mừng trở lại, <b><?= htmlspecialchars($username) ?></b>! ✨</p>
    </div>
    <div style="text-align: right;">
        <div style="font-size: 28px; font-weight: 800; color: var(--primary); letter-spacing: -1px;"><?= date('H:i') ?></div>
        <div style="font-size: 12px; color: var(--sub); font-weight: 600;">
            <?= (['Mon'=>'Thứ Hai','Tue'=>'Thứ Ba','Wed'=>'Thứ Tư','Thu'=>'Thứ Năm','Fri'=>'Thứ Sáu','Sat'=>'Thứ Bảy','Sun'=>'Chủ Nhật'])[date('D')] ?>, <?= date('d/m/Y') ?>
        </div>
    </div>
</div>

<div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; margin-bottom: 25px;">
    <div class="stat-card-mini">
        <div class="stat-icon" style="background: #eef2ff; color: #6366f1;"><i class="ri-pie-chart-line"></i></div>
        <div><div style="font-size: 11px; color: var(--sub);">Tiến độ</div><div style="font-weight: 800;"><?= $progress_percent ?>%</div></div>
    </div>
    <div class="stat-card-mini">
        <div class="stat-icon" style="background: #fff7ed; color: #f97316;"><i class="ri-calendar-event-line"></i></div>
        <div><div style="font-size: 11px; color: var(--sub);">Sự kiện nay</div><div style="font-weight: 800;"><?= $count_today_events ?></div></div>
    </div>
    <div class="stat-card-mini">
        <div class="stat-icon" style="background: #f0fdf4; color: #22c55e;"><i class="ri-task-line"></i></div>
        <div><div style="font-size: 11px; color: var(--sub);">Công việc</div><div style="font-weight: 800;"><?= $total_tasks ?></div></div>
    </div>
    <div class="stat-card-mini">
        <div class="stat-icon" style="background: #fef2f2; color: #ef4444;"><i class="ri-file-info-line"></i></div>
        <div><div style="font-size: 11px; color: var(--sub);">Tài liệu</div><div style="font-weight: 800;"><?= $total_files ?></div></div>
    </div>
</div>

<div style="display: grid; grid-template-columns: 2fr 1fr; gap: 20px;">
    <div class="left-col">
        <div class="card">
            <div class="section-title" style="color: #ea580c;"><i class="ri-notification-3-line"></i> Lịch trình sắp tới</div>
            <div class="event-scroll-box">
                <?php if($event_res && $event_res->num_rows > 0): ?>
                    <?php while($evt = $event_res->fetch_assoc()):
                        $is_today = ($evt['event_date'] == $today_date);
                        ?>
                        <div style="display: flex; align-items: center; gap: 12px; padding: 12px; background: <?= $is_today ? '#fff7ed' : '#f8fafc' ?>; border-radius: 12px; border: 1px solid <?= $is_today ? '#fed7aa' : '#e2e8f0' ?>; margin-bottom: 10px;">
                            <div style="text-align: center; min-width: 55px; border-right: 1px solid #cbd5e1; padding-right: 10px;">
                                <div style="font-size: 10px; color: var(--sub);"><?= date('d/m', strtotime($evt['event_date'])) ?></div>
                                <div style="font-weight: 800;"><?= substr($evt['start_time'], 0, 5) ?></div>
                            </div>
                            <div style="flex: 1;">
                                <div style="font-size: 14px; font-weight: 700; color: var(--text);"><?= htmlspecialchars($evt['subject']) ?></div>
                                <div style="font-size: 11px; color: var(--sub);"><i class="ri-map-pin-2-line"></i> <?= $evt['location'] ?: 'Chưa cập nhật' ?></div>
                            </div>
                            <?php if($is_today): ?><span style="background: #ea580c; color: #fff; font-size: 9px; padding: 2px 8px; border-radius: 20px; font-weight: 700;">NAY</span><?php endif; ?>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <p style="text-align: center; color: var(--sub); padding: 20px; font-size: 13px;">Không có sự kiện sắp tới.</p>
                <?php endif; ?>
            </div>
        </div>

        <div class="card">
            <div class="section-title"><i class="ri-table-2"></i> Thời khóa biểu tuần</div>
            <div class="tkb-wrapper">
                <div class="tkb-grid">
                    <div class="tkb-head">Tiết</div>
                    <?php
                    $days = [2=>'T2', 3=>'T3', 4=>'T4', 5=>'T5', 6=>'T6', 7=>'T7', 8=>'CN'];
                    foreach($days as $num => $label): ?>
                        <div class="tkb-head"><?= $label ?></div>
                    <?php endforeach; ?>

                    <div class="tkb-sep">BUỔI SÁNG</div>
                    <?php for($i=1; $i<=5; $i++): ?>
                        <div class="tkb-time"><?= $i ?></div>
                        <?php for($d=2; $d<=8; $d++): ?>
                            <div class="tkb-cell">
                                <?php if(isset($grid_data[$i][$d])): ?>
                                    <div class="tkb-subject"><?= htmlspecialchars($grid_data[$i][$d]) ?></div>
                                <?php endif; ?>
                            </div>
                        <?php endfor; ?>
                    <?php endfor; ?>

                    <div class="tkb-sep">BUỔI CHIỀU</div>
                    <?php for($i=6; $i<=10; $i++): ?>
                        <div class="tkb-time"><?= $i ?></div>
                        <?php for($d=2; $d<=8; $d++): ?>
                            <div class="tkb-cell">
                                <?php if(isset($grid_data[$i][$d])): ?>
                                    <div class="tkb-subject"><?= htmlspecialchars($grid_data[$i][$d]) ?></div>
                                <?php endif; ?>
                            </div>
                        <?php endfor; ?>
                    <?php endfor; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="right-col">
        <div class="card">
            <div class="section-title"><i class="ri-pie-chart-line"></i> Tiến độ mục tiêu</div>
            <div style="display: flex; justify-content: space-between; font-size: 14px; font-weight: 800; margin-bottom: 5px;">
                <span>Hoàn thành</span>
                <span style="color: var(--primary);"><?= $progress_percent ?>%</span>
            </div>
            <div class="progress-outer"><div class="progress-inner" style="width: <?= $progress_percent ?>%"></div></div>
            <div style="margin-top: 10px; font-size: 11px; color: var(--sub); text-align: center;">
                Đã xong <b><?= $completed_tasks ?></b> / <b><?= $total_tasks ?></b> công việc.
            </div>
        </div>

        <div class="card">
            <div class="section-title"><i class="ri-list-check"></i> Việc cần làm gấp</div>
            <div style="max-height: 250px; overflow-y: auto;">
                <?php if($urgent_tasks->num_rows > 0): ?>
                    <?php while($t = $urgent_tasks->fetch_assoc()): ?>
                        <div style="padding: 10px 0; border-bottom: 1px solid #f8fafc;">
                            <div style="font-size: 13px; font-weight: 700; color: var(--text);"><?= htmlspecialchars($t['title']) ?></div>
                            <div style="font-size: 10px; color: #ef4444; margin-top: 2px;"><i class="ri-alarm-warning-line"></i> <?= date('d/m H:i', strtotime($t['due_date'])) ?></div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <p style="text-align: center; color: var(--sub); font-size: 12px; padding: 10px;">Bạn đã hoàn thành hết việc!</p>
                <?php endif; ?>
            </div>
        </div>

        <div class="card" style="background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%); color: white; border: none; text-align: center; padding: 25px 20px;">
            <i class="ri-double-quotes-l" style="font-size: 24px; opacity: 0.5;"></i>
            <p style="margin: 15px 0; font-size: 13px; line-height: 1.6; font-style: italic; font-weight: 500;">
                "Thành công không phải là chìa khóa mở cánh cửa hạnh phúc. Hạnh phúc là chìa khóa dẫn tới thành công."
            </p>
            <div style="font-size: 11px; opacity: 0.8; font-weight: 700; text-transform: uppercase;">- Albert Schweitzer -</div>
        </div>
    </div>
</div>