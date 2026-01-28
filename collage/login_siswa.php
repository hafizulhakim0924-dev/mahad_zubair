<?php 
session_start();

$servername = "localhost";
$username = "ypikhair_admin";
$password = "hakim123123123";
$dbname = "ypikhair_datautama";

// Redirect jika sudah login
if (isset($_SESSION['student'])) {
    header('Location: profile.php');
    exit;
}

// ======================================================
// AUTO LOGIN UNTUK RETURNING USER
// ======================================================
if (isset($_GET['device_id']) && isset($_GET['token'])) {
    $device_id = $_GET['device_id'];
    $fcm_token = $_GET['token'];

    $conn = new mysqli($servername, $username, $password, $dbname);

    if (!$conn->connect_error) {
        // Cari siswa berdasarkan device_id yang terdaftar
        $stmt = $conn->prepare("
            SELECT students.* 
            FROM students
            JOIN tokens ON tokens.user_id = students.id
            WHERE tokens.device_id = ? 
            LIMIT 1
        ");
        
        if ($stmt) {
            $stmt->bind_param("s", $device_id);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                // Auto-login berhasil (user pernah login sebelumnya)
                $student = $result->fetch_assoc();
                $_SESSION['student'] = $student;
                
                // Update FCM token
                $stmt2 = $conn->prepare("
                    UPDATE tokens 
                    SET token = ? 
                    WHERE device_id = ? AND user_id = ?
                ");
                $stmt2->bind_param("ssi", $fcm_token, $device_id, $student['id']);
                $stmt2->execute();
                $stmt2->close();
                
                $conn->close();
                header("Location: profile.php");
                exit;
            }
            $stmt->close();
        }
    }
    $conn->close();
    // Jika auto-login gagal, lanjut ke form login (user baru)
}

// ======================================================
// MANUAL LOGIN (USER BARU ATAU AUTO-LOGIN GAGAL)
// ======================================================
$error = '';
$device_id = $_GET['device_id'] ?? $_POST['device_id'] ?? null;
$fcm_token = $_GET['token'] ?? $_POST['token'] ?? null;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $student_id = trim($_POST['student_id']);
    $password_input = trim($_POST['password']);

    // Admin khusus
    if ($student_id === 'khalid' && $password_input === 'syakila') {
        $_SESSION['admin_khusus'] = true;
        header('Location: penarikan.php');
        exit;
    }

    $conn = new mysqli($servername, $username, $password, $dbname);

    if ($conn->connect_error) {
        $error = "Koneksi database gagal!";
    } else {
        $student_id_int = intval($student_id);

        $stmt = $conn->prepare("
            SELECT * FROM students WHERE id = ? AND password = ?
        ");
        $stmt->bind_param("is", $student_id_int, $password_input);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $student = $result->fetch_assoc();
            $_SESSION['student'] = $student;

            // ================================
            // DAFTAR DEVICE UNTUK AUTO-LOGIN NEXT TIME
            // ================================
            if (!empty($device_id) && !empty($fcm_token)) {
                
                // Cek apakah device_id sudah terdaftar
                $stmt_check = $conn->prepare("
                    SELECT id FROM tokens WHERE device_id = ? AND user_id = ?
                ");
                $stmt_check->bind_param("si", $device_id, $student['id']);
                $stmt_check->execute();
                $check_result = $stmt_check->get_result();

                if ($check_result->num_rows > 0) {
                    // Update token jika device sudah terdaftar
                    $stmt_update = $conn->prepare("
                        UPDATE tokens 
                        SET token = ?, expired_at = DATE_ADD(NOW(), INTERVAL 7 DAY)
                        WHERE device_id = ? AND user_id = ?
                    ");
                    $stmt_update->bind_param("ssi", $fcm_token, $device_id, $student['id']);
                    $stmt_update->execute();
                    $stmt_update->close();
                } else {
                    // Insert token baru untuk device ini
                    $token = bin2hex(random_bytes(20));
                    
                    $stmt_insert = $conn->prepare("
                        INSERT INTO tokens (user_id, token, device_id, expired_at)
                        VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL 7 DAY))
                    ");
                    $stmt_insert->bind_param("iss", $student['id'], $token, $device_id);
                    $stmt_insert->execute();
                    $stmt_insert->close();
                }
                $stmt_check->close();
            }

            $conn->close();
            header("Location: profile.php");
            exit;

        } else {
            $error = "ID atau Password salah!";
        }
        
        $conn->close();
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Mahad Ibnu Zubair</title>

    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; -webkit-tap-highlight-color: transparent; }
        input, textarea, select { -webkit-user-select: text; }
        body { font-family: system-ui, sans-serif; background: #1e40af; height: 100vh; display:flex; align-items:center; justify-content:center; padding:20px; }
        .container { background:#fff; padding:40px; border-radius:12px; width:100%; max-width:380px; box-shadow:0 4px 20px rgba(0,0,0,0.15); }
        .logo { width:80px; height:80px; margin:0 auto 15px; display:block; border-radius:8px; background:#f8f9fa; padding:5px; }
        .title { color:#1e40af; text-align:center; font-size:1.6rem; font-weight:700; margin-bottom:8px; }
        .subtitle { color:#64748b; text-align:center; margin-bottom:25px; font-size:14px; }
        .input { width:100%; padding:14px; border:1px solid #d1d5db; border-radius:6px; margin-bottom:16px; font-size:16px; font-family:system-ui; }
        .input:focus { outline:none; border-color:#1e40af; box-shadow:0 0 0 2px rgba(30,64,175,0.1); }
        .btn { width:100%; padding:14px; background:#1e40af; color:#fff; border:0; border-radius:6px; cursor:pointer; font-size:1rem; font-weight:600; }
        .btn:active { background:#1e3a8a; }
        .btn2 { width:100%; padding:12px; background:transparent; color:#dc2626; border:1px solid #dc2626; border-radius:6px; text-align:center; text-decoration:none; display:block; font-weight:500; }
        .error { background:#fef2f2; border:1px solid #fca5a5; color:#dc2626; padding:12px; border-radius:6px; margin:15px 0; text-align:center; font-size:14px; }
        .divider { text-align:center; margin:20px 0; color:#9ca3af; font-size:14px; }
        .info { background:#dbeafe; border:1px solid #93c5fd; color:#1e40af; padding:10px; border-radius:6px; margin:10px 0; font-size:12px; text-align:center; }
        .info.new { background:#fef3c7; border:1px solid #fcd34d; color:#92400e; }
        .hidden { display:none; }
    </style>
</head>
<body>
    <div class="container">
        <img src="https://ibnuzubair.ypi-khairaummah.sch.id/logo.jpeg" class="logo" alt="Logo">

        <h1 class="title">Mahad Ibnu Zubair</h1>
        <p class="subtitle">Portal Siswa</p>

        <?php if(!empty($device_id)): ?>
        <div class="info new">
            📱 Login dari aplikasi mobile
        </div>
        <?php endif; ?>

        <form method="POST" id="loginForm">
            <input 
                type="text" 
                name="student_id" 
                class="input" 
                placeholder="ID Siswa" 
                required 
                autofocus
                autocomplete="username"
            >
            <input 
                type="password" 
                name="password" 
                class="input" 
                placeholder="Password" 
                required
                autocomplete="current-password"
            >
            
            <!-- HIDDEN FIELDS untuk device_id dan token dari app -->
            <?php if(!empty($device_id)): ?>
            <input type="hidden" name="device_id" value="<?= htmlspecialchars($device_id) ?>">
            <input type="hidden" name="token" value="<?= htmlspecialchars($fcm_token) ?>">
            <?php endif; ?>
            
            <button type="submit" class="btn">Masuk</button>
        </form>

        <?php if($error): ?>
        <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <div class="divider"><span>atau</span></div>
        <a href="register_siswa.php" class="btn2" style="background: #10b981; color: white; border-color: #10b981;">Daftar Akun Baru</a>
        <a href="change_password.php" class="btn2" style="margin-top: 10px;">Ubah Password</a>
    </div>

    <script>
        // Debug logging (hanya di console)
        const deviceId = '<?= htmlspecialchars($device_id ?? "") ?>';
        const fcmToken = '<?= htmlspecialchars($fcm_token ?? "") ?>';
        
        if (deviceId && fcmToken) {
            console.log('🔐 App Login Info:');
            console.log('Device ID:', deviceId);
            console.log('FCM Token:', fcmToken.substring(0, 10) + '...');
        }
    </script>
</body>
</html>