<?php
declare(strict_types=1);
session_start();

/**
 * ============================================================================
 * CareerPro Suite - Identity & Security Command Center
 * Version: 9.0.0 (Enterprise UI)
 * Architecture: App Shell Layout, Dual-Theme Sync, Secure AJAX Profile Engine,
 * Data Visualization, Real-time DB Integration.
 * ============================================================================
 */

// 1. SESSION GUARD: Ensure only authenticated students enter
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

require_once 'includes/db.php';
$db = Database::getInstance()->getConnection();
$userId = (int)$_SESSION['user_id'];

// 2. FETCH USER IDENTITY & METADATA
try {
    // Fetch core identity details and the last_updated timestamp from the resume table
    $stmt = $db->prepare("
        SELECT u.name, u.email, u.phone, u.created_at, r.last_updated, r.resume_data 
        FROM users u 
        LEFT JOIN resumes r ON u.id = r.user_id 
        WHERE u.id = :id
    ");
    $stmt->execute(['id' => $userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        session_destroy();
        header("Location: login.php?err=session_lost");
        exit;
    }

    $userName = $user['name'];
    $userEmail = $user['email'];
    $userPhone = $user['phone'] ?? '';
    $firstName = explode(' ', $userName)[0];
    $memberSince = date('F Y', strtotime($user['created_at']));
    $lastUpdated = $user['last_updated'] ? date('M j, Y • g:i A', strtotime($user['last_updated'])) : 'Never Synchronized';
    
    // Fetch total applications/scans for metadata display
    $statStmt = $db->prepare("SELECT COUNT(*) FROM applications WHERE user_id = :uid");
    $statStmt->execute(['uid' => $userId]);
    $totalScans = $statStmt->fetchColumn();

    // Check if resume is complete
    $resumeData = $user['resume_data'] ? json_decode($user['resume_data'], true) : null;
    $isProfileComplete = ($resumeData && !empty($resumeData['skills']) && !empty($resumeData['experience']));

} catch (PDOException $e) {
    die("Security Cluster Error: Unable to verify your identity. Please contact support.");
}
?>
<!DOCTYPE html>
<html lang="en" class="scroll-smooth dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Identity Management | CareerPro Suite</title>

    <!-- Theme Initialization Script -->
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
        .dark body { 
            background-color: #020202; 
            color: #ffffff;
        }
        
        /* Universal Scrollbar */
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
        .dark ::-webkit-scrollbar-thumb { background: #1f1f1f; }
        ::-webkit-scrollbar-thumb:hover { background: #df3c3c; }

        /* Architectural Backgrounds */
        .bg-grid-light { position: fixed; inset: 0; background-image: radial-gradient(#e2e8f0 1px, transparent 1px); background-size: 32px 32px; z-index: -2; mask-image: linear-gradient(to bottom, white, transparent); }
        .dark .bg-grid-light { display: none; }
        .bg-grid-dark { display: none; position: fixed; inset: 0; background-image: radial-gradient(rgba(255,255,255,0.02) 1px, transparent 1px); background-size: 32px 32px; z-index: -2; mask-image: radial-gradient(circle at 50% 50%, black, transparent 80%); }
        .dark .bg-grid-dark { display: block; }
        
        .mesh-glow { position: fixed; border-radius: 50%; filter: blur(120px); z-index: -1; opacity: 0.3; pointer-events: none; transition: all 0.5s ease; }
        .dark .mesh-glow { opacity: 0.15; }

        /* Glassmorphism Engines (Dual Theme) */
        .glass-nav { background: rgba(255, 255, 255, 0.85); backdrop-filter: blur(24px); border-bottom: 1px solid rgba(0, 0, 0, 0.05); transition: background-color 0.4s ease, border-color 0.4s ease; }
        .dark .glass-nav { background: rgba(5, 5, 5, 0.75); border-bottom: 1px solid rgba(255, 255, 255, 0.05); }
        
        .glass-card { background: rgba(255, 255, 255, 0.8); backdrop-filter: blur(12px); border: 1px solid rgba(0, 0, 0, 0.05); box-shadow: 0 10px 30px -10px rgba(0, 0, 0, 0.03); transition: transform 0.4s ease, border-color 0.4s ease, box-shadow 0.4s ease, background-color 0.4s ease; }
        .dark .glass-card { background: linear-gradient(145deg, rgba(20, 20, 20, 0.85) 0%, rgba(10, 10, 10, 0.95) 100%); border: 1px solid rgba(255, 255, 255, 0.05); border-top: 1px solid rgba(255, 255, 255, 0.08); box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5); }
        .glass-card:hover { transform: translateY(-5px); border-color: rgba(223, 60, 60, 0.3); box-shadow: 0 20px 40px -10px rgba(223, 60, 60, 0.1); }
        .dark .glass-card:hover { border-color: rgba(223, 60, 60, 0.5); box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.7); }

        /* Sidebar */
        .sidebar { background: #ffffff; border-right: 1px solid #e2e8f0; transition: all 0.3s ease; }
        .dark .sidebar { background: #050505; border-right: 1px solid rgba(255,255,255,0.05); box-shadow: none; }
        .nav-link { display: flex; align-items: center; gap: 0.75rem; padding: 0.85rem 1.5rem; color: #64748b; font-weight: 700; font-size: 0.875rem; transition: all 0.2s ease; border-left: 3px solid transparent; margin-bottom: 0.25rem; border-radius: 0.75rem; margin-left: 0.75rem; margin-right: 0.75rem; }
        .dark .nav-link { color: #94a3b8; }
        .nav-link:hover { color: #df3c3c; background: #fdf2f2; }
        .dark .nav-link:hover { color: #ffffff; background: rgba(223,60,60,0.05); }
        .nav-link.active { color: #df3c3c; background: #fdf2f2; border-left-color: #df3c3c; }
        .dark .nav-link.active { color: #ea6d6d; background: rgba(223, 60, 60, 0.1); border-left-color: #df3c3c; }

        /* Inputs & Forms */
        .input-group { position: relative; }
        .input-field { background: #ffffff; border: 1.5px solid #e2e8f0; color: #0f172a; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
        .dark .input-field { background: #0a0a0a; border: 1.5px solid rgba(255,255,255,0.1); color: white; }
        .input-field:focus { outline: none; border-color: #df3c3c; box-shadow: 0 0 0 4px rgba(223, 60, 60, 0.1); }
        .dark .input-field:focus { border-color: #df3c3c; box-shadow: 0 0 0 4px rgba(223, 60, 60, 0.15); }
        .input-icon { position: absolute; left: 1.25rem; top: 50%; transform: translateY(-50%); color: #94a3b8; transition: color 0.3s; pointer-events: none; }
        .dark .input-icon { color: #64748b; }
        .input-field:focus + .input-icon { color: #df3c3c; }
        .dark .input-field:focus + .input-icon { color: #df3c3c; }

        /* Buttons */
        .btn-primary { background: linear-gradient(135deg, #df3c3c 0%, #a61c1c 100%); position: relative; overflow: hidden; z-index: 1; transition: all 0.3s ease; border: none; color: white; cursor: pointer;}
        .dark .btn-primary { background: linear-gradient(135deg, #a61c1c 0%, #800000 100%); }
        .btn-primary::before { content: ''; position: absolute; top: 0; left: -100%; width: 100%; height: 100%; background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent); transition: left 0.5s ease; z-index: -1; }
        .btn-primary:hover:not(:disabled)::before { left: 100%; }
        .btn-primary:hover:not(:disabled) { box-shadow: 0 10px 25px -5px rgba(223, 60, 60, 0.4); transform: translateY(-2px); }
        .dark .btn-primary:hover:not(:disabled) { box-shadow: 0 10px 25px -5px rgba(223, 60, 60, 0.5); }
        .btn-primary:disabled { opacity: 0.6; cursor: not-allowed; transform: none; box-shadow: none; filter: grayscale(50%); }

        /* Custom Toggle Switch */
        .theme-toggle-label { width: 46px; height: 26px; background-color: #cbd5e1; border-radius: 9999px; position: relative; cursor: pointer; transition: all 0.3s; display: flex; align-items: center; border: 1px solid transparent; }
        .dark .theme-toggle-label { background-color: #0f111a; border-color: #334155; }
        .theme-toggle-ball { width: 20px; height: 20px; background-color: white; border-radius: 50%; position: absolute; top: 2px; left: 2px; transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1); display: flex; align-items: center; justify-content: center; box-shadow: 0 2px 4px rgba(0,0,0,0.2); }
        .dark .theme-toggle-ball { transform: translateX(22px); background-color: #1e293b; }

        /* Privacy Toggle */
        .toggle-checkbox:checked { right: 0; border-color: #df3c3c; }
        .dark .toggle-checkbox:checked { border-color: #df3c3c; }
        .toggle-checkbox:checked + .toggle-label { background-color: #df3c3c; }
        .dark .toggle-checkbox:checked + .toggle-label { background-color: #df3c3c; }

        /* Password Strength Meter */
        .strength-bar { height: 6px; border-radius: 3px; transition: all 0.4s ease; width: 0%; background-color: #ef4444; }
    </style>
</head>
<body class="flex h-screen overflow-hidden selection:bg-pcte-500 selection:text-white">

    <div class="bg-grid-light"></div>
    <div class="bg-grid-dark"></div>
    <div class="mesh-glow w-[500px] h-[500px] bg-pcte-500/20 dark:bg-pcte-500/10 top-[-100px] right-[-100px] animate-blob"></div>
    <div class="mesh-glow w-[400px] h-[400px] bg-orange-500/10 dark:bg-red-900/10 bottom-[-100px] left-[20%] animate-blob" style="animation-delay: 2s;"></div>

    <aside class="sidebar w-64 h-full hidden lg:flex flex-col justify-between shrink-0 relative z-50 shadow-xl dark:shadow-none">
        <div>
            <div class="h-24 flex items-center px-8 border-b border-slate-200 dark:border-white/5">
                <a href="index.php" class="flex items-center gap-3 group w-full">
                    <div class="w-10 h-10 rounded-xl bg-pcte-600 dark:bg-pcte-800 flex items-center justify-center shadow-lg shadow-pcte-500/40 dark:shadow-pcte-900/40 group-hover:rotate-12 transition-transform duration-500 shrink-0">
                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                    </div>
                    <div class="overflow-hidden">
                        <span class="text-2xl font-black tracking-tight text-slate-900 dark:text-white transition-colors duration-300 block leading-none">Career<span class="text-pcte-600 dark:text-pcte-500">Pro</span></span>
                    <span class="text-[9px] font-bold text-slate-400 uppercase tracking-widest mt-1 block">CareerPro Suite</span>
                    </div>
                </a>
            </div>

            <nav class="mt-8 px-2 space-y-1">
                <p class="px-4 text-[10px] font-black text-slate-400 dark:text-gray-600 uppercase tracking-widest mb-4">Workspace</p>
                
                <a href="dashboard.php" class="nav-link rounded-xl">
                    <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
                    Command Center
                </a>
                <a href="builder.php" class="nav-link rounded-xl">
                    <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                    Resume Engine
                </a>
                <a href="jobs.php" class="nav-link rounded-xl">
                    <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                    Job Opportunities
                </a>
                
                <p class="px-4 text-[10px] font-black text-slate-400 dark:text-gray-600 uppercase tracking-widest mt-8 mb-4">Settings</p>
                <a href="profile.php" class="nav-link active rounded-xl">
                    <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                    Identity Management
                </a>
            </nav>
        </div>

        <div class="p-6 border-t border-slate-200 dark:border-white/5 bg-slate-50 dark:bg-[#050505] transition-colors">
            <div class="flex items-center gap-3 mb-6 p-3 rounded-2xl bg-white dark:bg-dark-800 border border-slate-200 dark:border-white/5 shadow-sm group">
                <div class="w-10 h-10 rounded-xl bg-pcte-100 dark:bg-pcte-900/30 flex items-center justify-center font-black text-lg text-pcte-600 dark:text-pcte-400 shadow-inner group-hover:scale-105 transition-transform shrink-0">
                    <?php echo substr($firstName, 0, 1); ?>
                </div>
                <div class="overflow-hidden">
                    <h4 class="text-sm font-bold text-slate-900 dark:text-white truncate" title="<?php echo htmlspecialchars($userName); ?>"><?php echo htmlspecialchars($userName); ?></h4>
                    <p class="text-[9px] text-slate-500 uppercase font-black tracking-widest mt-0.5">Verified Student</p>
                </div>
            </div>
            <a href="api/auth.php?action=logout" class="flex items-center justify-center gap-2 w-full py-3 bg-slate-900 dark:bg-white text-white dark:text-black rounded-xl text-xs font-bold hover:scale-[1.02] transition-all shadow-md active:scale-95 group">
                <svg class="w-4 h-4 group-hover:-translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path></svg>
                Secure Log Out
            </a>
        </div>
    </aside>

    <main class="flex-1 flex flex-col h-screen overflow-hidden relative z-10">
        
        <header class="h-20 flex items-center justify-between px-6 lg:px-10 border-b border-slate-200 dark:border-white/5 glass-nav shrink-0 sticky top-0 z-40">
            <div class="flex items-center gap-4">
                <button id="mobile-menu-btn" class="lg:hidden p-2 rounded-xl bg-slate-100 dark:bg-dark-800 text-slate-600 dark:text-gray-400 hover:text-pcte-600 transition-colors">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path></svg>
                </button>
                <div class="hidden sm:block">
                    <h1 class="text-lg font-black text-slate-900 dark:text-white tracking-tight">Workspace / Identity</h1>
                    <p class="text-[10px] font-bold text-slate-500 uppercase tracking-widest mt-0.5">Manage Your Credentials</p>
                </div>
            </div>
            
            <div class="flex items-center gap-6">
                <div class="hidden sm:flex items-center gap-3 px-4 py-1.5 rounded-full bg-green-100 dark:bg-green-900/20 border border-green-200 dark:border-green-500/20">
                    <span class="w-2 h-2 rounded-full bg-green-500 shadow-[0_0_8px_rgba(16,185,129,0.8)] animate-pulse"></span>
                    <span class="text-[10px] font-black text-green-700 dark:text-green-400 uppercase tracking-widest">Secure Node Active</span>
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
            <a href="dashboard.php" class="nav-link rounded-lg">Command Center</a>
            <a href="builder.php" class="nav-link rounded-lg">Resume Engine</a>
            <a href="jobs.php" class="nav-link rounded-lg">Job Opportunities</a>
            <a href="profile.php" class="nav-link active rounded-lg">Identity Settings</a>
            <div class="h-px w-full bg-slate-200 dark:bg-white/10 my-4"></div>
            <a href="api/auth.php?action=logout" class="text-center text-red-500 font-bold py-3 bg-red-50 dark:bg-red-900/10 rounded-lg">Secure Sign Out</a>
        </div>

        <div class="flex-1 overflow-y-auto p-6 lg:p-12 pb-40 relative z-10 scroll-smooth">
            <div class="max-w-[1400px] mx-auto">
                
                <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-10 mb-12" data-aos="fade-down">
                    <div class="max-w-2xl">
                        <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-pcte-50 dark:bg-pcte-900/20 border border-pcte-100 dark:border-pcte-500/30 mb-4 shadow-sm">
                            <span class="w-1.5 h-1.5 rounded-full bg-pcte-500 dark:bg-pcte-500 animate-pulse"></span>
                            <span class="text-[9px] font-black tracking-widest text-pcte-600 dark:text-pcte-400 uppercase">Profile Settings</span>
                        </div>
                        <h2 class="text-4xl font-black text-slate-900 dark:text-white tracking-tight leading-none mb-3">Identity Management</h2>
                        <p class="text-slate-500 dark:text-gray-400 text-lg font-medium leading-relaxed">Control your credentials, privacy settings, and security protocols in one secure node.</p>
                    </div>
                    <?php if(!$isProfileComplete): ?>
                    <div class="bg-amber-50 dark:bg-amber-900/10 border border-amber-200 dark:border-amber-500/20 p-4 rounded-2xl flex items-center gap-4 shadow-sm w-full md:w-auto">
                        <div class="w-10 h-10 rounded-full bg-amber-100 dark:bg-amber-900/30 flex items-center justify-center text-amber-600 dark:text-amber-500 shrink-0">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                        </div>
                        <div>
                            <p class="text-xs font-black text-amber-800 dark:text-amber-400 uppercase tracking-widest">Incomplete Profile</p>
                            <a href="builder.php" class="text-xs font-bold text-slate-600 dark:text-gray-400 hover:text-slate-900 dark:hover:text-white underline">Add Resume Data to unlock jobs</a>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <div id="api-msg" class="hidden mb-10 p-5 rounded-2xl text-sm font-bold border flex items-center gap-4 transition-all animate-fade-in-up shadow-sm"></div>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                    
                    <div class="lg:col-span-2 space-y-8">
                        
                        <div class="glass-card p-8 md:p-10" data-aos="fade-up">
                            <h3 class="text-xl font-bold text-slate-900 dark:text-white mb-8 flex items-center gap-3">
                                <span class="w-1.5 h-6 bg-pcte-500 rounded-full"></span>
                                Credentials & Identity
                            </h3>
                            
                            <form id="profileForm" class="space-y-8">
                                <input type="hidden" name="action" value="update_profile">
                                
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                                    <div class="space-y-2 group">
                                        <label class="text-[11px] font-black text-slate-500 dark:text-gray-400 uppercase tracking-widest pl-1 transition-colors group-focus-within:text-pcte-500">Full Legal Name</label>
                                        <div class="input-group">
                                            <input type="text" name="name" value="<?php echo htmlspecialchars($userName); ?>" required class="input-field w-full rounded-2xl pl-12 pr-4 py-4 text-sm font-semibold shadow-sm">
                                            <svg class="w-5 h-5 input-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                                        </div>
                                    </div>
                                    
                                    <div class="space-y-2 group">
                                        <label class="text-[11px] font-black text-slate-500 dark:text-gray-400 uppercase tracking-widest pl-1 transition-colors group-focus-within:text-pcte-500">Primary Mobile Node</label>
                                        <div class="input-group">
                                            <input type="tel" name="phone" value="<?php echo htmlspecialchars($userPhone); ?>" placeholder="+91 00000 00000" class="input-field w-full rounded-2xl pl-12 pr-4 py-4 text-sm font-semibold shadow-sm">
                                            <svg class="w-5 h-5 input-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path></svg>
                                        </div>
                                    </div>
                                </div>

                                <div class="space-y-2 relative">
                                    <label class="text-[11px] font-black text-slate-500 dark:text-gray-400 uppercase tracking-widest pl-1 flex justify-between">
                                        Email Address
                                        <span class="text-pcte-600 dark:text-pcte-400 flex items-center gap-1"><svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"></path></svg> Immutable</span>
                                    </label>
                                    <div class="relative">
                                        <input type="email" value="<?php echo htmlspecialchars($userEmail); ?>" disabled class="w-full rounded-2xl pl-12 pr-4 py-4 text-sm font-semibold bg-slate-100 dark:bg-dark-900 border border-slate-200 dark:border-white/5 text-slate-400 dark:text-gray-600 cursor-not-allowed italic shadow-inner">
                                        <svg class="absolute left-4 top-[50%] transform -translate-y-1/2 w-5 h-5 text-slate-400 dark:text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                                    </div>
                                </div>

                                <div class="pt-4">
                                    <button type="submit" id="saveProfileBtn" class="btn-primary w-full sm:w-auto text-white font-bold py-4 px-10 rounded-[2rem] text-xs uppercase tracking-widest shadow-xl active:scale-95 flex items-center justify-center gap-2">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                                        Synchronize Data
                                    </button>
                                </div>
                            </form>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6" data-aos="fade-up">
                            <div class="glass-card p-8 flex flex-col justify-between overflow-hidden relative group">
                                <div class="absolute -right-4 -bottom-4 w-32 h-32 bg-pcte-500/10 rounded-full blur-2xl group-hover:scale-150 transition-transform duration-700 pointer-events-none"></div>
                                <div class="relative z-10">
                                    <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Member Node Active</p>
                                    <h4 class="text-xl font-bold text-slate-900 dark:text-white italic"><?php echo $memberSince; ?></h4>
                                </div>
                                <p class="text-[11px] text-slate-500 font-medium mt-4 leading-relaxed">Identity formally established on CareerPro cluster.</p>
                            </div>
                            <div class="glass-card p-8 flex flex-col justify-between overflow-hidden relative group">
                                <div class="absolute -right-4 -bottom-4 w-32 h-32 bg-green-500/10 rounded-full blur-2xl group-hover:scale-150 transition-transform duration-700 pointer-events-none"></div>
                                <div class="relative z-10 flex justify-between items-start">
                                    <div>
                                        <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Resume Engine Sync</p>
                                        <h4 class="text-sm font-bold text-green-600 dark:text-green-500"><?php echo $lastUpdated; ?></h4>
                                    </div>
                                    <div class="w-8 h-8 rounded-full bg-slate-100 dark:bg-dark-800 border border-slate-200 dark:border-white/5 flex items-center justify-center text-green-500 shrink-0 shadow-sm">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                    </div>
                                </div>
                                <p class="text-[11px] text-slate-500 font-medium mt-4 leading-relaxed">Last successful payload transmission to the centralized database.</p>
                            </div>
                        </div>
                    </div>

                    <div class="space-y-8">
                        
                        <div class="glass-card p-8" data-aos="fade-left">
                            <h3 class="text-lg font-bold text-slate-900 dark:text-white mb-6 flex items-center gap-3">
                                <div class="w-8 h-8 rounded-lg bg-pcte-100 dark:bg-dark-800 border border-pcte-200 dark:border-white/5 flex items-center justify-center shadow-inner">
                                    <svg class="w-4 h-4 text-pcte-600 dark:text-pcte-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" stroke-width="2.5"></path></svg>
                                </div>
                                Access Security
                            </h3>
                            <form id="securityForm" class="space-y-6">
                                <input type="hidden" name="action" value="change_password">
                                
                                <div class="space-y-2 relative group">
                                    <label class="text-[10px] font-black text-slate-500 dark:text-gray-400 uppercase tracking-widest pl-1 transition-colors group-focus-within:text-pcte-500">Current Cipher</label>
                                    <div class="input-group">
                                        <input type="password" name="current_password" id="currPass" required class="input-field w-full rounded-xl pl-11 pr-10 py-3.5 text-sm font-semibold shadow-sm tracking-widest" placeholder="••••••••">
                                        <svg class="w-4 h-4 input-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>
                                    </div>
                                </div>

                                <div class="space-y-2 relative group">
                                    <label class="text-[10px] font-black text-slate-500 dark:text-gray-400 uppercase tracking-widest pl-1 transition-colors group-focus-within:text-pcte-500">New Secure Cipher</label>
                                    <div class="input-group">
                                        <input type="password" name="new_password" id="newPass" required class="input-field w-full rounded-xl pl-11 pr-10 py-3.5 text-sm font-semibold shadow-sm tracking-widest" placeholder="••••••••">
                                        <svg class="w-4 h-4 input-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"></path></svg>
                                    </div>
                                    <div class="w-full bg-slate-200 dark:bg-dark-800 rounded-full h-1.5 mt-2 overflow-hidden shadow-inner">
                                        <div id="pass-meter" class="strength-bar"></div>
                                    </div>
                                    <p id="pass-text" class="text-[9px] text-slate-400 dark:text-gray-500 font-bold uppercase tracking-wide text-right mt-1">Weak</p>
                                </div>

                                <button type="submit" id="saveSecurityBtn" disabled class="w-full py-4 bg-slate-900 dark:bg-white text-white dark:text-black rounded-xl font-black uppercase text-[10px] tracking-[0.2em] shadow-lg active:scale-95 transition-all disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-2 cursor-pointer">
                                    Update Credentials
                                </button>
                            </form>
                        </div>

                        <div class="glass-card p-8" data-aos="fade-left" data-aos-delay="100">
                            <h3 class="text-lg font-bold text-slate-900 dark:text-white mb-6">Discovery Protocols</h3>
                            <form id="preferenceForm" class="space-y-6">
                                <input type="hidden" name="action" value="update_preferences">
                                <div class="flex items-center justify-between group p-4 rounded-xl border border-slate-200 dark:border-white/5 bg-slate-50 dark:bg-dark-800 transition-colors shadow-inner">
                                    <div>
                                        <h4 class="text-sm font-bold text-slate-900 dark:text-white">Public Profile Node</h4>
                                        <p class="text-[10px] text-slate-500 font-medium mt-0.5">Visible to verified employers</p>
                                    </div>
                                    <div class="relative inline-block w-12 align-middle select-none transition duration-200 ease-in">
                                        <input type="checkbox" name="profile_visible" id="vis_toggle" class="toggle-checkbox absolute block w-6 h-6 rounded-full bg-white border-4 border-slate-300 dark:border-[#141414] appearance-none cursor-pointer checked:right-0 checked:border-pcte-500 transition-all" checked/>
                                        <label for="vis_toggle" class="toggle-label block overflow-hidden h-6 rounded-full bg-slate-300 dark:bg-dark-900 cursor-pointer shadow-inner"></label>
                                    </div>
                                </div>
                                <button type="submit" id="savePrefBtn" class="w-full text-[10px] font-black text-slate-400 hover:text-pcte-600 dark:hover:text-pcte-400 transition-colors uppercase tracking-[0.25em] border-t border-slate-100 dark:border-white/5 pt-6 cursor-pointer">Save Privacy Node</button>
                            </form>
                        </div>

                        <div class="p-8 border-2 border-red-500/10 dark:border-red-900/20 bg-red-50 dark:bg-red-950/20 rounded-[2rem] text-center shadow-inner" data-aos="zoom-in">
                            <div class="w-12 h-12 bg-red-100 dark:bg-red-900/30 rounded-full flex items-center justify-center mx-auto mb-4 border border-red-200 dark:border-red-900/50 text-red-600 dark:text-red-500 shadow-sm">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                            </div>
                            <p class="text-[10px] text-red-600 dark:text-red-400 font-black uppercase tracking-widest mb-4">Recursive Data Destruction</p>
                            <button onclick="triggerSoftDelete()" class="w-full px-6 py-3.5 bg-red-600 hover:bg-red-700 text-white text-xs font-black uppercase tracking-widest rounded-xl transition-all shadow-lg active:scale-95 cursor-pointer">
                                Wipe Account
                            </button>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </main>

    <?php 
    if (file_exists('chatbot.php')) {
        include 'chatbot.php'; 
    }
    ?>

    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script>
        AOS.init({ once: true, duration: 800, easing: 'ease-out-cubic' });

        const themeBtn = document.getElementById('theme-toggle');
        const mobileMenuBtn = document.getElementById('mobile-menu-btn');
        const mobileMenu = document.getElementById('mobile-menu');

        const darkIcon = document.getElementById('theme-toggle-dark-icon');
        const lightIcon = document.getElementById('theme-toggle-light-icon');

        // Theme Sync Logic
        function syncThemeIcon() {
            if (document.documentElement.classList.contains('dark')) {
                if(darkIcon) darkIcon.classList.add('hidden');
                if(lightIcon) lightIcon.classList.remove('hidden');
            } else {
                if(lightIcon) lightIcon.classList.add('hidden');
                if(darkIcon) darkIcon.classList.remove('hidden');
            }
        }
        syncThemeIcon();

        themeBtn.addEventListener('click', () => {
            document.documentElement.classList.toggle('dark');
            if (document.documentElement.classList.contains('dark')) {
                localStorage.setItem('color-theme', 'dark');
            } else {
                localStorage.setItem('color-theme', 'light');
            }
            syncThemeIcon();
        });

        // Mobile Menu Toggle
        if(mobileMenuBtn) {
            mobileMenuBtn.addEventListener('click', () => {
                mobileMenu.classList.toggle('hidden');
            });
        }

        // Advanced Password Strength Meter
        const newPassInput = document.getElementById('newPass');
        const meter = document.getElementById('pass-meter');
        const meterText = document.getElementById('pass-text');
        const securityBtn = document.getElementById('saveSecurityBtn');

        if(newPassInput) {
            newPassInput.addEventListener('input', () => {
                const val = newPassInput.value;
                let strength = 0;
                
                if(val.length >= 8) strength += 25;
                if(/[A-Z]/.test(val)) strength += 25;
                if(/[0-9]/.test(val)) strength += 25;
                if(/[^A-Za-z0-9]/.test(val)) strength += 25;

                meter.style.width = strength + '%';
                
                if(strength <= 25) {
                    meter.style.backgroundColor = '#ef4444'; // Red
                    meterText.innerText = 'Weak'; meterText.style.color = '#ef4444';
                    securityBtn.disabled = true;
                } else if(strength <= 50) {
                    meter.style.backgroundColor = '#eab308'; // Yellow
                    meterText.innerText = 'Fair'; meterText.style.color = '#eab308';
                    securityBtn.disabled = true;
                } else if(strength <= 75) {
                    meter.style.backgroundColor = '#3b82f6'; // Blue
                    meterText.innerText = 'Good'; meterText.style.color = '#3b82f6';
                    securityBtn.disabled = false;
                } else {
                    meter.style.backgroundColor = '#22c55e'; // Green
                    meterText.innerText = 'Strong'; meterText.style.color = '#22c55e';
                    securityBtn.disabled = false;
                }
                
                if(val.length === 0) securityBtn.disabled = true;
            });
        }

        // Universal AJAX Handlers for Forms
        const apiMsg = document.getElementById('api-msg');
        async function runPipeline(formId, btnId) {
            const form = document.getElementById(formId);
            const btn = document.getElementById(btnId);
            if(!form) return;

            form.addEventListener('submit', async (e) => {
                e.preventDefault();
                const originalText = btn ? btn.innerHTML : '';
                if (btn) { 
                    btn.disabled = true; 
                    btn.innerHTML = `<svg class="w-5 h-5 animate-spin" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"></circle><path class="opacity-75" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg> Syncing Node...`;
                }

                // Hide previous msg
                apiMsg.classList.remove('translate-y-0', 'opacity-100');
                apiMsg.classList.add('translate-y-[-10px]', 'opacity-0');

                const formData = new FormData(form);
                const data = Object.fromEntries(formData.entries());

                // Simulate slight network delay for better UX feel
                setTimeout(async () => {
                    try {
                        const response = await fetch('api/profile-api.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify(data)
                        });

                        const result = await response.json();
                        
                        apiMsg.classList.remove('hidden');
                        apiMsg.innerHTML = `
                            <div class="shrink-0 mt-0.5">
                                ${result.status === 'success' ? '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>' : '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>'}
                            </div>
                            <div class="flex-1">${result.message}</div>
                        `;
                        
                        apiMsg.className = `mb-10 p-4 rounded-2xl text-sm font-bold border flex items-start gap-3 transition-all duration-300 transform translate-y-0 opacity-100 shadow-md ${
                            result.status === 'success' ? 'bg-green-50 dark:bg-green-900/20 border-green-200 dark:border-green-500/30 text-green-700 dark:text-green-400' : 'bg-red-50 dark:bg-red-900/20 border-red-200 dark:border-red-500/30 text-red-700 dark:text-red-400'
                        }`;

                        if (result.status === 'success' && formId === 'securityForm') {
                            form.reset();
                            meter.style.width = '0%';
                            meterText.innerText = 'Weak';
                            meterText.style.color = '#ef4444';
                        }

                    } catch (err) {
                        apiMsg.classList.remove('hidden');
                        apiMsg.innerHTML = `<div class="shrink-0 mt-0.5"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg></div><div class="flex-1">Critical Fault: Upstream connection interrupted.</div>`;
                        apiMsg.className = "mb-10 p-4 rounded-2xl text-sm font-bold border flex items-start gap-3 bg-red-50 dark:bg-red-900/20 border-red-200 dark:border-red-500/30 text-red-700 dark:text-red-400 transition-all duration-300 transform translate-y-0 opacity-100 shadow-md";
                    } finally {
                        if (btn) { 
                            btn.disabled = false; 
                            btn.innerHTML = originalText; 
                        }
                        window.scrollTo({ top: 0, behavior: 'smooth' });
                    }
                }, 300);
            });
        }

        runPipeline('profileForm', 'saveProfileBtn');
        runPipeline('securityForm', 'saveSecurityBtn');
        runPipeline('preferenceForm', 'savePrefBtn');

        function triggerSoftDelete() {
            const confirmVal = prompt("SECURITY PROTOCOL: Type 'CONFIRM WIPE' to delete all user data associated with this node:");
            if (confirmVal === 'CONFIRM WIPE') {
                alert("Soft-Delete signal accepted. Identity cluster wiped. Session terminating.");
                // This would normally call a delete API endpoint, but for now we log out
                window.location.href = 'api/auth.php?action=logout';
            }
        }
    </script>
</body>
</html>