<?php
declare(strict_types=1);
session_start();

/**
 * ============================================================================
 * CareerPro Suite — Dynamic ATS Resume Scanner v2.0
 * POST /api/ats-scanner.php  (multipart/form-data, field: resume_file)
 * Pure PHP — no external dependencies required.
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

/* ── File validation ─────────────────────────────────────────────── */
$uploadErr = $_FILES['resume_file']['error'] ?? UPLOAD_ERR_NO_FILE;
if ($uploadErr !== UPLOAD_ERR_OK) {
    $msg = match($uploadErr) {
        UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'File too large (max 5 MB).',
        UPLOAD_ERR_NO_FILE => 'No file uploaded.',
        default => 'Upload error. Please try again.',
    };
    echo json_encode(['status' => 'error', 'message' => $msg]);
    exit;
}

$file = $_FILES['resume_file'];
if ($file['size'] > 5 * 1024 * 1024) {
    echo json_encode(['status' => 'error', 'message' => 'File exceeds the 5 MB limit.']);
    exit;
}

$ext = strtolower(pathinfo((string)$file['name'], PATHINFO_EXTENSION));
if (!in_array($ext, ['pdf', 'docx'], true)) {
    echo json_encode(['status' => 'error', 'message' => 'Unsupported file type. Upload PDF or DOCX only.']);
    exit;
}

/* ── Text extraction ─────────────────────────────────────────────── */
$tmpPath = $file['tmp_name'];
$text    = '';

try {
    if ($ext === 'docx') {
        if (class_exists('ZipArchive')) {
            $zip = new ZipArchive();
            if ($zip->open($tmpPath) === true) {
                $xml = $zip->getFromName('word/document.xml');
                $zip->close();
                if ($xml !== false) {
                    // Preserve paragraph breaks
                    $xml = str_replace(['</w:p>', '</w:tr>'], ["\n\n", "\n"], (string)$xml);
                    $xml = str_replace(['</w:r>', '<w:tab/>'], [' ', "\t"], $xml);
                    $text = html_entity_decode(strip_tags($xml), ENT_QUOTES | ENT_HTML5, 'UTF-8');
                }
            }
        }

    } elseif ($ext === 'pdf') {
        // Try pdftotext first (available on some XAMPP setups)
        if (function_exists('shell_exec') && !str_contains(PHP_OS, 'WIN')) {
            $escaped = escapeshellarg($tmpPath);
            $out = (string) shell_exec("pdftotext -layout {$escaped} - 2>/dev/null");
            if (!empty(trim($out))) {
                $text = $out;
            }
        }

        // Pure-PHP PDF text extraction (handles compressed streams via gzuncompress)
        if (empty(trim($text))) {
            $raw = (string) file_get_contents($tmpPath);

            // Step 1: Decompress FlateDecode streams
            $decompressed = $raw;
            $streamData = [];
            preg_match_all('/stream\r?\n(.*?)\r?\nendstream/s', $raw, $streams);
            foreach ($streams[1] as $stream) {
                // Try gzuncompress (zlib) for FlateDecode
                $decoded = @gzuncompress($stream);
                if ($decoded === false) {
                    // Try gzinflate (raw deflate)
                    $decoded = @gzinflate($stream);
                }
                if ($decoded !== false && strlen($decoded) > 10) {
                    $streamData[] = $decoded;
                } else {
                    $streamData[] = $stream; // use raw if decompression fails
                }
            }
            $allContent = implode("\n", $streamData) ?: $raw;

            // Step 2: Extract text from BT...ET blocks
            $lines = [];
            preg_match_all('/BT(.*?)ET/s', $allContent, $btBlocks);
            foreach ($btBlocks[1] as $block) {
                // Handle TJ arrays: [(text) spacing (text)] TJ
                preg_match_all('/\[([^\]]*)\]\s*TJ/s', $block, $tjArr);
                foreach ($tjArr[1] as $arr) {
                    preg_match_all('/\(([^)\\\\]*(?:\\\\.[^)\\\\]*)*)\)/', $arr, $parts);
                    $chunk = '';
                    foreach ($parts[1] as $p) {
                        $p = stripcslashes($p);
                        $p = preg_replace('/[^\x20-\x7E]/', '', $p);
                        $chunk .= $p;
                    }
                    if (trim($chunk)) $lines[] = trim($chunk);
                }
                // Handle Tj: (text) Tj
                preg_match_all('/\(([^)\\\\]*(?:\\\\.[^)\\\\]*)*)\)\s*Tj/s', $block, $tjM);
                foreach ($tjM[1] as $m) {
                    $m = stripcslashes($m);
                    $m = preg_replace('/[^\x20-\x7E]/', '', $m);
                    if (trim($m)) $lines[] = trim($m);
                }
                // Handle ' and " operators (newline + show)
                preg_match_all('/\(([^)\\\\]*(?:\\\\.[^)\\\\]*)*)\)\s*[\'\"]/s', $block, $quoteM);
                foreach ($quoteM[1] as $m) {
                    $m = stripcslashes($m);
                    $m = preg_replace('/[^\x20-\x7E]/', '', $m);
                    if (trim($m)) $lines[] = trim($m);
                }
            }

            // Step 3: If BT/ET gave nothing, try extracting all string literals
            if (empty($lines)) {
                preg_match_all('/\(([^)\\\\]{2,}(?:\\\\.[^)\\\\]*)*)\)/', $allContent, $allStrings);
                foreach ($allStrings[1] as $s) {
                    $s = stripcslashes($s);
                    $s = preg_replace('/[^\x20-\x7E]/', '', $s);
                    if (strlen(trim($s)) >= 3) $lines[] = trim($s);
                }
            }

            $text = implode("\n", $lines);
        }
    }
} catch (Throwable $e) {
    error_log('ATS extract error: ' . $e->getMessage());
}

