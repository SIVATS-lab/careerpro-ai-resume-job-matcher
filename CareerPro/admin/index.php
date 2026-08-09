<?php
declare(strict_types=1);
session_start();

/**
 * ============================================================================
 * CareerPro Suite - Admin Command Center & Telemetry Dashboard
 * Version: 5.0.0 (Enterprise UI)
 * Architecture: Admin Session Guard, Real-time Database Metrics, System Telemetry
 * ============================================================================
 */

// 1. ADMIN SESSION GUARD: Ensure only authenticated administrators enter
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

require_once '../includes/db.php';
$db = Database::getInstance()->getConnection();
$adminName = $_SESSION['admin_name'] ?? 'Administrator';
$adminEmail = $_SESSION['admin_email'] ?? 'admin@careerpro.com';

// 2. TELEMETRY & SYSTEM METRICS ORCHESTRATION
try {
    // Total Students
    $userCount = $db->query("SELECT COUNT(*) FROM users")->fetchColumn();
    
    // Active Job Postings
    $jobCount = $db->query("SELECT COUNT(*) FROM jobs WHERE is_active = 1")->fetchColumn();
    
    // Total ATS Scans Executed Across Network
    $scanCount = $db->query("SELECT COUNT(*) FROM applications")->fetchColumn();
    
    // Average Network ATS Score
    $avgScore = $db->query("SELECT ROUND(AVG(ats_score)) FROM applications")->fetchColumn() ?: 0;

    // Recent Student Registrations
    $recentUsersStmt = $db->query("SELECT id, name, email, created_at FROM users ORDER BY created_at DESC LIMIT 5");
    $recentUsers = $recentUsersStmt->fetchAll(PDO::FETCH_ASSOC);

    // Recent System Activity / ATS Scans
    $recentScansStmt = $db->query("
        SELECT a.ats_score, a.applied_at, u.name as student_name, j.title as job_title, j.company 
        FROM applications a 
        JOIN users u ON a.user_id = u.id 
        JOIN jobs j ON a.job_id = j.id 
        ORDER BY a.applied_at DESC LIMIT 5
    ");
    $recentScans = $recentScansStmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Admin Telemetry Error: " . $e->getMessage());
    $userCount = 0;
    $jobCount = 0;
    $scanCount = 0;
    $avgScore = 0;
    $recentUsers = [];
    $recentScans = [];
}
?>
<!DOCTYPE html>
<html lang="en" class="scroll-smooth dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Admin Command Center | CareerPro Suite</title>

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
                        success: { 50: '#ecfdf5', 400: '#34d399', 500: '#10b981', 600: '#059669' }
                    },
                    animation: {
                        'fade-in-up': 'fadeInUp 0.5s ease-out forwards',
                        'count-up': 'fadeInUp 0.6s ease-out forwards',
                        'pulse-slow': 'pulse 3s ease-in-out infinite',
                        'spin-slow': 'spin 8s linear infinite',
                    },
                    keyframes: {
                        fadeInUp: { '0%': { opacity: '0', transform: 'translateY(16px)' }, '100%': { opacity: '1', transform: 'translateY(0)' } }
                    }
                }
            }
        }
    </script>

    <style>
        body { overflow-x: hidden; -webkit-font-smoothing: antialiased; background-color: #f8fafc; color: #0f172a; transition: background-color 0.4s ease; }
        .dark body { background-color: #020202; color: #ffffff; }
        @keyframes fadeInCard { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
        @keyframes shimmer { 0% { background-position: -200% 0; } 100% { background-position: 200% 0; } }
        .stat-card-anim { animation: fadeInCard 0.5s ease-out forwards; opacity: 0; }
        .stat-card-anim:nth-child(1) { animation-delay: 0.1s; }
        .stat-card-anim:nth-child(2) { animation-delay: 0.2s; }
        .stat-card-anim:nth-child(3) { animation-delay: 0.3s; }
        .stat-card-anim:nth-child(4) { animation-delay: 0.4s; }
        .table-anim { animation: fadeInCard 0.5s ease-out 0.5s both; }
        .card-hover { transition: transform 0.2s ease, box-shadow 0.2s ease; }
        .card-hover:hover { transform: translateY(-3px); }
        .row-anim { animation: fadeInCard 0.4s ease-out both; }
        tbody tr.row-anim:nth-child(1) { animation-delay: 0.6s; }
        tbody tr.row-anim:nth-child(2) { animation-delay: 0.7s; }
        tbody tr.row-anim:nth-child(3) { animation-delay: 0.8s; }
        tbody tr.row-anim:nth-child(4) { animation-delay: 0.9s; }
        tbody tr.row-anim:nth-child(5) { animation-delay: 1.0s; }
        .quick-action { transition: all 0.2s ease; }
        .quick-action:hover { transform: translateY(-2px); }

        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 3px; }
        .dark ::-webkit-scrollbar-thumb { background: #1f1f1f; }

        .bg-grid-light { position: fixed; inset: 0; background-image: radial-gradient(#e2e8f0 1px, transparent 1px); background-size: 32px 32px; z-index: -2; mask-image: linear-gradient(to bottom, white, transparent); }
        .dark .bg-grid-light { display: none; }
        .bg-grid-dark { display: none; position: fixed; inset: 0; background-image: radial-gradient(rgba(255,255,255,0.03) 1px, transparent 1px); background-size: 32px 32px; z-index: -2; mask-image: linear-gradient(circle at 50% 50%, black, transparent 80%); }
        .dark .bg-grid-dark { display: block; }
        
        .mesh-glow { position: fixed; border-radius: 50%; filter: blur(120px); z-index: -1; opacity: 0.3; pointer-events: none; }

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

        .glass-card { background: rgba(255, 255, 255, 0.8); backdrop-filter: blur(12px); border: 1px solid rgba(0, 0, 0, 0.05); box-shadow: 0 10px 30px -10px rgba(0,0,0,0.03); }
        .dark .glass-card { background: linear-gradient(145deg, rgba(20, 20, 20, 0.85) 0%, rgba(10, 10, 10, 0.95) 100%); border: 1px solid rgba(255, 255, 255, 0.05); border-top: 1px solid rgba(255, 255, 255, 0.08); box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5); }

        .theme-toggle-label { width: 46px; height: 26px; background-color: #cbd5e1; border-radius: 9999px; position: relative; cursor: pointer; transition: background-color 0.3s; display: flex; align-items: center; }
        .dark .theme-toggle-label { background-color: #334155; }
        .theme-toggle-ball { width: 20px; height: 20px; background-color: white; border-radius: 50%; position: absolute; top: 3px; left: 3px; transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1); display: flex; align-items: center; justify-content: center; box-shadow: 0 2px 4px rgba(0,0,0,0.2); }
        .dark .theme-toggle-ball { transform: translateX(20px); background-color: #0f172a; }
    </style>
</head>
<body class="flex h-screen overflow-hidden selection:bg-pcte-500 selection:text-white">

    <div class="bg-grid-light"></div>
    <div class="bg-grid-dark"></div>
    <div class="mesh-glow w-[500px] h-[500px] bg-pcte-500/20 top-[-100px] left-[-100px]"></div>

    <!-- ADMIN SIDEBAR -->
    <aside class="sidebar w-[280px] h-full hidden lg:flex flex-col justify-between shrink-0 relative z-50 shadow-2xl dark:shadow-none">
        <div class="flex-1 flex flex-col">
            <div class="h-24 flex items-center px-8 border-b border-slate-200 dark:border-white/5 shrink-0">
                <a href="index.php" class="flex items-center gap-3 group w-full">
                    <div class="w-10 h-10 rounded-xl bg-pcte-600 flex items-center justify-center shadow-lg shadow-pcte-500/40 shrink-0">
                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
                    </div>
                    <div class="overflow-hidden">
                        <span class="text-xl font-black tracking-tight text-slate-900 dark:text-white block leading-none">Admin<span class="text-pcte-600">Portal</span></span>
                        <span class="text-[9px] font-bold text-slate-400 uppercase tracking-widest mt-1 block">Security Node</span>
                    </div>
                </a>
            </div>

            <nav class="mt-8 px-2 space-y-1 flex-1 overflow-y-auto">
                <p class="px-4 text-[10px] font-black text-slate-400 dark:text-gray-600 uppercase tracking-widest mb-4">Command Center</p>
                <a href="index.php" class="nav-link active rounded-xl">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"></path></svg>
                    Telemetry Overview
                </a>
                <a href="jobs.php" class="nav-link rounded-xl">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                    Job Matrix Management
                </a>
                <a href="users.php" class="nav-link rounded-xl">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                    Student Roster
                </a>
                <a href="settings.php" class="nav-link rounded-xl">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path></svg>
                    Platform Config & API Keys
                </a>
            </nav>
        </div>

        <div class="p-6 border-t border-slate-200 dark:border-white/5 shrink-0 bg-slate-50 dark:bg-[#050505]">
            <div class="flex items-center gap-3 mb-4 p-3 rounded-2xl bg-white dark:bg-dark-800 border border-slate-200 dark:border-white/5 shadow-sm">
                <div class="w-10 h-10 rounded-xl bg-pcte-100 dark:bg-pcte-900/30 flex items-center justify-center font-black text-lg text-pcte-600 dark:text-pcte-400 shrink-0">
                    A
                </div>
                <div class="overflow-hidden">
                    <h4 class="text-sm font-bold text-slate-900 dark:text-white truncate"><?php echo htmlspecialchars($adminName); ?></h4>
                    <p class="text-[9px] text-slate-500 uppercase font-black tracking-widest mt-0.5">Super Administrator</p>
                </div>
            </div>
            <a href="logout.php" class="flex items-center justify-center gap-2 w-full py-2.5 bg-slate-900 dark:bg-white text-white dark:text-black rounded-xl text-xs font-bold hover:scale-[1.02] transition-all shadow-md">
                Secure Admin Logout
            </a>
        </div>
    </aside>

    <!-- MAIN ADMIN CONTENT -->
    <div class="flex-1 flex flex-col h-screen overflow-hidden relative z-10">
        
        <header class="h-20 flex items-center justify-between px-6 lg:px-10 border-b border-slate-200 dark:border-white/5 glass-nav shrink-0 sticky top-0 z-40">
            <h1 class="text-lg font-black text-slate-900 dark:text-white tracking-tight">System Telemetry & Administration</h1>
            
            <button id="theme-toggle" class="relative focus:outline-none hover:scale-105 transition-transform">
                <div class="theme-toggle-label shadow-inner border border-slate-300 dark:border-white/10">
                    <div class="theme-toggle-ball">
                        <svg id="theme-toggle-light-icon" class="w-3.5 h-3.5 text-amber-500 hidden dark:block absolute" fill="currentColor" viewBox="0 0 20 20"><path d="M10 2a1 1 0 011 1v1a1 1 0 11-2 0V3a1 1 0 011-1zm4.22 4.22a1 1 0 011.415 0l.708.708a1 1 0 01-1.414 1.414l-.708-.708a1 1 0 010-1.414zm3.78 3.78a1 1 0 010 2h-1a1 1 0 110-2h1zm-4.22 4.22a1 1 0 010 1.415l-.708.708a1 1 0 01-1.414-1.414l.708-.708a1 1 0 011.414 0zM10 16a1 1 0 011 1v1a1 1 0 11-2 0v-1a1 1 0 011-1zm-4.22-4.22a1 1 0 01-1.415 0l-.708-.708a1 1 0 011.414-1.414l.708.708a1 1 0 010 1.414zm-3.78-3.78a1 1 0 010-2h1a1 1 0 110 2h-1zm4.22-4.22a1 1 0 010-1.415l.708-.708a1 1 0 011.414 1.414l-.708.708a1 1 0 01-1.414 0zM10 5a5 5 0 100 10 5 5 0 000-10z"></path></svg>
                        <svg id="theme-toggle-dark-icon" class="w-3.5 h-3.5 text-slate-700 block dark:hidden absolute" fill="currentColor" viewBox="0 0 20 20"><path d="M17.293 13.293A8 8 0 016.707 2.707a8.001 8.001 0 1010.586 10.586z"></path></svg>
                    </div>
                </div>
            </button>
        </header>

        <div class="flex-1 overflow-y-auto p-6 lg:p-10 pb-45 relative z-10">
            <div class="max-w-[1500px] mx-auto">
                
                <!-- Quick Actions Bar -->
                <div class="flex flex-wrap gap-3 mb-8">
                    <a href="jobs.php" class="quick-action flex items-center gap-2 px-5 py-2.5 rounded-xl bg-pcte-600 text-white text-xs font-black uppercase tracking-wider shadow-lg shadow-pcte-500/20 hover:bg-pcte-700">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M12 4v16m8-8H4"/></svg>
                        Post New Job
                    </a>
                    <a href="users.php" class="quick-action flex items-center gap-2 px-5 py-2.5 rounded-xl bg-slate-900 dark:bg-white/10 text-white text-xs font-black uppercase tracking-wider shadow-lg">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                        Manage Students
                    </a>
                    <a href="settings.php" class="quick-action flex items-center gap-2 px-5 py-2.5 rounded-xl bg-slate-100 dark:bg-white/5 text-slate-700 dark:text-gray-300 border border-slate-200 dark:border-white/10 text-xs font-black uppercase tracking-wider">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/></svg>
                        Platform Config
                    </a>
                </div>

                <!-- Telemetry Cards -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-10">
                    <div class="glass-card p-6 rounded-2xl flex flex-col justify-between stat-card-anim card-hover">
                        <div class="flex items-center justify-between mb-4">
                            <span class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-400">Registered Students</span>
                            <div class="w-9 h-9 rounded-xl bg-green-100 dark:bg-green-900/20 flex items-center justify-center shrink-0">
                                <svg class="w-4 h-4 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                            </div>
                        </div>
                        <h3 class="text-4xl font-black text-slate-900 dark:text-white" data-count="<?php echo $userCount; ?>"><?php echo number_format($userCount); ?></h3>
                        <p class="text-[10px] text-green-500 font-bold mt-1 uppercase">Active registered users</p>
                    </div>
                    <div class="glass-card p-6 rounded-2xl flex flex-col justify-between stat-card-anim card-hover">
                        <div class="flex items-center justify-between mb-4">
                            <span class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-400">Active Job Postings</span>
                            <div class="w-9 h-9 rounded-xl bg-blue-100 dark:bg-blue-900/20 flex items-center justify-center shrink-0">
                                <svg class="w-4 h-4 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                            </div>
                        </div>
                        <h3 class="text-4xl font-black text-slate-900 dark:text-white"><?php echo number_format($jobCount); ?></h3>
                        <p class="text-[10px] text-blue-500 font-bold mt-1 uppercase">Live on network feed</p>
                    </div>
                    <div class="glass-card p-6 rounded-2xl flex flex-col justify-between stat-card-anim card-hover">
                        <div class="flex items-center justify-between mb-4">
                            <span class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-400">Total ATS Scans</span>
                            <div class="w-9 h-9 rounded-xl bg-purple-100 dark:bg-purple-900/20 flex items-center justify-center shrink-0">
                                <svg class="w-4 h-4 text-purple-600 dark:text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                            </div>
                        </div>
                        <h3 class="text-4xl font-black text-slate-900 dark:text-white"><?php echo number_format($scanCount); ?></h3>
                        <p class="text-[10px] text-purple-500 font-bold mt-1 uppercase">Parser executions</p>
                    </div>
                    <div class="glass-card p-6 rounded-2xl flex flex-col justify-between stat-card-anim card-hover">
                        <div class="flex items-center justify-between mb-4">
                            <span class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-400">Network Avg Score</span>
                            <div class="w-9 h-9 rounded-xl bg-amber-100 dark:bg-amber-900/20 flex items-center justify-center shrink-0">
                                <svg class="w-4 h-4 text-amber-600 dark:text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                            </div>
                        </div>
                        <h3 class="text-4xl font-black text-slate-900 dark:text-white"><?php echo $avgScore; ?><span class="text-2xl text-slate-400 ml-1">%</span></h3>
                        <div class="mt-3">
                            <div class="h-1.5 bg-slate-100 dark:bg-white/5 rounded-full overflow-hidden">
                                <div class="h-full bg-gradient-to-r from-pcte-500 to-amber-400 rounded-full" style="width: <?php echo min(100, (int)$avgScore); ?>%"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tables Grid -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 table-anim">
                    
                    <!-- Recent Students -->
                    <div class="glass-card p-6 md:p-8 rounded-3xl">
                        <div class="flex justify-between items-center mb-6">
                            <h3 class="text-base font-black uppercase tracking-wider text-slate-900 dark:text-white">Recent Student Registrations</h3>
                            <a href="users.php" class="text-xs font-bold text-pcte-600 dark:text-pcte-400 hover:underline">View All</a>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-sm">
                                <thead class="border-b border-slate-200 dark:border-white/10 text-[10px] font-black uppercase tracking-widest text-slate-400">
                                    <tr>
                                        <th class="pb-3">Name</th>
                                        <th class="pb-3">Email</th>
                                        <th class="pb-3 text-right">Joined</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 dark:divide-white/5 font-medium text-xs">
                                    <?php foreach($recentUsers as $u): ?>
                                    <tr class="row-anim">
                                        <td class="py-3 font-bold text-slate-800 dark:text-white"><?php echo htmlspecialchars($u['name']); ?></td>
                                        <td class="py-3 text-slate-500 dark:text-gray-400"><?php echo htmlspecialchars($u['email']); ?></td>
                                        <td class="py-3 text-right text-slate-400"><?php echo date('M j, Y', strtotime($u['created_at'])); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php if(empty($recentUsers)): ?>
                                    <tr><td colspan="3" class="py-6 text-center text-slate-400">No students registered yet.</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Recent Scans / Applications -->
                    <div class="glass-card p-6 md:p-8 rounded-3xl">
                        <div class="flex justify-between items-center mb-6">
                            <h3 class="text-base font-black uppercase tracking-wider text-slate-900 dark:text-white">Recent ATS Scan Activity</h3>
                            <span class="text-xs font-bold text-slate-400">Live Telemetry</span>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-sm">
                                <thead class="border-b border-slate-200 dark:border-white/10 text-[10px] font-black uppercase tracking-widest text-slate-400">
                                    <tr>
                                        <th class="pb-3">Student</th>
                                        <th class="pb-3">Role</th>
                                        <th class="pb-3 text-right">Match</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 dark:divide-white/5 font-medium text-xs">
                                    <?php foreach($recentScans as $sc): ?>
                                    <tr class="row-anim">
                                        <td class="py-3 font-bold text-slate-800 dark:text-white"><?php echo htmlspecialchars($sc['student_name']); ?></td>
                                        <td class="py-3 text-slate-500 dark:text-gray-400 truncate max-w-[150px]"><?php echo htmlspecialchars($sc['job_title']); ?></td>
                                        <td class="py-3 text-right font-bold text-pcte-600 dark:text-pcte-400"><?php echo $sc['ats_score']; ?>%</td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php if(empty($recentScans)): ?>
                                    <tr><td colspan="3" class="py-6 text-center text-slate-400">No scans executed yet.</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                </div>

            </div>
        </div>
    </div>

    <script>
        const themeBtn = document.getElementById('theme-toggle');
        const darkIcon = document.getElementById('theme-toggle-dark-icon');
        const lightIcon = document.getElementById('theme-toggle-light-icon');

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
        }
        syncThemeUI();

        if(themeBtn) {
            themeBtn.addEventListener('click', () => {
                document.documentElement.classList.toggle('dark');
                syncThemeUI();
            });
        }

        // Animated count-up for stat cards
        function animateCount(el, target, duration = 1200) {
            let start = 0;
            const step = target / (duration / 16);
            const timer = setInterval(() => {
                start += step;
                if (start >= target) { el.textContent = target.toLocaleString(); clearInterval(timer); }
                else { el.textContent = Math.floor(start).toLocaleString(); }
            }, 16);
        }
        document.querySelectorAll('[data-count]').forEach(el => {
            const target = parseInt(el.dataset.count, 10);
            if (!isNaN(target) && target > 0) { el.textContent = '0'; animateCount(el, target); }
        });
    </script>
</body>
</html>