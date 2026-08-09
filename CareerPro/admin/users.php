<?php
declare(strict_types=1);
session_start();

/**
 * ============================================================================
 * CareerPro Suite - Student Roster & Account Access Control
 * Version: 6.0.0 (Enterprise Administrative UI)
 * Architecture: 
 * - Admin Session Guard (Strict Access Verification)
 * - Administrative CRUD (Toggle Status, Purge Student Node)
 * - Real-time Database Telemetry (User Join Dates, Resume Sync Status, Scan Counts)
 * - Responsive App-Shell Layout (Sidebar + Top Navigation)
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

$msg = '';
$msgType = '';

// Generate CSRF token for admin forms
if (empty($_SESSION['admin_csrf_token'])) {
    $_SESSION['admin_csrf_token'] = bin2hex(random_bytes(32));
}

// ============================================================================
// 2. HANDLE ADMINISTRATIVE ACTIONS (POST REQUESTS)
// ============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF check
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['admin_csrf_token'], $_POST['csrf_token'])) {
        $msg = "Security token invalid. Please reload the page.";
        $msgType = "error";
    } else {
        $action = $_POST['action'] ?? '';

    // --- A. TOGGLE USER ACCESS (Active / Locked) ---
    if ($action === 'toggle_user_status') {
        $userId = (int)($_POST['user_id'] ?? 0);
        $currentStatus = (int)($_POST['current_status'] ?? 1);
        $newStatus = $currentStatus === 1 ? 0 : 1;

        try {
            $stmt = $db->prepare("UPDATE users SET is_active = :status, updated_at = NOW() WHERE id = :id");
            $stmt->execute(['status' => $newStatus, 'id' => $userId]);
            $msg = "Student access node status updated successfully.";
            $msgType = "success";
        } catch (PDOException $e) {
            error_log("Admin Toggle User Fault: " . $e->getMessage());
            $msg = "Failed to modify student account status.";
            $msgType = "error";
        }
    }

    // --- B. PURGE STUDENT ACCOUNT (Recursive Delete) ---
    if ($action === 'delete_user') {
        $userId = (int)($_POST['user_id'] ?? 0);
        if ($userId > 0) {
            try {
                // Because foreign keys are set to ON DELETE CASCADE, deleting from users
                // will automatically clear resumes and applications.
                $stmt = $db->prepare("DELETE FROM users WHERE id = :id");
                $stmt->execute(['id' => $userId]);
                $msg = "Student account and associated telemetry data permanently purged.";
                $msgType = "success";
            } catch (PDOException $e) {
                error_log("Admin Delete User Fault: " . $e->getMessage());
                $msg = "Failed to purge student account node.";
                $msgType = "error";
            }
        }
    }
    } // end CSRF check
} // end POST

// ============================================================================
// 3. FETCH STUDENT ROSTER & METRICS FROM DATABASE
// ============================================================================
try {
    // Query users along with resume sync status and application scan counts
    $query = "
        SELECT u.id, u.name, u.email, u.phone, u.is_active, u.created_at, 
               r.last_updated AS resume_updated,
               (SELECT COUNT(*) FROM applications a WHERE a.user_id = u.id) AS total_scans,
               (SELECT MAX(a.ats_score) FROM applications a WHERE a.user_id = u.id) AS best_score
        FROM users u
        LEFT JOIN resumes r ON u.id = r.user_id
        ORDER BY u.created_at DESC
    ";
    $stmtRoster = $db->query($query);
    $students = $stmtRoster->fetchAll(PDO::FETCH_ASSOC);

    // Calculate Roster Metrics
    $totalStudents = count($students);
    $activeStudents = count(array_filter($students, fn($s) => (int)$s['is_active'] === 1));
    $lockedStudents = $totalStudents - $activeStudents;
    $syncedResumes = count(array_filter($students, fn($s) => !empty($s['resume_updated'])));

} catch (PDOException $e) {
    error_log("Student Roster Fetch Error: " . $e->getMessage());
    $students = [];
    $totalStudents = 0;
    $activeStudents = 0;
    $lockedStudents = 0;
    $syncedResumes = 0;
}
?>
<!DOCTYPE html>
<html lang="en" class="scroll-smooth dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Student Roster | Admin Portal</title>

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
                        pcte: { 50: '#fdf2f2', 100: '#fbe4e4', 200: '#f8caca', 400: '#ea6d6d', 500: '#df3c3c', 600: '#c82626', 700: '#a61c1c', 800: '#800000', 900: '#701616' },
                        dark: { 950: '#020202', 900: '#050505', 850: '#0a0a0a', 800: '#0f111a', 700: '#1e293b' },
                        success: { 50: '#ecfdf5', 500: '#10b981', 600: '#059669' }
                    }
                }
            }
        }
    </script>

    <style>
        body { overflow-x: hidden; -webkit-font-smoothing: antialiased; background-color: #f8fafc; color: #0f172a; transition: background-color 0.4s ease; }
        .dark body { background-color: #020202; color: #ffffff; }
        @keyframes fadeInCard { from { opacity: 0; transform: translateY(18px); } to { opacity: 1; transform: translateY(0); } }
        .anim-card { animation: fadeInCard 0.5s ease-out both; }
        .anim-card:nth-child(1){animation-delay:.08s}.anim-card:nth-child(2){animation-delay:.16s}
        .anim-card:nth-child(3){animation-delay:.24s}.anim-card:nth-child(4){animation-delay:.32s}
        .table-anim { animation: fadeInCard 0.5s ease-out 0.4s both; }
        .row-anim { animation: fadeInCard 0.4s ease-out both; }
        .row-anim:nth-child(odd) { animation-delay: 0.05s; }
        .card-hover { transition: transform 0.2s, box-shadow 0.2s; }
        .card-hover:hover { transform: translateY(-3px); }

        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 3px; }
        .dark ::-webkit-scrollbar-thumb { background: #1f1f1f; }

        .bg-grid-light { position: fixed; inset: 0; background-image: radial-gradient(#e2e8f0 1px, transparent 1px); background-size: 32px 32px; z-index: -2; mask-image: linear-gradient(to bottom, white, transparent); }
        .dark .bg-grid-light { display: none; }
        .bg-grid-dark { display: none; position: fixed; inset: 0; background-image: radial-gradient(rgba(255,255,255,0.03) 1px, transparent 1px); background-size: 32px 32px; z-index: -2; mask-image: radial-gradient(circle at 50% 50%, black, transparent 80%); }
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

        .input-field { background: #ffffff; border: 1.5px solid #e2e8f0; color: #0f172a; transition: all 0.2s ease; }
        .dark .input-field { background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.1); color: white; }
        .input-field:focus { outline: none; border-color: #df3c3c; box-shadow: 0 0 0 3px rgba(223, 60, 60, 0.1); }

        .theme-toggle-label { width: 46px; height: 26px; background-color: #cbd5e1; border-radius: 9999px; position: relative; cursor: pointer; display: flex; align-items: center; }
        .dark .theme-toggle-label { background-color: #334155; }
        .theme-toggle-ball { width: 20px; height: 20px; background-color: white; border-radius: 50%; position: absolute; top: 3px; left: 3px; transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1); display: flex; align-items: center; justify-content: center; box-shadow: 0 2px 4px rgba(0,0,0,0.2); }
        .dark .theme-toggle-ball { transform: translateX(20px); background-color: #0f172a; }
    </style>
</head>
<body class="flex h-screen overflow-hidden selection:bg-pcte-500 selection:text-white">

    <div class="bg-grid-light"></div>
    <div class="bg-grid-dark"></div>
    <div class="mesh-glow w-[500px] h-[500px] bg-pcte-500/20 top-[-100px] right-[-100px]"></div>

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
                <a href="index.php" class="nav-link rounded-xl">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"></path></svg>
                    Telemetry Overview
                </a>
                <a href="jobs.php" class="nav-link rounded-xl">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                    Job Matrix Management
                </a>
                <a href="users.php" class="nav-link active rounded-xl">
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
            <div class="flex items-center gap-6">
                <h1 class="text-lg font-black text-slate-900 dark:text-white tracking-tight">Student Roster & Access Control</h1>
            </div>
            
            <div class="flex items-center gap-6">
                <div class="relative w-full max-w-[250px] hidden sm:block">
                    <svg class="w-4 h-4 absolute left-3.5 top-[50%] transform -translate-y-1/2 text-slate-400 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                    <input type="text" id="search-students" placeholder="Search student roster..." class="w-full bg-slate-100 dark:bg-dark-900 border border-slate-200 dark:border-white/10 rounded-full pl-10 pr-4 py-2 text-xs font-semibold text-slate-900 dark:text-white focus:outline-none focus:border-pcte-500 shadow-inner">
                </div>

                <button id="theme-toggle" class="relative focus:outline-none hover:scale-105 transition-transform">
                    <div class="theme-toggle-label shadow-inner border border-slate-300 dark:border-white/10">
                        <div class="theme-toggle-ball">
                            <svg id="theme-toggle-light-icon" class="w-3.5 h-3.5 text-amber-500 hidden dark:block absolute" fill="currentColor" viewBox="0 0 20 20"><path d="M10 2a1 1 0 011 1v1a1 1 0 11-2 0V3a1 1 0 011-1zm4.22 4.22a1 1 0 011.415 0l.708.708a1 1 0 01-1.414 1.414l-.708-.708a1 1 0 010-1.414zm3.78 3.78a1 1 0 010 2h-1a1 1 0 110-2h1zm-4.22 4.22a1 1 0 010 1.415l-.708.708a1 1 0 011.414-1.414l.708.708a1 1 0 010 1.414zm-3.78-3.78a1 1 0 010-2h1a1 1 0 110 2h-1zm4.22-4.22a1 1 0 010-1.415l.708-.708a1 1 0 011.414 1.414l-.708.708a1 1 0 01-1.414 0zM10 16a1 1 0 011 1v1a1 1 0 11-2 0v-1a1 1 0 011-1zm-4.22-4.22a1 1 0 01-1.415 0l-.708-.708a1 1 0 011.414-1.414l.708.708a1 1 0 010 1.414zm-3.78-3.78a1 1 0 010-2h1a1 1 0 110 2h-1zm4.22-4.22a1 1 0 010-1.415l.708-.708a1 1 0 011.414 1.414l.708.708a1 1 0 01-1.414 0zM10 5a5 5 0 100 10 5 5 0 000-10z"></path></svg>
                            <svg id="theme-toggle-dark-icon" class="w-3.5 h-3.5 text-slate-700 block dark:hidden absolute" fill="currentColor" viewBox="0 0 20 20"><path d="M17.293 13.293A8 8 0 016.707 2.707a8.001 8.001 0 1010.586 10.586z"></path></svg>
                        </div>
                    </div>
                </button>
            </div>
        </header>

        <div class="flex-1 overflow-y-auto p-6 lg:p-10 pb-40 relative z-10">
            <div class="max-w-[1500px] mx-auto space-y-8">
                
                <?php if(!empty($msg)): ?>
                <div class="p-4 rounded-2xl text-sm font-bold border flex items-center gap-3 shadow-sm <?php echo $msgType === 'success' ? 'bg-green-50 dark:bg-green-900/20 border-green-200 dark:border-green-500/30 text-green-700 dark:text-green-400' : 'bg-red-50 dark:bg-red-900/20 border-red-200 dark:border-red-500/30 text-red-700 dark:text-red-400'; ?>">
                    <?php echo htmlspecialchars($msg); ?>
                </div>
                <?php endif; ?>

                <!-- Roster Stat Cards -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                    <div class="glass-card p-6 rounded-2xl flex flex-col justify-between anim-card card-hover">
                        <div class="flex items-center justify-between mb-3">
                            <span class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-400">Total Enrolled</span>
                            <div class="w-9 h-9 rounded-xl bg-blue-50 dark:bg-blue-900/20 flex items-center justify-center"><svg class="w-4 h-4 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg></div>
                        </div>
                        <h3 class="text-4xl font-black text-slate-900 dark:text-white"><?php echo number_format($totalStudents); ?></h3>
                        <p class="text-[10px] text-blue-500 font-bold mt-1 uppercase">Registered student nodes</p>
                    </div>
                    <div class="glass-card p-6 rounded-2xl flex flex-col justify-between anim-card card-hover">
                        <div class="flex items-center justify-between mb-3">
                            <span class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-400">Active Access</span>
                            <div class="w-9 h-9 rounded-xl bg-green-50 dark:bg-green-900/20 flex items-center justify-center"><svg class="w-4 h-4 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg></div>
                        </div>
                        <h3 class="text-4xl font-black text-slate-900 dark:text-white"><?php echo number_format($activeStudents); ?></h3>
                        <p class="text-[10px] text-green-500 font-bold mt-1 uppercase">Fully operational nodes</p>
                    </div>
                    <div class="glass-card p-6 rounded-2xl flex flex-col justify-between anim-card card-hover">
                        <div class="flex items-center justify-between mb-3">
                            <span class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-400">Locked / Suspended</span>
                            <div class="w-9 h-9 rounded-xl bg-red-50 dark:bg-red-900/20 flex items-center justify-center"><svg class="w-4 h-4 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/></svg></div>
                        </div>
                        <h3 class="text-4xl font-black text-slate-900 dark:text-white"><?php echo number_format($lockedStudents); ?></h3>
                        <p class="text-[10px] text-red-500 font-bold mt-1 uppercase">Restricted access</p>
                    </div>
                    <div class="glass-card p-6 rounded-2xl flex flex-col justify-between anim-card card-hover">
                        <div class="flex items-center justify-between mb-3">
                            <span class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-400">Resumes Synced</span>
                            <div class="w-9 h-9 rounded-xl bg-purple-50 dark:bg-purple-900/20 flex items-center justify-center"><svg class="w-4 h-4 text-purple-600 dark:text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg></div>
                        </div>
                        <h3 class="text-4xl font-black text-slate-900 dark:text-white"><?php echo number_format($syncedResumes); ?></h3>
                        <p class="text-[10px] text-purple-500 font-bold mt-1 uppercase">Payloads populated</p>
                    </div>
                </div>

                <!-- Students Table Matrix -->
                <div class="glass-card p-6 md:p-8 rounded-3xl table-anim">
                    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
                        <div>
                            <h3 class="text-lg font-black uppercase tracking-wider text-slate-900 dark:text-white">Student Directory</h3>
                            <p class="text-xs text-slate-500 dark:text-gray-400 font-medium">Manage student credentials, access permissions, and audit telemetry.</p>
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm" id="students-table">
                            <thead class="border-b border-slate-200 dark:border-white/10 text-[10px] font-black uppercase tracking-widest text-slate-400">
                                <tr>
                                    <th class="pb-3">Student Name & Email</th>
                                    <th class="pb-3">Mobile Node</th>
                                    <th class="pb-3">Resume Status</th>
                                    <th class="pb-3">ATS Scans</th>
                                    <th class="pb-3">Access Status</th>
                                    <th class="pb-3 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-white/5 font-medium text-xs">
                                <?php foreach($students as $st): ?>
                                <tr class="student-row row-anim hover:bg-slate-50 dark:hover:bg-white/[.02] transition-colors">
                                    <td class="py-4">
                                        <div class="font-bold text-slate-900 dark:text-white student-name"><?php echo htmlspecialchars($st['name']); ?></div>
                                        <div class="text-[10px] text-slate-500 dark:text-gray-400 student-email"><?php echo htmlspecialchars($st['email']); ?></div>
                                    </td>
                                    <td class="py-4 text-slate-600 dark:text-gray-300">
                                        <?php echo !empty($st['phone']) ? htmlspecialchars($st['phone']) : '<span class="text-slate-400 italic">Not provided</span>'; ?>
                                    </td>
                                    <td class="py-4">
                                        <?php if(!empty($st['resume_updated'])): ?>
                                            <span class="px-2.5 py-1 bg-purple-50 dark:bg-purple-900/20 text-purple-600 dark:text-purple-400 rounded text-[9px] font-black uppercase tracking-wider border border-purple-200 dark:border-purple-500/20">Synced</span>
                                        <?php else: ?>
                                            <span class="px-2.5 py-1 bg-slate-100 dark:bg-white/5 text-slate-400 rounded text-[9px] font-black uppercase tracking-wider">Uninitialized</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-4">
                                        <div class="font-bold text-slate-800 dark:text-white"><?php echo (int)$st['total_scans']; ?> Runs</div>
                                        <div class="text-[9px] text-slate-400">Best: <?php echo $st['best_score'] !== null ? $st['best_score'] . '%' : 'N/A'; ?></div>
                                    </td>
                                    <td class="py-4">
                                        <?php if((int)$st['is_active'] === 1): ?>
                                            <span class="px-2.5 py-0.5 bg-green-100 dark:bg-green-900/30 text-green-600 rounded text-[9px] font-black uppercase">Active</span>
                                        <?php else: ?>
                                            <span class="px-2.5 py-0.5 bg-red-100 dark:bg-red-900/30 text-red-600 rounded text-[9px] font-black uppercase">Locked</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-4 text-right space-x-2">
                                        <!-- Toggle Status Form -->
                                        <form action="users.php" method="POST" class="inline">
                                            <input type="hidden" name="action" value="toggle_user_status">
                                            <input type="hidden" name="user_id" value="<?php echo $st['id']; ?>">
                                            <input type="hidden" name="current_status" value="<?php echo $st['is_active']; ?>">
                                            <button type="submit" class="px-3 py-1.5 bg-slate-200 dark:bg-dark-800 hover:bg-slate-300 dark:hover:bg-dark-700 rounded-lg text-[10px] font-bold cursor-pointer transition-colors">
                                                <?php echo (int)$st['is_active'] === 1 ? 'Lock Access' : 'Restore Access'; ?>
                                            </button>
                                        </form>

                                        <!-- Delete / Purge Form -->
                                        <form action="users.php" method="POST" class="inline" onsubmit="return confirm('CRITICAL SECURITY WARNING: Are you sure you want to permanently delete this student and all their telemetry data?');">
                                            <input type="hidden" name="action" value="delete_user">
                                            <input type="hidden" name="user_id" value="<?php echo $st['id']; ?>">
                                            <button type="submit" class="px-3 py-1.5 bg-red-100 dark:bg-red-900/20 text-red-600 hover:bg-red-200 rounded-lg text-[10px] font-bold cursor-pointer transition-colors">
                                                Purge Node
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if(empty($students)): ?>
                                <tr><td colspan="6" class="py-12 text-center text-slate-400 font-bold">No student nodes registered in the cluster yet.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <!-- SCRIPT ENGINES -->
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

        // Live Student Search Filtering
        const searchInput = document.getElementById('search-students');
        if(searchInput) {
            searchInput.addEventListener('input', (e) => {
                const val = e.target.value.toLowerCase();
                document.querySelectorAll('.student-row').forEach(row => {
                    const name = row.querySelector('.student-name').innerText.toLowerCase();
                    const email = row.querySelector('.student-email').innerText.toLowerCase();
                    if(name.includes(val) || email.includes(val)) {
                        row.style.display = '';
                    } else {
                        row.style.display = 'none';
                    }
                });
            });
        }
    </script>
</body>
</html>