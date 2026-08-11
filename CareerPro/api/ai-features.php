<?php
declare(strict_types=1);
session_start();

/**
 * ============================================================================
 * CareerPro Suite — Advanced AI Features API v1.0
 * Endpoints (action param):
 *   cover_letter      → AI cover letter generator
 *   interview_prep    → Per-job AI interview questions & answers
 *   resume_critique   → Full AI resume analysis & coaching
 *   skill_gap         → AI skill gap advisor (resume vs market)
 *   job_match_ai      → Deep AI job compatibility analysis
 * ============================================================================
 */

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized. Please log in.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed.']);
    exit;
}

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/config.php';

$userId = (int) $_SESSION['user_id'];
$db     = Database::getInstance()->getConnection();

// ── Load Gemini API key ───────────────────────────────────────────────────
$apiKey = '';
try {
    $st = $db->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'gemini_api_key' LIMIT 1");
    $st->execute();
    $apiKey = trim((string)($st->fetchColumn() ?: ''));
} catch (Throwable $e) {
    error_log('AI Features: DB key load error: ' . $e->getMessage());
}
if (empty($apiKey) && defined('GEMINI_API_KEY_FALLBACK') && GEMINI_API_KEY_FALLBACK !== '') {
    $apiKey = GEMINI_API_KEY_FALLBACK;
}

// ── Parse request ─────────────────────────────────────────────────────────
$raw    = (string) file_get_contents('php://input');
$data   = json_decode($raw, true) ?: [];
$action = trim($data['action'] ?? '');

if (empty($action)) {
    echo json_encode(['status' => 'error', 'message' => 'No action specified.']);
    exit;
}

// ── Load user resume ──────────────────────────────────────────────────────
$resume     = null;
$resumeText = '';
try {
    $rs = $db->prepare("SELECT resume_data FROM resumes WHERE user_id = :uid LIMIT 1");
    $rs->execute(['uid' => $userId]);
    $row = $rs->fetch(PDO::FETCH_ASSOC);
    if ($row && !empty($row['resume_data'])) {
        $resume     = json_decode($row['resume_data'], true);
        // Flatten resume to plain text for AI prompts
        $parts = [];
        if (!empty($resume['personal'])) {
            $p = $resume['personal'];
            $parts[] = trim(($p['fname'] ?? '') . ' ' . ($p['lname'] ?? ''));
            if (!empty($p['title'])) $parts[] = $p['title'];
        }
        if (!empty($resume['summary'])) $parts[] = "Summary: " . $resume['summary'];
        if (!empty($resume['skills']))  $parts[] = "Skills: " . implode(', ', $resume['skills']);
        if (!empty($resume['experience'])) {
            foreach ($resume['experience'] as $ex) {
                $parts[] = ($ex['title'] ?? '') . " at " . ($ex['company'] ?? '') .
                           " (" . ($ex['start'] ?? '') . " – " . ($ex['end'] ?? '') . "): " .
                           ($ex['desc'] ?? '');
            }
        }
        if (!empty($resume['education'])) {
            foreach ($resume['education'] as $edu) {
                $parts[] = ($edu['degree'] ?? '') . " from " . ($edu['school'] ?? '') .
                           " (" . ($edu['start'] ?? '') . " – " . ($edu['end'] ?? '') . ") " .
                           ($edu['grade'] ?? '');
            }
        }
        if (!empty($resume['projects'])) {
            foreach ($resume['projects'] as $pr) {
                $parts[] = "Project: " . ($pr['name'] ?? '') . " [" . ($pr['tech'] ?? '') . "]: " . ($pr['desc'] ?? '');
            }
        }
        if (!empty($resume['certifications'])) {
            $parts[] = "Certifications: " . implode(', ', $resume['certifications']);
        }
        $resumeText = implode("\n", array_filter($parts));
    }
} catch (Throwable $e) {
    error_log('AI Features: resume load error: ' . $e->getMessage());
}

