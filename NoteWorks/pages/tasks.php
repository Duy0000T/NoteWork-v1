<?php
// BẮT BUỘC: Start Session
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) die("Vui lòng đăng nhập.");

ob_start(); // Bật buffer để chuyển hướng mượt mà

$uid = $_SESSION['user_id'];
date_default_timezone_set('Asia/Ho_Chi_Minh');

// --- 1. HÀM UPLOAD FILE ---
function uploadMultipleToStorage($inputName, $conn, $uid, $taskId, $type = 'general') {
    if (isset($_FILES[$inputName]) && is_array($_FILES[$inputName]['name']) && $_FILES[$inputName]['name'][0] != "") {
        $target_dir = "uploads/";
        if (!file_exists($target_dir)) mkdir($target_dir, 0777, true);

        $count = count($_FILES[$inputName]['name']);
        for ($i = 0; $i < $count; $i++) {
            if ($_FILES[$inputName]['error'][$i] == 0) {
                $ext = pathinfo($_FILES[$inputName]['name'][$i], PATHINFO_EXTENSION);
                $new_name = time() . "_" . uniqid() . "." . $ext;
                $target_file = $target_dir . $new_name;

                if (move_uploaded_file($_FILES[$inputName]['tmp_name'][$i], $target_file)) {
                    $stmt = $conn->prepare("INSERT INTO files (user_id, task_id, filename, filepath, file_type) VALUES (?, ?, ?, ?, ?)");
                    $stmt->bind_param("iisss", $uid, $taskId, $_FILES[$inputName]['name'][$i], $target_file, $type);
                    $stmt->execute();
                }
            }
        }
    }
}

// --- 2. LOGIC MỚI: TOGGLE SUB-TASK & CẬP NHẬT TASK CHA ---
if (isset($_GET['toggle_sub'])) {
    $sid = intval($_GET['toggle_sub']);

    // 1. Lấy thông tin sub-task và parent_id
    $res = $conn->query("SELECT status, parent_id FROM tasks WHERE id=$sid AND user_id=$uid");
    if($res && $res->num_rows > 0) {
        $sub = $res->fetch_assoc();
        $pid = $sub['parent_id'];

        // 2. Đổi trạng thái sub-task
        $new_sub_status = ($sub['status'] == 'pending') ? 'completed' : 'pending';
        $conn->query("UPDATE tasks SET status='$new_sub_status' WHERE id=$sid");

        // 3. Kiểm tra Task Cha (Auto Update Logic)
        if($pid) {
            // Đếm tổng số con
            $total_subs = $conn->query("SELECT COUNT(*) as c FROM tasks WHERE parent_id=$pid")->fetch_assoc()['c'];
            // Đếm số con đã xong
            $done_subs = $conn->query("SELECT COUNT(*) as c FROM tasks WHERE parent_id=$pid AND status='completed'")->fetch_assoc()['c'];

            // Nếu Tổng == Đã xong => Cha xong, ngược lại là Pending
            $parent_status = ($total_subs == $done_subs) ? 'completed' : 'pending';
            $conn->query("UPDATE tasks SET status='$parent_status' WHERE id=$pid");
        }
    }
    header("Location: index.php?page=tasks"); exit();
}

// --- 3. LOGIC MỚI: HOÀN THÀNH TASK ĐỘC LẬP (CÓ FILE) ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['confirm_standalone_complete'])) {
    $tid = intval($_POST['task_id_complete']);

    // Upload file minh chứng (nếu có)
    uploadMultipleToStorage('proof_files', $conn, $uid, $tid, 'proof');

    // Đánh dấu hoàn thành
    $conn->query("UPDATE tasks SET status='completed' WHERE id=$tid AND user_id=$uid");
    header("Location: index.php?page=tasks"); exit();
}

// --- 4. LOGIC: MỞ LẠI TASK ĐỘC LẬP (RE-OPEN) ---
if (isset($_GET['reopen_task'])) {
    $tid = intval($_GET['reopen_task']);
    $conn->query("UPDATE tasks SET status='pending' WHERE id=$tid AND user_id=$uid");
    header("Location: index.php?page=tasks"); exit();
}

