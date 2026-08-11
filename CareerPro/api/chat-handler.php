<?php
declare(strict_types=1);
session_start();

/**
 * ============================================================================
 * CareerPro Suite — CareerBot AI Chat Handler v3.0
 * Priority chain:
 *   1. Gemini API key (from DB or config.php)
 *   2. Pollinations AI — free, no key (OpenAI-compatible)
 *   3. Rule-based smart fallback — always works offline
 * ============================================================================
 */

header('Content-Type: application/json; charset=utf-8');

/* ── Auth guard ── */
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Please log in to use CareerBot.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method.']);
    exit;
}

/* ── Parse input ── */
$raw     = (string) file_get_contents('php://input');
$data    = json_decode($raw, true) ?: [];
$userMsg = trim($data['message'] ?? '');
$history = is_array($data['history'] ?? null) ? $data['history'] : [];

if ($userMsg === '') {
    echo json_encode(['status' => 'error', 'message' => 'Empty message.']);
    exit;
}

/* ── Load API key ── */
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/config.php';

$apiKey = '';
try {
    $db  = Database::getInstance()->getConnection();
    $st  = $db->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'gemini_api_key' LIMIT 1");
    $st->execute();
    $apiKey = trim((string) ($st->fetchColumn() ?: ''));
} catch (Throwable $e) {
    error_log('CareerBot DB key error: ' . $e->getMessage());
}
if (empty($apiKey) && defined('GEMINI_API_KEY_FALLBACK') && GEMINI_API_KEY_FALLBACK !== '') {
    $apiKey = GEMINI_API_KEY_FALLBACK;
}

/* ── System prompt ── */
$SYSTEM = <<<SYS
You are CareerBot, an expert AI career advisor for students and job seekers.
Your role: help users write resumes, cover letters, prepare for interviews, and navigate the tech job market.
Tone: professional, encouraging, concise, practical.
Format: use **bold** for key terms, bullet lists with "-", "###" headers for long responses.
Keep responses under 350 words unless the user asks for something long.
Never give medical, legal, or financial advice. Stay focused on career topics.
SYS;

/* ── Build conversation for Gemini format ── */
$geminiContents = [];
foreach ($history as $turn) {
    $r = $turn['role'] ?? '';
    $t = trim($turn['text'] ?? '');
    if (in_array($r, ['user', 'model'], true) && $t !== '') {
        $geminiContents[] = ['role' => $r, 'parts' => [['text' => $t]]];
    }
}
$geminiContents[] = ['role' => 'user', 'parts' => [['text' => $userMsg]]];

/* ══════════════════════════════════════════════════════
   HELPER: cURL POST — handles SSL issues on Windows XAMPP
══════════════════════════════════════════════════════ */
function curlPost(string $url, array $payload, array $extraHeaders = [], int $timeout = 20): array {
    if (!function_exists('curl_init')) {
        return [0, '', 'cURL not available'];
    }
    $ch = curl_init($url);
    $headers = array_merge(['Content-Type: application/json', 'Accept: application/json'], $extraHeaders);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_CONNECTTIMEOUT => 8,
        // Fix SSL cert issues on Windows XAMPP
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 3,
    ]);
    $body    = curl_exec($ch);
    $err     = curl_error($ch);
    $code    = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return [$code, (string)($body ?: ''), $err];
}

