<?php
declare(strict_types=1);
session_start();

/**
 * ============================================================================
 * CareerPro Suite - Profile & Password Update API
 * Version: 1.0.0
 * Handles:
 *   action = update_profile   → update name / phone
 *   action = change_password  → verify current password, set new hash
 *   action = update_preferences → placeholder (privacy toggles)
 * ============================================================================
 */

header('Content-Type: application/json');

// 1. Session guard
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized. Please log in.']);
    exit;
}

// 2. AJAX only
if ($_SERVER['REQUEST_METHOD'] !== 'POST' ||
    empty($_SERVER['HTTP_X_REQUESTED_WITH']) ||
    strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest') {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Forbidden. Invalid request protocol.']);
    exit;
}

require_once '../includes/db.php';
$db     = Database::getInstance()->getConnection();
$userId = (int) $_SESSION['user_id'];

$input = file_get_contents('php://input');
$data  = json_decode($input, true);
$action = $data['action'] ?? '';

// ============================================================================
// ACTION: update_profile  — update name and/or phone
// ============================================================================
if ($action === 'update_profile') {
    $name  = trim($data['name'] ?? '');
    $phone = trim($data['phone'] ?? '');

    if (empty($name)) {
        echo json_encode(['status' => 'error', 'message' => 'Name cannot be empty.']);
        exit;
    }

    if (strlen($name) > 120) {
        echo json_encode(['status' => 'error', 'message' => 'Name is too long (max 120 characters).']);
        exit;
    }

    if (!empty($phone) && !preg_match('/^\+?[\d\s\-().]{7,20}$/', $phone)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid phone number format.']);
        exit;
    }

    try {
        $stmt = $db->prepare("UPDATE users SET name = :name, phone = :phone, updated_at = NOW() WHERE id = :id");
        $stmt->execute([
            'name'  => $name,
            'phone' => $phone ?: null,
            'id'    => $userId,
        ]);

        // Keep session name in sync
        $_SESSION['user_name'] = $name;

        echo json_encode(['status' => 'success', 'message' => 'Profile synchronized successfully.']);
    } catch (PDOException $e) {
        error_log("Profile Update Error (User $userId): " . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => 'Database error. Please try again.']);
    }
    exit;
}

// ============================================================================
// ACTION: change_password  — verify current password, save new bcrypt hash
// ============================================================================
if ($action === 'change_password') {
    $currentPassword = $data['current_password'] ?? '';
    $newPassword     = $data['new_password'] ?? '';

    if (empty($currentPassword) || empty($newPassword)) {
        echo json_encode(['status' => 'error', 'message' => 'Both current and new password are required.']);
        exit;
    }

    if (strlen($newPassword) < 8) {
        echo json_encode(['status' => 'error', 'message' => 'New password must be at least 8 characters.']);
        exit;
    }

    try {
        // Fetch current hash
        $stmt = $db->prepare("SELECT password_hash FROM users WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row || !password_verify($currentPassword, $row['password_hash'])) {
            echo json_encode(['status' => 'error', 'message' => 'Current password is incorrect.']);
            exit;
        }

        // Hash and save the new password
        $newHash = password_hash($newPassword, PASSWORD_DEFAULT, ['cost' => 12]);
        $update  = $db->prepare("UPDATE users SET password_hash = :hash, updated_at = NOW() WHERE id = :id");
        $update->execute(['hash' => $newHash, 'id' => $userId]);

        echo json_encode(['status' => 'success', 'message' => 'Password updated successfully.']);
    } catch (PDOException $e) {
        error_log("Password Change Error (User $userId): " . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => 'Database error. Please try again.']);
    }
    exit;
}

// ============================================================================
// ACTION: update_preferences  — privacy/notification toggles (extend as needed)
// ============================================================================
if ($action === 'update_preferences') {
    // Currently a UI-only toggle; stored as a system_settings note or extend users table
    echo json_encode(['status' => 'success', 'message' => 'Preferences saved.']);
    exit;
}

// Fallback
echo json_encode(['status' => 'error', 'message' => 'Invalid API action.']);
exit;