// --- 5. CÁC LOGIC CŨ (UPLOAD SUB, SAVE, DELETE...) ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['upload_subtask_file'])) {
    $sub_id = intval($_POST['subtask_id_upload']);
    uploadMultipleToStorage('sub_files', $conn, $uid, $sub_id, 'proof');
    header("Location: index.php?page=tasks"); exit();
}

if (isset($_GET['del_file'])) {
    $fid = intval($_GET['del_file']);
    $check_f = $conn->query("SELECT filepath FROM files WHERE id=$fid AND user_id=$uid");
    if($check_f && $check_f->num_rows > 0) {
        $path = $check_f->fetch_assoc()['filepath'];
        if(file_exists($path)) unlink($path);
        $conn->query("DELETE FROM files WHERE id=$fid");
    }
    if(isset($_GET['return_edit'])) header("Location: index.php?page=tasks&edit=".intval($_GET['return_edit'])."#taskFormBlock");
    else header("Location: index.php?page=tasks");
    exit();
}

if (isset($_GET['delete'])) {
    $del_id = intval($_GET['delete']);
    $files_check = $conn->query("SELECT filepath FROM files WHERE task_id=$del_id OR task_id IN (SELECT id FROM tasks WHERE parent_id=$del_id)");
    if ($files_check) { while ($f = $files_check->fetch_assoc()) { if (file_exists($f['filepath'])) unlink($f['filepath']); } }
    $conn->query("DELETE FROM tasks WHERE id=$del_id AND user_id=$uid");
    header("Location: index.php?page=tasks"); exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_task'])) {
    $title = trim($_POST['title']);
    $due = $_POST['due_date'];
    $cat_id = !empty($_POST['category_id']) ? intval($_POST['category_id']) : NULL;

    // Tạo Category mới
    if (!empty($_POST['new_category_name'])) {
        $new_cat_name = trim($_POST['new_category_name']);
        $new_cat_color = $_POST['new_category_color'] ?? '#6c757d';
        $check = $conn->query("SELECT id FROM categories WHERE user_id=$uid AND name='$new_cat_name'");
        if ($check->num_rows > 0) $cat_id = $check->fetch_assoc()['id'];
        else {
            $stmt = $conn->prepare("INSERT INTO categories (user_id, name, color) VALUES (?, ?, ?)");
            $stmt->bind_param("iss", $uid, $new_cat_name, $new_cat_color);
            if($stmt->execute()) $cat_id = $stmt->insert_id;
        }
    }

    $current_task_id = 0;
    if (isset($_POST['task_id']) && $_POST['task_id'] != '') {
        $current_task_id = intval($_POST['task_id']);
        $stmt = $conn->prepare("UPDATE tasks SET title=?, due_date=?, category_id=? WHERE id=? AND user_id=?");
        $stmt->bind_param("ssiii", $title, $due, $cat_id, $current_task_id, $uid);
        $stmt->execute();
        // Update tên subtask cũ
        if (isset($_POST['existing_subs']) && is_array($_POST['existing_subs'])) {
            foreach ($_POST['existing_subs'] as $sid => $stitle) {
                $stitle = trim($stitle);
                $conn->query("UPDATE tasks SET title='$stitle' WHERE id=$sid AND user_id=$uid");
            }
        }
    } else {
        $stmt = $conn->prepare("INSERT INTO tasks (user_id, title, due_date, status, category_id) VALUES (?, ?, ?, 'pending', ?)");
        $stmt->bind_param("issi", $uid, $title, $due, $cat_id);
        if ($stmt->execute()) $current_task_id = $stmt->insert_id;
    }

    // Insert Subtask Mới
    if (isset($_POST['subtasks']) && is_array($_POST['subtasks'])) {
        foreach ($_POST['subtasks'] as $sub_title) {
            if (trim($sub_title) != "") {
                $sub_title = trim($sub_title);
                // Mặc định subtask mới là pending -> Update lại cha thành pending nếu đang completed
                $conn->query("INSERT INTO tasks (user_id, title, status, parent_id) VALUES ($uid, '$sub_title', 'pending', $current_task_id)");
                $conn->query("UPDATE tasks SET status='pending' WHERE id=$current_task_id");
            }
        }
    }
    uploadMultipleToStorage('attachment_files', $conn, $uid, $current_task_id, 'att');
    header("Location: index.php?page=tasks"); exit();
}