/* ══════════════════════════════════════════════════════
   STRATEGY 1: Google Gemini API (standard AIzaSy... key)
══════════════════════════════════════════════════════ */
function tryGemini(string $key, array $contents, string $system): array {
    $models = [
        'gemini-1.5-flash',
        'gemini-1.5-flash-8b',
        'gemini-2.0-flash-lite',
        'gemini-2.0-flash',
        'gemini-pro',
    ];

    foreach ($models as $model) {
        $url = 'https://generativelanguage.googleapis.com/v1beta/models/'
             . $model . ':generateContent?key=' . urlencode($key);

        $payload = [
            'system_instruction' => ['parts' => [['text' => $system]]],
            'contents'           => $contents,
            'generationConfig'   => [
                'temperature'     => 0.75,
                'maxOutputTokens' => 800,
                'topP'            => 0.95,
            ],
        ];

        for ($try = 0; $try <= 1; $try++) {
            [$code, $body, $err] = curlPost($url, $payload);
            if ($err) { error_log("Gemini cURL err: $err"); break 2; }
            if ($code === 429 && $try === 0) { sleep(3); continue; }
            break;
        }

        if ($code === 200) {
            $j = json_decode($body, true);
            $t = $j['candidates'][0]['content']['parts'][0]['text'] ?? null;
            if ($t) return ['ok' => true, 'text' => trim($t)];
        }

        error_log("Gemini $model: HTTP $code — " . substr($body, 0, 200));

        if ($code === 429) {
            // Still rate limited after retry — try next model with longer wait
            sleep(5);
            continue;
        }

        // 403/404 = model unavailable, try next; 400/401 = bad request/auth, stop
        if ($code === 400 || $code === 401 || $code === 403) break;
    }
    return ['ok' => false];
}

/* ══════════════════════════════════════════════════════
   STRATEGY 2: Pollinations AI (free, no key needed)
   Uses their OpenAI-compatible endpoint
══════════════════════════════════════════════════════ */
function tryPollinations(array $contents, string $system): array {
    // Build OpenAI-style messages
    $messages = [['role' => 'system', 'content' => $system]];
    foreach ($contents as $turn) {
        $role     = ($turn['role'] === 'model') ? 'assistant' : 'user';
        $text     = $turn['parts'][0]['text'] ?? '';
        if ($text !== '') $messages[] = ['role' => $role, 'content' => $text];
    }

    $payload = [
        'model'       => 'openai',
        'messages'    => $messages,
        'temperature' => 0.75,
        'max_tokens'  => 800,
        'private'     => true,
        'seed'        => 42,
    ];

    // Try the OpenAI-compatible endpoint
    $endpoints = [
        'https://text.pollinations.ai/openai',
        'https://api.pollinations.ai/v1/chat/completions',
    ];

    foreach ($endpoints as $url) {
        [$code, $body, $err] = curlPost($url, $payload, [], 25);
        if ($err) { error_log("Pollinations cURL err ($url): $err"); continue; }

        if ($code === 200 && $body !== '') {
            // Try JSON parse first (OpenAI format)
            $j = json_decode($body, true);
            if (isset($j['choices'][0]['message']['content'])) {
                $t = trim($j['choices'][0]['message']['content']);
                if ($t !== '') return ['ok' => true, 'text' => $t];
            }
            // Plain text response
            $t = trim($body);
            if ($t !== '' && strlen($t) > 10) return ['ok' => true, 'text' => $t];
        }
        error_log("Pollinations $url: HTTP $code — " . substr($body, 0, 150));
    }
    return ['ok' => false];
}