$text = preg_replace('/[ \t]+/', ' ', trim($text)) ?: '';
$text = preg_replace('/\n{3,}/', "\n\n", $text) ?: '';

if (empty(trim($text))) {
    echo json_encode(['status' => 'error', 'message' => 'Could not extract text from this file. Make sure the PDF is not scanned/image-only. Try uploading a DOCX version instead.']);
    exit;
}

/* ══════════════════════════════════════════════════════════════════
   ANALYSIS ENGINE
══════════════════════════════════════════════════════════════════ */
$lower = mb_strtolower($text, 'UTF-8');
$lines = array_values(array_filter(array_map('trim', explode("\n", $text)), fn($l) => strlen($l) > 0));

// Word count (accurate)
preg_match_all('/\b\w+\b/', $lower, $wordMatches);
$words     = $wordMatches[0];
$wordCount = count($words);

// ── 1. CONTACT INFORMATION ────────────────────────────────────────
$hasEmail    = (bool) preg_match('/[\w.+\-]+@[\w\-]+\.[a-z]{2,}/i', $text);
$hasPhone    = (bool) preg_match('/(\+?\d[\d\s\-().]{7,}\d)/', $text);
$hasLinkedin = (bool) preg_match('/linkedin\.com\/in\//i', $text) || str_contains($lower, 'linkedin');
$hasGithub   = (bool) preg_match('/github\.com\//i', $text) || str_contains($lower, 'github');
$hasLocation = (bool) preg_match('/\b(city|state|india|delhi|mumbai|bangalore|hyderabad|chennai|pune|ludhiana|chandigarh|[a-z]+,\s*(punjab|haryana|up|maharashtra|karnataka|hp))\b/i', $text);
$hasPortfolio = (bool) preg_match('/(portfolio|website|www\.|http)/i', $text);

