<?php
declare(strict_types=1);
session_start();

/**
 * ============================================================================
 * CareerPro Suite - Advanced Resume Engine & Builder (Ultimate Edition)
 * Version: 9.5.0 (Enterprise App-Shell UI)
 * Architecture: 
 * - Monolithic App Shell Layout
 * - Strict Crimson/Dark Theme Sync
 * - Real-time JSON Data Binding (Virtual DOM approach)
 * - html2pdf.js High-Res Export Engine
 * - Native HTML5 Drag-and-Drop Node Reordering
 * - Self-Contained Live DB Auto-Save (PDO)
 * - Native Google Gemini AI Integration (Curl)
 * ============================================================================
 */

// ============================================================================
// 1. SESSION GUARD & CORE INITIALIZATION
// ============================================================================
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

require_once 'includes/db.php';
$userId = (int)$_SESSION['user_id'];
$db = Database::getInstance()->getConnection();

// ============================================================================
// 2. SELF-CONTAINED AJAX ENDPOINTS (DB Save & Gemini AI)
// ============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
    header('Content-Type: application/json');
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    $action = $data['action'] ?? '';

    // --- A. HANDLE DATABASE AUTO-SAVE ---
    if ($action === 'save_resume') {
        try {
            $resumePayload = json_encode($data['resume_state']);
            
            // Upsert into resumes table (Insert if not exists, Update if exists)
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
            error_log("Resume Sync Error: " . $e->getMessage());
            echo json_encode(['status' => 'error', 'message' => 'Database synchronization failed.']);
            exit;
        }
    }

    // --- B. HANDLE GEMINI AI INTEGRATION ---
    if ($action === 'ai_assist') {
        $prompt  = $data['prompt']  ?? '';
        $context = $data['context'] ?? '';

        if (empty($prompt) && empty($context)) {
            echo json_encode(['status' => 'error', 'message' => 'Empty prompt provided to AI Engine.']);
            exit;
        }

        $systemInstruction = "You are CareerBot, an expert ATS resume writer. Rewrite the provided text to be highly professional, impactful, and ATS-optimized. Use strong action verbs. Return ONLY the rewritten text — no filler phrases, no markdown bolding.";
        $fullPrompt = $systemInstruction . "\n\nTask: " . $prompt . "\n\nText to optimize:\n" . $context;

        // Load API key: DB → config fallback
        require_once 'includes/config.php';
        $apiKey = '';
        try {
            $keyStmt = $db->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'gemini_api_key' LIMIT 1");
            $keyStmt->execute();
            $apiKey = (string) $keyStmt->fetchColumn();
        } catch (Exception $ex) { /* ignore */ }
        if (empty($apiKey) && defined('GEMINI_API_KEY_FALLBACK')) {
            $apiKey = GEMINI_API_KEY_FALLBACK;
        }

        $aiText = '';

        // Try Gemini API first (if key looks like a standard key)
        if (!empty($apiKey) && str_starts_with($apiKey, 'AIzaSy')) {
            $payload  = ["contents" => [["parts" => [["text" => $fullPrompt]]]], "generationConfig" => ["temperature" => 0.6, "maxOutputTokens" => 800]];
            $models   = ['gemini-2.0-flash', 'gemini-1.5-flash', 'gemini-pro'];
            foreach ($models as $model) {
                $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . $model . ':generateContent?key=' . urlencode($apiKey);
                $ch  = curl_init($url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_POST,           true);
                curl_setopt($ch, CURLOPT_HTTPHEADER,     ['Content-Type: application/json']);
                curl_setopt($ch, CURLOPT_POSTFIELDS,     json_encode($payload));
                curl_setopt($ch, CURLOPT_TIMEOUT,        20);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                if ($httpCode === 200) {
                    $json   = json_decode($response, true);
                    $aiText = trim($json['candidates'][0]['content']['parts'][0]['text'] ?? '');
                    if (!empty($aiText)) break;
                }
                if ($httpCode === 429 || $httpCode === 400) break;
            }
        }

        // Fallback: Pollinations AI (always free, no key)
        if (empty($aiText)) {
            $pollBody = json_encode(['messages' => [['role' => 'user', 'content' => $fullPrompt]], 'model' => 'openai', 'max_tokens' => 800, 'temperature' => 0.6, 'private' => true]);
            $ch = curl_init('https://text.pollinations.ai/');
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
            echo json_encode(['status' => 'error', 'message' => 'AI Engine unavailable. Please try again.']);
        }
        exit;
    }
}