// ── Helper: call Gemini ───────────────────────────────────────────────────
function callGemini(string $key, string $prompt, float $temp = 0.7, int $maxTokens = 1500): string {
    // Model priority order — flash models use shared quota, lite uses a separate bucket
    $models = ['gemini-2.0-flash', 'gemini-1.5-flash', 'gemini-2.0-flash-lite', 'gemini-1.5-flash-8b'];
    $payload = [
        'contents'         => [['parts' => [['text' => $prompt]]]],
        'generationConfig' => ['temperature' => $temp, 'maxOutputTokens' => $maxTokens],
    ];
    foreach ($models as $model) {
        $url = 'https://generativelanguage.googleapis.com/v1beta/models/'
             . $model . ':generateContent?key=' . urlencode($key);
        $ch  = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);

        if ($err) {
            error_log("Gemini cURL err ($model): $err");
            break; // network error — skip to Pollinations fallback
        }

        if ($code === 200) {
            $j = json_decode($resp, true);
            $t = trim($j['candidates'][0]['content']['parts'][0]['text'] ?? '');
            if ($t !== '') return $t;
        }

        if ($code === 429) {
            // Rate limited — wait 3 seconds then try next model
            error_log("Gemini $model: 429 rate limit — waiting 3s before next model");
            sleep(3);
            continue; // try next model
        }

        error_log("Gemini $model: HTTP $code — " . substr((string)$resp, 0, 200));

        // Bad request or auth failure — no point trying other models
        if ($code === 400 || $code === 401 || $code === 403) break;
    }
    return '';
}

// ── Helper: Pollinations fallback ────────────────────────────────────────
function callPollinations(string $prompt, float $temp = 0.7, int $maxTokens = 1500): string {
    $payload = [
        'model'       => 'openai',
        'messages'    => [['role' => 'user', 'content' => $prompt]],
        'temperature' => $temp,
        'max_tokens'  => $maxTokens,
        'private'     => true,
    ];
    $endpoints = ['https://text.pollinations.ai/openai', 'https://api.pollinations.ai/v1/chat/completions'];
    foreach ($endpoints as $url) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($code === 200 && $resp) {
            $j = json_decode($resp, true);
            $t = trim($j['choices'][0]['message']['content'] ?? $resp);
            if ($t !== '' && strlen($t) > 10) return $t;
        }
    }
    return '';
}

// ── Helper: call AI with fallback ────────────────────────────────────────
// Returns the AI response text, or '' on failure.
// Sets $rateLimited=true (by reference) if all failures were 429s.
function askAI(string $apiKey, string $prompt, float $temp = 0.7, int $maxTokens = 1500, bool &$rateLimited = false): string {
    if (!empty($apiKey) && (str_starts_with($apiKey, 'AIza') || str_starts_with($apiKey, 'AQ.'))) {
        $result = callGemini($apiKey, $prompt, $temp, $maxTokens);
        if ($result !== '') return $result;
    }
    // Gemini failed or unavailable — try Pollinations free fallback
    $fallback = callPollinations($prompt, $temp, $maxTokens);
    if ($fallback !== '') return $fallback;
    // Everything failed
    $rateLimited = true;
    return '';
}

// ════════════════════════════════════════════════════════════════════════════
// ACTION: cover_letter
// ════════════════════════════════════════════════════════════════════════════
if ($action === 'cover_letter') {
    $jobTitle   = trim($data['job_title']   ?? '');
    $company    = trim($data['company']     ?? '');
    $jobDesc    = trim($data['job_desc']    ?? '');
    $tone       = trim($data['tone']        ?? 'professional'); // professional | confident | enthusiastic

    if (empty($jobTitle) || empty($company)) {
        echo json_encode(['status' => 'error', 'message' => 'Job title and company are required.']);
        exit;
    }
    if (empty($resumeText)) {
        echo json_encode(['status' => 'error', 'message' => 'Please complete your resume in the Resume Builder first.']);
        exit;
    }

    $userName = '';
    try {
        $us = $db->prepare("SELECT name FROM users WHERE id = :id LIMIT 1");
        $us->execute(['id' => $userId]);
        $userName = (string)($us->fetchColumn() ?: '');
    } catch (Throwable $e) {}

    $prompt = <<<PROMPT
You are an expert career coach and professional writer. Write a compelling cover letter for a job application.

CANDIDATE RESUME:
{$resumeText}

TARGET JOB:
- Position: {$jobTitle}
- Company: {$company}
- Job Description: {$jobDesc}

TONE: {$tone}
CANDIDATE NAME: {$userName}

INSTRUCTIONS:
1. Write a complete, ready-to-send cover letter (3-4 paragraphs).
2. Opening paragraph: Express enthusiasm for the specific role and company. Mention one specific thing about the company that excites you.
3. Middle paragraph(s): Connect 2-3 specific achievements from the candidate's resume to the job requirements. Use metrics where possible. Be specific, not generic.
4. Closing paragraph: Confident call-to-action. Express desire for interview.
5. Use a {$tone} tone throughout.
6. DO NOT use placeholder brackets like [Company Name] or [Your Name]. Fill everything in.
7. Keep it under 350 words.
8. Format: Start with "Dear Hiring Manager," and end with "Sincerely, {$userName}".
9. Return ONLY the cover letter text. No introduction, no meta-commentary.
PROMPT;

    $result = askAI($apiKey, $prompt, 0.75, 700);

    if (!empty($result)) {
        echo json_encode(['status' => 'success', 'data' => $result]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'AI service unavailable. Please try again.']);
    }
    exit;
}