$contactPoints = ($hasEmail ? 2 : 0) + ($hasPhone ? 2 : 0) + (($hasLinkedin || $hasGithub) ? 2 : 0) + ($hasLocation ? 1 : 0) + ($hasPortfolio ? 1 : 0);
$contactMax    = 8;
$contactPct    = min(100, (int)round($contactPoints / $contactMax * 100));
$contactStatus = $contactPct >= 75 ? 'pass' : ($contactPct >= 40 ? 'warn' : 'fail');

$missingContact = [];
if (!$hasEmail)    $missingContact[] = 'email';
if (!$hasPhone)    $missingContact[] = 'phone';
if (!$hasLinkedin && !$hasGithub) $missingContact[] = 'LinkedIn/GitHub';
$contactDetail = empty($missingContact) ? 'All contact details present' : 'Missing: ' . implode(', ', $missingContact);

// ── 2. PROFESSIONAL SUMMARY ──────────────────────────────────────
$summaryPatterns = [
    '/\b(professional\s+summary|career\s+(objective|summary)|executive\s+summary|about\s+me|profile\s+summary|summary\s+of\s+qualifications?|objective)\b/i',
];
$hasSummary = false;
foreach ($summaryPatterns as $p) {
    if (preg_match($p, $text)) { $hasSummary = true; break; }
}
// Check first block for a summary paragraph (3+ sentences)
$firstBlock = implode(' ', array_slice($lines, 0, 8));
$sentenceCount = preg_match_all('/[.!?]+/', $firstBlock);
if ($sentenceCount >= 3 && str_word_count($firstBlock) >= 30) $hasSummary = true;

// Quality: does summary contain value-words?
$summaryValueWords = ['experienced','skilled','passionate','motivated','results','proven','expertise','professional','specializ','dedicated'];
$summaryValueHits  = 0;
foreach ($summaryValueWords as $w) {
    if (str_contains($lower, $w)) $summaryValueHits++;
}
$summaryPct    = $hasSummary ? min(100, 60 + $summaryValueHits * 8) : 0;
$summaryStatus = $summaryPct >= 75 ? 'pass' : ($summaryPct > 0 ? 'warn' : 'fail');
$summaryDetail = $hasSummary
    ? ($summaryValueHits >= 2 ? 'Strong summary with impact language' : 'Summary found — add more value keywords')
    : 'No professional summary/objective found';

// ── 3. WORK EXPERIENCE ────────────────────────────────────────────
$expSectionPatterns = [
    '/\b(work\s+experience|professional\s+experience|employment(\s+history)?|career\s+history|positions?\s+held|work\s+history|internship(s)?|experience)\b/i'
];
$hasExpSection = false;
foreach ($expSectionPatterns as $p) {
    if (preg_match($p, $text)) { $hasExpSection = true; break; }
}
// Date range patterns: 2020 - 2023, Jan 2021 – Present, etc.
$dateRangeCount = preg_match_all(
    '/\b(jan|feb|mar|apr|may|jun|jul|aug|sep|oct|nov|dec)?\.?\s*(20\d{2}|19\d{2})\s*[-–—to]+\s*(jan|feb|mar|apr|may|jun|jul|aug|sep|oct|nov|dec)?\.?\s*(20\d{2}|19\d{2}|present|current|now|till\s+date)\b/i',
    $text
);
// Job-title indicators
$jobTitleWords = ['developer','engineer','analyst','designer','manager','intern','assistant','consultant','lead','architect','officer','coordinator','specialist','associate','executive'];
$jobTitleMatches = 0;
foreach ($jobTitleWords as $jt) {
    $jobTitleMatches += preg_match_all('/\b' . preg_quote($jt, '/') . '\b/i', $text);
}

$expScore  = ($hasExpSection ? 20 : 0) + min($dateRangeCount * 15, 45) + min($jobTitleMatches * 5, 35);
$expPct    = min(100, $expScore);
$expStatus = $expPct >= 75 ? 'pass' : ($expPct >= 35 ? 'warn' : 'fail');
$expDetail = $expPct >= 75 ? "$dateRangeCount experience entries with clear dates"
           : ($hasExpSection ? ($dateRangeCount > 0 ? "$dateRangeCount entries found — add more detail" : 'Section found but no date ranges') : 'No work experience section detected');

