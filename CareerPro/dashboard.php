<?php
declare(strict_types=1);
session_start();

/**
 * ============================================================================
 * CareerPro Suite - Advanced Student Dashboard (Enterprise UI v9.0)
 * Architecture: 
 * - Full Dark/Light Reactivity (Strict Crimson Theme)
 * - Real-time Database Sync (PDO) with Fallback Logic
 * - Chart.js Data Visualization (ATS Trends)
 * - Advanced App-Shell Layout (Sidebar + Top Navigation)
 * - Algorithmic Job Recommendations & Progress Matrices
 * ============================================================================
 */

// 1. SESSION GUARD: Security check for authenticated access
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

require_once 'includes/db.php';
$db = Database::getInstance()->getConnection();
$userId = (int)$_SESSION['user_id'];

// 2. DATA ORCHESTRATION: Syncing with Identity & Application Tables
try {
    // Fetch Core User Profile & Master Resume Node
    $stmt = $db->prepare("
        SELECT u.name, u.email, u.phone, u.created_at, r.resume_data, r.last_updated 
        FROM users u 
        LEFT JOIN resumes r ON u.id = r.user_id 
        WHERE u.id = :id
    ");
    $stmt->execute(['id' => $userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        session_destroy();
        header("Location: login.php?err=invalid_session");
        exit;
    }

    // Decode JSON Resume Architecture (Safe Decoding)
    $resume = null;
    if (!empty($user['resume_data'])) {
        $decoded = json_decode($user['resume_data'], true);
        if (json_last_error() === JSON_ERROR_NONE) {
            $resume = $decoded;
        }
    }

    // Fetch Application & ATS Scoring History for Timeline & Charts
    $scanStmt = $db->prepare("
        SELECT a.ats_score, a.applied_at, j.title, j.company, j.location, j.job_type
        FROM applications a 
        JOIN jobs j ON a.job_id = j.id 
        WHERE a.user_id = :id 
        ORDER BY a.applied_at DESC LIMIT 10
    ");
    $scanStmt->execute(['id' => $userId]);
    $scans = $scanStmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch Live High-Match Jobs for Recommendation Engine (efficient random selection)
    $recStmt = $db->prepare("
        SELECT id, title, company, location, job_type, logo 
        FROM jobs 
        WHERE is_active = 1 
        ORDER BY id DESC 
        LIMIT 8
    ");
    $recStmt->execute();
    $allRecs = $recStmt->fetchAll(PDO::FETCH_ASSOC);
    // Shuffle in PHP to avoid slow ORDER BY RAND() on large tables
    shuffle($allRecs);
    $recommendations = array_slice($allRecs, 0, 4);

    // Calculate user's rank: how many users have a higher average ATS score
    $rankStmt = $db->prepare("
        SELECT COUNT(*) + 1 FROM (
            SELECT user_id, AVG(ats_score) AS avg_score
            FROM applications
            GROUP BY user_id
        ) AS ranked
        WHERE avg_score > (
            SELECT COALESCE(AVG(ats_score), 0)
            FROM applications
            WHERE user_id = :uid
        )
    ");
    $rankStmt->execute(['uid' => $userId]);
    $topPerformers = (int)$rankStmt->fetchColumn();

} catch (PDOException $e) {
    error_log("Command Center Error: " . $e->getMessage());
    die("
        <div style='background:#050505; color:white; height:100vh; display:flex; align-items:center; justify-content:center; font-family:sans-serif;'>
            <div style='text-align:center;'>
                <div style='width:64px; height:64px; border-radius:16px; background:rgba(223, 60, 60, 0.1); border:1px solid #df3c3c; display:flex; align-items:center; justify-content:center; margin: 0 auto 20px auto;'>
                    <svg style='width:32px; height:32px; color:#df3c3c;' fill='none' stroke='currentColor' viewBox='0 0 24 24'><path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z'></path></svg>
                </div>
                <h1 style='color:#df3c3c; margin-bottom: 10px; font-weight:900; font-size:24px;'>Database Connection Fault</h1>
                <p style='color:#94a3b8; max-width:400px; line-height:1.6;'>Unable to synchronize with the PCTE cluster. Please verify your database connection parameters.</p>
            </div>
        </div>
    ");
}

// 3. LOGIC ENGINES: Metrics & Completion Math
$userName = $user['name'] ?? 'Student';
$firstName = explode(' ', $userName)[0];
$completionScore = 0;
$missingSectors = [];
$userSkills = [];

if ($resume) {
    if (!empty($resume['summary'])) { $completionScore += 20; } else { $missingSectors[] = 'Executive Summary'; }
    if (!empty($resume['experience'])) { $completionScore += 30; } else { $missingSectors[] = 'Work Experience'; }
    if (!empty($resume['education'])) { $completionScore += 20; } else { $missingSectors[] = 'Education Node'; }
    if (!empty($resume['skills'])) {
        $completionScore += 30;
        $userSkills = $resume['skills'];
    } else {
        $missingSectors[] = 'Skill Matrix';
    }
} else {
    $missingSectors = ['Initialize Profile', 'Add Summary', 'Add Experience', 'Add Education', 'Add Skills'];
}

$lastUpdated = !empty($user['last_updated']) ? date('M j, Y', strtotime($user['last_updated'])) : 'Never Synced';
$skillsCount = count($userSkills);
$totalScansCount = count($scans);
$avgAtsScore = $totalScansCount > 0 ? round(array_sum(array_column($scans, 'ats_score')) / $totalScansCount) : 0;
$memberSince = date('M Y', strtotime($user['created_at'] ?? 'now'));

// 4. CHART DATA PREPARATION (Extracting for Javascript Chart.js)
$chartDates = [];
$chartScores = [];

if ($totalScansCount > 0) {
    // Reverse scans so the oldest is first on the chart (left to right reading)
    $reversedScans = array_reverse(array_slice($scans, 0, 7)); 
    foreach ($reversedScans as $scan) {
        $chartDates[] = date('M j', strtotime($scan['applied_at']));
        $chartScores[] = (int)$scan['ats_score'];
    }
} else {
    // Fallback if no scans exist so the chart doesn't break
    $chartDates = ['Initial', 'Mock 1', 'Mock 2', 'Mock 3', 'Target'];
    $chartScores = [0, 0, 0, 0, 0];
}

$chartDatesJson = json_encode($chartDates);
$chartScoresJson = json_encode($chartScores);
?>
<!DOCTYPE html>
<html lang="en" class="scroll-smooth dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Command Center | CareerPro Suite</title>

    <script>
        if (localStorage.getItem('color-theme') === 'dark' || (!('color-theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }
    </script>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.tailwindcss.com"></script>
    
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    fontFamily: { sans: ['"Plus Jakarta Sans"', 'sans-serif'] },
                    colors: {
                        pcte: { 50: '#fdf2f2', 100: '#fbe4e4', 200: '#f8caca', 300: '#f2a3a3', 400: '#ea6d6d', 500: '#df3c3c', 600: '#c82626', 700: '#a61c1c', 800: '#800000', 900: '#701616', 950: '#3f0707' },
                        dark: { 950: '#020202', 900: '#050505', 850: '#0a0a0a', 800: '#0f111a', 700: '#1e293b', 600: '#1f1f1f' },
                        success: { 50: '#ecfdf5', 400: '#34d399', 500: '#10b981', 600: '#059669' },
                        amber: { 50: '#fffbeb', 500: '#f59e0b', 600: '#d97706' }
                    },
                    animation: {
                        'blob': 'blob 10s infinite',
                        'float-delayed': 'float 6s ease-in-out 3s infinite',
                        'pulse-slow': 'pulse 4s cubic-bezier(0.4, 0, 0.6, 1) infinite',
                        'fade-in-up': 'fadeInUp 0.5s ease-out forwards'
                    },
                    keyframes: {
                        blob: { '0%': { transform: 'translate(0px, 0px) scale(1)' }, '33%': { transform: 'translate(30px, -50px) scale(1.1)' }, '66%': { transform: 'translate(-20px, 20px) scale(0.9)' }, '100%': { transform: 'translate(0px, 0px) scale(1)' } },
                        fadeInUp: { '0%': { opacity: 0, transform: 'translateY(15px)' }, '100%': { opacity: 1, transform: 'translateY(0)' } }
                    }
                }
            }
        }
    </script>

    <style>
        body { 
            overflow-x: hidden; 
            -webkit-font-smoothing: antialiased; 
            transition: background-color 0.5s ease, color 0.5s ease;
            background-color: #f8fafc;
            color: #0f172a;
        }
        .dark body { background-color: #020202; color: #ffffff; }
        
        /* Universal Scrollbar */
        ::-webkit-scrollbar { width: 8px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
        .dark ::-webkit-scrollbar-thumb { background: #1f1f1f; }
        ::-webkit-scrollbar-thumb:hover { background: #df3c3c; }

        /* Architectural Backgrounds */
        .bg-grid-light { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-image: linear-gradient(to right, rgba(0,0,0,0.05) 1px, transparent 1px), linear-gradient(to bottom, rgba(0,0,0,0.05) 1px, transparent 1px); background-size: 50px 50px; z-index: -2; mask-image: radial-gradient(circle at 50% 30%, black, transparent 80%); }
        .dark .bg-grid-light { display: none; }
        .bg-grid-dark { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-image: linear-gradient(to right, rgba(255,255,255,0.03) 1px, transparent 1px), linear-gradient(to bottom, rgba(255,255,255,0.03) 1px, transparent 1px); background-size: 50px 50px; z-index: -2; mask-image: radial-gradient(circle at 50% 30%, black, transparent 80%); }
        .dark .bg-grid-dark { display: block; }
        
        .mesh-glow { position: fixed; border-radius: 50%; filter: blur(120px); z-index: -1; opacity: 0.4; pointer-events: none; transition: all 0.5s ease; }
        .dark .mesh-glow { opacity: 0.15; }

        /* Glassmorphism Engines */
        .glass-nav { background: rgba(255, 255, 255, 0.8); backdrop-filter: blur(24px); border-bottom: 1px solid rgba(0, 0, 0, 0.05); transition: background-color 0.4s ease, border-color 0.4s ease; }
        .dark .glass-nav { background: rgba(5, 5, 5, 0.75); border-bottom: 1px solid rgba(255, 255, 255, 0.05); }
        
        .glass-card { background: rgba(255, 255, 255, 0.7); backdrop-filter: blur(12px); border: 1px solid rgba(0, 0, 0, 0.05); box-shadow: 0 10px 30px -10px rgba(0, 0, 0, 0.05); transition: transform 0.4s ease, border-color 0.4s ease, box-shadow 0.4s ease, background-color 0.4s ease; }
        .dark .glass-card { background: linear-gradient(145deg, rgba(255, 255, 255, 0.03) 0%, rgba(255, 255, 255, 0.01) 100%); border: 1px solid rgba(255, 255, 255, 0.04); border-top: 1px solid rgba(255, 255, 255, 0.08); box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5); }
        .glass-card:hover { transform: translateY(-5px); border-color: rgba(223, 60, 60, 0.3); box-shadow: 0 20px 40px -10px rgba(223, 60, 60, 0.1); }
        .dark .glass-card:hover { border-color: rgba(223, 60, 60, 0.5); box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.7); }

        /* Sidebar & Links */
        .sidebar { background: #ffffff; border-right: 1px solid #e2e8f0; transition: all 0.3s ease; z-index: 50; }
        .dark .sidebar { background: #050505; border-right: 1px solid rgba(255,255,255,0.05); box-shadow: none; }
        .nav-link { display: flex; align-items: center; gap: 0.75rem; padding: 0.85rem 1.5rem; color: #64748b; font-weight: 700; font-size: 0.875rem; transition: all 0.2s ease; border-left: 3px solid transparent; margin-bottom: 0.25rem; border-radius: 0.75rem; margin-left: 0.75rem; margin-right: 0.75rem; }
        .dark .nav-link { color: #94a3b8; }
        .nav-link:hover { color: #df3c3c; background: #fdf2f2; }
        .dark .nav-link:hover { color: #ffffff; background: rgba(223,60,60,0.05); }
        .nav-link.active { color: #df3c3c; background: #fdf2f2; border-left-color: #df3c3c; }
        .dark .nav-link.active { color: #ea6d6d; background: rgba(223, 60, 60, 0.1); border-left-color: #df3c3c; }

        /* Buttons (Forced PCTE Red Theme) */
        .btn-primary { background: linear-gradient(135deg, #df3c3c 0%, #a61c1c 100%); position: relative; overflow: hidden; z-index: 1; transition: all 0.3s ease; border: none; color: white; cursor: pointer; }
        .dark .btn-primary { background: linear-gradient(135deg, #a61c1c 0%, #800000 100%); }
        .btn-primary::before { content: ''; position: absolute; top: 0; left: -100%; width: 100%; height: 100%; background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent); transition: left 0.5s ease; z-index: -1; }
        .btn-primary:hover::before { left: 100%; }
        .btn-primary:hover { box-shadow: 0 10px 30px -10px rgba(223, 60, 60, 0.6); transform: translateY(-2px); }
        .dark .btn-primary:hover { box-shadow: 0 10px 30px -10px rgba(223, 60, 60, 0.6); }

        .btn-outline { background: transparent; border: 2px solid rgba(0, 0, 0, 0.1); color: #1e293b; transition: all 0.3s ease; cursor: pointer; }
        .dark .btn-outline { border-color: rgba(255, 255, 255, 0.1); color: white; }
        .btn-outline:hover { border-color: #df3c3c; background: rgba(223, 60, 60, 0.05); color: #df3c3c; transform: translateY(-2px); }
        .dark .btn-outline:hover { color: white; border-color: white; }

        /* Custom Toggle Switch */
        .theme-toggle-label { width: 46px; height: 26px; background-color: #cbd5e1; border-radius: 9999px; position: relative; cursor: pointer; transition: all 0.3s; display: flex; align-items: center; border: 1px solid transparent; }
        .dark .theme-toggle-label { background-color: #0f111a; border-color: #334155; }
        .theme-toggle-ball { width: 20px; height: 20px; background-color: white; border-radius: 50%; position: absolute; top: 2px; left: 2px; transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1); display: flex; align-items: center; justify-content: center; box-shadow: 0 2px 4px rgba(0,0,0,0.2); }
        .dark .theme-toggle-ball { transform: translateX(22px); background-color: #1e293b; }

        /* Utilities */
        .progress-ring__circle { transition: stroke-dashoffset 1.5s ease-in-out; transform: rotate(-90deg); transform-origin: 50% 50%; stroke-linecap: round; }
        .timeline-dot { position: absolute; left: -24px; top: 2px; width: 12px; height: 12px; border-radius: 50%; background: #df3c3c; border: 2px solid #ffffff; box-shadow: 0 0 0 4px rgba(223, 60, 60, 0.1); transition: transform 0.3s ease; }
        .dark .timeline-dot { background: #df3c3c; border-color: #0a0a0a; box-shadow: 0 0 0 4px rgba(223, 60, 60, 0.2); }

        /* 3D Tilt Effect Utilities */
        .tilt-element { transform-style: preserve-3d; transform: perspective(1000px); }
        .tilt-inner { transform: translateZ(30px); }
    </style>
</head>
<body class="flex h-screen overflow-hidden selection:bg-pcte-500 selection:text-white">

    <div class="bg-grid-light"></div>
    <div class="bg-grid-dark"></div>
    <div class="mesh-glow w-[400px] md:w-[600px] h-[400px] md:h-[600px] bg-pcte-400/20 dark:bg-pcte-800/20 top-[-100px] left-[-100px] animate-blob"></div>
    <div class="mesh-glow w-[300px] md:w-[500px] h-[300px] md:h-[500px] bg-orange-300/20 dark:bg-red-900/10 bottom-[-100px] right-[-50px] animate-blob" style="animation-delay: 2s;"></div>

    <aside class="sidebar w-[280px] h-full hidden lg:flex flex-col justify-between shrink-0 relative shadow-2xl dark:shadow-none">
        
        <div class="flex-1 flex flex-col">
            <div class="h-24 flex items-center px-8 border-b border-slate-200 dark:border-white/5 shrink-0">
                <a href="index.php" class="flex items-center gap-3 group w-full cursor-pointer">
                    <div class="w-10 h-10 rounded-xl bg-pcte-600 dark:bg-pcte-800 flex items-center justify-center shadow-lg shadow-pcte-500/40 dark:shadow-pcte-900/40 group-hover:rotate-12 transition-transform duration-500 shrink-0">
                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                    </div>
                    <div class="overflow-hidden">
                        <span class="text-2xl font-black tracking-tight text-slate-900 dark:text-white transition-colors duration-300 block leading-none">Career<span class="text-pcte-600 dark:text-pcte-500">Pro</span></span>
                        <span class="text-[9px] font-bold text-slate-400 uppercase tracking-widest mt-1 block">CareerPro Suite</span>
                    </div>
                </a>
            </div>

            <nav class="mt-8 px-2 space-y-1 flex-1 overflow-y-auto">
                <p class="px-4 text-[10px] font-black text-slate-400 dark:text-gray-600 uppercase tracking-widest mb-4">Workspace</p>
                
                <a href="dashboard.php" class="nav-link active">
                    <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"></path></svg>
                    Command Center
                </a>
                <a href="builder.php" class="nav-link">
                    <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                    Resume Engine
                </a>
                <a href="jobs.php" class="nav-link">
                    <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                    Job Opportunities
                </a>
                
                <p class="px-4 text-[10px] font-black text-slate-400 dark:text-gray-600 uppercase tracking-widest mt-8 mb-4">Settings</p>
                <a href="profile.php" class="nav-link">
                    <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                    Identity Management
                </a>
            </nav>
        </div>

        <div class="p-6 border-t border-slate-200 dark:border-white/5 shrink-0 bg-slate-50 dark:bg-[#050505] transition-colors">
            <div class="flex items-center gap-3 mb-4 p-3 rounded-2xl bg-white dark:bg-dark-800 border border-slate-200 dark:border-white/5 shadow-sm group">
                <div class="w-10 h-10 rounded-xl bg-pcte-100 dark:bg-pcte-900/30 flex items-center justify-center font-black text-lg text-pcte-600 dark:text-pcte-400 shadow-inner group-hover:scale-105 transition-transform shrink-0">
                    <?php echo substr($firstName, 0, 1); ?>
                </div>
                <div class="overflow-hidden">
                    <h4 class="text-sm font-bold text-slate-900 dark:text-white truncate" title="<?php echo htmlspecialchars($userName); ?>"><?php echo htmlspecialchars($userName); ?></h4>
                    <p class="text-[9px] text-slate-500 uppercase font-black tracking-widest mt-0.5">Verified Student</p>
                </div>
            </div>
            <a href="api/auth.php?action=logout" class="flex items-center justify-center gap-2 w-full py-2.5 bg-slate-900 dark:bg-white text-white dark:text-black rounded-xl text-xs font-bold hover:scale-[1.02] transition-all shadow-md active:scale-95 group">
                <svg class="w-4 h-4 group-hover:-translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path></svg>
                Secure Log Out
            </a>
        </div>
    </aside>

    <div class="flex-1 flex flex-col h-screen overflow-hidden relative z-10">
        
        <header class="h-20 flex items-center justify-between px-6 lg:px-10 border-b border-slate-200 dark:border-white/5 glass-nav shrink-0 sticky top-0 z-40">
            <div class="flex items-center gap-4">
                <button id="mobile-menu-btn" class="lg:hidden p-2 rounded-xl bg-slate-100 dark:bg-dark-800 text-slate-600 dark:text-gray-400 hover:text-pcte-600 transition-colors">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path></svg>
                </button>
                <div class="hidden sm:block">
                    <h1 class="text-lg font-black text-slate-900 dark:text-white tracking-tight">Overview</h1>
                    <p class="text-[10px] font-bold text-slate-500 uppercase tracking-widest mt-0.5">Workspace Metrics</p>
                </div>
            </div>
            
            <div class="flex items-center gap-6">
                <div class="hidden sm:flex items-center gap-3 px-4 py-1.5 rounded-full bg-green-100 dark:bg-green-900/20 border border-green-200 dark:border-green-500/20">
                    <span class="w-2 h-2 rounded-full bg-green-500 shadow-[0_0_8px_rgba(16,185,129,0.8)] animate-pulse"></span>
                    <span class="text-[10px] font-black text-green-700 dark:text-green-400 uppercase tracking-widest">Network Online</span>
                </div>

                <div class="h-6 w-px bg-slate-200 dark:bg-white/10 hidden sm:block"></div>

                <button id="theme-toggle" class="relative focus:outline-none hover:scale-105 transition-transform" title="Toggle UI Mode">
                    <div class="theme-toggle-label shadow-inner border border-slate-300 dark:border-white/10">
                        <div class="theme-toggle-ball">
                            <svg id="theme-toggle-light-icon" class="w-3.5 h-3.5 text-amber-500 hidden dark:block absolute" fill="currentColor" viewBox="0 0 20 20"><path d="M10 2a1 1 0 011 1v1a1 1 0 11-2 0V3a1 1 0 011-1zm4.22 4.22a1 1 0 011.415 0l.708.708a1 1 0 01-1.414 1.414l-.708-.708a1 1 0 010-1.414zm3.78 3.78a1 1 0 010 2h-1a1 1 0 110-2h1zm-4.22 4.22a1 1 0 010 1.415l-.708.708a1 1 0 011.414-1.414l.708.708a1 1 0 010 1.414zm-3.78-3.78a1 1 0 010-2h1a1 1 0 110 2h-1zm4.22-4.22a1 1 0 010-1.415l.708-.708a1 1 0 011.414 1.414l-.708.708a1 1 0 01-1.414 0zM10 5a5 5 0 100 10 5 5 0 000-10z"></path></svg>
                            <svg id="theme-toggle-dark-icon" class="w-3.5 h-3.5 text-slate-700 block dark:hidden absolute" fill="currentColor" viewBox="0 0 20 20"><path d="M17.293 13.293A8 8 0 016.707 2.707a8.001 8.001 0 1010.586 10.586z"></path></svg>
                        </div>
                    </div>
                </button>
            </div>
        </header>

        <div id="mobile-menu" class="hidden lg:hidden absolute top-20 left-0 w-full glass-nav rounded-none border-x-0 border-t-0 border-b border-slate-200 dark:border-white/10 py-6 px-6 flex flex-col space-y-2 shadow-2xl origin-top z-50">
            <a href="dashboard.php" class="nav-link active rounded-lg">Command Center</a>
            <a href="builder.php" class="nav-link rounded-lg">Resume Engine</a>
            <a href="jobs.php" class="nav-link rounded-lg">Job Opportunities</a>
            <a href="profile.php" class="nav-link rounded-lg">Identity Settings</a>
            <div class="h-px w-full bg-slate-200 dark:bg-white/10 my-4"></div>
            <a href="api/auth.php?action=logout" class="text-center text-red-500 font-bold py-3 bg-red-50 dark:bg-red-900/10 rounded-lg">Secure Sign Out</a>
        </div>

        <div class="flex-1 overflow-y-auto p-6 lg:p-10 pb-40 relative z-10 scroll-smooth">
            <div class="max-w-[1500px] mx-auto">
                
                <!-- Welcome Section -->
                <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-8 mb-10" data-aos="fade-down">
                    <div class="max-w-2xl">
                        <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-pcte-50 dark:bg-pcte-900/20 border border-pcte-100 dark:border-pcte-500/30 mb-4 shadow-sm">
                            <span class="w-1.5 h-1.5 rounded-full bg-pcte-500 dark:bg-pcte-500 animate-pulse"></span>
                            <span class="text-[9px] font-black tracking-widest text-pcte-600 dark:text-pcte-400 uppercase">Dashboard Telemetry</span>
                        </div>
                        <h2 class="text-3xl lg:text-4xl font-black text-slate-900 dark:text-white tracking-tight leading-tight mb-2">Welcome Back, <span class="text-pcte-600 dark:text-pcte-500"><?php echo htmlspecialchars($firstName); ?>.</span></h2>
                        <p class="text-slate-500 dark:text-gray-400 text-base font-medium leading-relaxed">Your professional optimization is at <strong class="text-slate-900 dark:text-white"><?php echo $completionScore; ?>%</strong>. Let's refine your data points.</p>
                    </div>
                    <div class="flex flex-wrap gap-4 w-full md:w-auto mt-4 md:mt-0">
                        <a href="builder.php" class="flex-1 md:flex-none px-6 py-3.5 btn-primary rounded-xl font-bold flex items-center justify-center gap-2 shadow-lg active:scale-95 text-sm cursor-pointer">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                            Launch Builder
                        </a>
                        <a href="jobs.php" class="flex-1 md:flex-none px-6 py-3.5 btn-outline rounded-xl font-bold flex items-center justify-center gap-2 active:scale-95 text-sm bg-white dark:bg-[#0a0a0a] cursor-pointer">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                            Find Jobs
                        </a>
                    </div>
                </div>

                <!-- 3D Stat Cards -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-10">
                    
                    <div class="glass-card p-6 flex flex-col justify-between relative overflow-hidden group tilt-element" data-aos="fade-up" data-aos-delay="0">
                        <div class="tilt-inner">
                            <div class="absolute top-0 right-0 w-24 h-24 bg-pcte-500/5 dark:bg-pcte-500/10 rounded-bl-full pointer-events-none group-hover:scale-125 transition-transform duration-500"></div>
                            <div class="flex justify-between items-start mb-4 relative z-10">
                                <span class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-400 dark:text-gray-500">Avg Match</span>
                                <div class="w-10 h-10 rounded-xl bg-pcte-50 dark:bg-dark-800 flex items-center justify-center text-pcte-600 dark:text-pcte-500 border border-pcte-100 dark:border-white/5 shadow-inner">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path></svg>
                                </div>
                            </div>
                            <div class="relative z-10">
                                <h3 class="text-4xl font-black text-slate-900 dark:text-white"><?php echo $avgAtsScore; ?><span class="text-2xl text-slate-400 ml-1">%</span></h3>
                                <p class="text-[10px] text-slate-500 font-bold mt-1 uppercase tracking-wide">Across all scans</p>
                            </div>
                        </div>
                    </div>

                    <div class="glass-card p-6 flex flex-col justify-between relative overflow-hidden group tilt-element" data-aos="fade-up" data-aos-delay="100">
                        <div class="tilt-inner">
                            <div class="absolute top-0 right-0 w-24 h-24 bg-purple-500/5 dark:bg-purple-900/20 rounded-bl-full pointer-events-none group-hover:scale-125 transition-transform duration-500"></div>
                            <div class="flex justify-between items-start mb-4 relative z-10">
                                <span class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-400 dark:text-gray-500">Skills Indexed</span>
                                <div class="w-10 h-10 rounded-xl bg-purple-50 dark:bg-dark-800 flex items-center justify-center text-purple-600 dark:text-purple-400 border border-purple-100 dark:border-white/5 shadow-inner">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"></path></svg>
                                </div>
                            </div>
                            <div class="relative z-10">
                                <h3 class="text-4xl font-black text-slate-900 dark:text-white"><?php echo $skillsCount; ?> <span class="text-sm text-slate-400 font-bold tracking-tight">NODES</span></h3>
                                <p class="text-[10px] text-slate-500 font-bold mt-1 uppercase tracking-wide">Parser Format</p>
                            </div>
                        </div>
                    </div>

                    <div class="glass-card p-6 flex flex-col justify-between relative overflow-hidden group tilt-element" data-aos="fade-up" data-aos-delay="200">
                        <div class="tilt-inner">
                            <div class="absolute top-0 right-0 w-24 h-24 bg-success-500/5 dark:bg-success-900/20 rounded-bl-full pointer-events-none group-hover:scale-125 transition-transform duration-500"></div>
                            <div class="flex justify-between items-start mb-4 relative z-10">
                                <span class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-400 dark:text-gray-500">Total Scans</span>
                                <div class="w-10 h-10 rounded-xl bg-success-50 dark:bg-dark-800 flex items-center justify-center text-success-600 dark:text-success-400 border border-success-100 dark:border-white/5 shadow-inner">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                </div>
                            </div>
                            <div class="relative z-10">
                                <h3 class="text-4xl font-black text-slate-900 dark:text-white"><?php echo $totalScansCount; ?> <span class="text-sm text-slate-400 font-bold tracking-tight">RUNS</span></h3>
                                <p class="text-[10px] text-slate-500 font-bold mt-1 uppercase tracking-wide">Application Tracking</p>
                            </div>
                        </div>
                    </div>

                    <div class="glass-card p-6 flex flex-col justify-between relative overflow-hidden group tilt-element" data-aos="fade-up" data-aos-delay="300">
                        <div class="tilt-inner">
                            <div class="absolute top-0 right-0 w-24 h-24 bg-amber-500/5 dark:bg-amber-900/20 rounded-bl-full pointer-events-none group-hover:scale-125 transition-transform duration-500"></div>
                            <div class="flex justify-between items-start mb-4 relative z-10">
                                <span class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-400 dark:text-gray-500">Network Rank</span>
                                <div class="w-10 h-10 rounded-xl bg-amber-50 dark:bg-dark-800 flex items-center justify-center text-amber-600 dark:text-amber-400 border border-amber-100 dark:border-white/5 shadow-inner">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"></path></svg>
                                </div>
                            </div>
                            <div class="relative z-10">
                                <h3 class="text-3xl font-black text-slate-900 dark:text-white truncate">Top <?php echo max(1, round(($userId / ($userId + $topPerformers + 1)) * 100)); ?>%</h3>
                                <p class="text-[10px] text-slate-500 font-bold mt-1 uppercase tracking-wide">By ATS Scores</p>
                            </div>
                        </div>
                    </div>

                </div>

                <!-- Complex Matrices (Charts & Activity) -->
                <div class="grid grid-cols-1 xl:grid-cols-3 gap-8">
                    
                    <div class="xl:col-span-2 space-y-8">
                        
                        <!-- Chart.js Graph -->
                        <div class="glass-card p-6 md:p-8" data-aos="fade-right">
                            <div class="flex justify-between items-center mb-6">
                                <div>
                                    <h3 class="text-xl font-black text-slate-900 dark:text-white mb-1">ATS Optimization Trajectory</h3>
                                    <p class="text-xs text-slate-500 dark:text-gray-400 font-medium">Tracking resume compatibility across recent applications.</p>
                                </div>
                                <div class="hidden sm:flex items-center gap-2 bg-slate-50 dark:bg-dark-800 px-3 py-1.5 rounded-lg border border-slate-200 dark:border-white/5 shadow-inner">
                                    <span class="w-2.5 h-2.5 rounded bg-pcte-500 dark:bg-pcte-500"></span>
                                    <span class="text-[10px] font-black text-slate-600 dark:text-gray-300 uppercase tracking-widest">Match %</span>
                                </div>
                            </div>
                            
                            <div class="w-full h-[280px] relative">
                                <canvas id="atsTrendChart"></canvas>
                            </div>
                        </div>

                        <!-- Profile Integrity -->
                        <div class="glass-card p-6 md:p-8" data-aos="fade-up">
                            <h3 class="text-xl font-black text-slate-900 dark:text-white mb-1">Profile Integrity Matrix</h3>
                            <p class="text-xs text-slate-500 dark:text-gray-400 mb-8 font-medium">Ensure all structural nodes are filled to achieve 100% parser compatibility.</p>
                            
                            <div class="flex flex-col md:flex-row items-center gap-10">
                                <div class="relative w-40 h-40 shrink-0">
                                    <svg class="w-full h-full transform -rotate-90 shadow-xl rounded-full" viewBox="0 0 100 100">
                                        <circle class="text-slate-100 dark:text-dark-800 stroke-current" stroke-width="8" cx="50" cy="50" r="42" fill="transparent"></circle>
                                        <circle class="text-pcte-500 dark:text-pcte-500 progress-ring__circle stroke-current" stroke-width="10" cx="50" cy="50" r="42" fill="transparent" stroke-dasharray="263.89" stroke-dashoffset="<?php echo 263.89 - (263.89 * ($completionScore / 100)); ?>" stroke-linecap="round"></circle>
                                    </svg>
                                    <div class="absolute inset-0 flex flex-col items-center justify-center">
                                        <span class="text-4xl font-black text-slate-900 dark:text-white"><?php echo $completionScore; ?>%</span>
                                        <span class="text-[9px] font-black text-slate-400 uppercase tracking-widest mt-0.5">Health</span>
                                    </div>
                                </div>

                                <div class="flex-1 w-full space-y-3">
                                    <?php 
                                        $allSectors = [
                                            ['name' => 'Executive Summary', 'weight' => 20, 'status' => !in_array('Executive Summary', $missingSectors)],
                                            ['name' => 'Work Experience', 'weight' => 30, 'status' => !in_array('Work Experience', $missingSectors)],
                                            ['name' => 'Education Node', 'weight' => 20, 'status' => !in_array('Education Node', $missingSectors)],
                                            ['name' => 'Skill Matrix', 'weight' => 30, 'status' => !in_array('Skill Matrix', $missingSectors)]
                                        ];
                                        foreach($allSectors as $sector):
                                    ?>
                                    <div class="flex items-center justify-between p-3.5 rounded-2xl border <?php echo $sector['status'] ? 'bg-success-50 dark:bg-success-900/10 border-success-200 dark:border-success-500/20' : 'bg-slate-50 dark:bg-dark-800 border-slate-200 dark:border-white/5'; ?> transition-colors">
                                        <div class="flex items-center gap-3">
                                            <div class="w-7 h-7 rounded-full flex items-center justify-center <?php echo $sector['status'] ? 'bg-success-500 text-white shadow-sm' : 'bg-slate-200 dark:bg-dark-900 text-slate-400'; ?>">
                                                <?php if($sector['status']): ?>
                                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg>
                                                <?php else: ?>
                                                    <span class="text-[10px] font-black">!</span>
                                                <?php endif; ?>
                                            </div>
                                            <span class="text-xs font-bold <?php echo $sector['status'] ? 'text-success-700 dark:text-success-400' : 'text-slate-600 dark:text-gray-400'; ?>"><?php echo $sector['name']; ?></span>
                                        </div>
                                        <span class="text-[9px] font-black uppercase tracking-widest <?php echo $sector['status'] ? 'text-success-600 dark:text-success-500' : 'text-slate-400'; ?>">+<?php echo $sector['weight']; ?>%</span>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>

                    </div>

                    <div class="space-y-8" data-aos="fade-left" data-aos-delay="200">
                        
                        <!-- Target Roles -->
                        <div class="glass-card p-6 md:p-8">
                            <div class="flex justify-between items-end mb-6">
                                <h3 class="text-base font-black text-slate-900 dark:text-white uppercase tracking-tighter">Target Roles</h3>
                                <a href="jobs.php" class="text-[10px] font-black text-pcte-600 hover:text-pcte-700 dark:text-pcte-400 dark:hover:text-pcte-300 uppercase tracking-widest transition-colors">View All</a>
                            </div>
                            
                            <div class="space-y-3">
                                <?php foreach($recommendations as $rec): ?>
                                <div class="p-3.5 rounded-2xl bg-slate-50 dark:bg-dark-800 border border-slate-200 dark:border-white/5 hover:border-pcte-500 dark:hover:border-pcte-500 transition-all cursor-pointer group flex items-center justify-between" onclick="window.location.href='jobs.php'">
                                    <div class="flex items-center gap-3 overflow-hidden">
                                        <div class="w-10 h-10 rounded-xl bg-white dark:bg-dark-900 border border-slate-100 dark:border-white/10 flex items-center justify-center font-black text-lg text-pcte-600 dark:text-white shadow-sm shrink-0 group-hover:scale-110 transition-transform">
                                            <?php echo $rec['logo'] ?? substr($rec['company'], 0, 1); ?>
                                        </div>
                                        <div class="overflow-hidden">
                                            <h5 class="text-xs font-bold text-slate-900 dark:text-white truncate group-hover:text-pcte-600 dark:group-hover:text-pcte-400 transition-colors"><?php echo htmlspecialchars($rec['title']); ?></h5>
                                            <p class="text-[9px] text-slate-500 font-bold mt-0.5 truncate"><?php echo htmlspecialchars($rec['company']); ?></p>
                                        </div>
                                    </div>
                                    <div class="shrink-0 hidden sm:block ml-2">
                                        <span class="px-2 py-1 bg-slate-200 dark:bg-white/5 text-slate-600 dark:text-gray-400 text-[8px] font-black uppercase tracking-widest rounded shadow-inner"><?php echo $rec['job_type']; ?></span>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- Intelligence Feed Timeline -->
                        <div class="glass-card p-6 md:p-8 h-full flex flex-col">
                            <h3 class="text-base font-black text-slate-900 dark:text-white mb-8 uppercase tracking-tighter">Intelligence Feed</h3>
                            
                            <div class="flex-1 space-y-8 relative pl-3">
                                <div class="absolute left-[5px] top-1 bottom-1 w-0.5 bg-slate-200 dark:bg-white/5 rounded-full"></div>
                                
                                <?php foreach(array_slice($scans, 0, 5) as $index => $scan): ?>
                                <div class="relative pl-6 group">
                                    <div class="absolute left-[-24px] top-2 w-3 h-3 rounded-full bg-pcte-500 border-2 border-white dark:border-dark-800 shadow-[0_0_0_4px_rgba(223,60,60,0.1)] transition-transform group-hover:scale-125 z-10"></div>
                                    
                                    <p class="text-[8px] font-black text-slate-400 uppercase tracking-widest mb-1 opacity-70"><?php echo date('M j, Y • g:i A', strtotime($scan['applied_at'])); ?></p>
                                    <h4 class="text-xs font-bold text-slate-900 dark:text-white group-hover:text-pcte-600 dark:group-hover:text-pcte-400 transition-colors leading-tight"><?php echo htmlspecialchars($scan['title']); ?></h4>
                                    <p class="text-[10px] font-medium text-slate-500 dark:text-gray-500 mt-0.5"><?php echo htmlspecialchars($scan['company']); ?></p>
                                    
                                    <div class="mt-2.5 flex items-center gap-3">
                                        <div class="flex-1 h-1 bg-slate-200 dark:bg-dark-800 rounded-full overflow-hidden shadow-inner">
                                            <div class="h-full <?php echo $scan['ats_score'] >= 80 ? 'bg-success-500' : ($scan['ats_score'] >= 50 ? 'bg-amber-500' : 'bg-red-500'); ?> rounded-full" style="width: <?php echo $scan['ats_score']; ?>%"></div>
                                        </div>
                                        <span class="text-[9px] font-black <?php echo $scan['ats_score'] >= 80 ? 'text-success-600 dark:text-success-400' : 'text-slate-600 dark:text-gray-400'; ?>"><?php echo $scan['ats_score']; ?>%</span>
                                    </div>
                                </div>
                                <?php endforeach; ?>

                                <?php if(empty($scans)): ?>
                                <div class="text-center py-10 flex flex-col items-center opacity-50">
                                    <div class="w-12 h-12 rounded-full bg-slate-100 dark:bg-dark-800 flex items-center justify-center mb-3 shadow-inner">
                                        <svg class="w-6 h-6 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" stroke-width="2"></path></svg>
                                    </div>
                                    <p class="text-[10px] font-black uppercase tracking-widest text-slate-600 dark:text-gray-400">No Intelligence Logs Found</p>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>

                    </div>

                </div>
            </div>
        </div>
    </div>

    <!-- ==========================================
         JAVASCRIPT ENGINES
    =========================================== -->
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script>
        // --- 1. System Initialize ---
        AOS.init({ duration: 800, once: true, easing: 'ease-out-cubic' });

        // --- 2. Theme Toggle Core ---
        const themeBtn = document.getElementById('theme-toggle');
        const darkIcon = document.getElementById('theme-toggle-dark-icon');
        const lightIcon = document.getElementById('theme-toggle-light-icon');

        function getChartColors(isDark) {
            return {
                gridColor: isDark ? 'rgba(255, 255, 255, 0.05)' : 'rgba(0, 0, 0, 0.05)',
                textColor: isDark ? '#94a3b8' : '#64748b',
                lineColor: '#df3c3c', // Crimson
                fillStart: isDark ? 'rgba(223, 60, 60, 0.3)' : 'rgba(223, 60, 60, 0.15)',
                fillEnd: 'rgba(0,0,0,0)'
            };
        }

        let atsChartInstance = null; 

        function syncThemeUI() {
            const isDark = document.documentElement.classList.contains('dark');
            if (isDark) {
                if(darkIcon) darkIcon.classList.add('hidden');
                if(lightIcon) lightIcon.classList.remove('hidden');
                localStorage.setItem('color-theme', 'dark');
            } else {
                if(lightIcon) lightIcon.classList.add('hidden');
                if(darkIcon) darkIcon.classList.remove('hidden');
                localStorage.setItem('color-theme', 'light');
            }
            
            // Re-render Chart with new Theme Colors
            if(atsChartInstance) {
                const c = getChartColors(isDark);
                atsChartInstance.options.scales.x.grid.color = c.gridColor;
                atsChartInstance.options.scales.y.grid.color = c.gridColor;
                atsChartInstance.options.scales.x.ticks.color = c.textColor;
                atsChartInstance.options.scales.y.ticks.color = c.textColor;
                
                const ctx = document.getElementById('atsTrendChart').getContext('2d');
                let gradient = ctx.createLinearGradient(0, 0, 0, 300);
                gradient.addColorStop(0, c.fillStart);
                gradient.addColorStop(1, c.fillEnd);
                
                atsChartInstance.data.datasets[0].borderColor = c.lineColor;
                atsChartInstance.data.datasets[0].backgroundColor = gradient;
                atsChartInstance.data.datasets[0].pointBorderColor = c.lineColor;
                
                atsChartInstance.update();
            }
        }
        
        syncThemeUI(); 

        if(themeBtn) {
            themeBtn.addEventListener('click', () => {
                document.documentElement.classList.toggle('dark');
                syncThemeUI();
            });
        }

        // Mobile Menu
        const mobileBtn = document.getElementById('mobile-menu-btn');
        const mobileMenu = document.getElementById('mobile-menu');
        if(mobileBtn && mobileMenu) {
            mobileBtn.addEventListener('click', () => {
                mobileMenu.classList.toggle('hidden');
            });
        }

        // --- 3. Chart.js Data Visualization Engine ---
        document.addEventListener("DOMContentLoaded", function() {
            const canvas = document.getElementById('atsTrendChart');
            if(!canvas) return; 
            
            const ctx = canvas.getContext('2d');
            
            // PHP injected data arrays
            const rawDates = <?php echo $chartDatesJson; ?>;
            const rawScores = <?php echo $chartScoresJson; ?>;
            const isDark = document.documentElement.classList.contains('dark');
            const c = getChartColors(isDark);

            let gradient = ctx.createLinearGradient(0, 0, 0, 300);
            gradient.addColorStop(0, c.fillStart);
            gradient.addColorStop(1, c.fillEnd);

            atsChartInstance = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: rawDates,
                    datasets: [{
                        label: 'ATS Compatibility %',
                        data: rawScores,
                        borderColor: c.lineColor,
                        backgroundColor: gradient,
                        borderWidth: 3,
                        pointBackgroundColor: isDark ? '#0a0a0a' : '#ffffff',
                        pointBorderColor: c.lineColor,
                        pointBorderWidth: 2,
                        pointRadius: 4,
                        pointHoverRadius: 6,
                        fill: true,
                        tension: 0.4 // Smooth bezier curves
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            backgroundColor: isDark ? '#1e293b' : '#0f172a',
                            titleFont: { family: "'Plus Jakarta Sans', sans-serif", size: 12 },
                            bodyFont: { family: "'Plus Jakarta Sans', sans-serif", size: 13, weight: 'bold' },
                            padding: 10,
                            displayColors: false,
                            callbacks: { label: function(context) { return context.parsed.y + '% Match Rate'; } }
                        }
                    },
                    scales: {
                        x: {
                            grid: { color: c.gridColor, drawBorder: false },
                            ticks: { color: c.textColor, font: { family: "'Plus Jakarta Sans', sans-serif", size: 10, weight: 'bold' } }
                        },
                        y: {
                            beginAtZero: true,
                            max: 100,
                            grid: { color: c.gridColor, drawBorder: false },
                            ticks: { 
                                color: c.textColor, 
                                font: { family: "'Plus Jakarta Sans', sans-serif", size: 10, weight: 'bold' },
                                callback: function(value) { return value + '%'; }
                            }
                        }
                    },
                    interaction: { intersect: false, mode: 'index' }
                }
            });
        });

        // --- 4. Vanilla 3D Card Tilt Effect ---
        document.querySelectorAll('.tilt-element').forEach(card => {
            card.addEventListener('mousemove', e => {
                const rect = card.getBoundingClientRect();
                const x = e.clientX - rect.left;
                const y = e.clientY - rect.top;
                const centerX = rect.width / 2;
                const centerY = rect.height / 2;
                const rotateX = ((y - centerY) / centerY) * -10; 
                const rotateY = ((x - centerX) / centerX) * 10;
                
                card.style.transform = `perspective(1000px) rotateX(${rotateX}deg) rotateY(${rotateY}deg) scale3d(1.02, 1.02, 1.02)`;
            });
            
            card.addEventListener('mouseleave', () => {
                card.style.transform = `perspective(1000px) rotateX(0deg) rotateY(0deg) scale3d(1, 1, 1)`;
            });
        });
    </script>

    <?php 
    if (file_exists('chatbot.php')) {
        include 'chatbot.php'; 
    } 
    ?>

</body>
</html>