// ════════════════════════════════════════════════════════════════════════════
// ACTION: interview_prep
// ════════════════════════════════════════════════════════════════════════════
if ($action === 'interview_prep') {
    $jobTitle  = trim($data['job_title'] ?? '');
    $company   = trim($data['company']   ?? '');
    $jobDesc   = trim($data['job_desc']  ?? '');
    $type      = trim($data['type']      ?? 'mixed'); // hr | technical | mixed

    if (empty($jobTitle)) {
        echo json_encode(['status' => 'error', 'message' => 'Job title is required.']);
        exit;
    }

    $resumeCtx = !empty($resumeText) ? "CANDIDATE RESUME:\n{$resumeText}\n\n" : '';

    $typeInstruction = match($type) {
        'hr'        => 'Focus ONLY on behavioral/HR questions (Tell me about yourself, strengths/weaknesses, teamwork, conflict resolution).',
        'technical' => 'Focus ONLY on technical questions specific to the role\'s tech stack and problem-solving scenarios.',
        default     => 'Mix 3 HR/behavioral questions and 4 technical questions.',
    };

    $prompt = <<<PROMPT
You are an expert interviewer and career coach. Generate a targeted interview preparation guide.

{$resumeCtx}TARGET ROLE: {$jobTitle} at {$company}
JOB DESCRIPTION: {$jobDesc}

{$typeInstruction}

Generate exactly 7 interview questions with detailed model answers. For each:
1. The QUESTION (realistic, specific to this role)
2. WHY ASKED (1 sentence — what the interviewer is evaluating)
3. MODEL ANSWER (3-5 sentences, tailored to the candidate's resume if provided, using STAR method for behavioral questions)
4. PRO TIP (1 sentence — what makes a great answer stand out)

Format EXACTLY like this for each question:
---
Q[N]: [Question text]
WHY: [Why this is asked]
ANSWER: [Model answer]
TIP: [Pro tip]
---

Be specific to the role. Use actual technologies from the job description. Make answers sound human and authentic.
PROMPT;

    $result = askAI($apiKey, $prompt, 0.8, 2000);

    if (!empty($result)) {
        echo json_encode(['status' => 'success', 'data' => $result]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'AI service unavailable. Please try again.']);
    }
    exit;
}

