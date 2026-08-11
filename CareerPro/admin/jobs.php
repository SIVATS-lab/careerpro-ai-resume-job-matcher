<?php
declare(strict_types=1);
session_start();

/**
 * ============================================================================
 * CareerPro Suite — Job Matrix Management
 * Full CRUD: Add / Edit / Toggle / Delete jobs with CSRF protection.
 * ============================================================================
 */

if (!isset($_SESSION['admin_id'])) { header("Location: login.php"); exit; }

require_once '../includes/db.php';
$db = Database::getInstance()->getConnection();
$adminName = $_SESSION['admin_name'] ?? 'Administrator';
$msg = ''; $msgType = '';

if (empty($_SESSION['admin_csrf_token'])) {
    $_SESSION['admin_csrf_token'] = bin2hex(random_bytes(32));
}

// ── POST HANDLER ─────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['admin_csrf_token'], $_POST['csrf_token'])) {
        $msg = "Security token invalid. Please reload."; $msgType = "error";
    } else {
        $action     = $_POST['action'] ?? '';
        $skillsRaw  = trim($_POST['req_skills'] ?? '');
        $skillsArr  = $skillsRaw ? array_values(array_filter(array_map('trim', explode(',', $skillsRaw)))) : [];
        $skillsJson = json_encode($skillsArr);
        $isActive   = isset($_POST['is_active']) ? 1 : 0;

        if ($action === 'add_job') {
            $t = trim($_POST['title'] ?? ''); $c = trim($_POST['company'] ?? '');
            if (!$t || !$c) { $msg = "Title and Company are required."; $msgType = "error"; }
            else {
                try {
                    $db->prepare("INSERT INTO jobs (title,company,location,job_type,salary,description,req_skills,logo,is_active,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,NOW(),NOW())")
                       ->execute([$t,$c,trim($_POST['location']??'Remote'),trim($_POST['job_type']??'Full-time'),trim($_POST['salary']??''),trim($_POST['description']??''),$skillsJson,trim($_POST['logo']??'💼'),$isActive]);
                    $msg = "Job posting created successfully."; $msgType = "success";
                } catch (PDOException $e) { error_log($e->getMessage()); $msg = "Failed to create job posting."; $msgType = "error"; }
            }
        }

        if ($action === 'edit_job') {
            $id = (int)($_POST['job_id'] ?? 0); $t = trim($_POST['title'] ?? ''); $c = trim($_POST['company'] ?? '');
            if (!$id || !$t || !$c) { $msg = "Invalid submission data."; $msgType = "error"; }
            else {
                try {
                    $db->prepare("UPDATE jobs SET title=?,company=?,location=?,job_type=?,salary=?,description=?,req_skills=?,logo=?,is_active=?,updated_at=NOW() WHERE id=?")
                       ->execute([$t,$c,trim($_POST['location']??''),trim($_POST['job_type']??''),trim($_POST['salary']??''),trim($_POST['description']??''),$skillsJson,trim($_POST['logo']??''),$isActive,$id]);
                    $msg = "Job posting updated successfully."; $msgType = "success";
                } catch (PDOException $e) { error_log($e->getMessage()); $msg = "Failed to update job posting."; $msgType = "error"; }
            }
        }

        if ($action === 'toggle_job') {
            $id = (int)($_POST['job_id'] ?? 0); $cur = (int)($_POST['current_status'] ?? 1);
            if ($id > 0) {
                try {
                    $db->prepare("UPDATE jobs SET is_active=?,updated_at=NOW() WHERE id=?")->execute([$cur === 1 ? 0 : 1, $id]);
                    $msg = "Job status updated."; $msgType = "success";
                } catch (PDOException $e) { $msg = "Status update failed."; $msgType = "error"; }
            }
        }

        if ($action === 'delete_job') {
            $id = (int)($_POST['job_id'] ?? 0);
            if ($id > 0) {
                try {
                    $db->prepare("DELETE FROM jobs WHERE id=?")->execute([$id]);
                    $msg = "Job posting permanently deleted."; $msgType = "success";
                } catch (PDOException $e) { error_log($e->getMessage()); $msg = "Delete failed."; $msgType = "error"; }
            }
        }
    }
}

