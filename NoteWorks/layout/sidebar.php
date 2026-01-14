<?php $p = $_GET['page'] ?? 'dashboard'; ?>
<div class="sidebar">
    <div class="brand">
        <i class="ri-sticky-note-fill"></i>
        <h2>NoteWorks</h2>
    </div>
    <nav class="menu">
        <a href="index.php?page=dashboard" class="<?= $p=='dashboard'?'active':'' ?>">
            <i class="ri-dashboard-line"></i> Tổng quan
        </a>
        <a href="index.php?page=tasks" class="<?= $p=='tasks'?'active':'' ?>">
            <i class="ri-task-line"></i> Công việc
        </a>
        <a href="index.php?page=schedule" class="<?= $p=='schedule'?'active':'' ?>">
            <i class="ri-calendar-todo-line"></i> Lịch biểu
        </a>
        <a href="index.php?page=storage" class="<?= $p=='storage'?'active':'' ?>">
            <i class="ri-folder-3-line"></i> Tài liệu
        </a>
    </nav>
    <div class="logout-box">
        <a href="index.php?page=logout" class="btn-logout">
            <i class="ri-logout-box-r-line"></i> Đăng xuất
        </a>
    </div>
</div>