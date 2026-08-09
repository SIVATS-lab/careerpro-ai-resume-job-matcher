<?php
declare(strict_types=1);
session_start();

/**
 * ============================================================================
 * CareerPro Suite - ATS Scoring & Matcher API Node
 * Version: 5.0.0
 * Architecture: 
 * - Parses structural JSON resumes into NLP text strings.
 * - Extracts keywords and performs TF-IDF simulated matching.
 * - Auto-records application logs and scores in the database.
 * ============================================================================
 */

header('Content-Type: application/json');

// 1. SESSION GUARD: Ensure only authenticated students can scan
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
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

$jobId = isset($data['job_id']) ? (int)$data['job_id'] : 0;
$jobDesc = $data['job_description'] ?? '';

if ($jobId === 0) {
    echo json_encode(['status' => 'error', 'message' => 'Missing Job Identifier.']);
    exit;
}

try {
    // ========================================================================
    // 3. FETCH DATA (Resume & Job Requirements)
    // ========================================================================
    
    // Fetch User's Master Resume
    $stmtResume = $db->prepare("SELECT resume_data FROM resumes WHERE user_id = :uid LIMIT 1");
    $stmtResume->execute(['uid' => $userId]);
    $resumeRow = $stmtResume->fetch(PDO::FETCH_ASSOC);

    if (!$resumeRow || empty($resumeRow['resume_data'])) {
        echo json_encode(['status' => 'error', 'message' => 'Resume JSON payload missing. Please complete your profile in the Resume Engine.']);
        exit;
    }

    $resume = json_decode($resumeRow['resume_data'], true);

    // Fetch Job Data (Specifically the required skills array)
    $stmtJob = $db->prepare("SELECT req_skills FROM jobs WHERE id = :job_id LIMIT 1");
    $stmtJob->execute(['job_id' => $jobId]);
    $jobRow = $stmtJob->fetch(PDO::FETCH_ASSOC);

    if (!$jobRow) {
        echo json_encode(['status' => 'error', 'message' => 'Target role not found in the active database.']);
        exit;
    }

    // ========================================================================
    // 4. THE ATS PARSING & NLP ALGORITHM
    // ========================================================================
    
    // Step A: Flatten the Resume JSON into a single searchable corpus
    $resumeCorpus = "";
    
    // Add Skills
    if (!empty($resume['skills'])) {
        $resumeCorpus .= implode(" ", $resume['skills']) . " ";
    }
    // Add Experience
    if (!empty($resume['experience'])) {
        foreach ($resume['experience'] as $ex) {
            $resumeCorpus .= ($ex['title'] ?? '') . " " . ($ex['desc'] ?? '') . " ";
        }
    }
    // Add Projects
    if (!empty($resume['projects'])) {
        foreach ($resume['projects'] as $pr) {
            $resumeCorpus .= ($pr['tech'] ?? '') . " " . ($pr['desc'] ?? '') . " ";
        }
    }
    // Add Summary
    if (!empty($resume['summary'])) {
        $resumeCorpus .= $resume['summary'] . " ";
    }

    // Convert corpus to lowercase for case-insensitive matching
    $resumeCorpus = strtolower($resumeCorpus);

    // Step B: Determine Required Skills (Hard Skills)
    $requiredSkills = [];
    if (!empty($jobRow['req_skills'])) {
        $requiredSkills = json_decode($jobRow['req_skills'], true);
    } 
    
    // Fallback Keyword Extractor (If the admin didn't specify skills in the DB)
    if (empty($requiredSkills)) {
        $techKeywords = ['php', 'javascript', 'java', 'python', 'c++', 'react', 'node', 'mysql', 'sql', 'css', 'html', 'tailwind', 'laravel', 'git', 'github', 'aws', 'docker', 'machine learning', 'api', 'agile', 'leadership', 'communication'];
        $lowerJobDesc = strtolower($jobDesc);
        
        foreach ($techKeywords as $kw) {
            if (strpos($lowerJobDesc, $kw) !== false) {
                $requiredSkills[] = $kw;
            }
        }
        // If still empty, use some mock data to prevent math errors
        if (empty($requiredSkills)) {
            $requiredSkills = ['communication', 'problem solving', 'teamwork'];
        }
    }

    // Step C: Execute Matching Logic
    $matchedSkills = [];
    $missingSkills = [];

    foreach ($requiredSkills as $skill) {
        $skillLower = strtolower(trim($skill));
        if (strpos($resumeCorpus, $skillLower) !== false) {
            $matchedSkills[] = ucwords($skill);
        } else {
            $missingSkills[] = ucwords($skill);
        }
    }

    // Step D: Calculate Base Score
    $totalSkills = count($requiredSkills);
    $matchedCount = count($matchedSkills);
    $baseScore = $totalSkills > 0 ? ($matchedCount / $totalSkills) * 80 : 0; // Skills account for 80% of ATS score

    // Step E: Formatting & Structure Bonus (Simulating enterprise ATS structural checks)
    $structureBonus = 0;
    if (count($resume['experience'] ?? []) > 0) $structureBonus += 10;
    if (count($resume['projects'] ?? []) > 0) $structureBonus += 5;
    if (!empty($resume['summary'])) $structureBonus += 5;

    // Calculate final integer score (Capped at 100)
    $finalScore = min(100, (int)round($baseScore + $structureBonus));

    // Determine status badge
    $statusText = "Poor Match";
    if ($finalScore >= 80) $statusText = "Excellent Match";
    else if ($finalScore >= 60) $statusText = "Good Match";
    else if ($finalScore >= 40) $statusText = "Average Match";

    // ========================================================================
    // 5. RECORD APPLICATION IN DATABASE
    // ========================================================================
    // Check if the user has already scanned/applied for this job today to prevent spam
    $stmtCheck = $db->prepare("SELECT id FROM applications WHERE user_id = :uid AND job_id = :jid AND DATE(applied_at) = CURDATE()");
    $stmtCheck->execute(['uid' => $userId, 'jid' => $jobId]);
    
    if (!$stmtCheck->fetch()) {
        // Record new scan/application
        $stmtInsert = $db->prepare("INSERT INTO applications (user_id, job_id, ats_score, applied_at) VALUES (:uid, :jid, :score, NOW())");
        $stmtInsert->execute([
            'uid' => $userId,
            'jid' => $jobId,
            'score' => $finalScore
        ]);
    } else {
        // Update the existing scan score for today — must include date filter to target correct row
        $stmtUpdate = $db->prepare("UPDATE applications SET ats_score = :score, applied_at = NOW() WHERE user_id = :uid AND job_id = :jid AND DATE(applied_at) = CURDATE()");
        $stmtUpdate->execute([
            'uid'   => $userId,
            'jid'   => $jobId,
            'score' => $finalScore
        ]);
    }

    // ========================================================================
    // 6. RETURN JSON PAYLOAD
    // ========================================================================
    $responseData = [
        'overall_score' => $finalScore,
        'status' => $statusText,
        'hard_skills' => [
            'matched' => $matchedSkills,
            'missing' => $missingSkills
        ]
    ];

    echo json_encode([
        'status' => 'success',
        'message' => 'ATS Analysis completed successfully.',
        'data' => $responseData
    ]);
    exit;

} catch (PDOException $e) {
    error_log("Matcher API DB Error (User $userId): " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Database exception during parsing.']);
    exit;
} catch (Exception $e) {
    error_log("Matcher API Core Error: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'System exception during processing.']);
    exit;
}