// --- 6. PREPARE DATA ---
$edit_task = null; $edit_subs = []; $edit_files = [];
if (isset($_GET['edit'])) {
    $edit_id = intval($_GET['edit']);
    $edit_task = $conn->query("SELECT * FROM tasks WHERE id=$edit_id AND user_id=$uid")->fetch_assoc();
    if($edit_task) {
        $res_sub = $conn->query("SELECT * FROM tasks WHERE parent_id=$edit_id ORDER BY created_at ASC");
        while($s = $res_sub->fetch_assoc()) $edit_subs[] = $s;
        $res_file = $conn->query("SELECT * FROM files WHERE task_id=$edit_id AND file_type='att'");
        while($f = $res_file->fetch_assoc()) $edit_files[] = $f;
    }
}

// Query List
$filter_status = $_GET['status'] ?? 'all';
$filter_cat = $_GET['cat'] ?? 'all';
$page = isset($_GET['p']) ? max(1, intval($_GET['p'])) : 1;
$limit = 5; $offset = ($page - 1) * $limit;

$categories = [];
$c_res = $conn->query("SELECT * FROM categories WHERE user_id=$uid ORDER BY name ASC");
while($c = $c_res->fetch_assoc()) $categories[] = $c;

$where = " WHERE t.user_id=$uid AND t.parent_id IS NULL";
if ($filter_status == 'pending') $where .= " AND t.status='pending'";
elseif ($filter_status == 'completed') $where .= " AND t.status='completed'";
if ($filter_cat != 'all') $where .= " AND t.category_id = ".intval($filter_cat);

$total = $conn->query("SELECT COUNT(*) as c FROM tasks t $where")->fetch_assoc()['c'];
$total_pages = ceil($total / $limit);

