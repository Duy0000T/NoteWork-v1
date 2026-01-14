<?php
//
$uid = $_SESSION['user_id'];
if (!file_exists('uploads')) mkdir('uploads', 0777, true);

// --- 1. XỬ LÝ XÓA FILE (Cho cả 2 tab) ---
if (isset($_GET['del_id'])) {
    $del_id = intval($_GET['del_id']);

    // Kiểm tra quyền sở hữu file
    $check = $conn->query("SELECT * FROM files WHERE id = $del_id AND user_id = $uid");

    if ($check->num_rows > 0) {
        $file_data = $check->fetch_assoc();
        $file_path = $file_data['filepath'];

        // Xóa file vật lý
        if (file_exists($file_path)) {
            unlink($file_path);
        }

        // Xóa trong Database
        $conn->query("DELETE FROM files WHERE id = $del_id");

        // Refresh trang
        header("Location: index.php?page=storage");
        exit();
    } else {
        echo "<script>alert('Bạn không có quyền xóa file này!');</script>";
    }
}

// --- 2. XỬ LÝ UPLOAD (Cho Tab Kho lưu trữ chung) ---
if (isset($_POST['upload'])) {
    $name = $_FILES['file']['name'];
    $target = "uploads/" . time() . "_" . basename($name);

    if (move_uploaded_file($_FILES['file']['tmp_name'], $target)) {
        // Mặc định file_type là 'general'
        $stmt = $conn->prepare("INSERT INTO files (user_id, filename, filepath, file_type) VALUES (?, ?, ?, 'general')");
        $stmt->bind_param("iss", $uid, $name, $target);
        $stmt->execute();
        header("Location: index.php?page=storage"); exit();
    }
}
?>

<div class="header-welcome"><h1>Quản lý Tài liệu</h1></div>

<div class="tabs">
    <button class="tab-btn active" onclick="openTab(event, 'tab-tasks')">
        <i class="ri-folder-shared-line"></i> Tài liệu Công việc
    </button>
    <button class="tab-btn" onclick="openTab(event, 'tab-general')">
        <i class="ri-hard-drive-2-line"></i> Kho Lưu trữ
    </button>
</div>

<div id="tab-tasks" class="tab-content active">

    <div class="split-layout">

        <div class="split-col">
            <div class="col-header header-blue">
                <i class="ri-attachment-line"></i> Đính kèm (Đề bài/Hướng dẫn)
            </div>

            <?php
            $sql_att = "SELECT f.*, t.title as task_name 
                        FROM files f 
                        LEFT JOIN tasks t ON f.task_id = t.id 
                        WHERE f.user_id = $uid AND f.file_type = 'att' 
                        ORDER BY f.uploaded_at DESC";
            $att_files = $conn->query($sql_att);

            if ($att_files->num_rows > 0):
                ?>
                <table class="file-table">
                    <thead>
                    <tr>
                        <th>Tên File</th>
                        <th>Công việc</th>
                        <th class="action-col">Hành động</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php while($f = $att_files->fetch_assoc()): ?>
                        <tr>
                            <td>
                                <div style="font-weight:600; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; max-width: 140px;" title="<?= htmlspecialchars($f['filename']) ?>">
                                    <i class="ri-file-text-line"></i> <?= htmlspecialchars($f['filename']) ?>
                                </div>
                                <small style="color:#888"><?= date('d/m', strtotime($f['uploaded_at'])) ?></small>
                            </td>
                            <td>
                            <span style="font-size:12px; color:#555">
                                <?= $f['task_name'] ? htmlspecialchars($f['task_name']) : '<span style="color:#bbb">Đã xóa</span>' ?>
                            </span>
                            </td>
                            <td class="action-col">
                                <div class="btn-group">
                                    <a href="<?= $f['filepath'] ?>" download class="btn-icon btn-download" title="Tải về"><i class="ri-download-line"></i></a>
                                    <a href="index.php?page=storage&del_id=<?= $f['id'] ?>" class="btn-icon btn-delete" title="Xóa" onclick="return confirm('Bạn chắc chắn muốn xóa file đính kèm này?');"><i class="ri-delete-bin-line"></i></a>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div style="padding: 30px; text-align: center; color: #888;">
                    <i class="ri-file-search-line" style="font-size: 30px; margin-bottom: 10px; display:block;"></i>
                    Chưa có tài liệu đính kèm nào.
                </div>
            <?php endif; ?>
        </div>

        <div class="split-col">
            <div class="col-header header-green">
                <i class="ri-checkbox-circle-line"></i> Minh chứng (Báo cáo/Kết quả)
            </div>

            <?php
            $sql_proof = "SELECT f.*, t.title as task_name 
                          FROM files f 
                          LEFT JOIN tasks t ON f.task_id = t.id 
                          WHERE f.user_id = $uid AND f.file_type = 'proof' 
                          ORDER BY f.uploaded_at DESC";
            $proof_files = $conn->query($sql_proof);

            if ($proof_files->num_rows > 0):
                ?>
                <table class="file-table">
                    <thead>
                    <tr>
                        <th>Tên File</th>
                        <th>Công việc</th>
                        <th class="action-col">Hành động</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php while($f = $proof_files->fetch_assoc()): ?>
                        <tr>
                            <td>
                                <div style="font-weight:600; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; max-width: 140px;" title="<?= htmlspecialchars($f['filename']) ?>">
                                    <i class="ri-file-shred-line"></i> <?= htmlspecialchars($f['filename']) ?>
                                </div>
                                <small style="color:#888"><?= date('d/m', strtotime($f['uploaded_at'])) ?></small>
                            </td>
                            <td>
                            <span style="font-size:12px; color:#555">
                                <?= $f['task_name'] ? htmlspecialchars($f['task_name']) : '<span style="color:#bbb">Đã xóa</span>' ?>
                            </span>
                            </td>
                            <td class="action-col">
                                <div class="btn-group">
                                    <a href="<?= $f['filepath'] ?>" download class="btn-icon btn-download" title="Tải về"><i class="ri-download-line"></i></a>
                                    <a href="index.php?page=storage&del_id=<?= $f['id'] ?>" class="btn-icon btn-delete" title="Xóa" onclick="return confirm('Bạn chắc chắn muốn xóa minh chứng này?');"><i class="ri-delete-bin-line"></i></a>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div style="padding: 30px; text-align: center; color: #888;">
                    <i class="ri-folder-open-line" style="font-size: 30px; margin-bottom: 10px; display:block;"></i>
                    Chưa có minh chứng kết quả nào.
                </div>
            <?php endif; ?>
        </div>

    </div> </div>