// ── 4. SKILLS & KEYWORDS ──────────────────────────────────────────
$techCategories = [
    'Languages'      => ['php','javascript','python','java','c++','c#','c','ruby','swift','kotlin','typescript','go','golang','rust','scala','r','matlab','bash','shell','perl','dart'],
    'Frontend'       => ['react','angular','vue','next.js','nuxt','svelte','html','css','tailwind','bootstrap','sass','scss','webpack','vite','jquery','redux','material-ui'],
    'Backend'        => ['node','express','laravel','django','flask','spring','asp.net','fastapi','rails','symfony','codeigniter','nestjs','strapi'],
    'Databases'      => ['mysql','postgresql','mongodb','sqlite','redis','firebase','oracle','sql server','cassandra','dynamodb','elasticsearch','mariadb'],
    'Cloud & DevOps' => ['aws','azure','gcp','docker','kubernetes','jenkins','github actions','ci/cd','terraform','ansible','linux','nginx','apache','heroku','vercel','netlify'],
    'Mobile'         => ['android','ios','flutter','react native','xamarin','kotlin','swift'],
    'Data & AI'      => ['machine learning','deep learning','tensorflow','pytorch','keras','pandas','numpy','scikit-learn','tableau','power bi','data analysis','nlp','computer vision'],
    'Tools'          => ['git','github','gitlab','bitbucket','jira','confluence','figma','postman','vs code','intellij','xcode','android studio','photoshop','illustrator'],
    'Methods'        => ['agile','scrum','kanban','tdd','bdd','rest','graphql','microservices','mvc','oop','solid','devops','cloud native'],
];
$softSkillsList = ['leadership','communication','teamwork','problem.solving','analytical','creativity','management','adaptability','critical thinking','time management','collaboration','attention to detail','presentation','interpersonal','negotiation','multitasking'];

$foundByCategory = [];
$allFoundTech    = [];
foreach ($techCategories as $cat => $skills) {
    $catFound = [];
    foreach ($skills as $s) {
        if (str_contains($lower, $s)) {
            $catFound[]    = $s;
            $allFoundTech[] = $s;
        }
    }
    if (!empty($catFound)) $foundByCategory[$cat] = $catFound;
}

$foundSoft = [];
foreach ($softSkillsList as $s) {
    $pattern = '/\b' . preg_quote(str_replace('.', ' ', $s), '/') . '\b/i';
    if (preg_match($pattern, $lower)) $foundSoft[] = str_replace('.', ' ', $s);
}

$hasSkillSection = (bool) preg_match('/\b(technical\s+skills?|skills?\s+&?\s*(competencies|set)|core\s+competencies|technologies|stack|expertise|proficiencies)\b/i', $text);
$uniqueTechCount = count($allFoundTech);
$catCount        = count($foundByCategory);

$skillPct    = min(100, ($uniqueTechCount >= 10 ? 100 : ($uniqueTechCount >= 6 ? 80 : ($uniqueTechCount >= 3 ? 55 : $uniqueTechCount * 12))) + ($hasSkillSection ? 10 : 0));
$skillStatus = $skillPct >= 75 ? 'pass' : ($skillPct >= 40 ? 'warn' : 'fail');
$skillDetail = $uniqueTechCount >= 8 ? "$uniqueTechCount skills across $catCount categories"
             : ($uniqueTechCount > 0 ? "$uniqueTechCount skills found — add more tech keywords" : 'No recognisable skills detected');