$tasks = $conn->query("SELECT t.*, c.name as cat_name, c.color as cat_color FROM tasks t LEFT JOIN categories c ON t.category_id = c.id $where ORDER BY t.created_at DESC LIMIT $limit OFFSET $offset");
?>

    <div class="card" id="taskFormBlock">
        <div style="font-size: 18px; font-weight: bold; margin-bottom: 15px; color: #1e293b; display: flex; justify-content: space-between;">
            <span><?= $edit_task ? '✏️ Cập Nhật Công Việc' : '➕ Thêm Công Việc Mới' ?></span>
            <?php if($edit_task): ?><a href="index.php?page=tasks" style="font-size: 13px; color: #64748b;">Hủy</a><?php endif; ?>
        </div>

        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="save_task" value="1">
            <input type="hidden" name="task_id" value="<?= $edit_task['id'] ?? '' ?>">

            <div style="display: flex; gap: 10px; margin-bottom: 15px;">
                <input type="text" name="title" class="form-input" placeholder="Tên công việc..." required style="flex-grow: 1;" value="<?= htmlspecialchars($edit_task['title'] ?? '') ?>">
                <input type="date" name="due_date" class="form-input" required style="width: 160px;" value="<?= isset($edit_task['due_date']) ? date('Y-m-d', strtotime($edit_task['due_date'])) : '' ?>">
            </div>

            <div style="display: flex; gap: 10px; margin-bottom: 15px; align-items: center; background: #f8fafc; padding: 12px; border-radius: 8px; border: 1px solid #e2e8f0;">
                <div style="flex: 1;">
                    <label style="font-size: 11px; font-weight: 600; color: #64748b;">DANH MỤC</label>
                    <select name="category_id" class="form-input" id="catSelect" style="margin: 5px 0 0 0;">
                        <option value="">-- Không chọn --</option>
                        <?php foreach($categories as $cat): ?>
                            <option value="<?= $cat['id'] ?>" <?= (isset($edit_task['category_id']) && $edit_task['category_id'] == $cat['id']) ? 'selected' : '' ?>><?= htmlspecialchars($cat['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div style="color: #cbd5e1; font-weight: bold;">HOẶC</div>
                <div style="flex: 1;">
                    <label style="font-size: 11px; font-weight: 600; color: #64748b;">TẠO MỚI</label>
                    <div style="display: flex; gap: 5px; margin-top: 5px;">
                        <input type="text" name="new_category_name" class="form-input" placeholder="Nhập tên..." id="newCatInput" style="margin: 0;">
                        <input type="color" name="new_category_color" value="#3b82f6" style="height: 42px; width: 42px; padding: 2px; border: 1px solid #cbd5e1; border-radius: 6px;">
                    </div>
                </div>
            </div>

            <div style="margin-bottom: 20px;">
                <label style="font-size: 12px; font-weight: 600; color: #475569;">Checklist công việc con:</label>
                <?php if(!empty($edit_subs)): ?>
                    <div style="margin-bottom: 10px; padding: 10px; background: #f1f5f9; border-radius: 6px;">
                        <?php foreach($edit_subs as $es): ?>
                            <div class="edit-sub-row">
                                <i class="ri-git-commit-line" style="color:#94a3b8; margin-right: 5px;"></i>
                                <input type="text" name="existing_subs[<?= $es['id'] ?>]" value="<?= htmlspecialchars($es['title']) ?>">
                                <a href="index.php?page=tasks&del_file=<?= $es['id'] ?>&delete=<?= $es['id'] ?>" class="del-sub-btn" style="margin-left:5px; color:red;" onclick="return confirm('Xóa?')">&times;</a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <div id="subtask-container"></div>
                <button type="button" onclick="addSubtaskField()" style="margin-top: 5px; background: none; border: 1px dashed #94a3b8; padding: 6px 12px; border-radius: 6px; color: #64748b; cursor: pointer; font-size: 12px;">+ Thêm dòng mới</button>
            </div>

            <div style="border-top: 1px solid #f1f5f9; padding-top: 15px;">
                <?php if(!empty($edit_files)): ?>
                    <div style="margin-bottom: 10px;">
                        <?php foreach($edit_files as $ef): ?>
                            <span class="file-chip">
                             <a href="<?= $ef['filepath'] ?>" download="<?= htmlspecialchars($ef['filename']) ?>" style="color:inherit; text-decoration:none;">
                                <?= htmlspecialchars($ef['filename']) ?>
                            </a>
                            <a href="index.php?page=tasks&del_file=<?= $ef['id'] ?>&return_edit=<?= $edit_task['id'] ?>" class="file-del" onclick="return confirm('Xóa?')">&times;</a>
                        </span>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <label for="file-upload" style="cursor: pointer; color: #64748b; font-size: 13px; display: flex; align-items: center; gap: 5px;">
                        <i class="ri-attachment-line" style="font-size: 16px;"></i> <?= $edit_task ? 'Thêm file' : 'Đính kèm file' ?>
                        <input id="file-upload" type="file" name="attachment_files[]" multiple style="display: none;">
                    </label>
                    <button type="submit" class="btn-primary"><?= $edit_task ? 'Cập Nhật' : 'Lưu Công Việc' ?></button>
                </div>
            </div>
        </form>
    </div>

    <form method="GET" class="task-toolbar" style="margin-top: 25px; display: flex; gap: 10px; align-items: center;">
        <input type="hidden" name="page" value="tasks">
        <select name="cat" class="filter-select" onchange="this.form.submit()" style="padding: 8px; border-radius: 6px; border: 1px solid #cbd5e1;">
            <option value="all">-- Tất cả danh mục --</option>
            <?php foreach($categories as $cat): ?>
                <option value="<?= $cat['id'] ?>" <?= ($filter_cat == $cat['id']) ? 'selected' : '' ?>><?= htmlspecialchars($cat['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <select name="status" class="filter-select" onchange="this.form.submit()" style="padding: 8px; border-radius: 6px; border: 1px solid #cbd5e1;">
            <option value="all" <?= ($filter_status=='all')?'selected':'' ?>>Tất cả trạng thái</option>
            <option value="pending" <?= ($filter_status=='pending')?'selected':'' ?>>Đang làm</option>
            <option value="completed" <?= ($filter_status=='completed')?'selected':'' ?>>Đã xong</option>
        </select>
    </form>

    <div class="card" style="padding: 0; margin-top: 15px; overflow: hidden;">
        <table class="data-table" style="width: 100%; border-collapse: collapse;">
            <thead style="background: #f8fafc; border-bottom: 2px solid #e2e8f0;">
            <tr>
                <th style="padding: 12px 15px; text-align: left; width: 45%; color: #475569;">Nội dung</th>
                <th style="padding: 12px 15px; text-align: left; color: #475569;">Tài liệu chung</th>
                <th style="padding: 12px 15px; text-align: left; color: #475569;">Danh mục</th>
                <th style="padding: 12px 15px; text-align: right; color: #475569;">Hành động</th>
            </tr>
            </thead>
            <tbody>
            <?php if ($tasks && $tasks->num_rows > 0): ?>
                <?php while($row = $tasks->fetch_assoc()):
                    $tid = $row['id'];
                    // Lấy Sub-tasks trước để check logic
                    $subs = $conn->query("SELECT * FROM tasks WHERE parent_id = $tid ORDER BY created_at ASC");
                    $has_sub = ($subs->num_rows > 0);
                    ?>
                    <tr style="border-bottom: 1px solid #f1f5f9; vertical-align: top;">
                        <td style="padding: 15px;">
                            <div style="font-weight: 700; font-size: 15px; color: #1e293b; margin-bottom: 4px; display:flex; align-items:center; gap:8px;">
                                <i class="<?= $row['status']=='completed' ? 'ri-checkbox-circle-fill' : 'ri-time-line' ?>"
                                   style="color: <?= $row['status']=='completed' ? '#16a34a' : '#3b82f6' ?>; font-size: 18px;"></i>
                                <span style="<?= $row['status']=='completed' ? 'text-decoration:line-through; color:#94a3b8;' : '' ?>">
                                <?= htmlspecialchars($row['title']) ?>
                            </span>
                            </div>
                            <div style="font-size: 11px; color: #64748b; margin-left: 26px;">Hạn: <?= date('d/m/Y', strtotime($row['due_date'])) ?></div>

                            <?php if($has_sub): ?>
                                <div class="subtask-wrapper">
                                    <?php while($sub = $subs->fetch_assoc()): ?>
                                        <div class="subtask-card <?= $sub['status']=='completed'?'completed':'' ?>">
                                            <div style="flex-grow: 1;">
                                                <div style="display: flex; align-items: center; gap: 6px;">
                                                    <a href="index.php?page=tasks&toggle_sub=<?= $sub['id'] ?>" style="text-decoration: none; color: inherit;">
                                                        <i class="<?= $sub['status']=='completed' ? 'ri-checkbox-circle-fill' : 'ri-checkbox-blank-circle-line' ?>"
                                                           style="color: <?= $sub['status']=='completed' ? '#16a34a' : '#cbd5e1' ?>"></i>
                                                    </a>
                                                    <span><?= htmlspecialchars($sub['title']) ?></span>
                                                </div>
                                                <?php
                                                $sid = $sub['id']; $sfiles = $conn->query("SELECT * FROM files WHERE task_id=$sid AND file_type='proof'");
                                                if($sfiles->num_rows > 0): echo '<div style="margin-left: 20px;">';
                                                    while($sf = $sfiles->fetch_assoc()): ?>
                                                        <span class="file-chip" style="font-size:10px; background:#f0fdf4; color:#166534; border-color:#bbf7d0;">
                                                        <a href="<?= $sf['filepath'] ?>" download="<?= htmlspecialchars($sf['filename']) ?>" style="text-decoration:none; color:inherit;"><i class="ri-file-text-line"></i> <?= htmlspecialchars($sf['filename']) ?></a>
                                                        <a href="index.php?page=tasks&del_file=<?= $sf['id'] ?>" class="file-del" onclick="return confirm('Xóa?')">&times;</a>
                                                    </span>
                                                    <?php endwhile; echo '</div>'; endif; ?>
                                            </div>
                                            <i class="ri-upload-cloud-2-line" onclick="openSubUpload(<?= $sub['id'] ?>)" style="cursor: pointer; color: #64748b; font-size: 14px; padding: 4px;" title="Up minh chứng"></i>
                                        </div>
                                    <?php endwhile; ?>
                                </div>
                            <?php endif; ?>
                        </td>

                        <td style="padding: 15px;">
                            <?php $fs = $conn->query("SELECT * FROM files WHERE task_id=$tid AND file_type='att'");
                            if($fs->num_rows>0): while($f=$fs->fetch_assoc()): ?>
                                <span class="file-chip">
                                <a href="<?= $f['filepath'] ?>" download="<?= htmlspecialchars($f['filename']) ?>" style="text-decoration:none; color:inherit;"><i class="ri-file-download-line"></i> <?= htmlspecialchars($f['filename']) ?></a>
                                <a href="index.php?page=tasks&del_file=<?= $f['id'] ?>" class="file-del" onclick="return confirm('Xóa?')">&times;</a>
                            </span>
                            <?php endwhile; else: echo '<span style="color:#cbd5e1; font-size:12px;">-- Trống --</span>'; endif; ?>
                        </td>

                        <td style="padding: 15px;"><?php if($row['category_id']): ?><span class="cat-badge" style="background-color: <?= htmlspecialchars($row['cat_color']) ?>"><?= htmlspecialchars($row['cat_name']) ?></span><?php else: echo '<span style="color:#94a3b8; font-size:12px;">Chung</span>'; endif; ?></td>

                        <td style="padding: 15px; text-align: right;">
                            <div style="display: flex; justify-content: flex-end;">
                                <?php if ($has_sub): ?>
                                    <button class="action-btn btn-grey" title="Hoàn thành tất cả việc nhỏ để xong việc này" disabled><i class="ri-checkbox-indeterminate-line"></i></button>
                                <?php else: ?>
                                    <?php if($row['status'] == 'pending'): ?>
                                        <button onclick="openCompleteModal(<?= $row['id'] ?>)" class="action-btn btn-green" title="Xác nhận hoàn thành"><i class="ri-check-line"></i></button>
                                    <?php else: ?>
                                        <a href="index.php?page=tasks&reopen_task=<?= $row['id'] ?>" class="action-btn btn-green" title="Mở lại công việc"><i class="ri-refresh-line"></i></a>
                                    <?php endif; ?>
                                <?php endif; ?>

                                <a href="index.php?page=tasks&edit=<?= $row['id'] ?>#taskFormBlock" class="action-btn btn-blue" title="Sửa"><i class="ri-pencil-line"></i></a>
                                <a href="index.php?page=tasks&delete=<?= $row['id'] ?>" onclick="return confirm('Xóa vĩnh viễn?')" class="action-btn btn-red" title="Xóa"><i class="ri-delete-bin-line"></i></a>
                            </div>
                        </td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr><td colspan="4" class="text-center" style="padding: 40px; color: #94a3b8;">Chưa có dữ liệu.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

<?php if ($total_pages > 1): ?>
    <div style="display: flex; justify-content: center; gap: 5px; margin-top: 20px;">
        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
            <a href="index.php?page=tasks&p=<?= $i ?>&status=<?= $filter_status ?>&cat=<?= $filter_cat ?>" style="padding: 6px 12px; border: 1px solid #e2e8f0; border-radius: 6px; text-decoration: none; <?= ($i == $page) ? 'background:#3b82f6; color:white;' : 'background:white; color:#64748b;' ?>"><?= $i ?></a>
        <?php endfor; ?>
    </div>
<?php endif; ?>

    <div id="subUploadModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 9999; align-items: center; justify-content: center;">
        <div style="background: white; padding: 25px; border-radius: 12px; width: 400px; box-shadow: 0 10px 25px rgba(0,0,0,0.2);">
            <h3 style="margin-top: 0; font-size: 16px; color: #1e293b;">Nộp kết quả (Sub-task)</h3>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="upload_subtask_file" value="1">
                <input type="hidden" name="subtask_id_upload" id="modalSubId">
                <div style="background: #f8fafc; border: 2px dashed #cbd5e1; border-radius: 8px; padding: 20px; text-align: center; margin-bottom: 20px;">
                    <input type="file" name="sub_files[]" multiple class="form-input" style="border:none; width: 100%;">
                </div>
                <div style="display: flex; justify-content: flex-end; gap: 10px;">
                    <button type="button" onclick="document.getElementById('subUploadModal').style.display='none'" style="padding: 8px 15px; border: 1px solid #e2e8f0; background: white; border-radius: 6px; cursor: pointer;">Hủy</button>
                    <button type="submit" class="btn-primary" style="padding: 8px 20px;">Tải lên</button>
                </div>
            </form>
        </div>
    </div>

    <div id="completeTaskModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 9999; align-items: center; justify-content: center;">
        <div style="background: white; padding: 25px; border-radius: 12px; width: 400px; box-shadow: 0 10px 25px rgba(0,0,0,0.2);">
            <h3 style="margin-top: 0; font-size: 16px; color: #1e293b;">Xác nhận Hoàn thành</h3>
            <p style="font-size: 13px; color: #64748b; margin-bottom: 15px;">Bạn có muốn nộp thêm minh chứng trước khi đóng công việc này không?</p>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="confirm_standalone_complete" value="1">
                <input type="hidden" name="task_id_complete" id="modalTaskId">
                <div style="background: #f8fafc; border: 2px dashed #cbd5e1; border-radius: 8px; padding: 20px; text-align: center; margin-bottom: 20px;">
                    <input type="file" name="proof_files[]" multiple class="form-input" style="border:none; width: 100%;">
                </div>
                <div style="display: flex; justify-content: flex-end; gap: 10px;">
                    <button type="button" onclick="document.getElementById('completeTaskModal').style.display='none'" style="padding: 8px 15px; border: 1px solid #e2e8f0; background: white; border-radius: 6px; cursor: pointer;">Hủy</button>
                    <button type="submit" class="btn-primary" style="background: #16a34a; border-color: #16a34a; padding: 8px 20px;">Xác nhận Xong</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        document.getElementById('catSelect').addEventListener('change', function() {
            let isSelected = this.value !== "";
            let inputNew = document.getElementById('newCatInput');
            if(isSelected) { inputNew.disabled = true; inputNew.value = ""; inputNew.style.backgroundColor = "#f1f5f9"; }
            else { inputNew.disabled = false; inputNew.style.backgroundColor = "#fff"; }
        });

        function addSubtaskField() {
            const container = document.getElementById('subtask-container');
            const div = document.createElement('div');
            div.style.marginBottom = '5px'; div.style.display = 'flex'; div.style.alignItems = 'center';
            div.innerHTML = `<i class="ri-git-commit-line" style="color:#cbd5e1; margin-right:8px;"></i><input type="text" name="subtasks[]" class="form-input" placeholder="Việc nhỏ mới..." style="font-size: 13px; padding: 8px; flex-grow:1;"><button type="button" onclick="this.parentElement.remove()" style="border:none; background:none; color:#ef4444; cursor:pointer; margin-left:5px; font-size:16px;">&times;</button>`;
            container.appendChild(div);
            div.querySelector('input').focus();
        }

        function openSubUpload(id) {
            document.getElementById('modalSubId').value = id;
            document.getElementById('subUploadModal').style.display = 'flex';
        }

        function openCompleteModal(id) {
            document.getElementById('modalTaskId').value = id;
            document.getElementById('completeTaskModal').style.display = 'flex';
        }

        window.onclick = function(event) {
            if (event.target == document.getElementById('subUploadModal')) document.getElementById('subUploadModal').style.display = "none";
            if (event.target == document.getElementById('completeTaskModal')) document.getElementById('completeTaskModal').style.display = "none";
        }
    </script>
<?php ob_end_flush(); ?>