// ════════════════════════════════════════════════════════════════════════════
// ACTION: resume_critique
// ════════════════════════════════════════════════════════════════════════════
if ($action === 'resume_critique') {
    if (empty($resumeText)) {
        echo json_encode(['status' => 'error', 'message' => 'Please build your resume first before requesting AI analysis.']);
        exit;
    }

    $targetRole = trim($data['target_role'] ?? '');

    $roleContext = !empty($targetRole)
        ? "The candidate is targeting: {$targetRole} roles.\n"
        : "Provide general analysis for tech/software roles.\n";

    $prompt = <<<PROMPT
You are a senior HR professional and ATS expert with 15+ years of experience reviewing resumes at top tech companies. Provide a brutally honest but constructive resume critique.

RESUME CONTENT:
{$resumeText}

{$roleContext}

Provide a structured analysis in EXACTLY this JSON format (return only valid JSON, no markdown, no explanation):
{
  "overall_score": <integer 0-100>,
  "grade": "<Excellent|Good|Average|Needs Work>",
  "headline": "<One punchy sentence summarizing the resume's biggest strength or gap>",
  "sections": {
    "summary": { "score": <0-100>, "feedback": "<specific feedback>", "rewrite_suggestion": "<improved version if score < 80, else empty string>" },
    "experience": { "score": <0-100>, "feedback": "<specific feedback>", "top_improvement": "<single most important change>" },
    "skills": { "score": <0-100>, "feedback": "<specific feedback>", "missing_skills": ["<skill1>", "<skill2>", "<skill3>"] },
    "education": { "score": <0-100>, "feedback": "<specific feedback>" },
    "overall_format": { "score": <0-100>, "feedback": "<ATS compatibility assessment>" }
  },
  "top_strengths": ["<strength 1>", "<strength 2>", "<strength 3>"],
  "critical_fixes": ["<urgent fix 1>", "<urgent fix 2>", "<urgent fix 3>"],
  "quick_wins": ["<easy win 1 that takes <5 mins>", "<easy win 2>", "<easy win 3>"],
  "market_readiness": "<Ready to apply|Needs 1-2 weeks of work|Needs significant revision>",
  "recruiter_perspective": "<What a recruiter would think in the first 6 seconds of viewing this resume>"
}
PROMPT;

    $result = askAI($apiKey, $prompt, 0.3, 2000);

    if (!empty($result)) {
        // Try to extract clean JSON
        $result = preg_replace('/^```json\s*/i', '', $result);
        $result = preg_replace('/\s*```$/', '', $result);
        $result = trim($result);

        $parsed = json_decode($result, true);
        if (json_last_error() === JSON_ERROR_NONE && isset($parsed['overall_score'])) {
            echo json_encode(['status' => 'success', 'data' => $parsed]);
        } else {
            // AI returned non-JSON — wrap it
            echo json_encode(['status' => 'success', 'data' => ['raw' => $result]]);
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'AI service unavailable. Please try again.']);
    }
    exit;
}

// ════════════════════════════════════════════════════════════════════════════
// ACTION: skill_gap
// ════════════════════════════════════════════════════════════════════════════
if ($action === 'skill_gap') {
    $targetRole = trim($data['target_role'] ?? 'software developer');

    if (empty($resumeText)) {
        echo json_encode(['status' => 'error', 'message' => 'Please build your resume first.']);
        exit;
    }

    $prompt = <<<PROMPT
You are a senior tech career advisor. Analyze the candidate's current skills vs what is required for their target role in today's job market (2025-2026 India/Global).

CANDIDATE'S CURRENT PROFILE:
{$resumeText}

TARGET ROLE: {$targetRole}

Return ONLY valid JSON in EXACTLY this format (no markdown, no extra text):
{
  "target_role": "{$targetRole}",
  "readiness_score": <integer 0-100>,
  "readiness_label": "<Ready to Apply|Almost Ready|Needs 3-6 Months|Needs 6-12 Months>",
  "strengths": [
    { "skill": "<skill name>", "relevance": "<why this skill matters for the role>" }
  ],
  "critical_gaps": [
    { "skill": "<missing skill>", "priority": "critical|high|medium", "learn_in": "<realistic time estimate>", "free_resource": "<specific course/resource name>" }
  ],
  "learning_roadmap": [
    { "week": "Week 1-2", "focus": "<what to learn>", "action": "<specific thing to do>" },
    { "week": "Week 3-4", "focus": "<what to learn>", "action": "<specific thing to do>" },
    { "week": "Month 2", "focus": "<what to learn>", "action": "<specific thing to do>" },
    { "week": "Month 3", "focus": "<what to learn>", "action": "<specific thing to do>" }
  ],
  "project_suggestions": [
    { "title": "<project name>", "tech": "<technologies to use>", "why": "<how this fills the gap>" }
  ],
  "salary_range": { "current_profile": "<estimated range>", "after_upskilling": "<estimated range>" },
  "motivational_message": "<2-3 sentences of personalized encouragement based on their current profile>"
}
PROMPT;

    $result = askAI($apiKey, $prompt, 0.5, 2000);

    if (!empty($result)) {
        $result = preg_replace('/^```json\s*/i', '', $result);
        $result = preg_replace('/\s*```$/', '', $result);
        $result = trim($result);

        $parsed = json_decode($result, true);
        if (json_last_error() === JSON_ERROR_NONE && isset($parsed['readiness_score'])) {
            echo json_encode(['status' => 'success', 'data' => $parsed]);
        } else {
            echo json_encode(['status' => 'success', 'data' => ['raw' => $result]]);
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'AI service unavailable. Please try again.']);
    }
    exit;
}