// ── 5. EDUCATION ──────────────────────────────────────────────────
$eduPatterns = [
    '/\b(education|academic(s)?|qualification(s)?|degree|bachelor|master|b\.?tech|b\.?sc|m\.?sc|mca|bca|b\.?e\.?|m\.?e\.?|b\.?com|m\.?com|b\.?a\.?|m\.?a\.?|ph\.?d|diploma|university|college|institute|school|graduation|cgpa|gpa|percentage|10th|12th|matriculation|intermediate|class\s+x|class\s+xii)\b/i'
];
$hasEdu = false;
foreach ($eduPatterns as $p) {
    if (preg_match($p, $text)) { $hasEdu = true; break; }
}
$hasGPA     = (bool) preg_match('/\b(cgpa|gpa|percentage|marks?|grade)\s*[:\-]?\s*[\d.]+/i', $text);
$hasYear    = (bool) preg_match('/\b(20\d{2}|19\d{2})\b/', $text);
$eduPct     = ($hasEdu ? 60 : 0) + ($hasGPA ? 20 : 0) + ($hasYear ? 20 : 0);
$eduStatus  = $eduPct >= 75 ? 'pass' : ($eduPct >= 40 ? 'warn' : 'fail');
$eduDetail  = $hasEdu ? ($hasGPA ? 'Education with GPA/percentage found' : 'Education section found — add CGPA/grades') : 'No education section detected';

// ── 6. ACTION VERBS ───────────────────────────────────────────────
$actionVerbGroups = [
    'Leadership'  => ['led','managed','directed','supervised','oversaw','spearheaded','championed','mentored','coached','guided','founded','established'],
    'Building'    => ['developed','built','designed','created','architected','engineered','implemented','deployed','launched','shipped','released'],
    'Improving'   => ['improved','optimized','optimised','enhanced','streamlined','reduced','increased','accelerated','automated','modernized','refactored','upgraded'],
    'Achieving'   => ['achieved','delivered','exceeded','surpassed','earned','secured','won','attained','accomplished','completed'],
    'Collaboration'=> ['collaborated','partnered','coordinated','facilitated','communicated','presented','negotiated','integrated','aligned'],
    'Analysis'    => ['analysed','analyzed','researched','investigated','evaluated','assessed','identified','diagnosed','audited','reviewed','monitored'],
    'Other'       => ['maintained','supported','resolved','fixed','troubleshot','documented','trained','onboarded','migrated'],
];

$foundVerbsByGroup = [];
$allFoundVerbs     = [];
foreach ($actionVerbGroups as $group => $verbs) {
    $gFound = [];
    foreach ($verbs as $v) {
        if (preg_match('/\b' . preg_quote($v, '/') . '\b/i', $lower)) {
            $gFound[]       = $v;
            $allFoundVerbs[] = $v;
        }
    }
    if (!empty($gFound)) $foundVerbsByGroup[$group] = $gFound;
}

$verbCount  = count($allFoundVerbs);
$verbPct    = min(100, ($verbCount >= 8 ? 100 : ($verbCount >= 5 ? 80 : ($verbCount >= 3 ? 60 : $verbCount * 15))));
$verbStatus = $verbPct >= 75 ? 'pass' : ($verbPct >= 40 ? 'warn' : 'fail');
$verbDetail = $verbCount >= 8 ? "$verbCount strong action verbs found"
            : ($verbCount > 0 ? "$verbCount verbs — aim for 8+ across different categories" : 'No action verbs detected');