/* ══════════════════════════════════════════════════════
   STRATEGY 3: Smart rule-based fallback
   Always works — no internet needed.
   Covers the most common career questions.
══════════════════════════════════════════════════════ */
function smartFallback(string $msg): array {
    $m = mb_strtolower(trim($msg), 'UTF-8');

    // Resume writing
    if (preg_match('/\b(resume|cv|curriculum vitae)\b/', $m)) {
        if (preg_match('/\b(summary|objective|profile)\b/', $m)) {
            return ['ok' => true, 'text' => "**Writing a Strong Resume Summary**\n\nYour summary should be 2-4 lines at the top of your resume. Use this formula:\n\n- **[Your role/degree]** + **[years of experience or key skills]** + **[biggest achievement or value you bring]**\n\n**Example:**\n\"Motivated BCA graduate with hands-on experience in PHP, React, and MySQL. Developed 3 full-stack projects during internship, improving system performance by 30%. Seeking a developer role where I can build scalable web applications.\"\n\n**Key tips:**\n- Use strong action words: *developed, built, led, optimized*\n- Include 2-3 relevant tech skills\n- Quantify achievements with numbers\n- Tailor it to each job description"];
        }
        if (preg_match('/\b(skill|technology|tech stack)\b/', $m)) {
            return ['ok' => true, 'text' => "**Skills Section Best Practices**\n\nOrganize your skills into clear categories:\n\n**Technical Skills:**\n- Programming Languages: PHP, Python, JavaScript, Java\n- Frameworks: React, Laravel, Node.js, Django\n- Databases: MySQL, MongoDB, PostgreSQL\n- Tools: Git, Docker, VS Code, Postman\n\n**Soft Skills:**\n- Team Collaboration, Problem Solving, Communication\n\n**Tips:**\n- List only skills you can confidently discuss in an interview\n- Match skills to the job description keywords\n- Order by proficiency (most proficient first)\n- ATS systems scan for exact keyword matches — use standard terms"];
        }
        return ['ok' => true, 'text' => "**Resume Writing Guide**\n\n**Essential Sections:**\n1. **Contact Info** — Email, phone, LinkedIn/GitHub, city\n2. **Professional Summary** — 3-4 impactful lines\n3. **Technical Skills** — Organized by category\n4. **Work Experience / Internships** — STAR format bullet points\n5. **Projects** — Name, tech stack, impact, GitHub link\n6. **Education** — Degree, institution, CGPA, year\n7. **Certifications** — AWS, Google, Coursera, etc.\n\n**Common Mistakes to Avoid:**\n- Generic summaries with no specifics\n- Listing skills you can't demonstrate\n- No quantified achievements (add numbers!)\n- Canva templates with tables (ATS can't read them)\n\nWant help with a specific section?"];
    }

    // Interview preparation
    if (preg_match('/\b(interview|hr round|technical round|hired|hiring)\b/', $m)) {
        if (preg_match('/\b(hr|behavioral|soft|tell me about)\b/', $m)) {
            return ['ok' => true, 'text' => "**Top 5 HR Interview Questions & How to Answer**\n\n**1. \"Tell me about yourself\"**\nFormula: Present role/education → key skills/experience → why you're a fit\n\n**2. \"What are your strengths?\"**\nPick 2-3 relevant to the job. Back each with a specific example.\n\n**3. \"What is your greatest weakness?\"**\nMention a real weakness you've *actively improved*. Show self-awareness.\n\n**4. \"Why do you want this role?\"**\nResearch the company. Connect their mission to your goals.\n\n**5. \"Where do you see yourself in 5 years?\"**\nShow ambition but stay realistic. Align with career growth at the company.\n\n**Golden Rule:** Use the **STAR method** — Situation, Task, Action, Result."];
        }
        if (preg_match('/\b(technical|coding|dsa|algorithm|data structure)\b/', $m)) {
            return ['ok' => true, 'text' => "**Technical Interview Preparation Guide**\n\n**DSA Topics to Master:**\n- Arrays, Strings, Linked Lists\n- Stacks, Queues, Trees, Graphs\n- Sorting & Searching (Binary Search is a must)\n- Dynamic Programming (start with simple DP)\n- Hash Maps & Sets\n\n**Practice Platforms:**\n- LeetCode (start with Easy → Medium)\n- GeeksForGeeks (theory + practice)\n- HackerRank (language-specific tracks)\n\n**For Web Dev Roles:**\n- Deep dive into your primary language (PHP/JS/Python)\n- Understand HTTP, REST APIs, SQL optimization\n- Know your framework (Laravel, React, etc.) internals\n\n**Interview Day Tips:**\n- Think out loud — explain your approach before coding\n- Ask clarifying questions\n- Test with edge cases\n- Practice on a whiteboard or plain editor"];
        }
        return ['ok' => true, 'text' => "**Interview Preparation Checklist**\n\n**Before the Interview:**\n- Research the company (product, culture, recent news)\n- Re-read the job description — match your answers to it\n- Prepare 3-5 STAR stories from your projects/internships\n- Practice 5 common HR questions out loud\n- Prepare 2-3 smart questions to ask the interviewer\n\n**During the Interview:**\n- Greet confidently, maintain eye contact\n- Use STAR format for behavioral questions\n- For technical questions, think aloud\n- If stuck, ask for a hint rather than staying silent\n\n**After:**\n- Send a thank-you email within 24 hours\n- Reflect on questions you struggled with\n\nWhat type of interview are you preparing for? I can give more specific help!"];
    }

    // Cover letter
    if (preg_match('/\b(cover letter|covering letter|application letter)\b/', $m)) {
        return ['ok' => true, 'text' => "**Cover Letter Template for Tech Roles**\n\n---\n**[Your Name]** | [Email] | [Phone] | [Date]\n\nHiring Manager\n[Company Name]\n\nDear Hiring Manager,\n\nI am writing to express my interest in the **[Job Title]** position at **[Company]**. As a [degree] graduate with [X] months of experience in [key skill], I am excited about the opportunity to contribute to your team.\n\nDuring my [internship/project], I [specific achievement with numbers]. This experience strengthened my skills in [relevant skills], which I believe aligns directly with your requirement for [requirement from JD].\n\nI am particularly drawn to [Company] because [specific reason — their product, mission, or culture].\n\nI would welcome the opportunity to discuss how my background fits your needs. Thank you for your time.\n\nSincerely,\n[Your Name]\n\n---\n\n**Tips:** Keep it under 300 words. Always customize the middle paragraph for each company."];
    }

    // Skill roadmap / career path
    if (preg_match('/\b(skill|roadmap|career path|learn|technology|what to learn|future)\b/', $m)) {
        return ['ok' => true, 'text' => "**Skills Roadmap for Fresh CS/BCA/MCA Graduates (2025)**\n\n**Web Development Path:**\n- HTML/CSS → JavaScript → React or Vue\n- PHP/Node.js/Python for backend\n- MySQL + one NoSQL database\n- REST APIs, Git, basic Docker\n\n**Data Science/ML Path:**\n- Python (NumPy, Pandas)\n- SQL + data visualization (Matplotlib, Tableau)\n- ML basics (scikit-learn, regression, classification)\n- Deep Learning intro (TensorFlow/PyTorch)\n\n**High-Demand Certifications (Free/Cheap):**\n- Google: IT Support, Data Analytics (Coursera)\n- AWS Cloud Practitioner (foundational)\n- Meta Front-End Developer (Coursera)\n- GitHub Foundations\n\n**Pro Tips:**\n- Build 2-3 portfolio projects with GitHub links\n- Contribute to open source (even documentation)\n- Get 1 internship before final year — even 1-2 months counts\n\nWhich career path interests you most?"];
    }

    // LinkedIn
    if (preg_match('/\b(linkedin|profile|networking)\b/', $m)) {
        return ['ok' => true, 'text' => "**LinkedIn Profile Optimization Guide**\n\n**Must-Have Elements:**\n- **Professional photo** — clear, plain background, formal attire\n- **Headline** — don't just put 'Student'. Try: \"Aspiring Full-Stack Developer | PHP, React, MySQL\"\n- **About section** — 3-5 lines, first-person, include top skills and goals\n- **Featured section** — pin your best project or GitHub\n- **Experience** — even internships, side projects count\n- **Skills** — add 10+ skills and get endorsements\n\n**Growth Tips:**\n- Connect with alumni and professionals in your field\n- Post 2-3 times per week (project updates, learning insights)\n- Comment on posts in your niche — be visible\n- Use 'Open to Work' (visible to recruiters only option)\n- Message recruiters with a short, specific pitch\n\nWant me to write your LinkedIn headline or About section?"];
    }

    // Salary / negotiation
    if (preg_match('/\b(salary|package|ctc|negotiate|offer|lpa)\b/', $m)) {
        return ['ok' => true, 'text' => "**Salary Negotiation Guide for Fresh Graduates**\n\n**Realistic Expectations (India, 2025):**\n- Fresh BCA/B.Tech (tier-2 college): ₹2.5 – 5 LPA\n- With strong skills + internship: ₹4 – 7 LPA\n- Niche skills (ML, Cloud, Full-stack): ₹5 – 10 LPA\n\n**How to Negotiate:**\n1. Always let them make the first offer\n2. Research market rates on Glassdoor, Levels.fyi, AmbitionBox\n3. Counter with a range: \"Based on my research and skills, I was expecting ₹X–Y\"\n4. Negotiate on joining bonus or role if base is fixed\n5. Never lie about existing offers — it backfires\n\n**What to Evaluate Beyond Salary:**\n- Learning & growth opportunities\n- Tech stack (will it keep you employable?)\n- Team culture and manager quality\n- Work-from-home flexibility\n\nFocus on building skills now — your salary will follow."];
    }

    // Greeting
    if (preg_match('/^(hi|hello|hey|good morning|good evening|namaste|hii+)\b/', $m)) {
        return ['ok' => true, 'text' => "Hello! 👋 I'm **CareerBot**, your AI career advisor.\n\nI can help you with:\n- ✍️ **Resume writing** — summaries, skills, bullet points\n- 🎤 **Interview prep** — HR & technical questions\n- 📄 **Cover letters** — tailored templates\n- 🚀 **Career roadmap** — skills to learn, certifications\n- 💼 **LinkedIn optimization** — profile tips\n- 💰 **Salary guidance** — negotiation tips\n\nWhat would you like help with today?"];
    }

    // Thank you
    if (preg_match('/\b(thank|thanks|helpful|great|awesome|perfect)\b/', $m)) {
        return ['ok' => true, 'text' => "You're welcome! 😊 That's what I'm here for.\n\nFeel free to ask me anything else about your resume, interviews, or career planning. **Best of luck with your job search!**"];
    }

    // Default
    return ['ok' => true, 'text' => "I'm CareerBot, your AI career advisor! I'm best at helping with:\n\n- **Resume writing** — summaries, skills sections, bullet points\n- **Interview preparation** — HR & technical rounds\n- **Cover letters** — professional templates\n- **Career roadmaps** — what skills to learn\n- **LinkedIn profiles** — stand out to recruiters\n- **Salary negotiation** — know your worth\n\nCould you rephrase your question or pick one of the topics above? I want to give you the most helpful answer possible! 🎯"];
}

/* ══════════════════════════════════════════════════════
   EXECUTE CHAIN
══════════════════════════════════════════════════════ */
$result = ['ok' => false];

// 1. Gemini API (supports both legacy AIza... and new AQ. key formats)
if (!empty($apiKey) && (str_starts_with($apiKey, 'AIza') || str_starts_with($apiKey, 'AQ.'))) {
    $result = tryGemini($apiKey, $geminiContents, $SYSTEM);
}

// 2. Pollinations AI free fallback
if (!$result['ok']) {
    error_log('CareerBot: trying Pollinations AI fallback');
    $result = tryPollinations($geminiContents, $SYSTEM);
}

// 3. Smart rule-based fallback (always works)
if (!$result['ok']) {
    error_log('CareerBot: using rule-based smart fallback');
    $result = smartFallback($userMsg);
}

/* ── Respond ── */
if ($result['ok']) {
    echo json_encode(['status' => 'success', 'data' => $result['text']], JSON_UNESCAPED_UNICODE);
} else {
    echo json_encode(['status' => 'error', 'message' => 'AI service unavailable. Please try again shortly.']);
}
exit;