// ════════════════════════════════════════════════════════════════════════════
// ACTION: job_match_ai
// ════════════════════════════════════════════════════════════════════════════
if ($action === 'job_match_ai') {
    $jobId    = (int) ($data['job_id'] ?? 0);
    $jobTitle = trim($data['job_title'] ?? '');
    $jobDesc  = trim($data['job_desc']  ?? '');
    $reqSkills = $data['req_skills'] ?? [];

    if ($jobId === 0 || empty($jobTitle)) {
        echo json_encode(['status' => 'error', 'message' => 'Job data is required.']);
        exit;
    }
    if (empty($resumeText)) {
        echo json_encode(['status' => 'error', 'message' => 'Please complete your resume first.']);
        exit;
    }

    $skillsList = is_array($reqSkills) ? implode(', ', $reqSkills) : $reqSkills;

    $prompt = <<<PROMPT
You are an expert ATS system and career advisor. Perform a deep compatibility analysis between a candidate's resume and a job posting.

CANDIDATE RESUME:
{$resumeText}

JOB POSTING:
- Title: {$jobTitle}
- Required Skills: {$skillsList}
- Description: {$jobDesc}

Return ONLY valid JSON in EXACTLY this format (no markdown, no extra text):
{
  "match_score": <integer 0-100>,
  "match_label": "<Excellent Match|Strong Match|Good Match|Fair Match|Low Match>",
  "match_color": "<green|blue|amber|red>",
  "verdict": "<2-sentence personalized verdict explaining the match score>",
  "matched_skills": ["<skill1>", "<skill2>"],
  "missing_skills": ["<skill1>", "<skill2>"],
  "transferable_skills": ["<skill or experience that partially covers a gap>"],
  "resume_gaps": [
    { "gap": "<what is missing>", "importance": "must-have|nice-to-have", "fix": "<specific actionable fix>" }
  ],
  "application_tips": [
    "<specific tip for this application>",
    "<specific tip 2>",
    "<specific tip 3>"
  ],
  "interview_likelihood": "<High|Medium|Low>",
  "interview_likelihood_reason": "<why>",
  "should_apply": <true|false>,
  "apply_reasoning": "<honest advice on whether to apply now or upskill first>"
}
PROMPT;

    $result = askAI($apiKey, $prompt, 0.4, 1500);

    if (!empty($result)) {
        $result = preg_replace('/^```json\s*/i', '', $result);
        $result = preg_replace('/\s*```$/', '', $result);
        $result = trim($result);

        $parsed = json_decode($result, true);
        if (json_last_error() === JSON_ERROR_NONE && isset($parsed['match_score'])) {
            echo json_encode(['status' => 'success', 'data' => $parsed]);
        } else {
            echo json_encode(['status' => 'success', 'data' => ['raw' => $result]]);
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'AI service unavailable. Please try again.']);
    }
    exit;
}

// ════════════════════════════════════════════════════════════════════════════
// ACTION: improve_bullet
// Quick action: improve a single resume bullet point
// ════════════════════════════════════════════════════════════════════════════
if ($action === 'improve_bullet') {
    $bullet = trim($data['bullet'] ?? '');
    $role   = trim($data['role'] ?? '');

    if (empty($bullet)) {
        echo json_encode(['status' => 'error', 'message' => 'Bullet text is required.']);
        exit;
    }

    $roleCtx = !empty($role) ? " for a {$role} role" : '';

    $prompt = "You are an expert ATS resume writer. Improve this resume bullet point{$roleCtx}. Make it more impactful using strong action verbs and quantified results. Return ONLY 3 improved versions, numbered 1-3. Each on its own line. No explanation.\n\nOriginal: {$bullet}";

    $result = askAI($apiKey, $prompt, 0.7, 300);

    if (!empty($result)) {
        echo json_encode(['status' => 'success', 'data' => $result]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'AI service unavailable.']);
    }
    exit;
}

// ── Unknown action ────────────────────────────────────────────────────────
echo json_encode(['status' => 'error', 'message' => 'Unknown AI action: ' . htmlspecialchars($action)]);
exit;
