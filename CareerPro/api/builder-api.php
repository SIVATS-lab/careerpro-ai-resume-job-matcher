<?php
declare(strict_types=1);
session_start();

/**
 * ============================================================================
 * CareerPro Suite - Resume Builder API Node
 * Version: 2.5.0
 * Architecture: 
 * - Handles Real-time JSON Auto-saving
 * - Secure PDO Upsert Operations
 * - Google Gemini AI Integration (cURL)
 * - Session-protected Endpoint
 * ============================================================================
 */

header('Content-Type: application/json');

// 1. SESSION GUARD: Ensure only authenticated students can save data
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access. Session expired.']);
    exit;
}

require_once '../includes/db.php';
$userId = (int)$_SESSION['user_id'];
$db = Database::getInstance()->getConnection();

// 2. ACCEPT ONLY SECURE AJAX POST REQUESTS
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest') {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Forbidden. Invalid request protocol.']);
    exit;
}

$input = file_get_contents('php://input');
$data = json_decode($input, true);
$action = $data['action'] ?? '';

// ============================================================================
// 3. ROUTE: SAVE RESUME JSON STATE
// ============================================================================
if ($action === 'save_resume') {
    if (!isset($data['resume_state'])) {
        echo json_encode(['status' => 'error', 'message' => 'No payload detected.']);
        exit;
    }

    try {
        // Encode the incoming state array back into a secure JSON string
        $resumePayload = json_encode($data['resume_state']);
        
        // UPSERT Architecture: Insert if new, update if already exists
        $stmt = $db->prepare("
            INSERT INTO resumes (user_id, resume_data, last_updated) 
            VALUES (:uid, :data, NOW()) 
            ON DUPLICATE KEY UPDATE resume_data = :data2, last_updated = NOW()
        ");
        
        $stmt->execute([
            'uid' => $userId,
            'data' => $resumePayload,
            'data2' => $resumePayload
        ]);
        
        echo json_encode(['status' => 'success', 'message' => 'State synchronized with CareerPro Cloud.']);
        exit;

    } catch (PDOException $e) {
        error_log("Builder API Sync Error (User ID $userId): " . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => 'Database synchronization failed.']);
        exit;
    }
}

// ============================================================================
// 4. ROUTE: GOOGLE GEMINI AI ASSISTANT (REWRITE ENGINE)
// ============================================================================
if ($action === 'ai_assist') {
    $prompt = $data['prompt'] ?? '';
    $context = $data['context'] ?? '';
    
    if (empty($prompt) && empty($context)) {
        echo json_encode(['status' => 'error', 'message' => 'Empty prompt provided to AI Engine.']);
        exit;
    }

    // System Instruction for Resume Context
    $systemInstruction = "You are CareerBot, an expert Applicant Tracking System (ATS) resume writer. Your job is to rewrite the provided text to be highly professional, impactful, and optimized for corporate ATS parsers. 
    RULES:
    1. Use strong action verbs (e.g., Architected, Spearheaded, Engineered).
    2. Focus on measurable impact and technical accuracy.
    3. Return ONLY the rewritten text. 
    4. DO NOT use conversational filler (e.g., 'Here is your text:').
    5. DO NOT use markdown bolding (**) or italics. Return plain text only.";
    
    $fullPrompt = $systemInstruction . "\n\nTask: " . $prompt . "\n\nText to optimize:\n" . $context;

    // Fetch API key securely from settings table
    $apiKey = '';
    try {
        $keyStmt = $db->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'gemini_api_key' LIMIT 1");
        $keyStmt->execute();
        $dbKey = (string)$keyStmt->fetchColumn();
        if (!empty($dbKey)) {
            $apiKey = $dbKey;
        }
    } catch (Exception $ex) {
        error_log("Builder API: could not load API key from DB: " . $ex->getMessage());
    }

    // Also fall back to config.php constant if DB key is empty
    if (empty($apiKey)) {
        require_once '../includes/config.php';
        if (defined('GEMINI_API_KEY_FALLBACK')) {
            $apiKey = GEMINI_API_KEY_FALLBACK;
        }
    }

    if (empty($apiKey)) {
        echo json_encode(['status' => 'error', 'message' => 'AI service is not configured. Please contact the administrator.']);
        exit;
    }

    $payload = [
        "contents" => [["parts" => [["text" => $fullPrompt]]]],
        "generationConfig" => ["temperature" => 0.6, "maxOutputTokens" => 800]
    ];

    // Try Gemini models first
    $models   = ['gemini-2.0-flash', 'gemini-1.5-flash', 'gemini-pro'];
    $response = '';
    $httpCode = 0;
    $aiText   = '';

    if (!empty($apiKey)) {
        foreach ($models as $model) {
            $url = 'https://generativelanguage.googleapis.com/v1beta/models/'
                 . $model . ':generateContent?key=' . urlencode($apiKey);

            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST,           true);
            curl_setopt($ch, CURLOPT_HTTPHEADER,     ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_POSTFIELDS,     json_encode($payload));
            curl_setopt($ch, CURLOPT_TIMEOUT,        20);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

            $response = curl_exec($ch);
            if (curl_errno($ch)) {
                error_log("cURL Error in builder-api.php: " . curl_error($ch));
                curl_close($ch);
                break;
            }
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode === 200) {
                $json = json_decode($response, true);
                $aiText = trim($json['candidates'][0]['content']['parts'][0]['text'] ?? '');
                if (!empty($aiText)) break;
            }
            if ($httpCode === 429 || $httpCode === 400) break;
            error_log("Builder API: model $model returned $httpCode, trying next...");
        }
    }

    // Fallback: Pollinations AI (free, no key needed)
    if (empty($aiText)) {
        $pollUrl  = 'https://text.pollinations.ai/';
        $pollBody = json_encode([
            'messages'    => [['role' => 'user', 'content' => $fullPrompt]],
            'model'       => 'openai',
            'max_tokens'  => 800,
            'temperature' => 0.6,
            'private'     => true,
        ]);
        $ch = curl_init($pollUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST,           true);
        curl_setopt($ch, CURLOPT_HTTPHEADER,     ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_POSTFIELDS,     $pollBody);
        curl_setopt($ch, CURLOPT_TIMEOUT,        30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $pollResp = curl_exec($ch);
        $pollCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($pollCode === 200 && !empty(trim($pollResp))) {
            $pollJson = json_decode($pollResp, true);
            $aiText   = trim($pollJson['choices'][0]['message']['content'] ?? $pollResp);
        }
    }

    if (!empty($aiText)) {
        $aiText = str_replace(['**', '*'], '', $aiText);
        echo json_encode(['status' => 'success', 'data' => $aiText]);
    } else {
        error_log("Builder AI failed — all strategies exhausted (last HTTP $httpCode)");
        echo json_encode(['status' => 'error', 'message' => 'AI Engine unavailable. Please try again later.']);
    }
    exit;
}

// Fallback for invalid action
echo json_encode(['status' => 'error', 'message' => 'Invalid API Action.']);
exit;