// ── 7. QUANTIFIED ACHIEVEMENTS ───────────────────────────────────
// More comprehensive pattern for numbers + impact context
$quantPatterns = [
    '/\b\d+\s*(%|percent|x\b|times)/i',
    '/\b\d+\s*(users?|students?|customers?|clients?|employees?|teams?|members?)/i',
    '/\b\d+\s*(projects?|applications?|systems?|modules?|features?|bugs?|issues?|tickets?)/i',
    '/\b\d+\s*(hours?|days?|weeks?|months?|years?)/i',
    '/[₹\$£€]\s*[\d,k]+/i',
    '/\b(lakh|crore|million|thousand)\b/i',
    '/\b\d+\s*(lpa|ctc|salary|package)/i',
    '/\breduced\s+[a-z\s]+by\s+\d+/i',
    '/\bincreased\s+[a-z\s]+by\s+\d+/i',
    '/\bimproved\s+[a-z\s]+by\s+\d+/i',
];
$quantCount = 0;
foreach ($quantPatterns as $qp) {
    $quantCount += preg_match_all($qp, $text);
}
$quantPct    = min(100, ($quantCount >= 5 ? 100 : ($quantCount >= 3 ? 80 : ($quantCount >= 1 ? 50 : 0))));
$quantStatus = $quantPct >= 75 ? 'pass' : ($quantPct >= 40 ? 'warn' : 'fail');
$quantDetail = $quantCount >= 5 ? "$quantCount quantified achievements — excellent!"
             : ($quantCount > 0 ? "$quantCount metrics found — aim for 5+" : 'No numbers or metrics detected');

// ── 8. ATS FORMATTING ────────────────────────────────────────────
$tableCount      = preg_match_all('/\|\s*\w[\w\s]+\s*\|/', $text);
$columnCount     = 0;
foreach ($lines as $ln) {
    if (preg_match('/^.{10,}\s{6,}.{10,}$/', $ln)) $columnCount++;
}
$hasSpecialBoxes = (bool) preg_match('/[■□●○★☆✓✗✦✧]+/', $text);
$formatIssues    = 0;
if ($tableCount > 3)   $formatIssues++;
if ($columnCount > 15) $formatIssues++;
if ($hasSpecialBoxes)  $formatIssues++;

// Length quality
$lengthQuality = 'good';
if ($wordCount < 100)       $lengthQuality = 'too_short';
elseif ($wordCount > 1000)  $lengthQuality = 'too_long';
elseif ($wordCount < 200)   $lengthQuality = 'short';

if ($lengthQuality === 'too_short') $formatIssues += 2;
elseif ($lengthQuality === 'short') $formatIssues++;

$formatPct    = max(0, 100 - $formatIssues * 25);
$formatStatus = $formatPct >= 75 ? 'pass' : ($formatPct >= 40 ? 'warn' : 'fail');
$formatDetail = $formatIssues === 0 ? "Clean format, ~$wordCount words — ATS-ready"
              : ($formatIssues === 1 ? "Minor issues ($wordCount words) — review layout"
              : "Multiple issues: check tables/columns/length ($wordCount words)");

/* ══════════════════════════════════════════════════════════════════
   WEIGHTED SCORING
══════════════════════════════════════════════════════════════════ */
$scoring = [
    ['key'=>'contact',    'pct'=>$contactPct,  'weight'=>0.12],
    ['key'=>'summary',    'pct'=>$summaryPct,  'weight'=>0.10],
    ['key'=>'experience', 'pct'=>$expPct,      'weight'=>0.25],
    ['key'=>'skills',     'pct'=>$skillPct,    'weight'=>0.22],
    ['key'=>'education',  'pct'=>$eduPct,      'weight'=>0.10],
    ['key'=>'verbs',      'pct'=>$verbPct,     'weight'=>0.10],
    ['key'=>'quant',      'pct'=>$quantPct,    'weight'=>0.07],
    ['key'=>'format',     'pct'=>$formatPct,   'weight'=>0.04],
];

$weightedSum = 0;
foreach ($scoring as $s) {
    $weightedSum += $s['pct'] * $s['weight'];
}
$score = min(100, max(0, (int) round($weightedSum)));

$grade = match(true) {
    $score >= 85 => 'Excellent',
    $score >= 70 => 'Good',
    $score >= 50 => 'Average',
    default      => 'Poor',
};

$gradeIcon = match($grade) {
    'Excellent' => '🏆',
    'Good'      => '✅',
    'Average'   => '⚠️',
    'Poor'      => '❌',
};

