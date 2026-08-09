<?php
declare(strict_types=1);
session_start();

// Session guard — only authenticated students
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

require_once 'includes/db.php';
$db     = Database::getInstance()->getConnection();
$userId = (int)$_SESSION['user_id'];

// Fetch user
try {
    $stmt = $db->prepare("SELECT name FROM users WHERE id = :id LIMIT 1");
    $stmt->execute(['id' => $userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) { session_destroy(); header("Location: login.php"); exit; }
    $userName  = $user['name'];
    $firstName = explode(' ', $userName)[0];
} catch (PDOException $e) {
    $userName = 'Student'; $firstName = 'Student';
}

// Fetch live jobs
try {
    $jobStmt = $db->query("SELECT * FROM jobs WHERE is_active = 1 ORDER BY created_at DESC");
    $dbJobs  = $jobStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $dbJobs = [];
}

// Check resume readiness
try {
    $resStmt = $db->prepare("SELECT resume_data FROM resumes WHERE user_id = :uid LIMIT 1");
    $resStmt->execute(['uid' => $userId]);
    $resRow = $resStmt->fetch(PDO::FETCH_ASSOC);
    $isProfileReady = false;
    if ($resRow && !empty($resRow['resume_data'])) {
        $rd = json_decode($resRow['resume_data'], true);
        if (json_last_error() === JSON_ERROR_NONE && !empty($rd['skills'])) {
            $isProfileReady = true;
        }
    }
} catch (PDOException $e) {
    $isProfileReady = false;
}
?>
<!DOCTYPE html>
<html lang="en" class="scroll-smooth dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Job Opportunities | CareerPro Suite</title>
    <script>
        if (localStorage.getItem('color-theme') === 'dark' || (!('color-theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        } else { document.documentElement.classList.remove('dark'); }
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: { extend: {
                fontFamily: { sans: ['"Plus Jakarta Sans"', 'sans-serif'] },
                colors: {
                    pcte: { 50:'#fdf2f2',100:'#fbe4e4',400:'#ea6d6d',500:'#df3c3c',600:'#c82626',700:'#a61c1c',800:'#800000',900:'#701616' },
                    dark: { 950:'#020202',900:'#050505',800:'#0a0a0a',700:'#141414' }
                }
            }}
        }
    </script>
    <style>
        body { overflow-x:hidden; -webkit-font-smoothing:antialiased; background:#f8fafc; color:#0f172a; transition:background .4s,color .4s; }
        .dark body { background:#020202; color:#fff; }
        ::-webkit-scrollbar{width:6px}::-webkit-scrollbar-thumb{background:#cbd5e1;border-radius:3px}.dark ::-webkit-scrollbar-thumb{background:#1f1f1f}
        .glass-nav { background:rgba(255,255,255,.85); backdrop-filter:blur(24px); border-bottom:1px solid rgba(0,0,0,.05); }
        .dark .glass-nav { background:rgba(5,5,5,.8); border-bottom:1px solid rgba(255,255,255,.05); }
        .sidebar { background:#fff; border-right:1px solid #e2e8f0; }
        .dark .sidebar { background:#050505; border-right:1px solid rgba(255,255,255,.05); }
        .nav-link { display:flex;align-items:center;gap:.75rem;padding:.85rem 1.5rem;color:#64748b;font-weight:700;font-size:.875rem;transition:all .2s;border-left:3px solid transparent;border-radius:.75rem;margin:.125rem .75rem; }
        .dark .nav-link { color:#94a3b8; }
        .nav-link:hover { color:#df3c3c; background:#fdf2f2; }
        .dark .nav-link:hover { color:#fff; background:rgba(223,60,60,.05); }
        .nav-link.active { color:#df3c3c; background:#fdf2f2; border-left-color:#df3c3c; }
        .dark .nav-link.active { color:#ea6d6d; background:rgba(223,60,60,.1); border-left-color:#df3c3c; }
        .glass-card { background:rgba(255,255,255,.8); backdrop-filter:blur(12px); border:1px solid rgba(0,0,0,.05); box-shadow:0 10px 30px -10px rgba(0,0,0,.05); transition:transform .3s,border-color .3s,box-shadow .3s; }
        .dark .glass-card { background:linear-gradient(145deg,rgba(20,20,20,.9),rgba(10,10,10,.95)); border:1px solid rgba(255,255,255,.05); border-top-color:rgba(255,255,255,.08); box-shadow:0 25px 50px -12px rgba(0,0,0,.5); }
        .job-card { cursor:pointer; border:1px solid #e2e8f0; background:#fff; transition:all .2s cubic-bezier(.4,0,.2,1); border-radius:1.25rem; }
        .dark .job-card { border-color:rgba(255,255,255,.05); background:#0a0a0a; }
        .job-card:hover { border-color:#df3c3c; transform:translateX(4px); box-shadow:0 10px 25px -5px rgba(223,60,60,.1); }
        .dark .job-card:hover { border-color:#df3c3c; }
        .job-card.active { border-color:#df3c3c; background:#fdf2f2; border-left:4px solid #df3c3c; }
        .dark .job-card.active { background:rgba(223,60,60,.05); border-left:4px solid #df3c3c; }
        .btn-primary { background:linear-gradient(135deg,#df3c3c,#a61c1c); position:relative; overflow:hidden; z-index:1; transition:all .3s; border:none; color:#fff; cursor:pointer; }
        .btn-primary:hover:not(:disabled) { box-shadow:0 10px 25px -5px rgba(223,60,60,.4); transform:translateY(-2px); }
        .btn-primary:disabled { opacity:.5; cursor:not-allowed; }
        .progress-ring__circle { transition:stroke-dashoffset 1.5s ease; transform:rotate(-90deg); transform-origin:50% 50%; stroke-linecap:round; }
        .theme-toggle-label { width:44px; height:24px; background:#cbd5e1; border-radius:9999px; position:relative; cursor:pointer; transition:background .3s; display:flex; align-items:center; }
        .dark .theme-toggle-label { background:#334155; }
        .theme-toggle-ball { width:18px; height:18px; background:#fff; border-radius:50%; position:absolute; top:3px; left:3px; transition:transform .3s cubic-bezier(.4,0,.2,1); box-shadow:0 2px 4px rgba(0,0,0,.2); }
        .dark .theme-toggle-ball { transform:translateX(20px); background:#0f172a; }
    </style>
</head>
<body class="flex h-screen overflow-hidden selection:bg-pcte-500 selection:text-white">
<!-- Sidebar -->
<aside class="sidebar w-64 h-full hidden lg:flex flex-col justify-between shrink-0 z-50 shadow-xl dark:shadow-none">
    <div>
        <div class="h-20 flex items-center px-6 border-b border-slate-200 dark:border-white/5">
            <a href="index.php" class="flex items-center gap-3 group">
                <div class="w-9 h-9 rounded-xl bg-pcte-600 dark:bg-pcte-800 flex items-center justify-center shadow-lg group-hover:rotate-12 transition-transform">
                    <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                </div>
                <span class="text-xl font-black text-slate-900 dark:text-white">Career<span class="text-pcte-600 dark:text-pcte-500">Pro</span></span>
            </a>
        </div>
        <nav class="mt-6 px-2 space-y-1">
            <p class="px-4 text-[10px] font-black text-slate-400 dark:text-gray-600 uppercase tracking-widest mb-3">Workspace</p>
            <a href="dashboard.php" class="nav-link">
                <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                Command Center
            </a>
            <a href="builder.php" class="nav-link">
                <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                Resume Engine
            </a>
            <a href="jobs.php" class="nav-link active">
                <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                Job Opportunities
            </a>
            <p class="px-4 text-[10px] font-black text-slate-400 dark:text-gray-600 uppercase tracking-widest mt-6 mb-3">Settings</p>
            <a href="profile.php" class="nav-link">
                <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                Identity Management
            </a>
        </nav>
    </div>
    <div class="p-5 border-t border-slate-200 dark:border-white/5 bg-slate-50 dark:bg-[#050505]">
        <div class="flex items-center gap-3 mb-4 p-3 rounded-2xl bg-white dark:bg-dark-800 border border-slate-200 dark:border-white/5 shadow-sm">
            <div class="w-9 h-9 rounded-xl bg-pcte-100 dark:bg-pcte-900/30 flex items-center justify-center font-black text-pcte-600 dark:text-pcte-400 shrink-0">
                <?php echo strtoupper(substr($firstName, 0, 1)); ?>
            </div>
            <div class="overflow-hidden">
                <h4 class="text-sm font-bold text-slate-900 dark:text-white truncate"><?php echo htmlspecialchars($userName); ?></h4>
                <p class="text-[9px] text-slate-500 uppercase font-black tracking-widest">Verified Student</p>
            </div>
        </div>
        <a href="api/auth.php?action=logout" class="flex items-center justify-center gap-2 w-full py-2.5 bg-slate-900 dark:bg-white text-white dark:text-black rounded-xl text-xs font-bold hover:scale-[1.02] transition-all shadow-md">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
            Sign Out
        </a>
    </div>
</aside>

<!-- Main content -->
<div class="flex-1 flex flex-col h-screen overflow-hidden">

    <!-- Top nav -->
    <header class="h-16 flex items-center justify-between px-6 lg:px-10 border-b border-slate-200 dark:border-white/5 glass-nav shrink-0 z-40">
        <div class="flex items-center gap-4">
            <button id="mobile-menu-btn" class="lg:hidden p-2 rounded-xl bg-slate-100 dark:bg-dark-800 text-slate-600 dark:text-gray-400">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
            </button>
            <div>
                <h1 class="text-base font-black text-slate-900 dark:text-white">Job Opportunities</h1>
                <p class="text-[10px] font-bold text-slate-500 uppercase tracking-widest hidden sm:block"><?php echo count($dbJobs); ?> live roles available</p>
            </div>
        </div>
        <div class="flex items-center gap-4">
            <!-- Search -->
            <div class="relative hidden sm:block">
                <svg class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                <input type="text" id="search-jobs" placeholder="Search roles…" class="bg-slate-100 dark:bg-dark-800 border border-slate-200 dark:border-white/10 rounded-full pl-9 pr-4 py-2 text-sm font-semibold text-slate-900 dark:text-white focus:outline-none focus:border-pcte-500 w-52 transition-all">
            </div>
            <!-- Theme toggle -->
            <button id="theme-toggle" class="relative focus:outline-none">
                <div class="theme-toggle-label">
                    <div class="theme-toggle-ball">
                        <svg id="tl-light" class="w-2.5 h-2.5 text-amber-500 hidden dark:block absolute" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 5a5 5 0 100 10A5 5 0 0010 5z" clip-rule="evenodd"/></svg>
                        <svg id="tl-dark"  class="w-2.5 h-2.5 text-slate-700 block dark:hidden absolute" fill="currentColor" viewBox="0 0 20 20"><path d="M17.293 13.293A8 8 0 016.707 2.707a8.001 8.001 0 1010.586 10.586z"/></svg>
                    </div>
                </div>
            </button>
        </div>
    </header>

    <!-- Mobile menu -->
    <div id="mobile-menu" class="hidden absolute top-16 left-0 w-full glass-nav py-4 px-6 flex-col space-y-2 shadow-xl z-50 border-b border-slate-200 dark:border-white/10">
        <a href="dashboard.php" class="nav-link rounded-lg">Command Center</a>
        <a href="builder.php"   class="nav-link rounded-lg">Resume Engine</a>
        <a href="jobs.php"      class="nav-link active rounded-lg">Job Opportunities</a>
        <a href="profile.php"   class="nav-link rounded-lg">Identity Settings</a>
        <div class="h-px bg-slate-200 dark:bg-white/10 my-2"></div>
        <a href="api/auth.php?action=logout" class="text-center text-red-500 font-bold py-2 bg-red-50 dark:bg-red-900/10 rounded-lg text-sm">Sign Out</a>
    </div>

    <!-- Two-pane job layout -->
    <div class="flex flex-1 overflow-hidden flex-col md:flex-row">

        <!-- Left: Job List -->
        <div class="w-full md:w-[40%] lg:w-[36%] h-1/2 md:h-full overflow-y-auto border-b md:border-b-0 md:border-r border-slate-200 dark:border-white/5 bg-slate-50/60 dark:bg-[#080808]/60 p-4">

            <?php if (!$isProfileReady): ?>
            <div class="mb-4 p-4 rounded-2xl bg-amber-50 dark:bg-amber-900/10 border border-amber-200 dark:border-amber-500/20 flex items-start gap-3 shadow-sm">
                <svg class="w-5 h-5 text-amber-600 dark:text-amber-400 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                <div>
                    <p class="text-xs font-black text-amber-800 dark:text-amber-400 uppercase tracking-widest">Incomplete Profile</p>
                    <p class="text-[11px] text-slate-600 dark:text-gray-400 mt-1">Complete your resume to enable ATS scanning. <a href="builder.php" class="font-bold underline">Open Builder →</a></p>
                </div>
            </div>
            <?php endif; ?>

            <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-3 px-1">Live Roles (<?php echo count($dbJobs); ?>)</p>

            <?php if (empty($dbJobs)): ?>
                <div class="text-center py-16 opacity-50">
                    <svg class="w-12 h-12 text-slate-400 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                    <p class="text-xs font-black uppercase tracking-widest text-slate-500">No Roles Posted Yet</p>
                </div>
            <?php else: ?>
                <div id="job-list">
                    <?php foreach ($dbJobs as $i => $job): ?>
                    <div onclick="selectJob(<?php echo htmlspecialchars(json_encode($job)); ?>, event)"
                         class="job-card p-4 mb-3 flex gap-4 group" data-title="<?php echo strtolower(htmlspecialchars($job['title'])); ?>" data-company="<?php echo strtolower(htmlspecialchars($job['company'])); ?>">
                        <div class="w-12 h-12 rounded-xl border border-slate-200 dark:border-white/10 flex items-center justify-center font-black text-xl shrink-0 bg-pcte-50 dark:bg-dark-900 text-pcte-600 dark:text-pcte-500 group-hover:scale-105 transition-transform">
                            <?php echo $job['logo'] ?: strtoupper(substr($job['company'], 0, 1)); ?>
                        </div>
                        <div class="flex-1 min-w-0">
                            <h3 class="text-sm font-bold text-slate-900 dark:text-white truncate group-hover:text-pcte-600 dark:group-hover:text-pcte-400 transition-colors"><?php echo htmlspecialchars($job['title']); ?></h3>
                            <p class="text-[11px] font-bold text-slate-500 dark:text-gray-400 truncate"><?php echo htmlspecialchars($job['company']); ?></p>
                            <div class="flex flex-wrap gap-1.5 mt-2">
                                <span class="bg-slate-100 dark:bg-dark-800 text-slate-600 dark:text-gray-400 px-2 py-0.5 rounded text-[9px] font-black uppercase tracking-widest"><?php echo htmlspecialchars($job['location']); ?></span>
                                <span class="bg-pcte-50 dark:bg-pcte-900/20 text-pcte-600 dark:text-pcte-400 px-2 py-0.5 rounded text-[9px] font-black uppercase tracking-widest"><?php echo htmlspecialchars($job['job_type']); ?></span>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div id="no-results" class="hidden text-center py-12 opacity-50">
                    <p class="text-xs font-black uppercase tracking-widest text-slate-500">No matches found</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Right: Job Detail -->
        <div class="flex-1 h-1/2 md:h-full overflow-y-auto bg-white/50 dark:bg-[#050505]/60" id="detail-pane">

            <!-- Empty state -->
            <div id="empty-state" class="h-full flex flex-col items-center justify-center text-center p-8">
                <div class="w-24 h-24 bg-white dark:bg-dark-800 rounded-full border border-slate-200 dark:border-white/5 flex items-center justify-center mb-5 shadow-xl mx-auto">
                    <svg class="w-12 h-12 text-slate-300 dark:text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                </div>
                <h3 class="text-xl font-black text-slate-800 dark:text-white mb-2">Select a Role</h3>
                <p class="text-slate-500 dark:text-gray-400 text-sm max-w-xs">Click any job from the list to view details and run an ATS compatibility scan.</p>
            </div>

            <!-- Active job -->
            <div id="active-job" class="hidden p-8 lg:p-12 space-y-8">
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-6 border-b border-slate-200 dark:border-white/10 pb-8">
                    <div class="flex gap-5 items-center">
                        <div id="d-logo" class="w-16 h-16 rounded-2xl border border-slate-200 dark:border-white/10 flex items-center justify-center text-3xl font-black shrink-0 bg-pcte-50 dark:bg-dark-900 text-pcte-600 dark:text-pcte-500 shadow-inner"></div>
                        <div>
                            <h2 id="d-title"   class="text-2xl lg:text-3xl font-black text-slate-900 dark:text-white tracking-tight mb-1"></h2>
                            <p  id="d-company" class="text-pcte-600 dark:text-pcte-400 font-bold mb-2"></p>
                            <div class="flex flex-wrap gap-2 text-[10px] font-black uppercase tracking-widest">
                                <span id="d-loc"    class="bg-slate-100 dark:bg-dark-800 text-slate-600 dark:text-gray-400 px-3 py-1 rounded-lg border border-slate-200 dark:border-white/5"></span>
                                <span id="d-type"   class="bg-slate-100 dark:bg-dark-800 text-slate-600 dark:text-gray-400 px-3 py-1 rounded-lg border border-slate-200 dark:border-white/5"></span>
                                <span id="d-salary" class="bg-green-50 dark:bg-green-900/10 text-green-700 dark:text-green-400 px-3 py-1 rounded-lg border border-green-200 dark:border-green-500/20"></span>
                            </div>
                        </div>
                    </div>
                    <button id="scan-btn" onclick="runAtsScan()" <?php echo !$isProfileReady ? 'disabled title="Complete resume first"' : ''; ?>
                        class="btn-primary px-8 py-4 rounded-2xl font-black uppercase text-xs tracking-widest shadow-xl flex items-center gap-2 shrink-0 active:scale-95 transition-all">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                        Run ATS Scan
                    </button>
                </div>

                <div>
                    <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-3">Job Description</p>
                    <p id="d-desc" class="text-slate-700 dark:text-gray-300 leading-relaxed text-sm whitespace-pre-wrap"></p>
                </div>

                <div id="req-skills-block" class="hidden">
                    <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-3">Required Skills</p>
                    <div id="req-skills" class="flex flex-wrap gap-2"></div>
                </div>

                <!-- ATS Result -->
                <div id="ats-result" class="hidden glass-card rounded-3xl p-8 space-y-6">
                    <div class="flex flex-col sm:flex-row items-center gap-8">
                        <div class="relative w-40 h-40 shrink-0">
                            <svg class="w-full h-full" viewBox="0 0 100 100">
                                <circle class="text-slate-100 dark:text-dark-800 stroke-current" stroke-width="8" cx="50" cy="50" r="42" fill="transparent"/>
                                <circle id="score-ring" class="progress-ring__circle stroke-current" stroke-width="10" cx="50" cy="50" r="42" fill="transparent" stroke-dasharray="263.89" stroke-dashoffset="263.89" style="stroke:#df3c3c"/>
                            </svg>
                            <div class="absolute inset-0 flex flex-col items-center justify-center">
                                <span id="score-num"   class="text-4xl font-black text-slate-900 dark:text-white leading-none">0</span>
                                <span class="text-[9px] font-black text-slate-400 uppercase tracking-widest">/ 100</span>
                            </div>
                        </div>
                        <div class="flex-1 space-y-3">
                            <div id="ats-grade" class="inline-flex px-4 py-1.5 rounded-full text-sm font-black uppercase tracking-wider border"></div>
                            <p id="ats-msg" class="text-slate-600 dark:text-gray-400 text-sm leading-relaxed"></p>
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <p class="text-[9px] font-black text-green-600 uppercase tracking-widest mb-1">Matched Skills</p>
                                    <div id="ats-matched" class="flex flex-wrap gap-1.5"></div>
                                </div>
                                <div>
                                    <p class="text-[9px] font-black text-red-500 uppercase tracking-widest mb-1">Missing Skills</p>
                                    <div id="ats-missing" class="flex flex-wrap gap-1.5"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Scanning spinner -->
                <div id="ats-scanning" class="hidden glass-card rounded-3xl p-8 text-center">
                    <div class="w-12 h-12 rounded-full border-4 border-pcte-500 border-t-transparent animate-spin mx-auto mb-3"></div>
                    <p class="text-sm font-bold text-slate-700 dark:text-gray-300">Running ATS scan…</p>
                </div>
            </div>
        </div>
    </div>
</div><!-- /main -->

<?php include 'chatbot.php'; ?>

<script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
<script>
AOS.init({ once:true, duration:600 });

// Theme
const themeBtn = document.getElementById('theme-toggle');
themeBtn && themeBtn.addEventListener('click', () => {
    const dark = document.documentElement.classList.toggle('dark');
    localStorage.setItem('color-theme', dark ? 'dark' : 'light');
});

// Mobile menu
document.getElementById('mobile-menu-btn').addEventListener('click', () => {
    const m = document.getElementById('mobile-menu');
    m.classList.toggle('hidden'); m.classList.toggle('flex');
});

// Search filter
const searchInput = document.getElementById('search-jobs');
if (searchInput) {
    searchInput.addEventListener('input', () => {
        const q = searchInput.value.toLowerCase();
        let any = false;
        document.querySelectorAll('#job-list .job-card').forEach(card => {
            const match = (card.dataset.title + ' ' + card.dataset.company).includes(q);
            card.style.display = match ? '' : 'none';
            if (match) any = true;
        });
        document.getElementById('no-results').classList.toggle('hidden', any);
    });
}

let currentJobId = null;

function selectJob(job, e) {
    // Mark active card
    document.querySelectorAll('.job-card').forEach(c => c.classList.remove('active'));
    (e?.currentTarget || e?.target)?.classList.add('active');

    currentJobId = job.id;

    document.getElementById('empty-state').classList.add('hidden');
    document.getElementById('active-job').classList.remove('hidden');
    document.getElementById('ats-result').classList.add('hidden');
    document.getElementById('ats-scanning').classList.add('hidden');

    document.getElementById('d-logo').textContent    = job.logo || job.company[0];
    document.getElementById('d-title').textContent   = job.title;
    document.getElementById('d-company').textContent = job.company;
    document.getElementById('d-loc').textContent     = job.location;
    document.getElementById('d-type').textContent    = job.job_type;
    document.getElementById('d-salary').textContent  = job.salary || 'Salary TBD';
    document.getElementById('d-desc').textContent    = job.description || 'No description provided.';

    const skillsBlock = document.getElementById('req-skills-block');
    const skillsDiv   = document.getElementById('req-skills');
    let skills = [];
    try { skills = JSON.parse(job.req_skills || '[]'); } catch(e) {}
    if (skills.length > 0) {
        skillsDiv.innerHTML = skills.map(s =>
            `<span class="px-3 py-1 rounded-full text-[10px] font-bold bg-pcte-50 dark:bg-pcte-900/20 text-pcte-600 dark:text-pcte-400 border border-pcte-100 dark:border-pcte-500/20">${s}</span>`
        ).join('');
        skillsBlock.classList.remove('hidden');
    } else {
        skillsBlock.classList.add('hidden');
    }
}

async function runAtsScan() {
    if (!currentJobId) return;
    document.getElementById('ats-result').classList.add('hidden');
    document.getElementById('ats-scanning').classList.remove('hidden');

    try {
        const resp = await fetch('api/matcher-api.php', {
            method: 'POST',
            headers: { 'Content-Type':'application/json', 'X-Requested-With':'XMLHttpRequest' },
            body: JSON.stringify({ job_id: currentJobId, job_description: document.getElementById('d-desc').textContent })
        });
        const data = await resp.json();
        document.getElementById('ats-scanning').classList.add('hidden');

        if (data.status !== 'success') {
            alert(data.message || 'Scan failed. Please try again.');
            return;
        }

        const d = data.data;
        const score = d.overall_score || 0;

        // Ring
        const ring = document.getElementById('score-ring');
        const offset = 263.89 - (score / 100) * 263.89;
        ring.style.strokeDashoffset = offset;
        ring.style.stroke = score >= 80 ? '#22c55e' : score >= 60 ? '#3b82f6' : score >= 40 ? '#f59e0b' : '#ef4444';

        // Count-up
        const numEl = document.getElementById('score-num');
        let n = 0;
        const iv = setInterval(() => { n = Math.min(n + 2, score); numEl.textContent = n; if (n >= score) clearInterval(iv); }, 20);

        // Grade
        const gradeEl = document.getElementById('ats-grade');
        const gradeCls = {
            'Excellent Match': 'bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-400 border-green-200 dark:border-green-500/30',
            'Good Match':      'bg-blue-100  dark:bg-blue-900/30  text-blue-700  dark:text-blue-400  border-blue-200  dark:border-blue-500/30',
            'Average Match':   'bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-400 border-amber-200 dark:border-amber-500/30',
            'Poor Match':      'bg-red-100   dark:bg-red-900/30   text-red-700   dark:text-red-400   border-red-200   dark:border-red-500/30',
        };
        gradeEl.textContent  = d.status || 'Analysed';
        gradeEl.className    = 'inline-flex px-4 py-1.5 rounded-full text-sm font-black uppercase tracking-wider border ' + (gradeCls[d.status] || gradeCls['Average Match']);

        document.getElementById('ats-msg').textContent = score >= 80 ? 'Great match! Your resume aligns well with this role.' :
            score >= 60 ? 'Good match. A few tweaks will strengthen your application.' :
            score >= 40 ? 'Moderate match. Consider adding more relevant skills.' : 'Low match. Update your resume to target this role better.';

        const chip = (text, color) => `<span class="px-2 py-0.5 rounded text-[9px] font-bold ${color}">${text}</span>`;
        document.getElementById('ats-matched').innerHTML = (d.hard_skills?.matched||[]).map(s => chip(s,'bg-green-100 dark:bg-green-900/20 text-green-700 dark:text-green-400')).join('');
        document.getElementById('ats-missing').innerHTML = (d.hard_skills?.missing||[]).map(s => chip(s,'bg-red-100 dark:bg-red-900/20 text-red-700 dark:text-red-400')).join('');

        document.getElementById('ats-result').classList.remove('hidden');
    } catch (e) {
        document.getElementById('ats-scanning').classList.add('hidden');
        alert('Scan failed: ' + e.message);
    }
}
</script>
</body>
</html>
