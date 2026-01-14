<?php
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $user = $_POST['username'];
    $email = $_POST['email'];
    $pass = password_hash($_POST['password'], PASSWORD_DEFAULT);

    $check = $conn->query("SELECT * FROM users WHERE username='$user' OR email='$email'");
    if($check->num_rows > 0) {
        $error = "Username hoặc Email đã tồn tại";
    } else {
        $stmt = $conn->prepare("INSERT INTO users (username, email, password) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $user, $email, $pass);
        if ($stmt->execute()) header("Location: index.php?page=login");
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <title>Đăng ký</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link href="https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css" rel="stylesheet">
</head>
<body class="auth-body">
<div class="auth-box">
    <i class="ri-user-add-fill" style="font-size: 50px; color: var(--primary);"></i>
    <h2>Đăng ký tài khoản</h2>
    <?php if(isset($error)) echo "<p style='color:red; margin-bottom:10px;'>$error</p>"; ?>
    <form method="POST">
        <input type="text" name="username" placeholder="Username" required class="form-input">
        <input type="email" name="email" placeholder="Email" required class="form-input">
        <input type="password" name="password" placeholder="Password" required class="form-input">
        <button type="submit" class="btn-primary">Đăng ký ngay</button>
    </form>
    <a href="index.php?page=login" class="auth-link">Đã có tài khoản? Đăng nhập</a>
</div>
</body>
</html>