$summaryMsg = match($grade) {
    'Excellent' => "Outstanding resume! Your structure, keywords, and achievements are well-optimised for ATS systems. Small tweaks could push it to perfection.",
    'Good'      => "Strong resume with solid content. A few targeted improvements — more metrics and keywords — will maximise your ATS match rate.",
    'Average'   => "Your resume has potential but needs work. Focus on adding measurable achievements, stronger action verbs, and expanding your skills section.",
    'Poor'      => "This resume needs significant improvement. Ensure all key sections are present with clear structure, contact details, and relevant keywords.",
};

/* ── Strengths ── */
$strengths = [];
if ($contactPct >= 75)    $strengths[] = 'Complete contact information (' . ($hasEmail ? 'email' : '') . ($hasPhone ? ', phone' : '') . ')';
if ($hasSummary && $summaryValueHits >= 2) $strengths[] = 'Compelling professional summary with impact language';
elseif ($hasSummary)      $strengths[] = 'Professional summary present';
if ($expPct >= 75)        $strengths[] = "$dateRangeCount work experience entries with clear date ranges";
if ($uniqueTechCount >= 8) $strengths[] = "$uniqueTechCount technical skills across $catCount categories";
if ($verbCount >= 8)      $strengths[] = "Strong action vocabulary ($verbCount power verbs)";
if ($quantCount >= 3)     $strengths[] = "$quantCount quantified achievements showing measurable impact";
if ($hasEdu && $hasGPA)   $strengths[] = 'Education with academic metrics (GPA/percentage)';
if ($formatIssues === 0 && $wordCount >= 200) $strengths[] = "Clean ATS-compatible format (~$wordCount words)";
if ($hasLinkedin)         $strengths[] = 'LinkedIn profile included for recruiter verification';
if ($hasGithub)           $strengths[] = 'GitHub profile shows technical portfolio';
if (empty($strengths))    $strengths[] = 'Resume file successfully parsed and analysed';

/* ── Improvements ── */
$improvements = [];
if (!$hasEmail)             $improvements[] = 'Add a professional email address (critical for ATS contact parsing)';
if (!$hasPhone)             $improvements[] = 'Include a phone number with country code (+91...)';
if (!$hasLinkedin && !$hasGithub) $improvements[] = 'Add LinkedIn or GitHub URL to boost credibility';
if (!$hasSummary)           $improvements[] = 'Write a 3-4 line professional summary at the top';
elseif ($summaryValueHits < 2)   $improvements[] = 'Strengthen your summary with impact words (proven, results-driven, expertise)';
if ($dateRangeCount === 0)  $improvements[] = 'Add date ranges to all experience entries (e.g., Jan 2022 – Present)';
elseif ($expPct < 60)       $improvements[] = 'Expand experience descriptions with responsibilities and tools used';
if ($uniqueTechCount < 6)   $improvements[] = 'List more technical skills — ATS scans for keyword matches';
elseif ($catCount < 3)      $improvements[] = 'Add skills from multiple categories (languages, tools, frameworks)';
if ($verbCount < 5)         $improvements[] = 'Start bullet points with action verbs: Developed, Led, Optimised, Delivered';
if ($quantCount < 3)        $improvements[] = 'Add numbers: "Reduced load time by 40%" or "Managed 5-person team"';
if (!$hasEdu)               $improvements[] = 'Add an Education section with degree, institution, and year';
if ($lengthQuality === 'too_short') $improvements[] = 'Resume is too short (<100 words) — add much more detail';
if ($lengthQuality === 'short')     $improvements[] = 'Add more content to reach 300–500 words for optimal ATS scoring';
if ($lengthQuality === 'too_long')  $improvements[] = 'Resume is too long — focus on last 5 years and keep to 1–2 pages';
if ($tableCount > 2)        $improvements[] = 'Remove tables — ATS parsers often cannot read tabular data';
if ($columnCount > 15)      $improvements[] = 'Avoid multi-column layouts — use single-column for best ATS compatibility';