// ============================================================================
// 3. FETCH USER IDENTITY & INITIALIZE WORKSPACE STATE
// ============================================================================
try {
    $stmt = $db->prepare("SELECT name, email, phone FROM users WHERE id = :id");
    $stmt->execute(['id' => $userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        session_destroy();
        header("Location: login.php?err=session_lost");
        exit;
    }
    
    $userName = $user['name'];
    $firstName = explode(' ', $userName)[0];
    $userEmail = $user['email'];
    $userPhone = $user['phone'] ?? '';

    $resStmt = $db->prepare("SELECT resume_data FROM resumes WHERE user_id = :uid LIMIT 1");
    $resStmt->execute(['uid' => $userId]);
    $resumeRow = $resStmt->fetch(PDO::FETCH_ASSOC);

    if ($resumeRow && !empty($resumeRow['resume_data'])) {
        $preloadedData = $resumeRow['resume_data'];
    } else {
        // Smart Default Payload for New Users (Pre-filled to demonstrate formatting)
        $defaultState = [
            'personal' => [
                'fname' => $firstName, 
                'lname' => str_replace($firstName . ' ', '', $userName), 
                'email' => $userEmail, 
                'phone' => $userPhone, 
                'location' => 'Your City, State', 
                'link' => 'linkedin.com/in/username', 
                'title' => 'Software Engineering Student'
            ],
            'summary' => 'Driven and analytical student with a strong foundation in software development and data structures. Eager to leverage academic experience and technical skills to contribute to a forward-thinking engineering team.',
            'experience' => [
                [
                    'id' => 1, 
                    'title' => 'Software Intern', 
                    'company' => 'TechCorp India', 
                    'location' => 'Remote', 
                    'start' => 'Jan 2026', 
                    'end' => 'Present', 
                    'desc' => "• Architected scalable API endpoints using PHP and Laravel, reducing response times by 20%.\n• Collaborated with frontend developers to integrate dynamic UI components.\n• Managed version control using Git and GitHub in an Agile environment."
                ]
            ],
            'education' => [
                [
                    'id' => 1, 
                    'degree' => 'Bachelor of Computer Applications (BCA)', 
                    'school' => 'Your University / College', 
                    'start' => '2023', 
                    'end' => '2026', 
                    'grade' => 'CGPA: 8.5/10.0'
                ]
            ],
            'projects' => [
                [
                    'id' => 1, 
                    'name' => 'CareerPro ATS System', 
                    'tech' => 'PHP, MySQL, Tailwind CSS', 
                    'link' => 'github.com/project', 
                    'desc' => "• Developed an intelligent job matching platform with real-time resume parsing.\n• Implemented secure authentication and session management protocols."
                ]
            ],
            'certifications' => [],
            'languages' => ['English (Fluent)', 'Hindi (Native)', 'Punjabi (Native)'],
            'skills' => ['PHP', 'JavaScript', 'React.js', 'MySQL', 'Tailwind CSS', 'Git', 'Problem Solving'],
            'settings' => [
                'template' => 'modern', 
                'color' => '#df3c3c', 
                'font' => 'sans', 
                'spacing' => 'normal'
            ]
        ];
        $preloadedData = json_encode($defaultState);
    }
} catch (PDOException $e) {
    die("Database Connection Error. Please contact support.");
}
?>
<!DOCTYPE html>
<html lang="en" class="scroll-smooth dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Resume Engine | CareerPro Suite</title>

    <!-- Theme Initialization Script -->
    <script>
        if (localStorage.getItem('color-theme') === 'dark' || (!('color-theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }
    </script>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800;900&family=Inter:wght@400;500;600;700&family=Merriweather:wght@300;400;700&family=Roboto+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    
    <!-- Dependencies -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <script src="https://cdn.tailwindcss.com"></script>
    
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    fontFamily: { sans: ['"Plus Jakarta Sans"', 'sans-serif'] },
                    colors: {
                        pcte: { 50: '#fdf2f2', 100: '#fbe4e4', 200: '#f8caca', 300: '#f2a3a3', 400: '#ea6d6d', 500: '#df3c3c', 600: '#c82626', 700: '#a61c1c', 800: '#800000', 900: '#701616', 950: '#3f0707' },
                        dark: { 950: '#020202', 900: '#050505', 850: '#0a0a0a', 800: '#0f111a', 700: '#1e293b', 600: '#1f1f1f' }
                    },
                    animation: {
                        'blob': 'blob 10s infinite',
                        'slide-down': 'slideDown 0.3s cubic-bezier(0.4, 0, 0.2, 1) forwards',
                        'pulse-glow': 'pulseGlow 2s infinite',
                        'spin-slow': 'spin 3s linear infinite'
                    },
                    keyframes: {
                        blob: { '0%': { transform: 'translate(0px, 0px) scale(1)' }, '33%': { transform: 'translate(30px, -50px) scale(1.1)' }, '66%': { transform: 'translate(-20px, 20px) scale(0.9)' }, '100%': { transform: 'translate(0px, 0px) scale(1)' } },
                        slideDown: { '0%': { opacity: 0, transform: 'translateY(-10px)' }, '100%': { opacity: 1, transform: 'translateY(0)' } },
                        pulseGlow: { '0%, 100%': { opacity: 1, boxShadow: '0 0 15px rgba(223, 60, 60, 0.5)' }, '50%': { opacity: 0.8, boxShadow: '0 0 5px rgba(223, 60, 60, 0.2)' } }
                    }
                }
            }
        }
    </script>

    <style>
        /* ====================================================================
           CORE APP STYLES & GLASSMORPHISM
        ==================================================================== */
        body { 
            overflow-x: hidden; 
            -webkit-font-smoothing: antialiased; 
            background-color: #f8fafc; 
            color: #0f172a; 
            transition: background-color 0.4s ease; 
        }
        .dark body { background-color: #020202; color: #ffffff; }

        /* Universal Scrollbar */
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 3px; }
        .dark ::-webkit-scrollbar-thumb { background: #1f1f1f; }
        ::-webkit-scrollbar-thumb:hover { background: #df3c3c; }

        /* Background Meshes */
        .bg-grid-light { position: fixed; inset: 0; background-image: radial-gradient(#e2e8f0 1px, transparent 1px); background-size: 40px 40px; z-index: -2; mask-image: linear-gradient(to bottom, white, transparent); }
        .dark .bg-grid-light { display: none; }
        .bg-grid-dark { display: none; position: fixed; inset: 0; background-image: radial-gradient(rgba(255,255,255,0.02) 1px, transparent 1px); background-size: 40px 40px; z-index: -2; mask-image: linear-gradient(to bottom, white, transparent); }
        .dark .bg-grid-dark { display: block; }
        .mesh-glow { position: fixed; border-radius: 50%; filter: blur(120px); z-index: -1; opacity: 0.4; pointer-events: none; transition: all 0.5s ease; }
        .dark .mesh-glow { opacity: 0.15; }

        /* Glassmorphism Containers */
        .glass-nav { background: rgba(255, 255, 255, 0.85); backdrop-filter: blur(24px); border-bottom: 1px solid rgba(0,0,0,0.05); }
        .dark .glass-nav { background: rgba(5, 5, 5, 0.75); border-bottom: 1px solid rgba(255,255,255,0.05); }
        
        .sidebar { background: #ffffff; border-right: 1px solid #e2e8f0; z-index: 50; }
        .dark .sidebar { background: #050505; border-right: 1px solid rgba(255,255,255,0.05); }
        
        .nav-link { display: flex; align-items: center; gap: 0.75rem; padding: 0.85rem 1.5rem; color: #64748b; font-weight: 700; font-size: 0.875rem; transition: all 0.2s; border-radius: 0.75rem; margin: 0 0.75rem 0.25rem 0.75rem; border-left: 3px solid transparent; }
        .dark .nav-link { color: #94a3b8; }
        .nav-link:hover { color: #df3c3c; background: #fdf2f2; }
        .dark .nav-link:hover { color: #ffffff; background: rgba(223,60,60,0.05); }
        .nav-link.active { color: #df3c3c; background: #fdf2f2; border-left-color: #df3c3c; }
        .dark .nav-link.active { color: #ea6d6d; background: rgba(223, 60, 60, 0.1); border-left-color: #df3c3c; }

        .glass-card { background: rgba(255, 255, 255, 0.8); backdrop-filter: blur(12px); border: 1px solid rgba(0, 0, 0, 0.05); box-shadow: 0 10px 30px -10px rgba(0, 0, 0, 0.05); transition: transform 0.4s ease, border-color 0.4s ease, box-shadow 0.4s ease, background-color 0.4s ease; }
        .dark .glass-card { background: linear-gradient(145deg, rgba(20, 20, 20, 0.8) 0%, rgba(10, 10, 10, 0.9) 100%); border: 1px solid rgba(255, 255, 255, 0.04); border-top: 1px solid rgba(255, 255, 255, 0.08); box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5); }

        /* Form Inputs */
        .input-field { background: #ffffff; border: 1.5px solid #e2e8f0; color: #0f172a; transition: all 0.2s ease; }
        .dark .input-field { background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.1); color: white; }
        .input-field:focus { outline: none; border-color: #df3c3c; box-shadow: 0 0 0 3px rgba(223, 60, 60, 0.1); }
        .dark .input-field:focus { border-color: #df3c3c; box-shadow: 0 0 0 3px rgba(223, 60, 60, 0.2); }

        /* Buttons (Forced PCTE Red Theme) */
        .btn-primary { background: linear-gradient(135deg, #df3c3c 0%, #a61c1c 100%); position: relative; overflow: hidden; z-index: 1; transition: all 0.3s ease; border: none; color: white; cursor: pointer; }
        .dark .btn-primary { background: linear-gradient(135deg, #a61c1c 0%, #800000 100%); }
        .btn-primary::before { content: ''; position: absolute; top: 0; left: -100%; width: 100%; height: 100%; background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent); transition: left 0.5s ease; z-index: -1; }
        .btn-primary:hover:not(:disabled)::before { left: 100%; }
        .btn-primary:hover:not(:disabled) { box-shadow: 0 10px 25px -5px rgba(223, 60, 60, 0.4); transform: translateY(-2px); }
        .dark .btn-primary:hover:not(:disabled) { box-shadow: 0 10px 25px -5px rgba(223, 60, 60, 0.6); }
        .btn-primary:disabled { opacity: 0.6; cursor: not-allowed; transform: none; box-shadow: none; filter: grayscale(50%); }

        /* Custom Toggle Switch */
        .theme-toggle-label { width: 46px; height: 26px; background-color: #cbd5e1; border-radius: 9999px; position: relative; cursor: pointer; transition: background-color 0.3s; display: flex; align-items: center; }
        .dark .theme-toggle-label { background-color: #334155; }
        .theme-toggle-ball { width: 20px; height: 20px; background-color: white; border-radius: 50%; position: absolute; top: 3px; left: 3px; transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1); display: flex; align-items: center; justify-content: center; box-shadow: 0 2px 4px rgba(0,0,0,0.2); }
        .dark .theme-toggle-ball { transform: translateX(20px); background-color: #0f172a; }

        /* Editor Accordion & Native Drag-Drop */
        .accordion-content { display: none; overflow: hidden; }
        .accordion-content.active { display: block; animation: slideDown 0.3s cubic-bezier(0.4, 0, 0.2, 1) forwards; }
        
        .draggable-item { cursor: grab; transition: transform 0.2s, box-shadow 0.2s; border: 1px solid #e2e8f0; background: #ffffff; }
        .dark .draggable-item { border-color: rgba(255,255,255,0.05); background: #0a0a0a; }
        .draggable-item:active { cursor: grabbing; }
        .draggable-item.dragging { opacity: 0.5; transform: scale(0.98); box-shadow: 0 10px 20px rgba(0,0,0,0.2); }
        .drag-over { border: 2px dashed #df3c3c !important; background: rgba(223,60,60,0.05) !important; }

        /* Skill Chips */
        .skill-chip { display: inline-flex; align-items: center; padding: 6px 12px; background: #f1f5f9; border: 1px solid #e2e8f0; border-radius: 999px; font-size: 12px; font-weight: 700; margin: 0 8px 8px 0; transition: all 0.2s; color: #475569; }
        .dark .skill-chip { background: rgba(255,255,255,0.05); border-color: rgba(255,255,255,0.1); color: #cbd5e1; }
        .skill-chip:hover { background: #fdf2f2; border-color: #df3c3c; color: #a61c1c; }
        .dark .skill-chip:hover { background: rgba(223,60,60,0.2); border-color: #df3c3c; color: #fff; }
        .skill-chip button { margin-left: 6px; color: #94a3b8; transition: color 0.2s; cursor: pointer; }
        .skill-chip button:hover { color: #ef4444; }

        /* ====================================================================
           PDF PREVIEW & A4 TEMPLATE ENGINE (Virtual DOM Styles)
        ==================================================================== */
        .preview-pane { 
            background-color: #e2e8f0; 
            background-image: radial-gradient(#cbd5e1 1px, transparent 0); 
            background-size: 20px 20px; 
            position: relative; overflow-y: auto; overflow-x: hidden; 
            display: flex; flex-direction: column; align-items: center; padding: 40px 0; 
        }
        .dark .preview-pane { 
            background-color: #050505; 
            background-image: radial-gradient(rgba(255,255,255,0.05) 1px, transparent 0); 
        }
        
        .a4-wrapper {
            position: relative;
            transform-origin: top center;
            transition: transform 0.2s ease;
        }

       .a4-paper { 
            background: white; 
            width: 210mm; 
            min-height: 297mm; 
            padding: 20mm; 
            box-shadow: 0 25px 50px -12px rgba(0,0,0,0.2); 
            position: relative; 
            overflow: visible;
            box-sizing: border-box;
            -webkit-print-color-adjust: exact !important; 
            print-color-adjust: exact !important;
        }
        .dark .a4-paper { box-shadow: 0 25px 50px -12px rgba(0,0,0,0.8); }
        
        /* Font Hooks */
        .font-sans-doc { font-family: 'Inter', sans-serif; }
        .font-serif-doc { font-family: 'Merriweather', serif; }
        .font-mono-doc { font-family: 'Roboto Mono', monospace; }

        /* Spacing Hooks */
        .space-compact .res-section { margin-bottom: 12px; } .space-compact .res-item { margin-bottom: 8px; }
        .space-normal .res-section { margin-bottom: 20px; } .space-normal .res-item { margin-bottom: 14px; }
        .space-loose .res-section { margin-bottom: 28px; } .space-loose .res-item { margin-bottom: 20px; }

        /* -----------------------------------------
           1. TEMPLATE: MODERN (Default) 
        ----------------------------------------- */
        .tpl-modern { color: #1f2937; }
        .tpl-modern .res-header { text-align: left; border-bottom: 3px solid var(--theme-color); padding-bottom: 15px; margin-bottom: 20px; }
        .tpl-modern .res-name { font-size: 32pt; font-weight: 800; line-height: 1.1; letter-spacing: -0.5px; color: #111; text-transform: uppercase; }
        .tpl-modern .res-title { font-size: 14pt; font-weight: 600; color: var(--theme-color); margin-top: 4px; }
        .tpl-modern .res-contact { font-size: 9pt; color: #4b5563; margin-top: 8px; display: flex; flex-wrap: wrap; gap: 12px; }
        .tpl-modern .res-section-title { font-size: 14pt; font-weight: 700; color: var(--theme-color); text-transform: uppercase; letter-spacing: 1px; border-bottom: 1px solid #e5e7eb; padding-bottom: 4px; margin-bottom: 12px; }
        .tpl-modern .res-item-header { display: flex; justify-content: space-between; align-items: baseline; }
        .tpl-modern .res-item-title { font-size: 12pt; font-weight: 700; color: #111; }
        .tpl-modern .res-item-date { font-size: 10pt; font-weight: 600; color: var(--theme-color); }
        .tpl-modern .res-item-sub { font-size: 10.5pt; font-weight: 500; color: #4b5563; font-style: italic; margin-bottom: 4px; }
        .tpl-modern .res-desc { font-size: 10pt; line-height: 1.6; color: #374151; }
        .tpl-modern .res-bullets li { margin-left: 18px; margin-bottom: 4px; list-style-type: square; }
        .tpl-modern .res-skills { display: flex; flex-wrap: wrap; gap: 8px; }
        .tpl-modern .res-skill-badge { background: #f3f4f6; color: #1f2937; padding: 4px 10px; border-radius: 4px; font-size: 9.5pt; font-weight: 500; border-left: 3px solid var(--theme-color); }

        /* -----------------------------------------
           2. TEMPLATE: HARVARD (Strict Academic)
        ----------------------------------------- */
        .tpl-harvard { color: #000; font-family: 'Merriweather', serif !important; }
        .tpl-harvard .res-header { text-align: center; border-bottom: 1px solid #000; padding-bottom: 10px; margin-bottom: 15px; }
        .tpl-harvard .res-name { font-size: 24pt; font-weight: 700; text-transform: uppercase; }
        .tpl-harvard .res-title { display: none; }
        .tpl-harvard .res-contact { font-size: 10pt; margin-top: 5px; }
        .tpl-harvard .res-contact span::after { content: " | "; } .tpl-harvard .res-contact span:last-child::after { content: ""; }
        .tpl-harvard .res-section-title { font-size: 11pt; font-weight: 700; text-transform: uppercase; text-align: center; border-bottom: 1px solid #000; margin: 15px 0 10px 0; padding-bottom: 2px; }
        .tpl-harvard .res-item-header { display: flex; justify-content: space-between; }
        .tpl-harvard .res-item-title { font-size: 10.5pt; font-weight: 700; }
        .tpl-harvard .res-item-date { font-size: 10pt; }
        .tpl-harvard .res-item-sub { font-size: 10.5pt; font-style: italic; margin-bottom: 2px; }
        .tpl-harvard .res-desc { font-size: 10pt; line-height: 1.4; }
        .tpl-harvard .res-bullets li { margin-left: 15px; margin-bottom: 2px; list-style-type: disc; }
        .tpl-harvard .res-skills { font-size: 10pt; line-height: 1.5; }
        .tpl-harvard .res-skill-badge { display: inline; padding: 0; background: none; border: none; }
        .tpl-harvard .res-skill-badge::after { content: ", "; } .tpl-harvard .res-skill-badge:last-child::after { content: ""; }

        /* -----------------------------------------
           3. TEMPLATE: EXECUTIVE (Elegant Corporate)
        ----------------------------------------- */
        .tpl-executive { color: #1e293b; border-top: 8px solid var(--theme-color); padding-top: 15mm; }
        .tpl-executive .res-header { text-align: right; margin-bottom: 25px; }
        .tpl-executive .res-name { font-size: 36pt; font-weight: 300; letter-spacing: 2px; color: #0f172a; text-transform: uppercase; }
        .tpl-executive .res-title { font-size: 12pt; font-weight: 400; color: #64748b; letter-spacing: 4px; text-transform: uppercase; margin-top: 8px; }
        .tpl-executive .res-contact { font-size: 9pt; color: #475569; margin-top: 15px; display: flex; justify-content: flex-end; gap: 15px; }
        .tpl-executive .res-section-title { font-size: 12pt; font-weight: 800; color: #0f172a; text-transform: uppercase; letter-spacing: 2px; margin-bottom: 12px; display: flex; align-items: center; }
        .tpl-executive .res-section-title::before { content: ""; display: block; width: 15px; height: 3px; background: var(--theme-color); margin-right: 10px; }
        .tpl-executive .res-item-header { display: flex; justify-content: space-between; align-items: baseline; }
        .tpl-executive .res-item-title { font-size: 11pt; font-weight: 800; color: #0f172a; }
        .tpl-executive .res-item-date { font-size: 9pt; font-weight: 700; color: #64748b; }
        .tpl-executive .res-item-sub { font-size: 10pt; font-weight: 500; color: var(--theme-color); margin-bottom: 6px; }
        .tpl-executive .res-desc { font-size: 9.5pt; line-height: 1.7; color: #475569; }
        .tpl-executive .res-bullets li { margin-left: 20px; margin-bottom: 5px; list-style-type: circle; color: #475569; }
        .tpl-executive .res-skills { display: flex; flex-wrap: wrap; gap: 10px; }
        .tpl-executive .res-skill-badge { color: #0f172a; padding: 2px 0; font-size: 9pt; font-weight: 600; text-transform: uppercase; letter-spacing: 1px; border-bottom: 1px solid var(--theme-color); }

        /* -----------------------------------------
           4. TEMPLATE: TECH SPLIT
        ----------------------------------------- */
        .tpl-tech { color: #e5e7eb; background: #111827; padding: 0; min-height: 297mm; display: flex; }
        .tpl-tech .tech-left { width: 35%; background: #1f2937; padding: 15mm; }
        .tpl-tech .tech-right { width: 65%; padding: 15mm; background: #111827; }
        .tpl-tech .res-name { font-size: 28pt; font-weight: 800; color: #fff; line-height: 1.1; margin-bottom: 5px; }
        .tpl-tech .res-title { font-size: 12pt; font-weight: 500; color: var(--theme-color); margin-bottom: 20px; }
        .tpl-tech .res-contact { font-size: 9pt; display: flex; flex-direction: column; gap: 8px; margin-bottom: 30px; }
        .tpl-tech .res-section-title { font-size: 12pt; font-weight: 700; color: #fff; text-transform: uppercase; letter-spacing: 2px; margin-bottom: 15px; }
        .tpl-tech .tech-right .res-section-title { border-bottom: 2px solid #374151; padding-bottom: 5px; color: var(--theme-color); }
        .tpl-tech .res-item-header { display: flex; justify-content: space-between; align-items: baseline; }
        .tpl-tech .res-item-title { font-size: 11pt; font-weight: 700; color: #fff; }
        .tpl-tech .res-item-date { font-size: 9pt; color: #9ca3af; }
        .tpl-tech .res-item-sub { font-size: 10pt; color: #d1d5db; margin-bottom: 5px; }
        .tpl-tech .res-desc { font-size: 9.5pt; line-height: 1.6; color: #9ca3af; }
        .tpl-tech .res-bullets li { margin-left: 15px; list-style-type: disc; color: #9ca3af; }
        .tpl-tech .res-skill-badge { background: #374151; color: #fff; padding: 4px 8px; border-radius: 4px; font-size: 8.5pt; font-family: 'Roboto Mono', monospace; }

        /* -----------------------------------------
           5. TEMPLATE: MINIMALIST
        ----------------------------------------- */
        .tpl-minimalist { color: #333; font-family: 'Inter', sans-serif; }
        .tpl-minimalist .res-header { display: flex; justify-content: space-between; align-items: flex-end; border-bottom: 1px solid #ccc; padding-bottom: 10px; margin-bottom: 20px; }
        .tpl-minimalist .res-name { font-size: 26pt; font-weight: 300; letter-spacing: 1px; color: #111; }
        .tpl-minimalist .res-title { display: none; }
        .tpl-minimalist .res-contact { font-size: 8.5pt; color: #666; text-align: right; }
        .tpl-minimalist .res-contact span { display: block; }
        .tpl-minimalist .res-section-title { font-size: 10pt; font-weight: 700; color: var(--theme-color); text-transform: uppercase; letter-spacing: 2px; margin-bottom: 15px; margin-top: 25px; }
        .tpl-minimalist .res-item { display: grid; grid-template-columns: 1fr 3fr; gap: 15px; margin-bottom: 15px; }
        .tpl-minimalist .res-item-header { display: flex; flex-direction: column; }
        .tpl-minimalist .res-item-title { font-size: 10pt; font-weight: 700; color: #111; }
        .tpl-minimalist .res-item-date { font-size: 9pt; color: #666; margin-top: 4px; }
        .tpl-minimalist .res-item-sub { font-size: 10pt; font-weight: 600; color: #333; margin-bottom: 4px; }
        .tpl-minimalist .res-desc { font-size: 9.5pt; line-height: 1.6; color: #444; }
        .tpl-minimalist .res-bullets li { margin-left: 15px; margin-bottom: 3px; list-style-type: circle; }
        .tpl-minimalist .res-skills { font-size: 9.5pt; line-height: 1.6; }
        .tpl-minimalist .res-skill-badge { display: inline; padding: 0; background: none; border: none; }
        .tpl-minimalist .res-skill-badge::after { content: " • "; color: var(--theme-color); } .tpl-minimalist .res-skill-badge:last-child::after { content: ""; }


        /* Print Safety Overrides */
        @media print {
            body * { visibility: hidden; }
            #resume-canvas, #resume-canvas * { visibility: visible; }
            #resume-canvas { position: absolute; left: 0; top: 0; box-shadow: none; margin: 0; }
        }

        /* AI Glimmer */
        .ai-glow { animation: pulseGlow 2s infinite; }
    </style>
</head>
<body class="flex h-screen overflow-hidden selection:bg-pcte-500 selection:text-white">

    <!-- Global Background Elements -->
    <div class="bg-grid-light"></div>
    <div class="bg-grid-dark"></div>
    <div class="mesh-glow w-[500px] h-[500px] bg-pcte-500/20 top-[-100px] left-[-100px] animate-blob"></div>

    <!-- ======================================================================
         APP SHELL: SIDEBAR NAVIGATION
    ======================================================================= -->
    <aside class="sidebar w-[280px] h-full hidden lg:flex flex-col justify-between shrink-0 relative z-50 shadow-2xl dark:shadow-none">
        
        <div class="flex-1 flex flex-col">
            <!-- Logo Area -->
            <div class="h-24 flex items-center px-8 border-b border-slate-200 dark:border-white/5 shrink-0">
                <a href="index.php" class="flex items-center gap-3 group w-full">
                    <div class="w-10 h-10 rounded-xl bg-pcte-600 flex items-center justify-center shadow-lg shadow-pcte-500/40 group-hover:rotate-12 transition-transform duration-500 shrink-0">
                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                    </div>
                    <div class="overflow-hidden">
                        <span class="text-2xl font-black tracking-tight text-slate-900 dark:text-white transition-colors duration-300 block leading-none">Career<span class="text-pcte-600">Pro</span></span>
                        <span class="text-[9px] font-bold text-slate-400 uppercase tracking-widest mt-1 block">CareerPro Suite</span>
                    </div>
                </a>
            </div>

            <!-- Links -->
            <nav class="mt-8 px-2 space-y-1 flex-1 overflow-y-auto">
                <p class="px-4 text-[10px] font-black text-slate-400 dark:text-gray-600 uppercase tracking-widest mb-4">Workspace</p>
                <a href="dashboard.php" class="nav-link rounded-xl">
                    <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"></path></svg>
                    Command Center
                </a>
                <a href="builder.php" class="nav-link active rounded-xl">
                    <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                    Resume Engine
                </a>
                <a href="jobs.php" class="nav-link rounded-xl">
                    <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                    Job Matcher
                </a>
                
                <p class="px-4 text-[10px] font-black text-slate-400 dark:text-gray-600 uppercase tracking-widest mt-8 mb-4">Settings</p>
                <a href="profile.php" class="nav-link rounded-xl">
                    <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                    Identity Management
                </a>
            </nav>
        </div>

        <!-- User Node & Logout -->
        <div class="p-6 border-t border-slate-200 dark:border-white/5 shrink-0 bg-slate-50 dark:bg-[#050505] transition-colors">
            <div class="flex items-center gap-3 mb-6 p-3 rounded-2xl bg-white dark:bg-dark-800 border border-slate-200 dark:border-white/5 shadow-sm group">
                <div class="w-10 h-10 rounded-xl bg-pcte-100 dark:bg-pcte-900/30 flex items-center justify-center font-black text-lg text-pcte-600 dark:text-pcte-400 shadow-inner group-hover:scale-105 transition-transform shrink-0">
                    <?php echo substr($firstName, 0, 1); ?>
                </div>
                <div class="overflow-hidden">
                    <h4 class="text-sm font-bold text-slate-900 dark:text-white truncate" title="<?php echo htmlspecialchars($userName); ?>"><?php echo htmlspecialchars($userName); ?></h4>
                    <p class="text-[9px] text-slate-500 uppercase font-black tracking-widest mt-0.5">Verified Student</p>
                </div>
            </div>
            <a href="api/auth.php?action=logout" class="flex items-center justify-center gap-2 w-full py-3.5 bg-slate-900 dark:bg-white text-white dark:text-black rounded-xl text-xs font-bold hover:scale-[1.02] transition-all shadow-md active:scale-95 group">
                <svg class="w-4 h-4 group-hover:-translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path></svg>
                Secure Log Out
            </a>
        </div>
    </aside>

    <!-- ======================================================================
         MAIN CONTENT AREA
    ======================================================================= -->
    <div class="flex-1 flex flex-col h-screen overflow-hidden relative z-10">
        
        <!-- Top Nav Bar -->
        <header class="h-20 flex items-center justify-between px-6 lg:px-10 border-b border-slate-200 dark:border-white/5 glass-nav shrink-0 z-40">
            <div class="flex items-center gap-4">
                <button id="mobile-menu-btn" class="lg:hidden p-2 rounded-xl bg-slate-100 dark:bg-dark-800 text-slate-600 dark:text-gray-400 hover:text-pcte-600 transition-colors">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path></svg>
                </button>
                <div class="hidden sm:block">
                    <div class="flex items-center gap-3">
                        <h1 class="text-lg font-black text-slate-900 dark:text-white tracking-tight">Active JSON Node</h1>
                        <span id="save-status" class="bg-success-100 dark:bg-success-900/30 text-success-700 dark:text-success-400 px-2.5 py-1 rounded-md text-[9px] font-black uppercase tracking-widest flex items-center gap-1.5 border border-success-200 dark:border-success-500/20">
                            <span class="w-1.5 h-1.5 rounded-full bg-success-500 animate-pulse"></span> DB Synced
                        </span>
                    </div>
                </div>
            </div>
            
            <div class="flex items-center gap-3 sm:gap-4">
                <button id="theme-toggle" class="relative focus:outline-none hover:scale-105 transition-transform shrink-0" title="Toggle UI Mode">
                    <div class="theme-toggle-label shadow-inner border border-slate-300 dark:border-white/10">
                        <div class="theme-toggle-ball">
                            <svg id="theme-toggle-light-icon" class="w-3.5 h-3.5 text-amber-500 hidden dark:block absolute" fill="currentColor" viewBox="0 0 20 20"><path d="M10 2a1 1 0 011 1v1a1 1 0 11-2 0V3a1 1 0 011-1zm4.22 4.22a1 1 0 011.415 0l.708.708a1 1 0 01-1.414 1.414l-.708-.708a1 1 0 010-1.414zm3.78 3.78a1 1 0 010 2h-1a1 1 0 110-2h1zm-4.22 4.22a1 1 0 010 1.415l-.708.708a1 1 0 011.414-1.414l.708.708a1 1 0 010 1.414zm-3.78-3.78a1 1 0 010-2h1a1 1 0 110 2h-1zm4.22-4.22a1 1 0 010-1.415l.708-.708a1 1 0 011.414 1.414l-.708.708a1 1 0 01-1.414 0zM10 5a5 5 0 100 10 5 5 0 000-10z"></path></svg>
                            <svg id="theme-toggle-dark-icon" class="w-3.5 h-3.5 text-slate-700 block dark:hidden absolute" fill="currentColor" viewBox="0 0 20 20"><path d="M17.293 13.293A8 8 0 016.707 2.707a8.001 8.001 0 1010.586 10.586z"></path></svg>
                        </div>
                    </div>
                </button>

                <div class="h-6 w-px bg-slate-300 dark:bg-white/10 mx-1 hidden sm:block"></div>

                <button onclick="toggleSettingsModal()" class="flex items-center gap-2 bg-slate-100 dark:bg-dark-800 border border-slate-200 dark:border-white/10 hover:bg-slate-200 dark:hover:bg-white/5 text-slate-700 dark:text-gray-300 text-xs font-bold px-4 sm:px-5 py-2.5 rounded-xl transition shadow-sm cursor-pointer">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                    <span class="hidden sm:block">Design Setup</span>
                </button>
                
                <button onclick="exportPDF()" class="btn-primary flex items-center gap-2 text-xs font-bold px-5 py-2.5 rounded-xl shadow-lg shadow-pcte-500/30 cursor-pointer">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
                    <span class="hidden sm:block">Export PDF</span>
                    <span class="sm:hidden">Export</span>
                </button>
            </div>
        </header>

        <!-- Mobile Menu Overlay -->
        <div id="mobile-menu" class="hidden lg:hidden absolute top-[80px] left-0 w-full glass-nav rounded-none border-x-0 border-t-0 border-b border-slate-200 dark:border-white/10 py-6 px-6 flex flex-col space-y-2 shadow-2xl origin-top z-30">
            <a href="dashboard.php" class="nav-link rounded-lg">Command Center</a>
            <a href="builder.php" class="nav-link active rounded-lg">Resume Engine</a>
            <a href="jobs.php" class="nav-link rounded-lg">Job Opportunities</a>
            <a href="profile.php" class="nav-link rounded-lg">Identity Settings</a>
            <div class="h-px w-full bg-slate-200 dark:bg-white/10 my-4"></div>
            <a href="api/auth.php?action=logout" class="text-center text-red-500 font-bold py-3 bg-red-50 dark:bg-red-900/10 rounded-lg">Secure Sign Out</a>
        </div>

        <!-- Split Screen Work Area -->
        <div class="flex flex-1 w-full h-[calc(100vh-80px)]">
            
            <!-- LEFT PANE: JSON Editor Forms -->
            <div class="w-full lg:w-[45%] xl:w-[40%] h-full overflow-y-auto bg-slate-50 dark:bg-dark-900 border-r border-slate-200 dark:border-white/5 pb-32" id="editor-scroll">
                
                <div class="p-6 bg-pcte-50 dark:bg-pcte-900/10 border-b border-pcte-100 dark:border-pcte-500/10 flex justify-between items-center sticky top-0 z-20 backdrop-blur-md shadow-sm">
                    <p class="text-xs font-bold text-pcte-700 dark:text-pcte-400 uppercase tracking-widest">JSON Input Nodes</p>
                    <button onclick="runGlobalAI()" class="bg-pcte-600 hover:bg-pcte-700 text-white text-[10px] font-black uppercase tracking-widest px-4 py-2 rounded-lg shadow-md transition-colors flex items-center gap-1.5 cursor-pointer">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                        AI Rewrite
                    </button>
                </div>

                <div class="p-6 max-w-2xl mx-auto space-y-6" id="form-container">

                    <!-- Accordion: Personal Details -->
                    <div class="glass-card rounded-2xl overflow-hidden shadow-sm">
                        <button class="accordion-toggle w-full p-5 flex justify-between items-center text-left hover:bg-slate-50 dark:hover:bg-white/5 transition group border-b border-transparent cursor-pointer" onclick="toggleAccordion(this)">
                            <div class="flex items-center gap-4">
                                <div class="w-10 h-10 rounded-xl bg-slate-100 dark:bg-dark-800 border border-slate-200 dark:border-white/10 flex items-center justify-center text-slate-500 group-hover:text-pcte-500 transition">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                                </div>
                                <div>
                                    <span class="font-bold text-slate-900 dark:text-white block">Identity Vector</span>
                                    <span class="text-[10px] text-slate-500 uppercase tracking-widest">Header Information</span>
                                </div>
                            </div>
                            <svg class="w-5 h-5 text-slate-400 transform transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                        </button>
                        <div class="accordion-content active p-5 border-t border-slate-200 dark:border-white/5 bg-slate-50/50 dark:bg-dark-900/50">
                            <div class="grid grid-cols-2 gap-5">
                                <div><label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">First Name</label><input type="text" id="inp-fname" class="input-field w-full rounded-xl px-4 py-2.5 text-sm mt-1.5 font-semibold" data-sync="personal.fname"></div>
                                <div><label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">Last Name</label><input type="text" id="inp-lname" class="input-field w-full rounded-xl px-4 py-2.5 text-sm mt-1.5 font-semibold" data-sync="personal.lname"></div>
                                <div class="col-span-2"><label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">Target Role / Title</label><input type="text" id="inp-title" class="input-field w-full rounded-xl px-4 py-2.5 text-sm mt-1.5 font-semibold" data-sync="personal.title" placeholder="e.g. Senior Frontend Developer"></div>
                                <div class="col-span-2 sm:col-span-1"><label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">Email Address</label><input type="email" id="inp-email" class="input-field w-full rounded-xl px-4 py-2.5 text-sm mt-1.5 font-semibold" data-sync="personal.email"></div>
                                <div class="col-span-2 sm:col-span-1"><label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">Phone Number</label><input type="text" id="inp-phone" class="input-field w-full rounded-xl px-4 py-2.5 text-sm mt-1.5 font-semibold" data-sync="personal.phone"></div>
                                <div class="col-span-2 sm:col-span-1"><label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">Location / City</label><input type="text" id="inp-location" class="input-field w-full rounded-xl px-4 py-2.5 text-sm mt-1.5 font-semibold" data-sync="personal.location"></div>
                                <div class="col-span-2 sm:col-span-1"><label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">Portfolio Link</label><input type="text" id="inp-link" class="input-field w-full rounded-xl px-4 py-2.5 text-sm mt-1.5 font-semibold" data-sync="personal.link"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Accordion: Summary -->
                    <div class="glass-card rounded-2xl overflow-hidden shadow-sm">
                        <button class="accordion-toggle w-full p-5 flex justify-between items-center text-left hover:bg-slate-50 dark:hover:bg-white/5 transition group cursor-pointer" onclick="toggleAccordion(this)">
                            <div class="flex items-center gap-4">
                                <div class="w-10 h-10 rounded-xl bg-slate-100 dark:bg-dark-800 border border-slate-200 dark:border-white/10 flex items-center justify-center text-slate-500 group-hover:text-pcte-500 transition">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                </div>
                                <div>
                                    <span class="font-bold text-slate-900 dark:text-white block">Executive Summary</span>
                                    <span class="text-[10px] text-slate-500 uppercase tracking-widest">The Elevator Pitch</span>
                                </div>
                            </div>
                            <svg class="w-5 h-5 text-slate-400 transform transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                        </button>
                        <div class="accordion-content p-5 border-t border-slate-200 dark:border-white/5 bg-slate-50/50 dark:bg-dark-900/50">
                            <div class="relative">
                                <textarea id="inp-summary" rows="5" class="input-field w-full rounded-xl px-4 py-3 text-sm font-medium resize-y leading-relaxed" data-sync="summary" placeholder="Write a brief 2-3 sentence overview of your career..."></textarea>
                                <button type="button" onclick="openAIAssistant('Rewrite this summary to be more impactful and parseable by ATS systems. Focus on brevity and action verbs.', document.getElementById('inp-summary').value, 'summary')" class="absolute bottom-3 right-3 bg-pcte-100 dark:bg-pcte-900/20 border border-pcte-200 dark:border-pcte-500/30 text-pcte-700 dark:text-pcte-400 text-[10px] font-black uppercase tracking-widest px-3 py-2 rounded-lg flex items-center gap-1.5 hover:scale-105 transition-transform shadow-sm cursor-pointer">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg> AI Assist
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Accordion: Experience (Dynamic List) -->
                    <div class="glass-card rounded-2xl overflow-hidden shadow-sm">
                        <button class="accordion-toggle w-full p-5 flex justify-between items-center text-left hover:bg-slate-50 dark:hover:bg-white/5 transition group cursor-pointer" onclick="toggleAccordion(this)">
                            <div class="flex items-center gap-4">
                                <div class="w-10 h-10 rounded-xl bg-slate-100 dark:bg-dark-800 border border-slate-200 dark:border-white/10 flex items-center justify-center text-slate-500 group-hover:text-pcte-500 transition">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                                </div>
                                <div>
                                    <span class="font-bold text-slate-900 dark:text-white block">Work Experience</span>
                                    <span class="text-[10px] text-slate-500 uppercase tracking-widest">Drag to reorder</span>
                                </div>
                            </div>
                            <svg class="w-5 h-5 text-slate-400 transform transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                        </button>
                        <div class="accordion-content p-5 border-t border-slate-200 dark:border-white/5 bg-slate-50/50 dark:bg-dark-900/50">
                            <div id="experience-list" class="space-y-4"></div>
                            <button onclick="addSectionItem('experience')" class="mt-4 w-full py-3.5 bg-slate-100 dark:bg-dark-800 border border-dashed border-slate-300 dark:border-gray-600 rounded-xl text-xs font-black uppercase tracking-widest text-slate-500 dark:text-gray-400 hover:text-pcte-600 dark:hover:text-white hover:border-pcte-500 transition-colors flex items-center justify-center gap-2 shadow-inner cursor-pointer">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"></path></svg> Add Experience Node
                            </button>
                        </div>
                    </div>

                    <!-- Accordion: Projects (Dynamic List) -->
                    <div class="glass-card rounded-2xl overflow-hidden shadow-sm">
                        <button class="accordion-toggle w-full p-5 flex justify-between items-center text-left hover:bg-slate-50 dark:hover:bg-white/5 transition group cursor-pointer" onclick="toggleAccordion(this)">
                            <div class="flex items-center gap-4">
                                <div class="w-10 h-10 rounded-xl bg-slate-100 dark:bg-dark-800 border border-slate-200 dark:border-white/10 flex items-center justify-center text-slate-500 group-hover:text-pcte-500 transition">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"></path></svg>
                                </div>
                                <div>
                                    <span class="font-bold text-slate-900 dark:text-white block">Project Portfolio</span>
                                    <span class="text-[10px] text-slate-500 uppercase tracking-widest">Key achievements</span>
                                </div>
                            </div>
                            <svg class="w-5 h-5 text-slate-400 transform transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                        </button>
                        <div class="accordion-content p-5 border-t border-slate-200 dark:border-white/5 bg-slate-50/50 dark:bg-dark-900/50">
                            <div id="projects-list" class="space-y-4"></div>
                            <button onclick="addSectionItem('projects')" class="mt-4 w-full py-3.5 bg-slate-100 dark:bg-dark-800 border border-dashed border-slate-300 dark:border-gray-600 rounded-xl text-xs font-black uppercase tracking-widest text-slate-500 dark:text-gray-400 hover:text-pcte-600 dark:hover:text-white hover:border-pcte-500 transition-colors flex items-center justify-center gap-2 shadow-inner cursor-pointer">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"></path></svg> Add Project Node
                            </button>
                        </div>
                    </div>

                    <!-- Accordion: Education (Dynamic List) -->
                    <div class="glass-card rounded-2xl overflow-hidden shadow-sm">
                        <button class="accordion-toggle w-full p-5 flex justify-between items-center text-left hover:bg-slate-50 dark:hover:bg-white/5 transition group cursor-pointer" onclick="toggleAccordion(this)">
                            <div class="flex items-center gap-4">
                                <div class="w-10 h-10 rounded-xl bg-slate-100 dark:bg-dark-800 border border-slate-200 dark:border-white/10 flex items-center justify-center text-slate-500 group-hover:text-pcte-500 transition">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l9-5-9-5-9 5 9 5z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14v7"></path></svg>
                                </div>
                                <div>
                                    <span class="font-bold text-slate-900 dark:text-white block">Education</span>
                                    <span class="text-[10px] text-slate-500 uppercase tracking-widest">Academic History</span>
                                </div>
                            </div>
                            <svg class="w-5 h-5 text-slate-400 transform transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                        </button>
                        <div class="accordion-content p-5 border-t border-slate-200 dark:border-white/5 bg-slate-50/50 dark:bg-dark-900/50">
                            <div id="education-list" class="space-y-4"></div>
                            <button onclick="addSectionItem('education')" class="mt-4 w-full py-3.5 bg-slate-100 dark:bg-dark-800 border border-dashed border-slate-300 dark:border-gray-600 rounded-xl text-xs font-black uppercase tracking-widest text-slate-500 dark:text-gray-400 hover:text-pcte-600 dark:hover:text-white hover:border-pcte-500 transition-colors flex items-center justify-center gap-2 shadow-inner cursor-pointer">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"></path></svg> Add Education Node
                            </button>
                        </div>
                    </div>
                    
                    <!-- Accordion: Certifications -->
                    <div class="glass-card rounded-2xl overflow-hidden shadow-sm">
                        <button class="accordion-toggle w-full p-5 flex justify-between items-center text-left hover:bg-slate-50 dark:hover:bg-white/5 transition group cursor-pointer" onclick="toggleAccordion(this)">
                            <div class="flex items-center gap-4">
                                <div class="w-10 h-10 rounded-xl bg-slate-100 dark:bg-dark-800 border border-slate-200 dark:border-white/10 flex items-center justify-center text-slate-500 group-hover:text-pcte-500 transition">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                </div>
                                <div>
                                    <span class="font-bold text-slate-900 dark:text-white block">Certifications</span>
                                    <span class="text-[10px] text-slate-500 uppercase tracking-widest">Awards & Courses</span>
                                </div>
                            </div>
                            <svg class="w-5 h-5 text-slate-400 transform transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                        </button>
                        <div class="accordion-content p-5 border-t border-slate-200 dark:border-white/5 bg-slate-50/50 dark:bg-dark-900/50">
                            <div id="certifications-list" class="space-y-4"></div>
                            <button onclick="addSectionItem('certifications')" class="mt-4 w-full py-3.5 bg-slate-100 dark:bg-dark-800 border border-dashed border-slate-300 dark:border-gray-600 rounded-xl text-xs font-black uppercase tracking-widest text-slate-500 dark:text-gray-400 hover:text-pcte-600 dark:hover:text-white hover:border-pcte-500 transition-colors flex items-center justify-center gap-2 shadow-inner cursor-pointer">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"></path></svg> Add Certification Node
                            </button>
                        </div>
                    </div>

                    <!-- Accordion: Skills & Languages (Chips) -->
                    <div class="glass-card rounded-2xl overflow-hidden shadow-sm">
                        <button class="accordion-toggle w-full p-5 flex justify-between items-center text-left hover:bg-slate-50 dark:hover:bg-white/5 transition group cursor-pointer" onclick="toggleAccordion(this)">
                            <div class="flex items-center gap-4">
                                <div class="w-10 h-10 rounded-xl bg-slate-100 dark:bg-dark-800 border border-slate-200 dark:border-white/10 flex items-center justify-center text-slate-500 group-hover:text-pcte-500 transition">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                                </div>
                                <div>
                                    <span class="font-bold text-slate-900 dark:text-white block">Skills & Languages</span>
                                    <span class="text-[10px] text-slate-500 uppercase tracking-widest">Keywords for ATS</span>
                                </div>
                            </div>
                            <svg class="w-5 h-5 text-slate-400 transform transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                        </button>
                        <div class="accordion-content p-5 border-t border-slate-200 dark:border-white/5 bg-slate-50/50 dark:bg-dark-900/50">
                            <label class="text-[11px] font-black text-slate-500 dark:text-gray-400 uppercase tracking-widest pl-1">Technical Skills</label>
                            <div class="bg-white dark:bg-dark-800 p-4 rounded-xl border border-slate-200 dark:border-white/5 min-h-[100px] shadow-inner mt-2 mb-6">
                                <div id="skills-container" class="flex flex-wrap"></div>
                                <input type="text" id="skill-input" placeholder="Type a core skill and press Enter..." class="bg-transparent border-none text-sm font-bold text-slate-900 dark:text-white focus:outline-none focus:ring-0 w-full mt-3 placeholder:font-normal" onkeydown="handleSkillInput(event, 'skills')">
                            </div>
                            
                            <label class="text-[11px] font-black text-slate-500 dark:text-gray-400 uppercase tracking-widest pl-1">Languages</label>
                            <div class="bg-white dark:bg-dark-800 p-4 rounded-xl border border-slate-200 dark:border-white/5 min-h-[80px] shadow-inner mt-2">
                                <div id="languages-container" class="flex flex-wrap"></div>
                                <input type="text" id="language-input" placeholder="e.g. English, Hindi, Punjabi..." class="bg-transparent border-none text-sm font-bold text-slate-900 dark:text-white focus:outline-none focus:ring-0 w-full mt-3 placeholder:font-normal" onkeydown="handleSkillInput(event, 'languages')">
                            </div>
                        </div>
                    </div>

                </div>
            </div>

            <!-- RIGHT PANE: A4 PDF Preview Engine -->
            <div class="hidden lg:flex w-[55%] xl:w-[60%] h-full preview-pane flex-col items-center pb-32">
                
                <div class="fixed top-[100px] right-8 bg-white/90 dark:bg-dark-900/90 rounded-xl flex flex-col p-1.5 shadow-2xl z-40 border border-slate-200 dark:border-white/10 backdrop-blur-xl">
                    <button onclick="changeZoom(0.1)" class="w-10 h-10 rounded-lg hover:bg-slate-100 dark:hover:bg-white/10 flex items-center justify-center text-slate-600 dark:text-gray-300 transition cursor-pointer" title="Zoom In"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg></button>
                    <div class="w-full h-px bg-slate-200 dark:bg-white/10 my-1"></div>
                    <div id="zoom-level" class="text-[10px] font-black text-center text-slate-900 dark:text-white py-1 select-none">100%</div>
                    <div class="w-full h-px bg-slate-200 dark:bg-white/10 my-1"></div>
                    <button onclick="changeZoom(-0.1)" class="w-10 h-10 rounded-lg hover:bg-slate-100 dark:hover:bg-white/10 flex items-center justify-center text-slate-600 dark:text-gray-300 transition cursor-pointer" title="Zoom Out"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4"></path></svg></button>
                </div>

                <div class="a4-wrapper" id="preview-wrapper">
                    <!-- This div is populated dynamically by JS based on the template logic -->
                    <div id="resume-canvas" class="a4-paper tpl-modern font-sans-doc space-normal"></div>
                </div>
                
            </div>
        </div>
    </div>

    <!-- ======================================================================
         MODALS & SIDEBARS
    ======================================================================= -->

    <!-- Design Settings Modal -->
    <div id="settings-modal" class="fixed inset-0 bg-slate-900/60 dark:bg-black/80 backdrop-blur-sm z-[100] hidden flex items-center justify-center opacity-0 transition-opacity duration-300">
        <div class="bg-white dark:bg-dark-900 w-full max-w-2xl rounded-[2rem] p-8 md:p-10 transform scale-95 transition-transform duration-300 border border-slate-200 dark:border-white/10 shadow-2xl" id="settings-card">
            
            <div class="flex justify-between items-center mb-8 border-b border-slate-200 dark:border-white/10 pb-5">
                <h2 class="text-2xl font-black text-slate-900 dark:text-white tracking-tight">Document Architecture</h2>
                <button onclick="toggleSettingsModal()" class="w-10 h-10 rounded-full bg-slate-100 dark:bg-dark-800 flex items-center justify-center text-slate-500 hover:bg-red-100 hover:text-red-500 transition-colors cursor-pointer"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg></button>
            </div>
            
            <div class="space-y-8">
                <!-- Layout Engine -->
                <div>
                    <label class="block text-[11px] font-black text-slate-400 dark:text-gray-500 mb-3 uppercase tracking-[0.2em]">Layout Engine</label>
                    <div class="grid grid-cols-2 md:grid-cols-5 gap-3">
                        <button onclick="setTemplate('modern')" id="btn-tpl-modern" class="py-4 px-2 border-2 border-slate-200 bg-slate-50 dark:border-white/5 dark:bg-dark-800 rounded-xl text-xs font-bold text-slate-600 dark:text-gray-400 hover:border-pcte-500 transition-all cursor-pointer">Modern</button>
                        <button onclick="setTemplate('harvard')" id="btn-tpl-harvard" class="py-4 px-2 border-2 border-slate-200 bg-slate-50 dark:border-white/5 dark:bg-dark-800 rounded-xl text-xs font-bold text-slate-600 dark:text-gray-400 hover:border-pcte-500 transition-all cursor-pointer">Harvard</button>
                        <button onclick="setTemplate('executive')" id="btn-tpl-executive" class="py-4 px-2 border-2 border-slate-200 bg-slate-50 dark:border-white/5 dark:bg-dark-800 rounded-xl text-xs font-bold text-slate-600 dark:text-gray-400 hover:border-pcte-500 transition-all cursor-pointer">Executive</button>
                        <button onclick="setTemplate('tech')" id="btn-tpl-tech" class="py-4 px-2 border-2 border-slate-200 bg-slate-50 dark:border-white/5 dark:bg-dark-800 rounded-xl text-xs font-bold text-slate-600 dark:text-gray-400 hover:border-pcte-500 transition-all cursor-pointer">Tech Split</button>
                        <button onclick="setTemplate('minimalist')" id="btn-tpl-minimalist" class="py-4 px-2 border-2 border-slate-200 bg-slate-50 dark:border-white/5 dark:bg-dark-800 rounded-xl text-xs font-bold text-slate-600 dark:text-gray-400 hover:border-pcte-500 transition-all cursor-pointer">Minimalist</button>
                    </div>
                </div>

                <!-- Color Configurator -->
                <div>
                    <label class="block text-[11px] font-black text-slate-400 dark:text-gray-500 mb-3 uppercase tracking-[0.2em]">Accent Color</label>
                    <div class="flex gap-4">
                        <button onclick="setThemeColor('#df3c3c')" class="w-12 h-12 rounded-full bg-[#df3c3c] border-2 border-transparent hover:scale-110 transition-transform color-btn shadow-md ring-offset-2 dark:ring-offset-dark-900 cursor-pointer"></button>
                        <button onclick="setThemeColor('#800000')" class="w-12 h-12 rounded-full bg-[#800000] border-2 border-transparent hover:scale-110 transition-transform color-btn shadow-md ring-offset-2 dark:ring-offset-dark-900 cursor-pointer"></button>
                        <button onclick="setThemeColor('#111111')" class="w-12 h-12 rounded-full bg-[#111111] border-2 border-transparent hover:scale-110 transition-transform color-btn shadow-md ring-offset-2 dark:ring-offset-dark-900 cursor-pointer"></button>
                        <button onclick="setThemeColor('#475569')" class="w-12 h-12 rounded-full bg-slate-600 border-2 border-transparent hover:scale-110 transition-transform color-btn shadow-md ring-offset-2 dark:ring-offset-dark-900 cursor-pointer"></button>
                    </div>
                </div>

                <!-- Font & Spacing -->
                <div class="grid grid-cols-2 gap-6">
                    <div>
                        <label class="block text-[11px] font-black text-slate-400 dark:text-gray-500 mb-2 uppercase tracking-[0.2em]">Typography</label>
                        <div class="relative">
                            <select onchange="setFont(this.value)" id="font-select" class="w-full bg-slate-50 dark:bg-dark-800 border border-slate-200 dark:border-white/10 rounded-xl px-4 py-3.5 text-sm font-bold text-slate-900 dark:text-white appearance-none cursor-pointer focus:outline-none focus:border-pcte-500 shadow-sm">
                                <option value="sans">Jakarta (Sans-Serif)</option>
                                <option value="serif">Merriweather (Serif)</option>
                                <option value="mono">Roboto (Monospace)</option>
                            </select>
                            <svg class="w-4 h-4 absolute right-4 top-[50%] transform -translate-y-1/2 text-slate-400 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                        </div>
                    </div>
                    <div>
                        <label class="block text-[11px] font-black text-slate-400 dark:text-gray-500 mb-2 uppercase tracking-[0.2em]">Node Density</label>
                        <div class="relative">
                            <select onchange="setSpacing(this.value)" id="spacing-select" class="w-full bg-slate-50 dark:bg-dark-800 border border-slate-200 dark:border-white/10 rounded-xl px-4 py-3.5 text-sm font-bold text-slate-900 dark:text-white appearance-none cursor-pointer focus:outline-none focus:border-pcte-500 shadow-sm">
                                <option value="compact">Compact (Fit More)</option>
                                <option value="normal">Normal (Standard)</option>
                                <option value="loose">Loose (Airy)</option>
                            </select>
                            <svg class="w-4 h-4 absolute right-4 top-[50%] transform -translate-y-1/2 text-slate-400 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mt-10 pt-6 border-t border-slate-200 dark:border-white/10 flex justify-end">
                <button onclick="toggleSettingsModal()" class="btn-primary font-black px-10 py-3.5 rounded-xl uppercase text-xs tracking-widest active:scale-95 shadow-xl cursor-pointer">Apply Architecture</button>
            </div>
        </div>
    </div>

    <!-- AI Chatbot Overlay Widget -->
    <div id="ai-sidebar" class="fixed top-[80px] right-0 w-full sm:w-[450px] h-[calc(100vh-80px)] bg-white dark:bg-dark-950 border-l border-slate-200 dark:border-white/10 shadow-[0_0_50px_rgba(0,0,0,0.2)] transform translate-x-full transition-transform duration-500 z-50 flex flex-col">
        
        <div class="p-6 border-b border-slate-200 dark:border-white/10 bg-pcte-50 dark:bg-pcte-900/10 flex justify-between items-center shrink-0">
            <div class="flex items-center gap-4">
                <div class="w-10 h-10 rounded-xl bg-pcte-600 flex items-center justify-center shadow-lg">
                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                </div>
                <div>
                    <h3 class="font-black text-slate-900 dark:text-white text-base">CareerBot AI</h3>
                    <div class="text-[10px] font-bold text-success-600 dark:text-success-400 flex items-center gap-1.5 uppercase tracking-widest mt-0.5"><span class="w-1.5 h-1.5 rounded-full bg-success-500 animate-pulse"></span> Gemini NLP Active</div>
                </div>
            </div>
            <button onclick="closeAI()" class="w-10 h-10 rounded-full bg-white dark:bg-dark-800 flex items-center justify-center text-slate-400 hover:text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors shadow-sm cursor-pointer"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg></button>
        </div>
        
        <div id="ai-chat-box" class="flex-1 overflow-y-auto p-6 space-y-6 bg-slate-50/50 dark:bg-[#080808]/50">
            <!-- Dynamic Chat Injection -->
        </div>

        <div class="p-6 border-t border-slate-200 dark:border-white/10 bg-white dark:bg-dark-900 shrink-0">
            <div class="relative">
                <textarea id="ai-chat-input" rows="2" class="w-full bg-slate-50 dark:bg-dark-950 border border-slate-200 dark:border-white/10 rounded-2xl px-4 py-3 text-sm font-medium resize-none pr-14 focus:outline-none focus:border-pcte-500 shadow-inner" placeholder="Ask AI to optimize your phrasing..." onkeydown="handleAIEnter(event)"></textarea>
                <button onclick="submitAIChat()" class="absolute right-2 bottom-2 w-10 h-10 rounded-xl bg-pcte-600 hover:bg-pcte-700 flex items-center justify-center text-white transition-transform active:scale-90 shadow-md cursor-pointer">
                    <svg class="w-4 h-4 transform rotate-45" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path></svg>
                </button>
            </div>
        </div>
    </div>

    <!-- ======================================================================
         JAVASCRIPT LOGIC (VIRTUAL DOM, AJAX, DRAG/DROP, PDF)
    ======================================================================= -->
    <script>
        // ---------------------------------------------------------
        // 1. STATE MANAGEMENT & HYDRATION (The "Virtual DOM")
        // ---------------------------------------------------------
        let state = <?php echo $preloadedData; ?>;
        
        // Data Integrity Check (Failsafe for older JSONs)
        if(!state.certifications) state.certifications = [];
        if(!state.languages) state.languages = [];
        if(!state.settings) state.settings = { template: 'modern', color: '#df3c3c', font: 'sans', spacing: 'normal' };
        
        let dragSourceId = null;
        let dragSourceSection = null;
        let currentTargetField = null;
        let zoomLevel = 1.0;
        let saveTimeout;
        let activeContextForAI = "";

        // Initialization Pipeline
        window.onload = () => {
            syncThemeIcon();
            hydrateInputs();
            renderDynamicLists();
            renderSkills();
            applySettingsToUI();
            updatePreview();
            
            // Adjust zoom dynamically based on screen real estate
            if(window.innerWidth > 1024 && window.innerWidth < 1500) {
                changeZoom(-0.2);
            }
        };

        // ---------------------------------------------------------
        // 2. THEME ENGINE 
        // ---------------------------------------------------------
        const themeBtn = document.getElementById('theme-toggle');
        function syncThemeIcon() {
            const isDark = document.documentElement.classList.contains('dark');
            const dIcon = document.getElementById('theme-toggle-dark-icon');
            const lIcon = document.getElementById('theme-toggle-light-icon');
            if(dIcon && lIcon) {
                if(isDark) { dIcon.classList.add('hidden'); lIcon.classList.remove('hidden'); } 
                else { lIcon.classList.add('hidden'); dIcon.classList.remove('hidden'); }
            }
        }
        if(themeBtn) {
            themeBtn.addEventListener('click', () => {
                document.documentElement.classList.toggle('dark');
                localStorage.setItem('color-theme', document.documentElement.classList.contains('dark') ? 'dark' : 'light');
                syncThemeIcon();
            });
        }
        
        const mobileBtn = document.getElementById('mobile-menu-btn');
        if(mobileBtn) {
            mobileBtn.addEventListener('click', () => {
                document.getElementById('mobile-menu').classList.toggle('hidden');
            });
        }

        // ---------------------------------------------------------
        // 3. TWO-WAY DATA BINDING 
        // ---------------------------------------------------------
        function hydrateInputs() {
            document.querySelectorAll('[data-sync]').forEach(el => {
                const path = el.getAttribute('data-sync').split('.');
                let val = path.length === 1 ? state[path[0]] : state[path[0]][path[1]];
                el.value = val || '';
            });
        }

        document.querySelectorAll('input[data-sync], textarea[data-sync]').forEach(el => {
            el.addEventListener('input', (e) => {
                const path = e.target.getAttribute('data-sync').split('.');
                if(path.length === 1) state[path[0]] = e.target.value;
                else state[path[0]][path[1]] = e.target.value;
                triggerEngineUpdate();
            });
        });

        // ---------------------------------------------------------
        // 4. DYNAMIC LIST RENDERING
        // ---------------------------------------------------------
        function renderDynamicLists() {
            renderList('experience', state.experience, ['title', 'company', 'location', 'start', 'end', 'desc'], 'Job Title', 'Company Name');
            renderList('education', state.education, ['degree', 'school', 'start', 'end', 'grade'], 'Degree / Credential', 'Institution Name');
            renderList('projects', state.projects, ['name', 'tech', 'link', 'desc'], 'Project Name', 'Tech Stack');
            renderList('certifications', state.certifications, ['name', 'issuer', 'date'], 'Certificate Name', 'Issuing Body');
            setupDragAndDrop();
        }

        function renderList(section, dataArray, fields, lbl1, lbl2) {
            const container = document.getElementById(`${section}-list`);
            if(!container) return;
            
            container.innerHTML = dataArray.map((item, index) => `
                <div class="draggable-item relative bg-white dark:bg-dark-900 p-5 rounded-2xl shadow-sm mb-4" draggable="true" data-id="${item.id}" data-section="${section}">
                    <div class="flex justify-between items-center mb-4 pb-3 border-b border-slate-200 dark:border-white/5">
                        <div class="cursor-grab text-slate-400 hover:text-pcte-500 drag-handle flex items-center gap-2 text-[10px] font-black uppercase tracking-widest transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M4 8h16M4 16h16"></path></svg>
                            Node ${index + 1}
                        </div>
                        <button onclick="removeSectionItem('${section}', ${item.id})" class="text-slate-400 hover:text-red-500 bg-slate-100 dark:bg-white/5 p-1.5 rounded-lg transition-colors cursor-pointer"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg></button>
                    </div>
                    
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        ${fields.map(f => {
                            if(f === 'desc') {
                                return `
                                <div class="col-span-1 sm:col-span-2 relative mt-2">
                                    <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">Description & Impact</label>
                                    <textarea rows="4" class="input-field w-full rounded-xl px-4 py-3 text-sm mt-1.5 resize-y font-medium leading-relaxed" oninput="updateItem('${section}', ${item.id}, '${f}', this.value)">${item[f] || ''}</textarea>
                                    <button onclick="openAIAssistant('Optimize these bullets for corporate ATS systems. Use strong action verbs. Do not use asterisks or markdown formatting.', this.previousElementSibling.value, '${section}_${item.id}_${f}')" class="absolute bottom-3 right-3 bg-pcte-100 dark:bg-dark-800 border border-pcte-200 dark:border-white/10 text-pcte-700 dark:text-pcte-400 hover:bg-pcte-600 hover:text-white text-[10px] font-black uppercase tracking-widest px-3 py-1.5 rounded-lg flex items-center gap-1 transition-colors shadow-sm cursor-pointer">AI Assist</button>
                                </div>`;
                            }
                            let label = f.charAt(0).toUpperCase() + f.slice(1);
                            if(f === fields[0]) label = lbl1; 
                            if(f === fields[1]) label = lbl2;
                            if(f === 'date') label = "Completion Date";
                            let colSpan = (f === 'start' || f === 'end' || f === 'grade' || f === 'location' || f === 'date') ? '' : 'col-span-1 sm:col-span-2 md:col-span-1';
                            return `
                                <div class="${colSpan}">
                                    <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">${label}</label>
                                    <input type="text" class="input-field w-full rounded-xl px-4 py-3 text-sm mt-1.5 font-semibold" value="${item[f] || ''}" oninput="updateItem('${section}', ${item.id}, '${f}', this.value)">
                                </div>`;
                        }).join('')}
                    </div>
                </div>
            `).join('');
        }

        function addSectionItem(section) {
            const newItem = { id: Date.now() };
            if(section === 'experience') Object.assign(newItem, {title:'', company:'', location:'', start:'', end:'', desc:'• '});
            if(section === 'education') Object.assign(newItem, {degree:'', school:'', start:'', end:'', grade:''});
            if(section === 'projects') Object.assign(newItem, {name:'', tech:'', link:'', desc:'• '});
            if(section === 'certifications') Object.assign(newItem, {name:'', issuer:'', date:''});
            state[section].push(newItem);
            
            renderDynamicLists(); 
            triggerEngineUpdate();
            
            setTimeout(() => {
                const list = document.getElementById(`${section}-list`);
                if(list && list.lastElementChild) list.lastElementChild.scrollIntoView({behavior: "smooth", block: "center"});
            }, 100);
        }

        function removeSectionItem(section, id) {
            state[section] = state[section].filter(item => item.id !== id);
            renderDynamicLists(); 
            triggerEngineUpdate();
        }

        function updateItem(section, id, field, value) {
            const item = state[section].find(i => i.id === id);
            if(item) { item[field] = value; triggerEngineUpdate(); }
        }

        // ---------------------------------------------------------
        // 5. NATIVE HTML5 DRAG AND DROP
        // ---------------------------------------------------------
        function setupDragAndDrop() {
            const draggables = document.querySelectorAll('.draggable-item');
            draggables.forEach(draggable => {
                draggable.addEventListener('dragstart', () => {
                    draggable.classList.add('dragging');
                    dragSourceId = draggable.getAttribute('data-id');
                    dragSourceSection = draggable.getAttribute('data-section');
                });
                draggable.addEventListener('dragend', () => {
                    draggable.classList.remove('dragging');
                    document.querySelectorAll('.drag-over').forEach(el => el.classList.remove('drag-over'));
                });
                draggable.addEventListener('dragover', e => {
                    e.preventDefault();
                    if(draggable.classList.contains('dragging')) return;
                    if(draggable.getAttribute('data-section') === dragSourceSection) {
                        draggable.classList.add('drag-over');
                    }
                });
                draggable.addEventListener('dragleave', () => draggable.classList.remove('drag-over'));
                draggable.addEventListener('drop', e => {
                    e.preventDefault();
                    draggable.classList.remove('drag-over');
                    const targetId = draggable.getAttribute('data-id');
                    const section = draggable.getAttribute('data-section');
                    if (section === dragSourceSection && dragSourceId !== targetId) {
                        reorderState(section, dragSourceId, targetId);
                    }
                });
            });
        }

        function reorderState(section, sourceId, targetId) {
            const arr = state[section];
            const srcIdx = arr.findIndex(i => i.id == sourceId);
            const tgtIdx = arr.findIndex(i => i.id == targetId);
            const [moved] = arr.splice(srcIdx, 1);
            arr.splice(tgtIdx, 0, moved);
            renderDynamicLists(); 
            triggerEngineUpdate();
        }

        // ---------------------------------------------------------
        // 6. MULTI-ARRAY CHIPS (Skills & Languages)
        // ---------------------------------------------------------
        function renderSkills() {
            renderChips('skills');
            renderChips('languages');
        }
        
        function renderChips(arrayName) {
            const container = document.getElementById(`${arrayName}-container`);
            if(!container) return;
            container.innerHTML = state[arrayName].map((val, i) => `
                <div class="skill-chip shadow-sm animate-fade-in-up" style="animation-delay: ${i*10}ms">
                    ${val}
                    <button onclick="removeChip('${arrayName}', ${i})" title="Remove">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>
            `).join('');
        }

        function handleSkillInput(e, arrayName) {
            if (e.key === 'Enter' || e.key === ',') {
                e.preventDefault();
                const val = e.target.value.trim().replace(/,$/, '');
                if (val && !state[arrayName].includes(val)) {
                    state[arrayName].push(val);
                    e.target.value = '';
                    renderChips(arrayName); 
                    triggerEngineUpdate();
                }
            }
        }
        
        function removeChip(arrayName, index) {
            state[arrayName].splice(index, 1);
            renderChips(arrayName); 
            triggerEngineUpdate();
        }

        // ---------------------------------------------------------
        // 7. UI ACCORDIONS & MODALS
        // ---------------------------------------------------------
        function toggleAccordion(btn) {
            const content = btn.nextElementSibling;
            const icon = btn.querySelector('svg:last-child');
            const isActive = content.classList.contains('active');
            
            // Close all others
            document.querySelectorAll('.accordion-content').forEach(el => el.classList.remove('active'));
            document.querySelectorAll('.accordion-toggle svg:last-child').forEach(el => el.style.transform = 'rotate(0deg)');
            
            // Open clicked
            if (!isActive) {
                content.classList.add('active');
                icon.style.transform = 'rotate(180deg)';
                setTimeout(() => { btn.scrollIntoView({ behavior: 'smooth', block: 'start' }); }, 300);
            }
        }

        function toggleSettingsModal() {
            const m = document.getElementById('settings-modal');
            const c = document.getElementById('settings-card');
            if(m.classList.contains('hidden')) {
                m.classList.remove('hidden');
                
                // Hydrate inputs
                document.getElementById('font-select').value = state.settings.font;
                document.getElementById('spacing-select').value = state.settings.spacing;
                
                setTimeout(() => { m.classList.remove('opacity-0'); c.classList.remove('scale-95'); }, 10);
            } else {
                m.classList.add('opacity-0'); c.classList.add('scale-95');
                setTimeout(() => { m.classList.add('hidden'); }, 300);
            }
        }

        function changeZoom(delta) {
            zoomLevel += delta;
            if(zoomLevel < 0.3) zoomLevel = 0.3;
            if(zoomLevel > 1.5) zoomLevel = 1.5;
            document.getElementById('preview-wrapper').style.transform = `scale(${zoomLevel})`;
            document.getElementById('zoom-level').innerText = `${Math.round(zoomLevel * 100)}%`;
        }

        // ---------------------------------------------------------
        // 8. TEMPLATE & DESIGN STATE SYNC
        // ---------------------------------------------------------
        function setTemplate(tpl) { state.settings.template = tpl; applySettingsToUI(); triggerEngineUpdate(); }
        function setThemeColor(hex) { state.settings.color = hex; document.documentElement.style.setProperty('--theme-color', hex); applySettingsToUI(); triggerEngineUpdate(); }
        function setFont(fnt) { state.settings.font = fnt; applySettingsToUI(); triggerEngineUpdate(); }
        function setSpacing(spc) { state.settings.spacing = spc; applySettingsToUI(); triggerEngineUpdate(); }

        function applySettingsToUI() {
            const canvas = document.getElementById('resume-canvas');
            const s = state.settings;
            
            document.documentElement.style.setProperty('--theme-color', s.color);
            canvas.style.setProperty('--theme-color', s.color); // also set directly on element
            canvas.className = `a4-paper tpl-${s.template} font-${s.font}-doc space-${s.spacing}`;
            
            // Reset template buttons
            const btnIds = ['modern', 'harvard', 'executive', 'tech', 'minimalist'];
            btnIds.forEach(id => {
                const b = document.getElementById(`btn-tpl-${id}`);
                if(b) {
                    b.classList.remove('border-pcte-500', 'bg-pcte-50', 'dark:border-pcte-500', 'dark:bg-pcte-900/20', 'text-pcte-700', 'dark:text-white');
                    b.classList.add('border-slate-200', 'dark:border-white/5', 'bg-slate-50', 'dark:bg-dark-800', 'text-slate-600', 'dark:text-gray-400');
                }
            });
            
            // Activate selected template button
            const activeBtn = document.getElementById(`btn-tpl-${s.template}`);
            if(activeBtn) {
                activeBtn.classList.remove('border-slate-200', 'dark:border-white/5', 'bg-slate-50', 'dark:bg-dark-800', 'text-slate-600', 'dark:text-gray-400');
                activeBtn.classList.add('border-pcte-500', 'bg-pcte-50', 'dark:border-pcte-500', 'dark:bg-pcte-900/20', 'text-pcte-700', 'dark:text-white');
            }

            // Update color selectors
            document.querySelectorAll('.color-btn').forEach(b => {
                b.classList.remove('border-white', 'ring-4');
                b.classList.add('border-transparent');
                
                // RGB to Hex logic
                const bg = window.getComputedStyle(b).backgroundColor;
                const rgb = bg.match(/\d+/g);
                if(rgb) {
                    const hex = '#' + rgb.map(x => parseInt(x).toString(16).padStart(2, '0')).join('');
                    if(hex === s.color || hex.toLowerCase() === s.color.toLowerCase()) {
                        b.classList.add('border-white', 'ring-4');
                    }
                }
            });
        }

        // ---------------------------------------------------------
        // 9. DATABASE SYNC & LIVE PREVIEW RENDERER
        // ---------------------------------------------------------
        function triggerEngineUpdate() {
            updatePreview(); // Immediately reflect in canvas
            
            const status = document.getElementById('save-status');
            status.innerHTML = `<svg class="w-3 h-3 text-amber-500 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg> Saving...`;
            status.className = "bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-400 px-2.5 py-1 rounded-md text-[9px] font-black uppercase tracking-widest flex items-center gap-1.5 border border-amber-200 dark:border-amber-500/20";

            clearTimeout(saveTimeout);
            
            // Debounce the database write by 800ms to save server load
            saveTimeout = setTimeout(async () => {
                try {
                    const res = await fetch(window.location.href, {
                        method: 'POST',
                        headers: { 
                            'Content-Type': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: JSON.stringify({ action: 'save_resume', resume_state: state })
                    });
                    
                    if(res.ok) {
                        status.innerHTML = `<span class="w-1.5 h-1.5 rounded-full bg-success-500 animate-pulse"></span> DB Synced`;
                        status.className = "bg-success-100 dark:bg-success-900/30 text-success-700 dark:text-success-400 px-2.5 py-1 rounded-md text-[9px] font-black uppercase tracking-widest flex items-center gap-1.5 border border-success-200 dark:border-success-500/20";
                    } else {
                        throw new Error('Sync failed');
                    }
                } catch (e) {
                    status.innerHTML = `⚠️ Sync Failed`;
                    status.className = "bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-400 px-2.5 py-1 rounded-md text-[9px] font-black uppercase tracking-widest flex items-center gap-1.5 border border-red-200 dark:border-red-500/20";
                }
            }, 800); 
        }

        // Text Formatter for PDF Preview (Converts plain text to HTML bullet lists)
        function formatBullets(text) {
            if(!text) return '';
            const lines = text.split('\n');
            let hasBullets = lines.some(l => l.trim().startsWith('•') || l.trim().startsWith('-') || l.trim().startsWith('*'));
            
            if(hasBullets) {
                return `<ul class="res-bullets">` + lines.map(l => {
                    let clean = l.replace(/^[•\-*]/, '').trim();
                    return clean ? `<li>${clean}</li>` : '';
                }).join('') + `</ul>`;
            }
            return `<div class="res-desc">${text.replace(/\n/g, '<br>')}</div>`;
        }

        // ---------------------------------------------------------
        // MASSIVE TEMPLATE GENERATION SWITCH
        // ---------------------------------------------------------
        function updatePreview() {
            const c = document.getElementById('resume-canvas');
            const s = state;
            
            // Build Universal Contact HTML String
            let contactHTML = '';
            if(s.personal.email) contactHTML += `<span>${s.personal.email}</span>`;
            if(s.personal.phone) contactHTML += `<span>${s.personal.phone}</span>`;
            if(s.personal.location) contactHTML += `<span>${s.personal.location}</span>`;
            if(s.personal.link) contactHTML += `<span>${s.personal.link}</span>`;

            if(s.settings.template === 'tech') {
                // ===================================
                // TEMPLATE 1: TECH SPLIT
                // ===================================
                // Use negative margin trick is replaced with a proper full-bleed wrapper
                // The a4-paper padding is overridden to 0 for this template, and inner
                // divs carry their own padding. This prevents pdf clipping.
                c.style.padding = '0';
                c.innerHTML = `
                    <div style="display:flex; width:100%; min-height:257mm;">
                        
                        <div class="tech-left" style="width: 35%; background: #1f2937; padding: 15mm; color: #e5e7eb; box-sizing:border-box; min-height:257mm;">
                            <div class="res-name" style="font-size:28pt; font-weight:800; color:#fff; line-height:1.1; margin-bottom:5px;">${s.personal.fname}<br>${s.personal.lname}</div>
                            <div class="res-title" style="font-size:12pt; font-weight:500; color:var(--theme-color); margin-bottom:20px;">${s.personal.title || ''}</div>
                            
                            <div class="res-section-title" style="font-size:12pt; font-weight:700; color:#fff; text-transform:uppercase; letter-spacing:2px; margin-bottom:15px; margin-top:30px;">Contact</div>
                            <div class="res-contact" style="font-size:9pt; display:flex; flex-direction:column; gap:8px;">
                                ${s.personal.email ? `<span>✉ ${s.personal.email}</span>` : ''}
                                ${s.personal.phone ? `<span>☎ ${s.personal.phone}</span>` : ''}
                                ${s.personal.location ? `<span>📍 ${s.personal.location}</span>` : ''}
                                ${s.personal.link ? `<span>🔗 ${s.personal.link}</span>` : ''}
                            </div>

                            ${s.skills.length > 0 ? `
                            <div class="res-section-title" style="font-size:12pt; font-weight:700; color:#fff; text-transform:uppercase; letter-spacing:2px; margin-bottom:15px; margin-top:30px;">Skills</div>
                            <div style="display:flex; flex-wrap:wrap; gap:8px;">
                                ${s.skills.map(sk => `<span style="background:#374151; color:#fff; padding:4px 8px; border-radius:4px; font-size:8.5pt; font-family:'Roboto Mono', monospace;">${sk}</span>`).join('')}
                            </div>` : ''}
                            
                            ${s.education.length > 0 ? `
                            <div class="res-section-title" style="font-size:12pt; font-weight:700; color:#fff; text-transform:uppercase; letter-spacing:2px; margin-bottom:15px; margin-top:30px;">Education</div>
                            <div>
                                ${s.education.map(ed => `
                                    <div class="res-item" style="margin-bottom:15px;">
                                        <div style="font-size:10pt; font-weight:700; color:#fff;">${ed.degree}</div>
                                        <div style="font-size:9pt; color:#d1d5db; margin-bottom:4px;">${ed.school}</div>
                                        <div style="font-size:8pt; color:#9ca3af;">${ed.start} - ${ed.end}</div>
                                        <div style="font-size:9pt; color:#9ca3af; margin-top:4px;">${ed.grade}</div>
                                    </div>
                                `).join('')}
                            </div>` : ''}
                            
                            ${s.languages && s.languages.length > 0 ? `
                            <div class="res-section-title" style="font-size:12pt; font-weight:700; color:#fff; text-transform:uppercase; letter-spacing:2px; margin-bottom:15px; margin-top:30px;">Languages</div>
                            <div style="font-size:9pt; line-height: 1.8;">${s.languages.join('<br>')}</div>` : ''}
                        </div>
                        
                        <div class="tech-right" style="width: 65%; background: #ffffff; padding: 15mm; color: #374151; box-sizing:border-box;">
                            ${s.summary ? `
                            <div class="res-section">
                                <div style="font-size:12pt; font-weight:700; color:var(--theme-color); text-transform:uppercase; letter-spacing:2px; margin-bottom:15px; border-bottom:2px solid #e5e7eb; padding-bottom:5px;">Profile</div>
                                <div style="font-size:10pt; line-height:1.6; color: #111;">${s.summary.replace(/\n/g, '<br>')}</div>
                            </div>` : ''}

                            ${s.experience.length > 0 ? `
                            <div class="res-section" style="margin-top:25px;">
                                <div style="font-size:12pt; font-weight:700; color:var(--theme-color); text-transform:uppercase; letter-spacing:2px; margin-bottom:15px; border-bottom:2px solid #e5e7eb; padding-bottom:5px;">Experience</div>
                                ${s.experience.map(ex => `
                                    <div class="res-item" style="margin-bottom:20px;">
                                        <div style="display:flex; justify-content:space-between; align-items:baseline;">
                                            <div style="font-size:11pt; font-weight:700; color:#111;">${ex.title}</div>
                                            <div style="font-size:9pt; font-weight:600; color:var(--theme-color);">${ex.start} - ${ex.end}</div>
                                        </div>
                                        <div style="font-size:10pt; font-weight:500; color:#4b5563; margin-bottom:8px;">${ex.company} ${ex.location ? `| ${ex.location}` : ''}</div>
                                        <div style="font-size:9.5pt; line-height:1.6; color:#374151;">${formatBullets(ex.desc)}</div>
                                    </div>
                                `).join('')}
                            </div>` : ''}

                            ${s.projects.length > 0 ? `
                            <div class="res-section" style="margin-top:25px;">
                                <div style="font-size:12pt; font-weight:700; color:var(--theme-color); text-transform:uppercase; letter-spacing:2px; margin-bottom:15px; border-bottom:2px solid #e5e7eb; padding-bottom:5px;">Projects</div>
                                ${s.projects.map(pr => `
                                    <div class="res-item" style="margin-bottom:20px;">
                                        <div style="font-size:11pt; font-weight:700; color:#111;">${pr.name} ${pr.link ? `<span style="font-size:8pt;font-weight:normal;color:#6b7280;margin-left:8px;">(${pr.link})</span>` : ''}</div>
                                        <div style="font-size:9pt; font-weight:600; color:var(--theme-color); margin-bottom:8px; font-family:'Roboto Mono', monospace;">${pr.tech}</div>
                                        <div style="font-size:9.5pt; line-height:1.6; color:#374151;">${formatBullets(pr.desc)}</div>
                                    </div>
                                `).join('')}
                            </div>` : ''}
                        </div>
                    </div>
                `;
            } else if (s.settings.template === 'executive') {
                // ===================================
                // TEMPLATE 2: EXECUTIVE 
                // ===================================
                c.style.padding = '20mm';
                c.innerHTML = `
                    <div class="res-header">
                        <div class="res-name">${s.personal.fname} ${s.personal.lname}</div>
                        ${s.personal.title ? `<div class="res-title">${s.personal.title}</div>` : ''}
                        <div class="res-contact">${contactHTML}</div>
                    </div>
                    
                    ${s.summary ? `
                    <div class="res-section">
                        <div class="res-section-title">Executive Summary</div>
                        <div class="res-desc" style="font-size:10.5pt; font-weight:500;">${s.summary.replace(/\n/g, '<br>')}</div>
                    </div>` : ''}
                    
                    ${s.experience.length > 0 ? `
                    <div class="res-section">
                        <div class="res-section-title">Professional Experience</div>
                        ${s.experience.map(ex => `
                            <div class="res-item">
                                <div class="res-item-header">
                                    <div class="res-item-title">${ex.title}</div>
                                    <div class="res-item-date">${ex.start} - ${ex.end}</div>
                                </div>
                                <div class="res-item-sub">${ex.company} ${ex.location ? `| ${ex.location}` : ''}</div>
                                ${formatBullets(ex.desc)}
                            </div>
                        `).join('')}
                    </div>` : ''}
                    
                    <div style="display:flex; gap:30px;">
                        <div style="flex:1;">
                            ${s.projects.length > 0 ? `
                            <div class="res-section">
                                <div class="res-section-title">Key Projects</div>
                                ${s.projects.map(pr => `
                                    <div class="res-item">
                                        <div class="res-item-header"><div class="res-item-title">${pr.name}</div></div>
                                        <div class="res-item-sub">${pr.tech}</div>
                                        ${formatBullets(pr.desc)}
                                    </div>
                                `).join('')}
                            </div>` : ''}
                        </div>
                        <div style="flex:1;">
                            ${s.education.length > 0 ? `
                            <div class="res-section">
                                <div class="res-section-title">Education</div>
                                ${s.education.map(ed => `
                                    <div class="res-item">
                                        <div class="res-item-title">${ed.degree}</div>
                                        <div class="res-item-sub" style="margin-bottom:0;">${ed.school}</div>
                                        <div class="res-item-date" style="margin-bottom:4px;">${ed.start} - ${ed.end}</div>
                                        <div class="res-desc">${ed.grade}</div>
                                    </div>
                                `).join('')}
                            </div>` : ''}
                            
                            ${s.certifications && s.certifications.length > 0 ? `
                            <div class="res-section">
                                <div class="res-section-title">Certifications</div>
                                ${s.certifications.map(ce => `
                                    <div class="res-item">
                                        <div class="res-item-title">${ce.name}</div>
                                        <div class="res-item-date" style="margin-bottom:4px;">${ce.date}</div>
                                        <div class="res-item-sub">${ce.issuer}</div>
                                    </div>
                                `).join('')}
                            </div>` : ''}

                            ${s.skills.length > 0 ? `
                            <div class="res-section">
                                <div class="res-section-title">Core Competencies</div>
                                <div class="res-skills">
                                    ${s.skills.map(sk => `<div class="res-skill-badge">${sk}</div>`).join('')}
                                </div>
                            </div>` : ''}

                            ${s.languages && s.languages.length > 0 ? `
                            <div class="res-section">
                                <div class="res-section-title">Languages</div>
                                <div class="res-skills" style="color:#475569; font-weight:600; font-size:9.5pt;">
                                    ${s.languages.join(' • ')}
                                </div>
                            </div>` : ''}
                        </div>
                    </div>
                `;
            } else if (s.settings.template === 'minimalist') {
                // ===================================
                // TEMPLATE 3: MINIMALIST
                // ===================================
                c.style.padding = '20mm';
                c.innerHTML = `
                    <div class="res-header" style="display:flex; justify-content:space-between; align-items:flex-end; border-bottom:2px solid #e5e7eb; padding-bottom:15px; margin-bottom:25px;">
                        <div>
                            <div class="res-name" style="font-size:28pt; font-weight:300; color:#111827; letter-spacing:-1px;">${s.personal.fname} <strong style="font-weight:800;">${s.personal.lname}</strong></div>
                            ${s.personal.title ? `<div class="res-title" style="font-size:12pt; font-weight:600; color:var(--theme-color); margin-top:5px;">${s.personal.title}</div>` : ''}
                        </div>
                        <div class="res-contact" style="text-align:right; font-size:9pt; color:#6b7280; line-height:1.6;">
                            ${s.personal.email ? `<div>${s.personal.email}</div>` : ''}
                            ${s.personal.phone ? `<div>${s.personal.phone}</div>` : ''}
                            ${s.personal.location ? `<div>${s.personal.location}</div>` : ''}
                            ${s.personal.link ? `<div>${s.personal.link}</div>` : ''}
                        </div>
                    </div>

                    ${s.summary ? `
                    <div class="res-section" style="margin-bottom:30px;">
                        <div class="res-desc" style="font-size:10.5pt; line-height:1.7; color:#374151; font-weight:500;">${s.summary.replace(/\n/g, '<br>')}</div>
                    </div>` : ''}

                    ${s.experience.length > 0 ? `
                    <div class="res-section" style="margin-bottom:30px;">
                        <div class="res-section-title" style="font-size:11pt; font-weight:800; color:#111827; text-transform:uppercase; letter-spacing:2px; margin-bottom:20px;">Experience</div>
                        ${s.experience.map(ex => `
                            <div class="res-item" style="display:grid; grid-template-columns: 1fr 3fr; gap:20px; margin-bottom:20px;">
                                <div class="res-item-date" style="font-size:9.5pt; font-weight:700; color:var(--theme-color);">${ex.start} — <br>${ex.end}</div>
                                <div>
                                    <div class="res-item-title" style="font-size:11pt; font-weight:800; color:#111827;">${ex.title}</div>
                                    <div class="res-item-sub" style="font-size:10pt; font-weight:600; color:#6b7280; margin-bottom:8px;">${ex.company} ${ex.location ? `| ${ex.location}` : ''}</div>
                                    <div class="res-desc" style="font-size:9.5pt; color:#4b5563; line-height:1.6;">${formatBullets(ex.desc)}</div>
                                </div>
                            </div>
                        `).join('')}
                    </div>` : ''}

                    ${s.projects.length > 0 ? `
                    <div class="res-section" style="margin-bottom:30px;">
                        <div class="res-section-title" style="font-size:11pt; font-weight:800; color:#111827; text-transform:uppercase; letter-spacing:2px; margin-bottom:20px;">Projects</div>
                        ${s.projects.map(pr => `
                            <div class="res-item" style="display:grid; grid-template-columns: 1fr 3fr; gap:20px; margin-bottom:20px;">
                                <div class="res-item-date" style="font-size:9pt; font-weight:700; color:#6b7280; font-family:'Roboto Mono', monospace; word-wrap:break-word;">${pr.tech}</div>
                                <div>
                                    <div class="res-item-title" style="font-size:11pt; font-weight:800; color:#111827;">${pr.name} ${pr.link ? `<span style="font-size:8.5pt; font-weight:500; color:#9ca3af; margin-left:8px;">${pr.link}</span>` : ''}</div>
                                    <div class="res-desc" style="font-size:9.5pt; color:#4b5563; line-height:1.6; margin-top:6px;">${formatBullets(pr.desc)}</div>
                                </div>
                            </div>
                        `).join('')}
                    </div>` : ''}

                    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:40px;">
                        <div>
                            ${s.education.length > 0 ? `
                            <div class="res-section">
                                <div class="res-section-title" style="font-size:11pt; font-weight:800; color:#111827; text-transform:uppercase; letter-spacing:2px; margin-bottom:20px;">Education</div>
                                ${s.education.map(ed => `
                                    <div class="res-item" style="margin-bottom:15px;">
                                        <div class="res-item-title" style="font-size:10.5pt; font-weight:800; color:#111827;">${ed.degree}</div>
                                        <div class="res-item-sub" style="font-size:9.5pt; font-weight:600; color:#6b7280; margin-top:2px;">${ed.school}</div>
                                        <div style="display:flex; justify-content:space-between; margin-top:4px;">
                                            <div class="res-item-date" style="font-size:9pt; font-weight:600; color:var(--theme-color);">${ed.start} - ${ed.end}</div>
                                            <div class="res-desc" style="font-size:9pt; font-weight:700; color:#4b5563;">${ed.grade}</div>
                                        </div>
                                    </div>
                                `).join('')}
                            </div>` : ''}
                        </div>
                        
                        <div>
                            ${s.skills.length > 0 ? `
                            <div class="res-section" style="margin-bottom:25px;">
                                <div class="res-section-title" style="font-size:11pt; font-weight:800; color:#111827; text-transform:uppercase; letter-spacing:2px; margin-bottom:15px;">Skills</div>
                                <div class="res-skills" style="font-size:10pt; line-height:1.7; color:#374151; font-weight:600;">
                                    ${s.skills.join(' <span style="color:var(--theme-color); margin:0 4px;">•</span> ')}
                                </div>
                            </div>` : ''}
                            
                            ${s.certifications && s.certifications.length > 0 ? `
                            <div class="res-section">
                                <div class="res-section-title" style="font-size:11pt; font-weight:800; color:#111827; text-transform:uppercase; letter-spacing:2px; margin-bottom:15px;">Certifications</div>
                                ${s.certifications.map(ce => `
                                    <div class="res-item" style="margin-bottom:12px;">
                                        <div class="res-item-title" style="font-size:10pt; font-weight:700; color:#111827;">${ce.name}</div>
                                        <div style="display:flex; justify-content:space-between; margin-top:2px;">
                                            <div class="res-item-sub" style="font-size:9pt; color:#6b7280;">${ce.issuer}</div>
                                            <div class="res-item-date" style="font-size:9pt; font-weight:600; color:var(--theme-color);">${ce.date}</div>
                                        </div>
                                    </div>
                                `).join('')}
                            </div>` : ''}
                        </div>
                    </div>
                `;
            } else {
                // ===================================
                // TEMPLATE 4/5: STANDARD & HARVARD
                // ===================================
                c.style.padding = '20mm';
                c.innerHTML = `
                    <div class="res-header">
                        <div class="res-name">${s.personal.fname} ${s.personal.lname}</div>
                        ${s.personal.title ? `<div class="res-title">${s.personal.title}</div>` : ''}
                        <div class="res-contact">${contactHTML}</div>
                    </div>

                    ${s.summary ? `
                    <div class="res-section">
                        <div class="res-section-title">Professional Summary</div>
                        <div class="res-desc">${s.summary.replace(/\n/g, '<br>')}</div>
                    </div>` : ''}

                    ${s.experience.length > 0 ? `
                    <div class="res-section">
                        <div class="res-section-title">Experience</div>
                        ${s.experience.map(ex => `
                            <div class="res-item">
                                <div class="res-item-header">
                                    <div class="res-item-title">${ex.title}</div>
                                    <div class="res-item-date">${ex.start} - ${ex.end}</div>
                                </div>
                                <div class="res-item-sub">${ex.company} ${ex.location ? `| ${ex.location}` : ''}</div>
                                ${formatBullets(ex.desc)}
                            </div>
                        `).join('')}
                    </div>` : ''}

                    ${s.projects.length > 0 ? `
                    <div class="res-section">
                        <div class="res-section-title">Projects</div>
                        ${s.projects.map(pr => `
                            <div class="res-item">
                                <div class="res-item-header">
                                    <div class="res-item-title">${pr.name} ${pr.link ? `<span style="font-size:9pt;font-weight:normal;">| ${pr.link}</span>` : ''}</div>
                                </div>
                                <div class="res-item-sub">${pr.tech}</div>
                                ${formatBullets(pr.desc)}
                            </div>
                        `).join('')}
                    </div>` : ''}

                    ${s.education.length > 0 ? `
                    <div class="res-section">
                        <div class="res-section-title">Education</div>
                        ${s.education.map(ed => `
                            <div class="res-item">
                                <div class="res-item-header">
                                    <div class="res-item-title">${ed.degree} ${ed.grade ? `(${ed.grade})` : ''}</div>
                                    <div class="res-item-date">${ed.start} - ${ed.end}</div>
                                </div>
                                <div class="res-item-sub">${ed.school}</div>
                            </div>
                        `).join('')}
                    </div>` : ''}

                    ${s.certifications && s.certifications.length > 0 ? `
                    <div class="res-section">
                        <div class="res-section-title">Certifications</div>
                        ${s.certifications.map(ce => `
                            <div class="res-item">
                                <div class="res-item-header">
                                    <div class="res-item-title">${ce.name}</div>
                                    <div class="res-item-date">${ce.date}</div>
                                </div>
                                <div class="res-item-sub">${ce.issuer}</div>
                            </div>
                        `).join('')}
                    </div>` : ''}

                    <div style="display:flex; gap:20px; margin-top:15px;">
                        ${s.skills.length > 0 ? `
                        <div style="flex:2;">
                            <div class="res-section-title">Technical Skills</div>
                            <div class="res-skills">
                                ${s.skills.map(sk => `<div class="res-skill-badge">${sk}</div>`).join('')}
                            </div>
                        </div>` : ''}
                        
                        ${s.languages && s.languages.length > 0 ? `
                        <div style="flex:1;">
                            <div class="res-section-title">Languages</div>
                            <div class="res-skills" style="display:flex; flex-wrap:wrap; gap:8px;">
                                ${s.languages.map(lang => `<div class="res-skill-badge" style="background:transparent; border:1px solid #e5e7eb;">${lang}</div>`).join('')}
                            </div>
                        </div>` : ''}
                    </div>
                `;
            }
        }

        // ============================================================================
        // 10. PDF GENERATION ENGINE (HTML2PDF)
        // ============================================================================
      // ============================================================================
        // 10. PDF GENERATION ENGINE (HTML2PDF)
        // ============================================================================
        function exportPDF() {
            const btn = document.querySelector('button[onclick="exportPDF()"]');
            const originalText = btn.innerHTML;

            btn.disabled = true;
            btn.innerHTML = `<svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg> <span class="hidden sm:block">Generating...</span>`;

            // Resolve the theme color CSS variable to a real hex value
            const themeColor = getComputedStyle(document.documentElement)
                                .getPropertyValue('--theme-color').trim() || state.settings.color || '#df3c3c';

            const fullName = `${(state.personal.fname || 'Resume').trim()}_${(state.personal.lname || '').trim()}`.replace(/\s+/g, '_');
            const isTech   = state.settings.template === 'tech';

            // Collect all stylesheet text from the current page and inject page-break protections
            let allCSS = `:root { --theme-color: ${themeColor}; }
* { box-sizing: border-box; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
body { margin:0; padding:0; background: ${isTech ? '#111827' : 'white'}; }
.res-item { page-break-inside: avoid; break-inside: avoid; }`;
            
            document.querySelectorAll('style').forEach(s => { allCSS += '\n' + s.textContent; });
            // Replace CSS vars in the collected styles
            allCSS = allCSS.replace(/var\(--theme-color\)/g, themeColor);

            // Clone the live resume element
            const source = document.getElementById('resume-canvas');
            const clone  = source.cloneNode(true);
            clone.id = 'pdf-clone-inner';

            // Strip transforms and force the correct background based on the template
            clone.style.cssText = `
                width: 794px !important;
                min-height: auto !important;
                padding: ${isTech ? '0' : '20mm'} !important;
                box-shadow: none !important;
                overflow: visible !important;
                transform: none !important;
                background: ${isTech ? '#111827' : 'white'} !important;
                position: static !important;
            `;

            // Bake CSS variable values into every inline-styled element
            clone.querySelectorAll('[style]').forEach(el => {
                el.style.cssText = el.style.cssText.replace(/var\(--theme-color\)/g, themeColor);
            });

            // Build a full standalone HTML document string for the iframe
            const docHTML = `<!DOCTYPE html>
<html><head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800;900&family=Inter:wght@400;500;600;700&family=Merriweather:wght@300;400;700&family=Roboto+Mono:wght@400;500&display=swap" rel="stylesheet">
<style>${allCSS}</style>
</head><body style="margin:0;padding:0;background:${isTech ? '#111827' : 'white'};">
${clone.outerHTML}
</body></html>`;

            // Create a hidden iframe, write the full document into it, then capture
            const iframe = document.createElement('iframe');
            iframe.style.cssText = 'position:fixed;top:0;left:0;width:794px;height:1px;opacity:0;pointer-events:none;border:none;z-index:-9999;';
            document.body.appendChild(iframe);

            iframe.onload = function() {
                const iframeDoc = iframe.contentDocument || iframe.contentWindow.document;
                const target = iframeDoc.getElementById('pdf-clone-inner');

                const opt = {
                    margin:   0,
                    filename: `${fullName}_Resume.pdf`,
                    image:    { type: 'jpeg', quality: 0.98 },
                    html2canvas: {
                        scale:           2,
                        useCORS:         true,
                        allowTaint:      true,
                        backgroundColor: isTech ? '#111827' : '#ffffff',
                        scrollX:         0,
                        scrollY:         0,
                        windowWidth:     794,
                        width:           794,
                    },
                    pagebreak: { mode: ['css', 'legacy'] },
                    jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' }
                };

                // Give fonts 400ms to load inside the iframe before capturing
                setTimeout(() => {
                    html2pdf().set(opt).from(target).save()
                        .then(() => {
                            document.body.removeChild(iframe);
                            btn.disabled  = false;
                            btn.innerHTML = originalText;
                        })
                        .catch(err => {
                            console.error('PDF export error:', err);
                            if (document.body.contains(iframe)) document.body.removeChild(iframe);
                            btn.disabled  = false;
                            btn.innerHTML = originalText;
                            alert('PDF export failed. Please try again.');
                        });
                }, 400);
            };

            // Write the document into the iframe (triggers onload)
            iframe.contentDocument.open();
            iframe.contentDocument.write(docHTML);
            iframe.contentDocument.close();
        }

        // ============================================================================
        // 11. GEMINI AI ENGINE (Connecting to PHP Backend via Curl)
        // ============================================================================
        function openAIAssistant(promptContext, currentText, targetFieldId) {
            currentTargetField = targetFieldId;
            const sidebar = document.getElementById('ai-sidebar');
            const box = document.getElementById('ai-chat-box');
            activeContextForAI = currentText;
            
            // Slide in the sidebar
            sidebar.classList.remove('translate-x-full');
            
            // Initial AI greeting based on context
            box.innerHTML = `
                <div class="bg-white dark:bg-dark-800 border border-slate-200 dark:border-white/10 rounded-2xl p-5 text-sm text-slate-700 dark:text-gray-300 rounded-tr-none w-[90%] shadow-sm">
                    <p class="font-black text-slate-900 dark:text-white mb-3">Initializing AI Node...</p>
                    <p class="italic border-l-4 border-pcte-500 pl-3 mb-4 text-xs text-slate-500 dark:text-gray-400 bg-slate-50 dark:bg-dark-900 p-2 rounded-r-lg">"${currentText}"</p>
                    <p class="font-medium text-xs text-pcte-600 dark:text-pcte-400">${promptContext}</p>
                </div>
            `;
            
            // Auto-Trigger the first request
            submitAIChat(promptContext);
        }

        function closeAI() {
            document.getElementById('ai-sidebar').classList.add('translate-x-full');
            currentTargetField = null;
        }

        function handleAIEnter(e) {
            if(e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                submitAIChat();
            }
        }

        async function submitAIChat(forcedPrompt = null) {
            const input = document.getElementById('ai-chat-input');
            const prompt = forcedPrompt || input.value.trim();
            if(!prompt && !forcedPrompt) return;

            const box = document.getElementById('ai-chat-box');
            
            if(!forcedPrompt) {
                box.innerHTML += `
                    <div class="bg-white dark:bg-dark-800 border border-slate-200 dark:border-white/10 rounded-2xl p-4 text-sm text-slate-700 dark:text-gray-300 rounded-tr-none w-3/4 ml-auto mt-4 shadow-sm">
                        ${prompt}
                    </div>
                `;
                input.value = '';
            }
            box.scrollTop = box.scrollHeight;

            // Typing Indicator HTML
            const typingId = 'typing-' + Date.now();
            box.innerHTML += `
                <div id="${typingId}" class="bg-pcte-50 dark:bg-pcte-900/20 border border-pcte-100 dark:border-pcte-900/50 rounded-2xl p-4 rounded-tl-none w-1/3 mt-4 flex items-center justify-center gap-2">
                    <span class="w-2 h-2 bg-pcte-500 rounded-full animate-bounce" style="animation-delay:0ms"></span>
                    <span class="w-2 h-2 bg-pcte-500 rounded-full animate-bounce" style="animation-delay:150ms"></span>
                    <span class="w-2 h-2 bg-pcte-500 rounded-full animate-bounce" style="animation-delay:300ms"></span>
                </div>
            `;
            box.scrollTop = box.scrollHeight;

            try {
                // Call self (PHP block at the very top of this file)
                const res = await fetch(window.location.href, {
                    method: 'POST',
                    headers: { 
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({ action: 'ai_assist', prompt: prompt, context: activeContextForAI })
                });
                
                const data = await res.json();
                document.getElementById(typingId).remove();

                if(data.status === 'success') {
                    // Provide the AI response and an Apply button
                    box.innerHTML += `
                        <div class="bg-pcte-50 dark:bg-pcte-900/20 border border-pcte-100 dark:border-pcte-900/50 rounded-2xl p-5 text-sm text-slate-900 dark:text-white rounded-tl-none w-[90%] ml-auto mt-4 shadow-md">
                            <p class="mb-3 font-bold text-pcte-700 dark:text-pcte-400">Gemini Optimization:</p>
                            <div class="bg-white dark:bg-dark-950 p-4 rounded-xl border border-pcte-200 dark:border-white/10 mb-4 font-medium leading-relaxed shadow-inner" id="ai-suggestion">${data.data.replace(/\n/g, '<br>')}</div>
                            <div class="flex flex-wrap gap-2">
                                <button onclick="applyAIText()" class="btn-primary text-white text-xs font-black uppercase tracking-widest px-4 py-2.5 rounded-xl shadow-md cursor-pointer">Apply Update</button>
                                <button onclick="submitAIChat('Make it shorter, punchier, and remove passive voice.')" class="bg-white dark:bg-dark-800 border border-slate-200 dark:border-white/10 hover:border-pcte-500 dark:hover:border-pcte-500 text-slate-600 dark:text-gray-300 text-xs font-bold px-4 py-2.5 rounded-xl transition-all cursor-pointer">Make Shorter</button>
                            </div>
                        </div>
                    `;
                    // Update active context so subsequent AI requests build upon the new generated text
                    activeContextForAI = data.data; 
                } else {
                    box.innerHTML += `<div class="bg-red-50 dark:bg-red-900/20 text-red-600 dark:text-red-400 p-4 rounded-xl mt-4 text-sm font-bold border border-red-200 dark:border-red-900/50">${data.message}</div>`;
                }
            } catch(e) {
                document.getElementById(typingId).remove();
                box.innerHTML += `<div class="bg-red-50 dark:bg-red-900/20 text-red-600 dark:text-red-400 p-4 rounded-xl mt-4 text-sm font-bold border border-red-200 dark:border-red-900/50">Network Error: Failed to reach AI node. Make sure XAMPP curl is enabled.</div>`;
            }
            box.scrollTop = box.scrollHeight;
        }

        function applyAIText() {
            if(!currentTargetField) return;
            
            // Extract text from the latest suggestion box
            const newTextElems = document.querySelectorAll('#ai-suggestion');
            let textToApply = newTextElems[newTextElems.length-1].innerHTML.replace(/<br>/g, '\n');

            // Apply to the specific input field that triggered it
            if(currentTargetField === 'summary') {
                document.getElementById('inp-summary').value = textToApply;
                state.summary = textToApply;
            } else {
                const parts = currentTargetField.split('_'); // e.g. "experience_123456_desc"
                updateItem(parts[0], parseInt(parts[1]), parts[2], textToApply);
                renderDynamicLists(); // re-render inputs to show new text
            }
            
            triggerEngineUpdate(); // Sync to PDF and Database
            closeAI();
        }

        function runGlobalAI() {
            openAIAssistant("Please review my entire master resume data payload and suggest global improvements for modern ATS systems. Identify missing skills based on my title.", JSON.stringify(state, null, 2), "none");
        }
    </script>
    
    <!-- Public Chatbot Fallback (If exists) -->
    <?php 
    if (file_exists('chatbot.php')) {
        include 'chatbot.php'; 
    } 
    ?>
</body>
</html>