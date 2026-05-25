<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit();
}

$user_id = (int) $_SESSION['user_id'];

require_once __DIR__ . '/../public/database.config.php';
$db = $conn;


// Auto-migrate: add profile columns if they don't exist yet


$body   = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $body['action'] ?? '';

switch ($action) {

    // ── Load profile ──────────────────────────────────────────────────────
    case 'load':
        $stmt = $db->prepare(
            "SELECT username, email, display_name, bio, gender, location, avatar_data, cover_data
             FROM accounts WHERE id = ?"
        );
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$row) {
            echo json_encode(['success' => false, 'error' => 'User not found']);
            exit();
        }

        echo json_encode([
            'success'      => true,
            'username'     => $row['username'],
            'email'        => $row['email'],
            'display_name' => $row['display_name'] ?? '',
            'bio'          => $row['bio']          ?? '',
            'gender'       => $row['gender']       ?? '',
            'location'     => $row['location']     ?? '',
            'avatar_data'  => $row['avatar_data']  ?? '',
            'cover_data'   => $row['cover_data']   ?? '',
        ]);
        break;

    // ── Save profile fields ───────────────────────────────────────────────
    case 'save':
        $display_name = trim($body['display_name'] ?? '');
        $email        = trim($body['email']        ?? '');
        $bio          = trim($body['bio']          ?? '');
        $gender       = trim($body['gender']       ?? '');
        $location     = trim($body['location']     ?? '');

        // Validate email format
        if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['success' => false, 'error' => 'Invalid email address']);
            exit();
        }

        // Check email uniqueness if changed
        if ($email) {
            $check = $db->prepare("SELECT id FROM accounts WHERE email = ? AND id != ?");
            $check->bind_param('si', $email, $user_id);
            $check->execute();
            if ($check->get_result()->num_rows > 0) {
                echo json_encode(['success' => false, 'error' => 'Email already in use']);
                exit();
            }
            $check->close();
        }

        $stmt = $db->prepare(
            "UPDATE accounts
             SET display_name = ?, bio = ?, gender = ?, location = ?
                 " . ($email ? ", email = ?" : "") . "
             WHERE id = ?"
        );

        if ($email) {
            $stmt->bind_param('sssssi', $display_name, $bio, $gender, $location, $email, $user_id);
        } else {
            $stmt->bind_param('ssssi', $display_name, $bio, $gender, $location, $user_id);
        }

        if ($stmt->execute()) {
            // Update session display name
            $_SESSION['display_name'] = $display_name ?: $_SESSION['username'];
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => $db->error]);
        }
        $stmt->close();
        break;

    // ── Save avatar (base64) ──────────────────────────────────────────────
    case 'save_avatar':
        $avatar_data = $body['avatar_data'] ?? '';
        // Validate it's a base64 image (rough check)
        if ($avatar_data && !preg_match('/^data:image\/(jpeg|png|gif|webp);base64,/', $avatar_data)) {
            echo json_encode(['success' => false, 'error' => 'Invalid image format']);
            exit();
        }
        // Limit size: ~500KB base64
        if (strlen($avatar_data) > 700000) {
            echo json_encode(['success' => false, 'error' => 'Image too large (max ~500KB)']);
            exit();
        }
        $stmt = $db->prepare("UPDATE accounts SET avatar_data = ? WHERE id = ?");
        $stmt->bind_param('si', $avatar_data, $user_id);
        if ($stmt->execute()) {
            $_SESSION['avatar_data'] = $avatar_data;
            echo json_encode(['success' => true, 'avatar_data' => $avatar_data]);
        } else {
            echo json_encode(['success' => false, 'error' => $db->error]);
        }
        $stmt->close();
        break;

    // ── Save cover photo (base64) ─────────────────────────────────────────
    case 'save_cover':
        $cover_data = $body['cover_data'] ?? '';
        if ($cover_data && !preg_match('/^data:image\/(jpeg|png|gif|webp);base64,/', $cover_data)) {
            echo json_encode(['success' => false, 'error' => 'Invalid image format']);
            exit();
        }
        if (strlen($cover_data) > 1500000) {
            echo json_encode(['success' => false, 'error' => 'Image too large (max ~1MB)']);
            exit();
        }
        $stmt = $db->prepare("UPDATE accounts SET cover_data = ? WHERE id = ?");
        $stmt->bind_param('si', $cover_data, $user_id);
        if ($stmt->execute()) {
            $_SESSION['cover_data'] = $cover_data;
            echo json_encode(['success' => true, 'cover_data' => $cover_data]);
        } else {
            echo json_encode(['success' => false, 'error' => $db->error]);
        }
        $stmt->close();
        break;

    // ── Change password ───────────────────────────────────────────────────
    case 'change_password':
        $current = $body['current_password'] ?? '';
        $new_pw  = $body['new_password']     ?? '';
        $confirm = $body['confirm_password'] ?? '';

        if (!$current || !$new_pw || !$confirm) {
            echo json_encode(['success' => false, 'error' => 'All password fields are required']);
            exit();
        }
        if ($new_pw !== $confirm) {
            echo json_encode(['success' => false, 'error' => 'New passwords do not match']);
            exit();
        }
        if (strlen($new_pw) < 8) {
            echo json_encode(['success' => false, 'error' => 'Password must be at least 8 characters']);
            exit();
        }

        // Verify current password
        $stmt = $db->prepare("SELECT password FROM accounts WHERE id = ?");
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$row || !password_verify($current, $row['password'])) {
            echo json_encode(['success' => false, 'error' => 'Current password is incorrect']);
            exit();
        }

        $hashed = password_hash($new_pw, PASSWORD_BCRYPT);
        $stmt = $db->prepare("UPDATE accounts SET password = ? WHERE id = ?");
        $stmt->bind_param('si', $hashed, $user_id);
        if ($stmt->execute()) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => $db->error]);
        }
        $stmt->close();
        break;

    default:
        echo json_encode(['success' => false, 'error' => 'Unknown action']);
}

$db->close();