<div id="tab-general" class="tab-content">
    <div class="card" style="margin-bottom: 20px; padding: 20px;">
        <h3 style="margin-bottom: 15px; font-size: 16px;">Tải lên tài liệu cá nhân</h3>
        <form method="POST" enctype="multipart/form-data" class="flex-form" style="display:flex; gap:10px;">
            <input type="file" name="file" required class="form-input grow" style="flex:1; padding: 10px; border:1px solid #ddd; border-radius:8px;">
            <button type="submit" name="upload" class="btn-primary" style="padding: 10px 20px; background: var(--primary); color:white; border:none; border-radius:8px; cursor:pointer;">
                <i class="ri-upload-cloud-2-line"></i> Tải lên
            </button>
        </form>
    </div>

    <div class="file-grid">
        <?php
        $gen_files = $conn->query("SELECT * FROM files WHERE user_id=$uid AND file_type='general' ORDER BY uploaded_at DESC");
        while($f = $gen_files->fetch_assoc()): ?>
            <div class="file-card">
                <div class="file-icon"><i class="ri-folder-3-fill"></i></div>
                <div class="file-name" title="<?= htmlspecialchars($f['filename']) ?>"><?= htmlspecialchars($f['filename']) ?></div>
                <div style="font-size: 12px; color: #888;">
                    <?= date('d/m/Y', strtotime($f['uploaded_at'])) ?>
                </div>

                <div class="card-actions">
                    <a href="<?= $f['filepath'] ?>" download class="btn-icon btn-download" title="Tải về"><i class="ri-download-line"></i></a>
                    <a href="index.php?page=storage&del_id=<?= $f['id'] ?>" class="btn-icon btn-delete" title="Xóa" onclick="return confirm('Xóa tài liệu này khỏi kho lưu trữ?');"><i class="ri-delete-bin-line"></i></a>
                </div>
            </div>
        <?php endwhile; ?>
    </div>
</div>

<script>
    function openTab(evt, tabName) {
        var i, tabcontent, tablinks;
        // Ẩn nội dung tất cả các tab
        tabcontent = document.getElementsByClassName("tab-content");
        for (i = 0; i < tabcontent.length; i++) {
            tabcontent[i].classList.remove("active");
        }
        // Bỏ active ở các nút
        tablinks = document.getElementsByClassName("tab-btn");
        for (i = 0; i < tablinks.length; i++) {
            tablinks[i].classList.remove("active");
        }
        // Hiển thị tab được chọn
        document.getElementById(tabName).classList.add("active");
        evt.currentTarget.classList.add("active");
    }
</script>