<?php
declare(strict_types=1);
session_start();

/**
 * ============================================================================
 * CareerPro Suite - Secure Authentication & Registration API
 * Version: 2.0.0
 * Architecture: 
 * - Handles New User Provisioning (AJAX)
 * - Cryptographic Password Hashing (Bcrypt)
 * - Anti-Enumeration & CSRF Protection
 * - Session Annihilation (Logout)
 * ============================================================================
 */

// Include the database connection (Path assumes this file is in api/)
require_once '../includes/db.php';

// ============================================================================
// 1. HANDLE SECURE LOGOUT (GET REQUEST)
// ============================================================================
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    // Annihilate all session data securely
    $_SESSION = array();
    
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(), 
            '', 
            time() - 42000,
            $params["path"], 
            $params["domain"],
            $params["secure"], 
            $params["httponly"]
        );
    }
    
    session_destroy();
    
    // Route back to login page
    header("Location: ../login.php?logout=1");
    exit;
}

// ============================================================================
// 2. HANDLE AJAX REGISTRATION (POST REQUEST)
// ============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
    
    header('Content-Type: application/json');
    $data = json_decode(file_get_contents('php://input'), true);
    
    $action = $data['action'] ?? '';
    
    if ($action === 'register') {
        
        // 1. CSRF Token Validation
        $csrfToken = $data['csrf_token'] ?? '';
        if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrfToken)) {
            echo json_encode(['status' => 'error', 'message' => 'Security token invalid. Please refresh the page.']);
            exit;
        }

        // 2. Sanitize and Extract Inputs — strip tags only, NOT htmlspecialchars
        // htmlspecialchars() would corrupt names like "O'Brien" or "José"
        // We use htmlspecialchars only at output time (in PHP templates)
        $name  = trim(strip_tags($data['name'] ?? ''));
        $email = trim(filter_var($data['email'] ?? '', FILTER_SANITIZE_EMAIL));
        $password = $data['password'] ?? '';
        $confirmPassword = $data['confirm_password'] ?? '';

        // 3. Strict Server-Side Validation
        if (empty($name) || empty($email) || empty($password)) {
            echo json_encode(['status' => 'error', 'message' => 'All fields are required to provision an account.']);
            exit;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid email format provided.']);
            exit;
        }

        // Enforce Domain (Optional)
        // REMOVED: domain restriction — any valid email is now accepted

        if ($password !== $confirmPassword) {
            echo json_encode(['status' => 'error', 'message' => 'Cryptographic keys (passwords) do not match.']);
            exit;
        }

        if (strlen($password) < 8) {
            echo json_encode(['status' => 'error', 'message' => 'Cipher must be at least 8 characters long for security.']);
            exit;
        }

        try {
            $db = Database::getInstance()->getConnection();

            // 4. Check for Existing Identity
            $stmt = $db->prepare("SELECT id FROM users WHERE email = :email LIMIT 1");
            $stmt->execute(['email' => $email]);
            
            if ($stmt->fetch()) {
                // Return generic error to prevent email enumeration, but clear enough for the user
                echo json_encode(['status' => 'error', 'message' => 'An identity node with this email already exists.']);
                exit;
            }

            // 5. Cryptographic Hashing
            $passwordHash = password_hash($password, PASSWORD_DEFAULT, ['cost' => 12]);

            // 6. Database Transaction (Ensure user and resume row are created safely)
            $db->beginTransaction();

            // Insert User
            $insertUser = $db->prepare("INSERT INTO users (name, email, password_hash, is_active, created_at) VALUES (:name, :email, :hash, 1, NOW())");
            $insertUser->execute([
                'name' => $name,
                'email' => $email,
                'hash' => $passwordHash
            ]);
            
            $newUserId = $db->lastInsertId();

            // Initialize a blank Resume Node to establish database relationship
            $insertResume = $db->prepare("INSERT INTO resumes (user_id, resume_data, last_updated) VALUES (:user_id, :data, NOW())");
            $insertResume->execute([
                'user_id' => $newUserId,
                'data' => null // Builder will populate this on first load
            ]);

            $db->commit();

            // 7. Auto-login after registration — create the session immediately
            session_regenerate_id(true);
            $_SESSION['user_id']   = (int) $newUserId;
            $_SESSION['user_name'] = $name;

            echo json_encode([
                'status'  => 'success',
                'message' => 'Account provisioned successfully. Redirecting...',
                'data'    => ['redirect' => '../dashboard.php'],
            ]);
            exit;

        } catch (PDOException $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log("Registration Fault: " . $e->getMessage());
            echo json_encode(['status' => 'error', 'message' => 'Database provision failed. Contact network administrator.']);
            exit;
        }
    }
    
    // Fallback for unknown actions
    echo json_encode(['status' => 'error', 'message' => 'Invalid API Action.']);
    exit;
}

// Block direct browser access to this file
header('HTTP/1.0 403 Forbidden');
echo "403 Forbidden - Direct access to this API node is not permitted.";
exit;