// ── FETCH DATA ────────────────────────────────────────────────────────────────
try {
    $jobs = $db->query("SELECT j.*, (SELECT COUNT(*) FROM applications a WHERE a.job_id = j.id) AS app_count FROM jobs j ORDER BY j.created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { error_log($e->getMessage()); $jobs = []; }

$totalJobs    = count($jobs);
$activeJobs   = count(array_filter($jobs, fn($j) => (int)$j['is_active'] === 1));
$inactiveJobs = $totalJobs - $activeJobs;
$totalApps    = array_sum(array_column($jobs, 'app_count'));
$csrf = htmlspecialchars($_SESSION['admin_csrf_token']);
?>
<!DOCTYPE html>
<html lang="en" class="scroll-smooth dark">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<title>Job Matrix Management | Admin Portal</title>
<script>if(localStorage.getItem('color-theme')==='dark'||(!('color-theme' in localStorage)&&window.matchMedia('(prefers-color-scheme:dark)').matches)){document.documentElement.classList.add('dark')}else{document.documentElement.classList.remove('dark')}</script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
<script src="https://cdn.tailwindcss.com"></script>
<script>
tailwind.config = {
    darkMode:'class',
    theme:{extend:{
        fontFamily:{sans:['"Plus Jakarta Sans"','sans-serif']},
        colors:{
            pcte:{50:'#fdf2f2',100:'#fbe4e4',200:'#f8caca',300:'#f2a3a3',400:'#ea6d6d',500:'#df3c3c',600:'#c82626',700:'#a61c1c',800:'#800000',900:'#701616',950:'#3f0707'},
            dark:{950:'#020202',900:'#050505',850:'#0a0a0a',800:'#0f111a',700:'#1e293b',600:'#1f1f1f'}
        },
        animation:{'fade-in-up':'fadeInUp 0.4s ease-out forwards','slide-in':'slideIn 0.3s ease-out forwards','pulse-glow':'pulseGlow 2s ease-in-out infinite'},
        keyframes:{
            fadeInUp:{'0%':{opacity:'0',transform:'translateY(16px)'},'100%':{opacity:'1',transform:'translateY(0)'}},
            slideIn:{'0%':{opacity:'0',transform:'translateX(-10px)'},'100%':{opacity:'1',transform:'translateX(0)'}},
            pulseGlow:{'0%,100%':{boxShadow:'0 0 0 0 rgba(223,60,60,0)'},'50%':{boxShadow:'0 0 20px 4px rgba(223,60,60,0.25)'}}
        }
    }}
}
</script>
<style>
*{box-sizing:border-box}
body{overflow-x:hidden;-webkit-font-smoothing:antialiased;background-color:#f8fafc;color:#0f172a;transition:background-color .4s ease}
.dark body{background-color:#020202;color:#fff}
::-webkit-scrollbar{width:6px;height:6px}::-webkit-scrollbar-thumb{background:#cbd5e1;border-radius:3px}.dark ::-webkit-scrollbar-thumb{background:#1f1f1f}
.bg-grid-light{position:fixed;inset:0;background-image:radial-gradient(#e2e8f0 1px,transparent 1px);background-size:32px 32px;z-index:-2;mask-image:linear-gradient(to bottom,white,transparent)}
.dark .bg-grid-light{display:none}
.bg-grid-dark{display:none;position:fixed;inset:0;background-image:radial-gradient(rgba(255,255,255,.03) 1px,transparent 1px);background-size:32px 32px;z-index:-2;mask-image:radial-gradient(circle at 50% 50%,black,transparent 80%)}
.dark .bg-grid-dark{display:block}
.mesh-glow{position:fixed;border-radius:50%;filter:blur(120px);z-index:-1;opacity:.25;pointer-events:none}
.glass-nav{background:rgba(255,255,255,.88);backdrop-filter:blur(24px);border-bottom:1px solid rgba(0,0,0,.06)}
.dark .glass-nav{background:rgba(5,5,5,.8);border-bottom:1px solid rgba(255,255,255,.05)}
.sidebar{background:#fff;border-right:1px solid #e2e8f0;z-index:50}
.dark .sidebar{background:#050505;border-right:1px solid rgba(255,255,255,.05)}
.nav-link{display:flex;align-items:center;gap:.75rem;padding:.85rem 1.5rem;color:#64748b;font-weight:700;font-size:.875rem;transition:all .2s;border-radius:.75rem;margin:0 .75rem .25rem .75rem;border-left:3px solid transparent}
.dark .nav-link{color:#94a3b8}
.nav-link:hover{color:#df3c3c;background:#fdf2f2}.dark .nav-link:hover{color:#fff;background:rgba(223,60,60,.05)}
.nav-link.active{color:#df3c3c;background:#fdf2f2;border-left-color:#df3c3c}.dark .nav-link.active{color:#ea6d6d;background:rgba(223,60,60,.1);border-left-color:#df3c3c}
.glass-card{background:rgba(255,255,255,.82);backdrop-filter:blur(12px);border:1px solid rgba(0,0,0,.05);box-shadow:0 8px 24px -8px rgba(0,0,0,.06)}
.dark .glass-card{background:linear-gradient(145deg,rgba(18,18,18,.9),rgba(8,8,8,.95));border:1px solid rgba(255,255,255,.05);border-top:1px solid rgba(255,255,255,.09);box-shadow:0 20px 50px -12px rgba(0,0,0,.6)}
.input-field{background:#fff;border:1.5px solid #e2e8f0;color:#0f172a;transition:all .2s;width:100%}
.dark .input-field{background:rgba(255,255,255,.04);border:1.5px solid rgba(255,255,255,.1);color:#fff}
.input-field:focus{outline:none;border-color:#df3c3c;box-shadow:0 0 0 3px rgba(223,60,60,.12)}
.dark .input-field option{background:#111}
.btn-primary{background:linear-gradient(135deg,#df3c3c,#a61c1c);color:#fff;font-weight:800;border:none;cursor:pointer;transition:all .2s}
.btn-primary:hover{transform:translateY(-1px);box-shadow:0 8px 20px -4px rgba(223,60,60,.4)}
.ttl{width:46px;height:26px;background:#cbd5e1;border-radius:9999px;position:relative;cursor:pointer;display:flex;align-items:center}
.dark .ttl{background:#334155}
.ttb{width:20px;height:20px;background:#fff;border-radius:50%;position:absolute;top:3px;left:3px;transition:transform .3s;box-shadow:0 2px 4px rgba(0,0,0,.2)}
.dark .ttb{transform:translateX(20px);background:#0f172a}
/* Modal */
.modal-overlay{position:fixed;inset:0;background:rgba(0,0,0,.75);backdrop-filter:blur(8px);z-index:200;display:none;align-items:flex-start;justify-content:center;padding:2rem 1rem;overflow-y:auto}
.modal-overlay.open{display:flex;animation:fadeOverlay .2s ease-out}
@keyframes fadeOverlay{from{opacity:0}to{opacity:1}}
.modal-box{background:#fff;border-radius:1.5rem;width:100%;max-width:720px;box-shadow:0 30px 80px rgba(0,0,0,.4);margin:auto;animation:modalPop .3s cubic-bezier(.34,1.56,.64,1) forwards}
.dark .modal-box{background:#0d0d0d;border:1px solid rgba(255,255,255,.09)}
@keyframes modalPop{from{opacity:0;transform:scale(.94) translateY(10px)}to{opacity:1;transform:scale(1) translateY(0)}}
.fl{display:block;font-size:.68rem;font-weight:800;text-transform:uppercase;letter-spacing:.14em;color:#64748b;margin-bottom:.4rem}
.dark .fl{color:#94a3b8}
/* Job card badge */
.badge{display:inline-flex;align-items:center;padding:.2rem .65rem;border-radius:9999px;font-size:.65rem;font-weight:800;letter-spacing:.08em;text-transform:uppercase}
.badge-active{background:#dcfce7;color:#15803d}.dark .badge-active{background:rgba(21,128,61,.15);color:#4ade80}
.badge-inactive{background:#fee2e2;color:#b91c1c}.dark .badge-inactive{background:rgba(185,28,28,.15);color:#f87171}
/* Stat card hover */
.stat-card{transition:transform .2s,box-shadow .2s}.stat-card:hover{transform:translateY(-3px)}
/* Table row animation */
.job-row{animation:fadeInUp .35s ease-out forwards;opacity:0}
.job-row:nth-child(1){animation-delay:.05s}.job-row:nth-child(2){animation-delay:.1s}.job-row:nth-child(3){animation-delay:.15s}
.job-row:nth-child(4){animation-delay:.2s}.job-row:nth-child(5){animation-delay:.25s}.job-row:nth-child(n+6){animation-delay:.3s}
</style>
</head>
<body class="flex h-screen overflow-hidden selection:bg-pcte-500 selection:text-white">
<div class="bg-grid-light"></div>
<div class="bg-grid-dark"></div>
<div class="mesh-glow w-[600px] h-[600px] bg-pcte-500/15 top-[-150px] right-[-150px]"></div>
<div class="mesh-glow w-[400px] h-[400px] bg-blue-500/10 bottom-0 left-0"></div>

<!-- ── SIDEBAR ──────────────────────────────────────────────────────────── -->
<aside class="sidebar w-[280px] h-full hidden lg:flex flex-col justify-between shrink-0 shadow-2xl dark:shadow-none">
    <div class="flex-1 flex flex-col">
        <div class="h-24 flex items-center px-8 border-b border-slate-200 dark:border-white/5 shrink-0">
            <a href="index.php" class="flex items-center gap-3 w-full">
                <div class="w-10 h-10 rounded-xl bg-pcte-600 flex items-center justify-center shadow-lg shadow-pcte-500/40 shrink-0">
                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
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
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/></svg>
                Telemetry Overview
            </a>
            <a href="jobs.php" class="nav-link active rounded-xl">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                Job Matrix Management
            </a>
            <a href="users.php" class="nav-link rounded-xl">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                Student Roster
            </a>
            <a href="settings.php" class="nav-link rounded-xl">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/></svg>
                Platform Config & API Keys
            </a>
        </nav>
    </div>
    <div class="p-6 border-t border-slate-200 dark:border-white/5 shrink-0 bg-slate-50 dark:bg-[#050505]">
        <div class="flex items-center gap-3 mb-4 p-3 rounded-2xl bg-white dark:bg-white/5 border border-slate-200 dark:border-white/5 shadow-sm">
            <div class="w-10 h-10 rounded-xl bg-pcte-100 dark:bg-pcte-900/30 flex items-center justify-center font-black text-lg text-pcte-600 dark:text-pcte-400 shrink-0">A</div>
            <div class="overflow-hidden">
                <h4 class="text-sm font-bold text-slate-900 dark:text-white truncate"><?php echo htmlspecialchars($adminName); ?></h4>
                <p class="text-[9px] text-slate-500 uppercase font-black tracking-widest mt-0.5">Super Administrator</p>
            </div>
        </div>
        <a href="logout.php" class="flex items-center justify-center gap-2 w-full py-2.5 bg-slate-900 dark:bg-white text-white dark:text-black rounded-xl text-xs font-bold hover:scale-[1.02] transition-all shadow-md">Secure Admin Logout</a>
    </div>
</aside>

<!-- ── MAIN CONTENT ─────────────────────────────────────────────────────── -->
<div class="flex-1 flex flex-col h-screen overflow-hidden relative z-10">

    <!-- Top Nav -->
    <header class="h-20 flex items-center justify-between px-6 lg:px-10 border-b border-slate-200 dark:border-white/5 glass-nav shrink-0 sticky top-0 z-40">
        <div class="flex items-center gap-4">
            <!-- Mobile menu toggle -->
            <button id="mob-menu-btn" class="lg:hidden p-2 rounded-xl bg-slate-100 dark:bg-white/5 border border-slate-200 dark:border-white/10 text-slate-600 dark:text-gray-300">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
            </button>
            <h1 class="text-lg font-black text-slate-900 dark:text-white tracking-tight">Job Matrix Management</h1>
        </div>
        <div class="flex items-center gap-4">
            <!-- Search -->
            <div class="relative hidden sm:block">
                <svg class="w-4 h-4 absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                <input id="search-jobs" type="text" placeholder="Search jobs..." class="bg-slate-100 dark:bg-white/5 border border-slate-200 dark:border-white/10 rounded-full pl-10 pr-4 py-2 text-xs font-semibold text-slate-900 dark:text-white focus:outline-none focus:border-pcte-500 w-52 shadow-inner">
            </div>
            <!-- Add Job Button -->
            <button onclick="openAddModal()" class="btn-primary flex items-center gap-2 px-5 py-2.5 rounded-xl text-xs font-black uppercase tracking-wider shadow-lg shadow-pcte-500/20 animate-pulse-glow">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M12 4v16m8-8H4"/></svg>
                <span class="hidden sm:inline">New Job</span>
            </button>
            <!-- Theme Toggle -->
            <button id="theme-toggle" class="relative focus:outline-none hover:scale-105 transition-transform">
                <div class="ttl shadow-inner border border-slate-300 dark:border-white/10">
                    <div class="ttb">
                        <svg id="ttl-light" class="w-3.5 h-3.5 text-amber-500 hidden dark:block absolute" fill="currentColor" viewBox="0 0 20 20"><path d="M10 2a1 1 0 011 1v1a1 1 0 11-2 0V3a1 1 0 011-1zm4.22 4.22a1 1 0 011.415 0l.708.708a1 1 0 01-1.414 1.414l-.708-.708a1 1 0 010-1.414zm3.78 3.78a1 1 0 010 2h-1a1 1 0 110-2h1zm-4.22 4.22a1 1 0 010 1.415l-.708.708a1 1 0 01-1.414-1.414l.708-.708a1 1 0 011.414 0zM10 16a1 1 0 011 1v1a1 1 0 11-2 0v-1a1 1 0 011-1zm-4.22-4.22a1 1 0 01-1.415 0l-.708-.708a1 1 0 011.414-1.414l.708.708a1 1 0 010 1.414zm-3.78-3.78a1 1 0 010-2h1a1 1 0 110 2h-1zm4.22-4.22a1 1 0 010-1.415l.708-.708a1 1 0 011.414 1.414l.708.708a1 1 0 01-1.414 0zM10 5a5 5 0 100 10 5 5 0 000-10z"/></svg>
                        <svg id="ttl-dark" class="w-3.5 h-3.5 text-slate-700 block dark:hidden absolute" fill="currentColor" viewBox="0 0 20 20"><path d="M17.293 13.293A8 8 0 016.707 2.707a8.001 8.001 0 1010.586 10.586z"/></svg>
                    </div>
                </div>
            </button>
        </div>
    </header>

    <!-- Scrollable Body -->
    <div class="flex-1 overflow-y-auto p-6 lg:p-10 pb-20 relative z-10">
        <div class="max-w-[1500px] mx-auto space-y-8">

            <?php if(!empty($msg)): ?>
            <div class="p-4 rounded-2xl text-sm font-bold border flex items-center gap-3 shadow-sm animate-fade-in-up
                <?php echo $msgType==='success'
                    ? 'bg-green-50 dark:bg-green-900/20 border-green-200 dark:border-green-500/30 text-green-700 dark:text-green-400'
                    : 'bg-red-50 dark:bg-red-900/20 border-red-200 dark:border-red-500/30 text-red-700 dark:text-red-400'; ?>">
                <?php if($msgType==='success'): ?>
                    <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                <?php else: ?>
                    <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                <?php endif; ?>
                <?php echo htmlspecialchars($msg); ?>
            </div>
            <?php endif; ?>

            <!-- Stat Cards -->
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-5">
                <div class="glass-card p-6 rounded-2xl stat-card">
                    <p class="text-[10px] font-black uppercase tracking-[.2em] text-slate-400">Total Jobs</p>
                    <p class="text-4xl font-black text-slate-900 dark:text-white mt-3"><?php echo $totalJobs; ?></p>
                    <p class="text-[10px] text-blue-500 font-bold mt-1 uppercase">In the matrix</p>
                </div>
                <div class="glass-card p-6 rounded-2xl stat-card">
                    <p class="text-[10px] font-black uppercase tracking-[.2em] text-slate-400">Live Postings</p>
                    <p class="text-4xl font-black text-slate-900 dark:text-white mt-3"><?php echo $activeJobs; ?></p>
                    <p class="text-[10px] text-green-500 font-bold mt-1 uppercase">Visible to students</p>
                </div>
                <div class="glass-card p-6 rounded-2xl stat-card">
                    <p class="text-[10px] font-black uppercase tracking-[.2em] text-slate-400">Archived</p>
                    <p class="text-4xl font-black text-slate-900 dark:text-white mt-3"><?php echo $inactiveJobs; ?></p>
                    <p class="text-[10px] text-amber-500 font-bold mt-1 uppercase">Hidden from feed</p>
                </div>
                <div class="glass-card p-6 rounded-2xl stat-card">
                    <p class="text-[10px] font-black uppercase tracking-[.2em] text-slate-400">ATS Scans</p>
                    <p class="text-4xl font-black text-slate-900 dark:text-white mt-3"><?php echo $totalApps; ?></p>
                    <p class="text-[10px] text-purple-500 font-bold mt-1 uppercase">Total match runs</p>
                </div>
            </div>

            <!-- Jobs Table -->
            <div class="glass-card p-6 md:p-8 rounded-3xl">
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
                    <div>
                        <h3 class="text-lg font-black uppercase tracking-wider text-slate-900 dark:text-white">Job Postings Directory</h3>
                        <p class="text-xs text-slate-500 dark:text-gray-400 font-medium mt-0.5">Manage all job listings, toggle visibility, and update details.</p>
                    </div>
                    <button onclick="openAddModal()" class="btn-primary flex items-center gap-2 px-5 py-2.5 rounded-xl text-xs font-black uppercase tracking-wider shrink-0">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M12 4v16m8-8H4"/></svg>
                        Post New Job
                    </button>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm" id="jobs-table">
                        <thead class="border-b border-slate-200 dark:border-white/10 text-[10px] font-black uppercase tracking-widest text-slate-400">
                            <tr>
                                <th class="pb-3 pl-1">Job Title & Company</th>
                                <th class="pb-3">Type & Location</th>
                                <th class="pb-3">Salary</th>
                                <th class="pb-3">ATS Scans</th>
                                <th class="pb-3">Status</th>
                                <th class="pb-3 text-right pr-1">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-white/5 text-xs font-medium">
                            <?php if(empty($jobs)): ?>
                            <tr><td colspan="6" class="py-16 text-center text-slate-400 font-bold">
                                <div class="flex flex-col items-center gap-3">
                                    <svg class="w-12 h-12 text-slate-300 dark:text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                                    <span>No job postings yet. Click "Post New Job" to get started.</span>
                                </div>
                            </td></tr>
                            <?php else: foreach($jobs as $j):
                                $skills = json_decode($j['req_skills'] ?? '[]', true) ?: [];
                                // Encode the job data safely as a base64 JSON for the data attribute
                                $jobJson = base64_encode(json_encode($j));
                            ?>
                            <tr class="job-row hover:bg-slate-50 dark:hover:bg-white/[.02] transition-colors"
                                data-title="<?php echo strtolower(htmlspecialchars($j['title'])); ?>"
                                data-company="<?php echo strtolower(htmlspecialchars($j['company'])); ?>">
                                <td class="py-4 pl-1">
                                    <div class="flex items-center gap-3">
                                        <div class="w-9 h-9 rounded-xl bg-slate-100 dark:bg-white/5 flex items-center justify-center text-lg shrink-0 border border-slate-200 dark:border-white/10">
                                            <?php echo htmlspecialchars($j['logo'] ?? '💼'); ?>
                                        </div>
                                        <div>
                                            <div class="font-bold text-slate-900 dark:text-white job-title"><?php echo htmlspecialchars($j['title']); ?></div>
                                            <div class="text-[10px] text-slate-500 dark:text-gray-400 job-company"><?php echo htmlspecialchars($j['company']); ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="py-4">
                                    <div class="font-semibold text-slate-700 dark:text-gray-200"><?php echo htmlspecialchars($j['job_type']); ?></div>
                                    <div class="text-[10px] text-slate-400 mt-0.5"><?php echo htmlspecialchars($j['location']); ?></div>
                                </td>
                                <td class="py-4 font-semibold text-slate-700 dark:text-gray-300">
                                    <?php echo !empty($j['salary']) ? htmlspecialchars($j['salary']) : '<span class="text-slate-400 italic">N/A</span>'; ?>
                                </td>
                                <td class="py-4">
                                    <span class="font-bold text-slate-800 dark:text-white"><?php echo (int)$j['app_count']; ?></span>
                                    <span class="text-slate-400"> runs</span>
                                </td>
                                <td class="py-4">
                                    <?php if((int)$j['is_active']===1): ?>
                                        <span class="badge badge-active">● Live</span>
                                    <?php else: ?>
                                        <span class="badge badge-inactive">○ Archived</span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-4 text-right pr-1">
                                    <div class="flex items-center justify-end gap-2">
                                        <!-- Edit — safe data attribute approach -->
                                        <button type="button"
                                            data-job="<?php echo htmlspecialchars($jobJson); ?>"
                                            onclick="openEditModal(this.dataset.job)"
                                            class="px-3 py-1.5 bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400 hover:bg-blue-100 dark:hover:bg-blue-900/40 rounded-lg text-[10px] font-bold transition-colors cursor-pointer">
                                            Edit
                                        </button>
                                        <!-- Toggle -->
                                        <form method="POST" class="inline">
                                            <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
                                            <input type="hidden" name="action" value="toggle_job">
                                            <input type="hidden" name="job_id" value="<?php echo $j['id']; ?>">
                                            <input type="hidden" name="current_status" value="<?php echo $j['is_active']; ?>">
                                            <button type="submit" class="px-3 py-1.5 bg-slate-100 dark:bg-white/5 hover:bg-slate-200 dark:hover:bg-white/10 rounded-lg text-[10px] font-bold text-slate-600 dark:text-gray-300 transition-colors cursor-pointer">
                                                <?php echo (int)$j['is_active']===1 ? 'Archive' : 'Activate'; ?>
                                            </button>
                                        </form>
                                        <!-- Delete -->
                                        <form method="POST" class="inline" onsubmit="return confirm('Permanently delete this job and all its ATS scan records?');">
                                            <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
                                            <input type="hidden" name="action" value="delete_job">
                                            <input type="hidden" name="job_id" value="<?php echo $j['id']; ?>">
                                            <button type="submit" class="px-3 py-1.5 bg-red-50 dark:bg-red-900/20 text-red-600 dark:text-red-400 hover:bg-red-100 dark:hover:bg-red-900/40 rounded-lg text-[10px] font-bold transition-colors cursor-pointer">
                                                Delete
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div><!-- /max-w -->
    </div><!-- /scrollable -->
</div><!-- /main -->

<!-- ── ADD JOB MODAL ──────────────────────────────────────────────────────── -->
<div id="add-modal" class="modal-overlay" onclick="if(event.target===this)closeAddModal()">
    <div class="modal-box p-8 md:p-10">
        <div class="flex items-center justify-between mb-8">
            <div>
                <h2 class="text-xl font-black text-slate-900 dark:text-white">Post New Job</h2>
                <p class="text-xs text-slate-400 mt-1">Fill in the details below. Required fields are marked *</p>
            </div>
            <button onclick="closeAddModal()" class="w-9 h-9 rounded-xl bg-slate-100 dark:bg-white/5 flex items-center justify-center text-slate-500 hover:text-red-500 transition-colors cursor-pointer">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
        <form method="POST" class="space-y-5">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
            <input type="hidden" name="action" value="add_job">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                <div>
                    <label class="fl">Job Title *</label>
                    <input type="text" name="title" required placeholder="e.g. Full-Stack Developer" class="input-field rounded-xl px-4 py-3 text-sm font-semibold">
                </div>
                <div>
                    <label class="fl">Company *</label>
                    <input type="text" name="company" required placeholder="e.g. TechCorp India" class="input-field rounded-xl px-4 py-3 text-sm font-semibold">
                </div>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-5">
                <div>
                    <label class="fl">Job Type</label>
                    <select name="job_type" class="input-field rounded-xl px-4 py-3 text-sm font-semibold">
                        <option value="Full-time">Full-time</option>
                        <option value="Part-time">Part-time</option>
                        <option value="Internship">Internship</option>
                        <option value="Contract">Contract</option>
                        <option value="Freelance">Freelance</option>
                    </select>
                </div>
                <div>
                    <label class="fl">Location</label>
                    <input type="text" name="location" value="Remote" placeholder="e.g. Remote / Ludhiana" class="input-field rounded-xl px-4 py-3 text-sm font-semibold">
                </div>
                <div>
                    <label class="fl">Salary (Display)</label>
                    <input type="text" name="salary" placeholder="e.g. ₹4–6 LPA" class="input-field rounded-xl px-4 py-3 text-sm font-semibold">
                </div>
            </div>
            <div>
                <label class="fl">Required Skills (comma-separated) *</label>
                <input type="text" name="req_skills" required placeholder="PHP, MySQL, JavaScript, React" class="input-field rounded-xl px-4 py-3 text-sm font-semibold">
            </div>
            <div>
                <label class="fl">Job Description *</label>
                <textarea name="description" required rows="4" placeholder="Describe the role, responsibilities, and requirements..." class="input-field rounded-xl px-4 py-3 text-sm font-semibold resize-none"></textarea>
            </div>
            <div class="grid grid-cols-2 gap-5">
                <div>
                    <label class="fl">Logo / Emoji</label>
                    <input type="text" name="logo" value="💼" maxlength="4" class="input-field rounded-xl px-4 py-3 text-sm font-semibold">
                </div>
                <div class="flex items-end pb-1">
                    <label class="flex items-center gap-3 cursor-pointer select-none">
                        <input type="checkbox" name="is_active" checked class="w-5 h-5 rounded accent-pcte-600 cursor-pointer">
                        <span class="text-sm font-bold text-slate-700 dark:text-gray-300">Publish immediately (Live)</span>
                    </label>
                </div>
            </div>
            <div class="flex justify-end gap-3 pt-4 border-t border-slate-200 dark:border-white/10">
                <button type="button" onclick="closeAddModal()" class="px-6 py-3 rounded-xl bg-slate-100 dark:bg-white/5 text-slate-700 dark:text-gray-300 text-sm font-bold hover:bg-slate-200 dark:hover:bg-white/10 transition-colors cursor-pointer">Cancel</button>
                <button type="submit" class="btn-primary px-8 py-3 rounded-xl text-sm font-black uppercase tracking-wider shadow-lg">Post Job</button>
            </div>
        </form>
    </div>
</div>

<!-- ── EDIT JOB MODAL ─────────────────────────────────────────────────────── -->
<div id="edit-modal" class="modal-overlay" onclick="if(event.target===this)closeEditModal()">
    <div class="modal-box p-8 md:p-10">
        <div class="flex items-center justify-between mb-8">
            <div>
                <h2 class="text-xl font-black text-slate-900 dark:text-white">Edit Job Posting</h2>
                <p class="text-xs text-slate-400 mt-1">Update the job details below.</p>
            </div>
            <button onclick="closeEditModal()" class="w-9 h-9 rounded-xl bg-slate-100 dark:bg-white/5 flex items-center justify-center text-slate-500 hover:text-red-500 transition-colors cursor-pointer">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
        <form method="POST" class="space-y-5">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
            <input type="hidden" name="action" value="edit_job">
            <input type="hidden" name="job_id" id="edit-job-id">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                <div>
                    <label class="fl">Job Title *</label>
                    <input type="text" id="edit-title" name="title" required class="input-field rounded-xl px-4 py-3 text-sm font-semibold">
                </div>
                <div>
                    <label class="fl">Company *</label>
                    <input type="text" id="edit-company" name="company" required class="input-field rounded-xl px-4 py-3 text-sm font-semibold">
                </div>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-5">
                <div>
                    <label class="fl">Job Type</label>
                    <select id="edit-job-type" name="job_type" class="input-field rounded-xl px-4 py-3 text-sm font-semibold">
                        <option value="Full-time">Full-time</option>
                        <option value="Part-time">Part-time</option>
                        <option value="Internship">Internship</option>
                        <option value="Contract">Contract</option>
                        <option value="Freelance">Freelance</option>
                    </select>
                </div>
                <div>
                    <label class="fl">Location</label>
                    <input type="text" id="edit-location" name="location" class="input-field rounded-xl px-4 py-3 text-sm font-semibold">
                </div>
                <div>
                    <label class="fl">Salary (Display)</label>
                    <input type="text" id="edit-salary" name="salary" class="input-field rounded-xl px-4 py-3 text-sm font-semibold">
                </div>
            </div>
            <div>
                <label class="fl">Required Skills (comma-separated)</label>
                <input type="text" id="edit-skills" name="req_skills" class="input-field rounded-xl px-4 py-3 text-sm font-semibold">
            </div>
            <div>
                <label class="fl">Job Description *</label>
                <textarea id="edit-description" name="description" required rows="4" class="input-field rounded-xl px-4 py-3 text-sm font-semibold resize-none"></textarea>
            </div>
            <div class="grid grid-cols-2 gap-5">
                <div>
                    <label class="fl">Logo / Emoji</label>
                    <input type="text" id="edit-logo" name="logo" maxlength="4" class="input-field rounded-xl px-4 py-3 text-sm font-semibold">
                </div>
                <div class="flex items-end pb-1">
                    <label class="flex items-center gap-3 cursor-pointer select-none">
                        <input type="checkbox" id="edit-active" name="is_active" class="w-5 h-5 rounded accent-pcte-600 cursor-pointer">
                        <span class="text-sm font-bold text-slate-700 dark:text-gray-300">Live (visible to students)</span>
                    </label>
                </div>
            </div>
            <div class="flex justify-end gap-3 pt-4 border-t border-slate-200 dark:border-white/10">
                <button type="button" onclick="closeEditModal()" class="px-6 py-3 rounded-xl bg-slate-100 dark:bg-white/5 text-slate-700 dark:text-gray-300 text-sm font-bold hover:bg-slate-200 dark:hover:bg-white/10 transition-colors cursor-pointer">Cancel</button>
                <button type="submit" class="btn-primary px-8 py-3 rounded-xl text-sm font-black uppercase tracking-wider shadow-lg">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<!-- Mobile Sidebar Drawer -->
<div id="mob-sidebar" class="fixed inset-0 z-[300] hidden">
    <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" onclick="closeMobSidebar()"></div>
    <aside class="absolute left-0 top-0 h-full w-[280px] bg-white dark:bg-[#050505] border-r border-slate-200 dark:border-white/5 shadow-2xl flex flex-col justify-between transform -translate-x-full transition-transform duration-300" id="mob-sidebar-panel">
        <div class="flex-1 flex flex-col">
            <div class="h-20 flex items-center px-6 border-b border-slate-200 dark:border-white/5">
                <span class="text-xl font-black text-slate-900 dark:text-white">Admin<span class="text-pcte-600">Portal</span></span>
            </div>
            <nav class="mt-6 px-2 space-y-1">
                <a href="index.php" class="nav-link rounded-xl">Telemetry Overview</a>
                <a href="jobs.php" class="nav-link active rounded-xl">Job Matrix Management</a>
                <a href="users.php" class="nav-link rounded-xl">Student Roster</a>
                <a href="settings.php" class="nav-link rounded-xl">Platform Config & API Keys</a>
            </nav>
        </div>
        <div class="p-5 border-t border-slate-200 dark:border-white/5">
            <a href="logout.php" class="flex items-center justify-center w-full py-3 bg-slate-900 dark:bg-white text-white dark:text-black rounded-xl text-xs font-bold">Logout</a>
        </div>
    </aside>
</div>

<script>
// ── Theme ────────────────────────────────────────────────────────────────────
const themeBtn = document.getElementById('theme-toggle');
function syncTheme() {
    const d = document.documentElement.classList.contains('dark');
    localStorage.setItem('color-theme', d ? 'dark' : 'light');
}
if(themeBtn) themeBtn.addEventListener('click', () => { document.documentElement.classList.toggle('dark'); syncTheme(); });

// ── Mobile Sidebar ────────────────────────────────────────────────────────────
const mobBtn   = document.getElementById('mob-menu-btn');
const mobSb    = document.getElementById('mob-sidebar');
const mobPanel = document.getElementById('mob-sidebar-panel');
function closeMobSidebar() { mobPanel.classList.add('-translate-x-full'); setTimeout(()=>mobSb.classList.add('hidden'), 300); }
if(mobBtn) { mobBtn.addEventListener('click', () => { mobSb.classList.remove('hidden'); setTimeout(()=>mobPanel.classList.remove('-translate-x-full'), 10); }); }

// ── Add Modal ─────────────────────────────────────────────────────────────────
function openAddModal()  { document.getElementById('add-modal').classList.add('open'); document.body.style.overflow='hidden'; }
function closeAddModal() { document.getElementById('add-modal').classList.remove('open'); document.body.style.overflow=''; }

// ── Edit Modal ────────────────────────────────────────────────────────────────
function openEditModal(encodedData) {
    // Decode the base64-encoded JSON safely (no inline quote issues)
    let job;
    try { job = JSON.parse(atob(encodedData)); } catch(e) { console.error('Failed to decode job data', e); return; }

    document.getElementById('edit-job-id').value      = job.id;
    document.getElementById('edit-title').value       = job.title || '';
    document.getElementById('edit-company').value     = job.company || '';
    document.getElementById('edit-location').value    = job.location || '';
    document.getElementById('edit-salary').value      = job.salary || '';
    document.getElementById('edit-description').value = job.description || '';
    document.getElementById('edit-logo').value        = job.logo || '💼';
    document.getElementById('edit-active').checked    = job.is_active == 1;
    // Parse skills JSON array → comma-separated string
    try {
        const sk = job.req_skills ? JSON.parse(job.req_skills) : [];
        document.getElementById('edit-skills').value = Array.isArray(sk) ? sk.join(', ') : '';
    } catch(e) { document.getElementById('edit-skills').value = ''; }
    // Set job type dropdown
    const sel = document.getElementById('edit-job-type');
    for (let i = 0; i < sel.options.length; i++) {
        if (sel.options[i].value === job.job_type) { sel.selectedIndex = i; break; }
    }
    document.getElementById('edit-modal').classList.add('open');
    document.body.style.overflow = 'hidden';
}
function closeEditModal() { document.getElementById('edit-modal').classList.remove('open'); document.body.style.overflow=''; }

// ── Live Search ───────────────────────────────────────────────────────────────
const searchInput = document.getElementById('search-jobs');
if(searchInput) {
    searchInput.addEventListener('input', e => {
        const q = e.target.value.toLowerCase();
        document.querySelectorAll('#jobs-table tbody tr.job-row').forEach(row => {
            const t = (row.dataset.title + ' ' + row.dataset.company);
            row.style.display = t.includes(q) ? '' : 'none';
        });
    });
}

// ── Escape Key Closes Modals ─────────────────────────────────────────────────
document.addEventListener('keydown', e => { if(e.key==='Escape') { closeAddModal(); closeEditModal(); } });
</script>
</body>
</html>
