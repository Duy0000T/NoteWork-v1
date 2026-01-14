<?php
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $user = $_POST['username'];
    $pass = $_POST['password'];
    $stmt = $conn->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->bind_param("s", $user);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        if (password_verify($pass, $row['password'])) {
            $_SESSION['user_id'] = $row['id'];
            $_SESSION['username'] = $row['username'];
            header("Location: index.php?page=dashboard");
            exit();
        } else $error = "Mật khẩu không đúng";
    } else $error = "Tài khoản không tồn tại";
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <title>Đăng nhập</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link href="https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css" rel="stylesheet">
</head>
<body class="auth-body">
<div class="auth-box">
    <i class="ri-user-smile-fill" style="font-size: 50px; color: var(--primary);"></i>
    <h2>Đăng nhập</h2>
    <?php if(isset($error)) echo "<p style='color:red; margin-bottom:10px;'>$error</p>"; ?>
    <form method="POST">
        <input type="text" name="username" placeholder="Username" required class="form-input">
        <input type="password" name="password" placeholder="Password" required class="form-input">
        <button type="submit" class="btn-primary">Đăng nhập</button>
    </form>
    <a href="index.php?page=register" class="auth-link">Chưa có tài khoản? Đăng ký</a>
    <br>
    <a href="index.php" style="color: #999; font-size: 12px;">Về trang chủ</a>
</div>
</body>
</html>