/* ── Keyword intelligence ── */
// Suggest missing keywords based on what they already have
$suggestByCategory = [];
foreach ($techCategories as $cat => $allInCat) {
    $foundInCat = array_values(array_filter($allInCat, fn($s) => str_contains($lower, $s)));
    $missingInCat = array_diff($allInCat, $foundInCat);
    // If they have some skills in a cat, suggest complementary ones
    if (!empty($foundInCat) && !empty($missingInCat)) {
        foreach (array_slice(array_values($missingInCat), 0, 2) as $m) {
            $suggestByCategory[] = $m;
        }
    }
}

// Universal important keywords
$universalMissing = ['git', 'agile', 'rest api', 'team collaboration', 'communication', 'problem solving', 'leadership', 'documentation', 'testing', 'code review'];
$universalMissingOut = [];
foreach ($universalMissing as $k) {
    if (!str_contains($lower, strtolower($k))) $universalMissingOut[] = ucwords($k);
}

$keywordsToAdd = array_unique(array_merge(
    array_slice($suggestByCategory, 0, 8),
    array_slice($universalMissingOut, 0, 5)
));

/* ── Build checks ── */
$checks = [
    ['label' => 'Contact Information',     'status' => $contactStatus, 'detail' => $contactDetail,  'score' => $contactPct],
    ['label' => 'Professional Summary',    'status' => $summaryStatus, 'detail' => $summaryDetail,  'score' => $summaryPct],
    ['label' => 'Work Experience',         'status' => $expStatus,     'detail' => $expDetail,      'score' => $expPct],
    ['label' => 'Skills & Keywords',       'status' => $skillStatus,   'detail' => $skillDetail,    'score' => $skillPct],
    ['label' => 'Education',               'status' => $eduStatus,     'detail' => $eduDetail,      'score' => $eduPct],
    ['label' => 'Action Verbs',            'status' => $verbStatus,    'detail' => $verbDetail,     'score' => $verbPct],
    ['label' => 'Quantified Achievements', 'status' => $quantStatus,   'detail' => $quantDetail,    'score' => $quantPct],
    ['label' => 'ATS-Friendly Format',     'status' => $formatStatus,  'detail' => $formatDetail,   'score' => $formatPct],
];

/* ── Top keywords found (display nicely) ── */
$displayKeywords = [];
foreach ($allFoundTech as $k) {
    $displayKeywords[] = ucwords(str_replace(['.', '-'], ['. ', '-'], $k));
}
foreach (array_slice($foundSoft, 0, 4) as $s) {
    $displayKeywords[] = ucwords($s);
}
$displayKeywords = array_values(array_unique($displayKeywords));

/* ══════════════════════════════════════════════════════════════════
   RESPOND
══════════════════════════════════════════════════════════════════ */
echo json_encode([
    'status'              => 'success',
    'score'               => $score,
    'grade'               => $grade,
    'grade_icon'          => $gradeIcon,
    'summary'             => $summaryMsg,
    'word_count'          => $wordCount,
    'checks'              => $checks,
    'strengths'           => array_values(array_slice($strengths,     0, 6)),
    'improvements'        => array_values(array_slice($improvements,  0, 6)),
    'keywords_found'      => array_values(array_slice($displayKeywords, 0, 16)),
    'keywords_missing'    => array_values(array_slice($keywordsToAdd,   0, 10)),
    'skills_by_category'  => $foundByCategory,
    'verbs_by_group'      => $foundVerbsByGroup,
    'stats'               => [
        'tech_skills'   => $uniqueTechCount,
        'soft_skills'   => count($foundSoft),
        'action_verbs'  => $verbCount,
        'metrics'       => $quantCount,
        'experience_entries' => (int) $dateRangeCount,
        'categories'    => $catCount,
    ],
], JSON_UNESCAPED_UNICODE);
exit;
