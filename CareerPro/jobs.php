<?php
declare(strict_types=1);
session_start();

if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }

require_once 'includes/db.php';
$db     = Database::getInstance()->getConnection();
$userId = (int)$_SESSION['user_id'];

try {
    $stmt = $db->prepare("SELECT name FROM users WHERE id = :id LIMIT 1");
    $stmt->execute(['id' => $userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) { session_destroy(); header("Location: login.php"); exit; }
    $userName  = $user['name'];
    $firstName = explode(' ', $userName)[0];
} catch (PDOException $e) { $userName = 'Student'; $firstName = 'Student'; }

try {
    $jobStmt = $db->query("SELECT * FROM jobs WHERE is_active = 1 ORDER BY created_at DESC");
    $dbJobs  = $jobStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $dbJobs = []; }

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
} catch (PDOException $e) { $isProfileReady = false; }
$jobCount = count($dbJobs);
?>
<!DOCTYPE html>
<html lang="en" class="scroll-smooth dark">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Job Opportunities | CareerPro Suite</title>
<script>if(localStorage.getItem('color-theme')==='dark'||(!('color-theme' in localStorage)&&window.matchMedia('(prefers-color-scheme:dark)').matches)){document.documentElement.classList.add('dark')}else{document.documentElement.classList.remove('dark')}</script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
<script src="https://cdn.tailwindcss.com"></script>
<script>
tailwind.config={darkMode:'class',theme:{extend:{
  fontFamily:{sans:['"Plus Jakarta Sans"','sans-serif']},
  colors:{
    pcte:{50:'#fdf2f2',100:'#fbe4e4',200:'#f8caca',400:'#ea6d6d',500:'#df3c3c',600:'#c82626',700:'#a61c1c',800:'#800000',900:'#701616'},
    dark:{950:'#020202',900:'#050505',800:'#0a0a0a',700:'#141414',600:'#1f1f1f'}
  }
}}}
</script>
<style>
*{box-sizing:border-box}
body{overflow-x:hidden;-webkit-font-smoothing:antialiased;background:#f8fafc;color:#0f172a;transition:background .4s,color .4s}
.dark body{background:#020202;color:#fff}
::-webkit-scrollbar{width:5px}::-webkit-scrollbar-thumb{background:#cbd5e1;border-radius:3px}.dark ::-webkit-scrollbar-thumb{background:#222}::-webkit-scrollbar-thumb:hover{background:#df3c3c}
.glass-nav{background:rgba(255,255,255,.9);backdrop-filter:blur(24px);border-bottom:1px solid rgba(0,0,0,.06)}
.dark .glass-nav{background:rgba(5,5,5,.88);border-bottom:1px solid rgba(255,255,255,.05)}
.sidebar{background:#fff;border-right:1px solid #e2e8f0}
.dark .sidebar{background:#050505;border-right:1px solid rgba(255,255,255,.05)}
.nav-link{display:flex;align-items:center;gap:.75rem;padding:.85rem 1.5rem;color:#64748b;font-weight:700;font-size:.875rem;transition:all .2s;border-left:3px solid transparent;border-radius:.75rem;margin:.125rem .75rem}
.dark .nav-link{color:#94a3b8}
.nav-link:hover{color:#df3c3c;background:#fdf2f2}.dark .nav-link:hover{color:#fff;background:rgba(223,60,60,.06)}
.nav-link.active{color:#df3c3c;background:#fdf2f2;border-left-color:#df3c3c}.dark .nav-link.active{color:#ea6d6d;background:rgba(223,60,60,.1);border-left-color:#df3c3c}
.glass-card{background:rgba(255,255,255,.88);backdrop-filter:blur(16px);border:1px solid rgba(0,0,0,.06);box-shadow:0 8px 24px -8px rgba(0,0,0,.08);transition:all .3s}
.dark .glass-card{background:linear-gradient(145deg,rgba(18,18,18,.94),rgba(8,8,8,.98));border:1px solid rgba(255,255,255,.06);border-top-color:rgba(255,255,255,.1);box-shadow:0 20px 50px -12px rgba(0,0,0,.7)}
.job-card{cursor:pointer;border:1.5px solid #e2e8f0;background:#fff;transition:all .22s cubic-bezier(.4,0,.2,1);border-radius:1.25rem;position:relative;overflow:hidden}
.dark .job-card{border-color:rgba(255,255,255,.07);background:#0a0a0a}
.job-card:hover{border-color:#df3c3c;transform:translateX(4px) translateY(-1px);box-shadow:0 8px 28px -6px rgba(223,60,60,.18)}
.job-card.active{border-color:#df3c3c;border-left-width:4px;background:#fdf2f2}
.dark .job-card.active{background:rgba(223,60,60,.07);border-left-width:4px}
.btn-primary{background:linear-gradient(135deg,#df3c3c,#a61c1c);position:relative;overflow:hidden;z-index:1;transition:all .3s;border:none;color:#fff;cursor:pointer}
.btn-primary::after{content:'';position:absolute;inset:0;background:linear-gradient(90deg,transparent,rgba(255,255,255,.15),transparent);transform:translateX(-100%);transition:transform .5s}
.btn-primary:hover:not(:disabled)::after{transform:translateX(100%)}
.btn-primary:hover:not(:disabled){box-shadow:0 12px 28px -6px rgba(223,60,60,.5);transform:translateY(-2px)}
.btn-primary:disabled{opacity:.45;cursor:not-allowed;transform:none}
.btn-ghost{background:transparent;border:1.5px solid rgba(0,0,0,.1);color:#374151;transition:all .2s;cursor:pointer;font-weight:700}
.dark .btn-ghost{border-color:rgba(255,255,255,.1);color:#e2e8f0}
.btn-ghost:hover{border-color:#df3c3c;background:rgba(223,60,60,.06);color:#df3c3c}
.progress-ring__circle{transition:stroke-dashoffset 1.8s cubic-bezier(.4,0,.2,1);transform:rotate(-90deg);transform-origin:50% 50%;stroke-linecap:round}
.filter-pill{padding:.35rem .9rem;border-radius:9999px;font-size:.68rem;font-weight:800;border:1.5px solid transparent;cursor:pointer;transition:all .2s;text-transform:uppercase;letter-spacing:.06em}
.filter-pill.active,.filter-pill:hover{border-color:#df3c3c;background:rgba(223,60,60,.09);color:#df3c3c}
.filter-pill:not(.active){border-color:#e2e8f0;background:#fff;color:#64748b}
.dark .filter-pill:not(.active){border-color:rgba(255,255,255,.08);background:rgba(255,255,255,.03);color:#94a3b8}
.ai-tab{padding:.55rem 1.1rem;font-size:.7rem;font-weight:800;border-radius:.75rem;cursor:pointer;transition:all .2s;letter-spacing:.05em;text-transform:uppercase;border:none;background:transparent}
.ai-tab.active{background:rgba(223,60,60,.12);color:#df3c3c}
.dark .ai-tab.active{background:rgba(223,60,60,.18);color:#ea6d6d}
.ai-tab:not(.active){color:#94a3b8}
.ai-tab:not(.active):hover{color:#df3c3c;background:rgba(223,60,60,.05)}
.ai-loading{display:flex;align-items:center;gap:.5rem;color:#94a3b8;font-size:.82rem;font-weight:600;padding:1.5rem;justify-content:center}
.ai-loading svg{animation:spin 1s linear infinite}
@keyframes spin{to{transform:rotate(360deg)}}
.score-badge{display:inline-flex;align-items:center;gap:.4rem;padding:.35rem .9rem;border-radius:9999px;font-size:.72rem;font-weight:800;text-transform:uppercase;letter-spacing:.06em;border:1.5px solid}
.theme-toggle-label{width:44px;height:24px;background:#cbd5e1;border-radius:9999px;position:relative;cursor:pointer;transition:background .3s;display:flex;align-items:center}
.dark .theme-toggle-label{background:#334155}
.theme-toggle-ball{width:18px;height:18px;background:#fff;border-radius:50%;position:absolute;top:3px;left:3px;transition:transform .3s cubic-bezier(.4,0,.2,1);box-shadow:0 2px 4px rgba(0,0,0,.2)}
.dark .theme-toggle-ball{transform:translateX(20px);background:#0f172a}
@keyframes fadeSlideIn{from{opacity:0;transform:translateY(8px)}to{opacity:1;transform:translateY(0)}}
.anim-in{animation:fadeSlideIn .35s ease-out forwards}
</style>
</head>
<body class="flex h-screen overflow-hidden selection:bg-pcte-500 selection:text-white">
<!-- SIDEBAR -->
<aside class="sidebar w-64 h-full hidden lg:flex flex-col justify-between shrink-0 z-50 shadow-xl dark:shadow-none">
  <div>
    <div class="h-20 flex items-center px-6 border-b border-slate-200 dark:border-white/5">
      <a href="index.php" class="flex items-center gap-3 group">
        <div class="w-9 h-9 rounded-xl bg-pcte-600 dark:bg-pcte-800 flex items-center justify-center shadow-lg group-hover:rotate-12 transition-transform duration-500">
          <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
        </div>
        <span class="text-xl font-black text-slate-900 dark:text-white">Career<span class="text-pcte-600 dark:text-pcte-500">Pro</span></span>
      </a>
    </div>
    <nav class="mt-6 px-2 space-y-1">
      <p class="px-4 text-[10px] font-black text-slate-400 dark:text-gray-600 uppercase tracking-widest mb-3">Workspace</p>
      <a href="dashboard.php" class="nav-link"><svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>Command Center</a>
      <a href="builder.php" class="nav-link"><svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>Resume Engine</a>
      <a href="jobs.php" class="nav-link active"><svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>Job Opportunities</a>
      <p class="px-4 text-[10px] font-black text-slate-400 dark:text-gray-600 uppercase tracking-widest mt-6 mb-3">Settings</p>
      <a href="profile.php" class="nav-link"><svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>Identity Management</a>
    </nav>
  </div>
  <div class="p-5 border-t border-slate-200 dark:border-white/5 bg-slate-50 dark:bg-[#050505]">
    <div class="flex items-center gap-3 mb-4 p-3 rounded-2xl bg-white dark:bg-dark-800 border border-slate-200 dark:border-white/5 shadow-sm">
      <div class="w-9 h-9 rounded-xl bg-pcte-100 dark:bg-pcte-900/30 flex items-center justify-center font-black text-pcte-600 dark:text-pcte-400 shrink-0"><?php echo strtoupper(substr($firstName,0,1)); ?></div>
      <div class="overflow-hidden">
        <h4 class="text-sm font-bold text-slate-900 dark:text-white truncate"><?php echo htmlspecialchars($userName); ?></h4>
        <p class="text-[9px] text-slate-500 uppercase font-black tracking-widest">Verified Student</p>
      </div>
    </div>
    <a href="api/auth.php?action=logout" class="flex items-center justify-center gap-2 w-full py-2.5 bg-slate-900 dark:bg-white text-white dark:text-black rounded-xl text-xs font-bold hover:scale-[1.02] transition-all shadow-md">
      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>Sign Out
    </a>
  </div>
</aside>

<!-- MAIN -->
<div class="flex-1 flex flex-col h-screen overflow-hidden">

  <!-- Header -->
  <header class="h-16 flex items-center justify-between px-5 lg:px-8 border-b border-slate-200 dark:border-white/5 glass-nav shrink-0 z-40">
    <div class="flex items-center gap-3">
      <button id="mobile-menu-btn" class="lg:hidden p-2 rounded-xl bg-slate-100 dark:bg-dark-800 text-slate-600 dark:text-gray-400">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
      </button>
      <div>
        <h1 class="text-base font-black text-slate-900 dark:text-white">Job Opportunities</h1>
        <p class="text-[10px] font-bold text-slate-500 uppercase tracking-widest hidden sm:flex items-center gap-1.5">
          <span class="w-1.5 h-1.5 rounded-full bg-green-500 animate-pulse inline-block"></span>
          <?php echo $jobCount; ?> live roles &bull; AI-powered matching
        </p>
      </div>
    </div>
    <div class="flex items-center gap-3">
      <div class="relative hidden sm:block">
        <svg class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
        <input type="text" id="search-jobs" placeholder="Search roles…" class="bg-slate-100 dark:bg-dark-800 border border-slate-200 dark:border-white/10 rounded-full pl-9 pr-4 py-2 text-sm font-semibold text-slate-900 dark:text-white focus:outline-none focus:border-pcte-500 w-52 transition-all">
      </div>
      <!-- Filter pills -->
      <div class="hidden md:flex items-center gap-1.5">
        <button class="filter-pill active" data-filter="all">All</button>
        <button class="filter-pill" data-filter="Full-time">Full-time</button>
        <button class="filter-pill" data-filter="Internship">Internship</button>
        <button class="filter-pill" data-filter="Remote">Remote</button>
      </div>
      <button id="theme-toggle" class="relative focus:outline-none">
        <div class="theme-toggle-label"><div class="theme-toggle-ball">
          <svg id="tl-light" class="w-2.5 h-2.5 text-amber-500 hidden dark:block absolute" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 5a5 5 0 100 10A5 5 0 0010 5z" clip-rule="evenodd"/></svg>
          <svg id="tl-dark" class="w-2.5 h-2.5 text-slate-700 block dark:hidden absolute" fill="currentColor" viewBox="0 0 20 20"><path d="M17.293 13.293A8 8 0 016.707 2.707a8.001 8.001 0 1010.586 10.586z"/></svg>
        </div></div>
      </button>
    </div>
  </header>

  <!-- Mobile menu -->
  <div id="mobile-menu" class="hidden absolute top-16 left-0 w-full glass-nav py-4 px-6 flex-col space-y-2 shadow-xl z-50 border-b border-slate-200 dark:border-white/10">
    <a href="dashboard.php" class="nav-link rounded-lg">Command Center</a>
    <a href="builder.php" class="nav-link rounded-lg">Resume Engine</a>
    <a href="jobs.php" class="nav-link active rounded-lg">Job Opportunities</a>
    <a href="profile.php" class="nav-link rounded-lg">Identity Settings</a>
    <div class="h-px bg-slate-200 dark:bg-white/10 my-2"></div>
    <a href="api/auth.php?action=logout" class="text-center text-red-500 font-bold py-2 bg-red-50 dark:bg-red-900/10 rounded-lg text-sm">Sign Out</a>
  </div>

  <!-- Two-pane layout -->
  <div class="flex flex-1 overflow-hidden flex-col md:flex-row">
    <!-- LEFT: Job List -->
    <div class="w-full md:w-[38%] lg:w-[34%] h-1/2 md:h-full overflow-y-auto border-b md:border-b-0 md:border-r border-slate-200 dark:border-white/5 bg-slate-50/80 dark:bg-[#070707]/80 p-4 flex flex-col gap-3">

      <?php if (!$isProfileReady): ?>
      <div class="p-4 rounded-2xl bg-amber-50 dark:bg-amber-900/10 border border-amber-200 dark:border-amber-500/20 flex items-start gap-3 shadow-sm">
        <svg class="w-5 h-5 text-amber-600 dark:text-amber-400 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
        <div>
          <p class="text-xs font-black text-amber-800 dark:text-amber-400 uppercase tracking-widest">Resume Required</p>
          <p class="text-[11px] text-slate-600 dark:text-gray-400 mt-0.5">Build your resume to unlock AI scanning. <a href="builder.php" class="font-bold underline text-pcte-600">Open Builder →</a></p>
        </div>
      </div>
      <?php endif; ?>

      <div class="flex items-center justify-between px-1">
        <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Live Roles (<span id="visible-count"><?php echo $jobCount; ?></span>)</p>
        <span class="text-[9px] font-bold text-pcte-500 uppercase tracking-wider">↑ AI Sorted</span>
      </div>

      <?php if (empty($dbJobs)): ?>
      <div class="flex flex-col items-center justify-center py-20 opacity-50 gap-3">
        <svg class="w-12 h-12 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
        <p class="text-xs font-black uppercase tracking-widest text-slate-500">No Roles Posted Yet</p>
      </div>
      <?php else: ?>
      <div id="job-list" class="flex flex-col gap-2.5">
        <?php foreach ($dbJobs as $i => $job):
          $skills = json_decode($job['req_skills'] ?? '[]', true) ?: [];
          $skillPreview = array_slice($skills, 0, 3);
        ?>
        <div onclick="selectJob(<?php echo htmlspecialchars(json_encode($job)); ?>, this)"
             class="job-card p-4 flex gap-3.5 group"
             data-title="<?php echo strtolower(htmlspecialchars($job['title'])); ?>"
             data-company="<?php echo strtolower(htmlspecialchars($job['company'])); ?>"
             data-type="<?php echo htmlspecialchars($job['job_type']); ?>"
             data-location="<?php echo htmlspecialchars($job['location']); ?>"
             style="animation:fadeSlideIn .3s ease-out <?php echo $i*0.05; ?>s both">
          <div class="w-11 h-11 rounded-xl border border-slate-200 dark:border-white/10 flex items-center justify-center font-black text-lg shrink-0 bg-gradient-to-br from-pcte-50 to-white dark:from-dark-800 dark:to-dark-900 text-pcte-600 dark:text-pcte-400 group-hover:scale-110 transition-transform shadow-sm">
            <?php echo htmlspecialchars($job['logo'] ?: strtoupper(substr($job['company'],0,1))); ?>
          </div>
          <div class="flex-1 min-w-0">
            <h3 class="text-sm font-bold text-slate-900 dark:text-white truncate group-hover:text-pcte-600 dark:group-hover:text-pcte-400 transition-colors"><?php echo htmlspecialchars($job['title']); ?></h3>
            <p class="text-[11px] font-semibold text-slate-500 dark:text-gray-400 truncate mb-1.5"><?php echo htmlspecialchars($job['company']); ?></p>
            <div class="flex flex-wrap gap-1">
              <span class="bg-slate-100 dark:bg-dark-700 text-slate-600 dark:text-gray-400 px-2 py-0.5 rounded-md text-[9px] font-black uppercase tracking-wider"><?php echo htmlspecialchars($job['location']); ?></span>
              <span class="bg-pcte-50 dark:bg-pcte-900/25 text-pcte-600 dark:text-pcte-400 px-2 py-0.5 rounded-md text-[9px] font-black uppercase tracking-wider"><?php echo htmlspecialchars($job['job_type']); ?></span>
              <?php if (!empty($job['salary'])): ?>
              <span class="bg-green-50 dark:bg-green-900/20 text-green-700 dark:text-green-400 px-2 py-0.5 rounded-md text-[9px] font-black uppercase tracking-wider"><?php echo htmlspecialchars($job['salary']); ?></span>
              <?php endif; ?>
            </div>
            <?php if (!empty($skillPreview)): ?>
            <div class="flex flex-wrap gap-1 mt-1.5">
              <?php foreach ($skillPreview as $sk): ?>
              <span class="text-[9px] font-bold text-slate-400 dark:text-gray-600 bg-slate-50 dark:bg-white/5 px-1.5 py-0.5 rounded border border-slate-200 dark:border-white/5"><?php echo htmlspecialchars($sk); ?></span>
              <?php endforeach; ?>
              <?php if (count($skills) > 3): ?>
              <span class="text-[9px] font-bold text-pcte-400">+<?php echo count($skills)-3; ?> more</span>
              <?php endif; ?>
            </div>
            <?php endif; ?>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
      <div id="no-results" class="hidden text-center py-12 opacity-50">
        <p class="text-xs font-black uppercase tracking-widest text-slate-500">No matches found</p>
      </div>
      <?php endif; ?>
    </div>
    <!-- RIGHT: Job Detail -->
    <div class="flex-1 h-1/2 md:h-full overflow-y-auto bg-white/60 dark:bg-[#050505]/70" id="detail-pane">

      <!-- Empty state -->
      <div id="empty-state" class="h-full flex flex-col items-center justify-center text-center p-8 gap-5">
        <div class="w-28 h-28 bg-white dark:bg-dark-800 rounded-3xl border border-slate-200 dark:border-white/5 flex items-center justify-center shadow-2xl mx-auto relative">
          <div class="absolute inset-0 rounded-3xl bg-gradient-to-br from-pcte-500/10 to-transparent"></div>
          <svg class="w-14 h-14 text-slate-300 dark:text-gray-700" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
        </div>
        <div>
          <h3 class="text-2xl font-black text-slate-800 dark:text-white mb-2">Select a Role</h3>
          <p class="text-slate-500 dark:text-gray-400 text-sm max-w-xs leading-relaxed">Click any job to view details, run ATS scan, generate a cover letter, and get AI interview prep.</p>
        </div>
        <div class="flex flex-wrap gap-2 justify-center mt-2">
          <span class="px-3 py-1.5 rounded-full bg-pcte-50 dark:bg-pcte-900/20 text-pcte-600 dark:text-pcte-400 text-xs font-bold border border-pcte-100 dark:border-pcte-500/20">🎯 ATS Scanner</span>
          <span class="px-3 py-1.5 rounded-full bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400 text-xs font-bold border border-blue-100 dark:border-blue-500/20">🤖 AI Match</span>
          <span class="px-3 py-1.5 rounded-full bg-green-50 dark:bg-green-900/20 text-green-700 dark:text-green-400 text-xs font-bold border border-green-100 dark:border-green-500/20">📄 Cover Letter</span>
          <span class="px-3 py-1.5 rounded-full bg-amber-50 dark:bg-amber-900/20 text-amber-700 dark:text-amber-400 text-xs font-bold border border-amber-100 dark:border-amber-500/20">🎤 Interview Prep</span>
        </div>
      </div>

      <!-- Active job detail -->
      <div id="active-job" class="hidden p-6 lg:p-10 space-y-6 anim-in">

        <!-- Job Header -->
        <div class="flex flex-col sm:flex-row justify-between items-start gap-5 pb-6 border-b border-slate-200 dark:border-white/10">
          <div class="flex gap-4 items-start flex-1 min-w-0">
            <div id="d-logo" class="w-16 h-16 rounded-2xl border border-slate-200 dark:border-white/10 flex items-center justify-center text-3xl font-black shrink-0 bg-gradient-to-br from-pcte-50 to-white dark:from-dark-800 dark:to-dark-900 text-pcte-600 dark:text-pcte-400 shadow-lg"></div>
            <div class="min-w-0">
              <h2 id="d-title" class="text-2xl lg:text-3xl font-black text-slate-900 dark:text-white tracking-tight leading-tight mb-1"></h2>
              <p id="d-company" class="text-pcte-600 dark:text-pcte-400 font-bold text-base mb-2"></p>
              <div class="flex flex-wrap gap-2">
                <span id="d-loc" class="bg-slate-100 dark:bg-dark-800 text-slate-600 dark:text-gray-400 px-3 py-1 rounded-lg border border-slate-200 dark:border-white/5 text-[10px] font-black uppercase tracking-widest"></span>
                <span id="d-type" class="bg-pcte-50 dark:bg-pcte-900/20 text-pcte-700 dark:text-pcte-300 px-3 py-1 rounded-lg border border-pcte-100 dark:border-pcte-500/20 text-[10px] font-black uppercase tracking-widest"></span>
                <span id="d-salary" class="bg-green-50 dark:bg-green-900/15 text-green-700 dark:text-green-400 px-3 py-1 rounded-lg border border-green-200 dark:border-green-500/20 text-[10px] font-black uppercase tracking-widest"></span>
              </div>
            </div>
          </div>
          <button id="scan-btn" onclick="runAtsScan()" <?php echo !$isProfileReady ? 'disabled title="Complete resume first"' : ''; ?>
            class="btn-primary px-7 py-3.5 rounded-2xl font-black uppercase text-xs tracking-widest shadow-xl flex items-center gap-2 shrink-0">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
            Run ATS Scan
          </button>
        </div>

        <!-- Description + Skills -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
          <div class="lg:col-span-2 space-y-4">
            <div>
              <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Job Description</p>
              <p id="d-desc" class="text-slate-700 dark:text-gray-300 leading-relaxed text-sm whitespace-pre-wrap"></p>
            </div>
          </div>
          <div id="req-skills-block" class="hidden">
            <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Required Skills</p>
            <div id="req-skills" class="flex flex-wrap gap-2"></div>
          </div>
        </div>

        <!-- ATS Scanning spinner -->
        <div id="ats-scanning" class="hidden glass-card rounded-3xl p-8 text-center">
          <div class="w-12 h-12 rounded-full border-4 border-pcte-500 border-t-transparent animate-spin mx-auto mb-3"></div>
          <p class="text-sm font-bold text-slate-700 dark:text-gray-300">Running ATS analysis…</p>
          <p class="text-xs text-slate-400 mt-1">Comparing your resume against job requirements</p>
        </div>

        <!-- ATS Result -->
        <div id="ats-result" class="hidden glass-card rounded-3xl p-7 space-y-5">
          <div class="flex items-center justify-between mb-1">
            <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">ATS Compatibility Score</p>
            <span id="ats-grade" class="score-badge"></span>
          </div>
          <div class="flex flex-col sm:flex-row items-center gap-7">
            <div class="relative w-36 h-36 shrink-0">
              <svg class="w-full h-full" viewBox="0 0 100 100">
                <circle class="text-slate-100 dark:text-dark-800 stroke-current" stroke-width="8" cx="50" cy="50" r="40" fill="transparent"/>
                <circle id="score-ring" class="progress-ring__circle stroke-current" stroke-width="10" cx="50" cy="50" r="40" fill="transparent" stroke-dasharray="251.33" stroke-dashoffset="251.33" style="stroke:#df3c3c"/>
              </svg>
              <div class="absolute inset-0 flex flex-col items-center justify-center">
                <span id="score-num" class="text-4xl font-black text-slate-900 dark:text-white leading-none">0</span>
                <span class="text-[9px] font-black text-slate-400 uppercase tracking-widest">/ 100</span>
              </div>
            </div>
            <div class="flex-1 space-y-3 w-full">
              <p id="ats-msg" class="text-slate-600 dark:text-gray-400 text-sm leading-relaxed"></p>
              <div class="grid grid-cols-2 gap-3">
                <div class="bg-green-50 dark:bg-green-900/10 rounded-xl p-3 border border-green-200 dark:border-green-500/20">
                  <p class="text-[9px] font-black text-green-600 uppercase tracking-widest mb-2">✓ Matched Skills</p>
                  <div id="ats-matched" class="flex flex-wrap gap-1"></div>
                </div>
                <div class="bg-red-50 dark:bg-red-900/10 rounded-xl p-3 border border-red-200 dark:border-red-500/20">
                  <p class="text-[9px] font-black text-red-500 uppercase tracking-widest mb-2">✗ Missing Skills</p>
                  <div id="ats-missing" class="flex flex-wrap gap-1"></div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- AI Features Tabs -->
        <div id="ai-section" class="hidden glass-card rounded-3xl overflow-hidden">
          <div class="border-b border-slate-200 dark:border-white/10 px-6 py-3 flex items-center gap-1 bg-slate-50/50 dark:bg-white/[.02]">
            <span class="text-[9px] font-black text-slate-400 uppercase tracking-widest mr-3">AI Tools</span>
            <button class="ai-tab active" onclick="switchAiTab('match')">🤖 AI Match</button>
            <button class="ai-tab" onclick="switchAiTab('cover')">📄 Cover Letter</button>
            <button class="ai-tab" onclick="switchAiTab('interview')">🎤 Interview Prep</button>
          </div>

          <!-- Tab: AI Match -->
          <div id="tab-match" class="p-6">
            <div class="flex items-center justify-between mb-4">
              <div>
                <h3 class="font-black text-slate-900 dark:text-white text-base">Deep AI Compatibility Analysis</h3>
                <p class="text-xs text-slate-500 mt-0.5">Gemini AI analyzes your resume vs this job in detail</p>
              </div>
              <button onclick="runAiMatch()" <?php echo !$isProfileReady ? 'disabled' : ''; ?>
                class="btn-primary px-5 py-2.5 rounded-xl text-xs font-black uppercase tracking-wider flex items-center gap-2 shadow-lg">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                Analyse Now
              </button>
            </div>
            <div id="match-result" class="text-slate-500 dark:text-gray-500 text-sm text-center py-8">
              Click "Analyse Now" to get a deep AI compatibility report for this role.
            </div>
          </div>

          <!-- Tab: Cover Letter -->
          <div id="tab-cover" class="hidden p-6">
            <div class="flex items-center justify-between mb-4">
              <div>
                <h3 class="font-black text-slate-900 dark:text-white text-base">AI Cover Letter Generator</h3>
                <p class="text-xs text-slate-500 mt-0.5">Tailored to this job using your resume data</p>
              </div>
              <div class="flex items-center gap-2">
                <select id="cover-tone" class="text-xs font-bold bg-slate-100 dark:bg-dark-800 border border-slate-200 dark:border-white/10 rounded-xl px-3 py-2 text-slate-700 dark:text-gray-300 focus:outline-none focus:border-pcte-500">
                  <option value="professional">Professional</option>
                  <option value="confident">Confident</option>
                  <option value="enthusiastic">Enthusiastic</option>
                </select>
                <button onclick="generateCoverLetter()" <?php echo !$isProfileReady ? 'disabled' : ''; ?>
                  class="btn-primary px-5 py-2.5 rounded-xl text-xs font-black uppercase tracking-wider flex items-center gap-2 shadow-lg">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                  Generate
                </button>
              </div>
            </div>
            <div id="cover-result">
              <p class="text-slate-500 dark:text-gray-500 text-sm text-center py-8">Generate a personalized cover letter tailored to this role and your resume.</p>
            </div>
          </div>

          <!-- Tab: Interview Prep -->
          <div id="tab-interview" class="hidden p-6">
            <div class="flex items-center justify-between mb-4">
              <div>
                <h3 class="font-black text-slate-900 dark:text-white text-base">AI Interview Preparation</h3>
                <p class="text-xs text-slate-500 mt-0.5">7 targeted questions with model answers for this role</p>
              </div>
              <div class="flex items-center gap-2">
                <select id="interview-type" class="text-xs font-bold bg-slate-100 dark:bg-dark-800 border border-slate-200 dark:border-white/10 rounded-xl px-3 py-2 text-slate-700 dark:text-gray-300 focus:outline-none focus:border-pcte-500">
                  <option value="mixed">Mixed (HR + Tech)</option>
                  <option value="hr">HR / Behavioral</option>
                  <option value="technical">Technical Only</option>
                </select>
                <button onclick="generateInterviewPrep()" <?php echo !$isProfileReady ? 'disabled' : ''; ?>
                  class="btn-primary px-5 py-2.5 rounded-xl text-xs font-black uppercase tracking-wider flex items-center gap-2 shadow-lg">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/></svg>
                  Prepare Me
                </button>
              </div>
            </div>
            <div id="interview-result">
              <p class="text-slate-500 dark:text-gray-500 text-sm text-center py-8">Get role-specific interview questions and model answers powered by Gemini AI.</p>
            </div>
          </div>
        </div>

      </div><!-- /active-job -->
    </div><!-- /detail-pane -->
  </div><!-- /two-pane -->
</div><!-- /main -->

<?php if (file_exists('chatbot.php')) include 'chatbot.php'; ?>
<script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
<script>
AOS.init({once:true,duration:500});

// Theme toggle
document.getElementById('theme-toggle').addEventListener('click',()=>{
  const d=document.documentElement.classList.toggle('dark');
  localStorage.setItem('color-theme',d?'dark':'light');
});

// Mobile menu
document.getElementById('mobile-menu-btn').addEventListener('click',()=>{
  const m=document.getElementById('mobile-menu');
  m.classList.toggle('hidden');m.classList.toggle('flex');
});

// Search
document.getElementById('search-jobs').addEventListener('input',function(){
  const q=this.value.toLowerCase();
  let n=0;
  document.querySelectorAll('#job-list .job-card').forEach(c=>{
    const match=(c.dataset.title+' '+c.dataset.company).includes(q);
    c.style.display=match?'':'none';
    if(match)n++;
  });
  document.getElementById('visible-count').textContent=n;
  document.getElementById('no-results').classList.toggle('hidden',n>0);
});

// Filter pills
document.querySelectorAll('.filter-pill').forEach(pill=>{
  pill.addEventListener('click',function(){
    document.querySelectorAll('.filter-pill').forEach(p=>p.classList.remove('active'));
    this.classList.add('active');
    const f=this.dataset.filter;
    let n=0;
    document.querySelectorAll('#job-list .job-card').forEach(c=>{
      const show=f==='all'||c.dataset.type===f||c.dataset.location.toLowerCase().includes(f.toLowerCase());
      c.style.display=show?'':'none';
      if(show)n++;
    });
    document.getElementById('visible-count').textContent=n;
    document.getElementById('no-results').classList.toggle('hidden',n>0);
  });
});

// State
let currentJob=null;

function selectJob(job,el){
  document.querySelectorAll('.job-card').forEach(c=>c.classList.remove('active'));
  el.classList.add('active');
  currentJob=job;

  // Show detail
  document.getElementById('empty-state').classList.add('hidden');
  const aj=document.getElementById('active-job');
  aj.classList.remove('hidden');
  aj.classList.add('anim-in');

  // Reset AI panels
  document.getElementById('ats-result').classList.add('hidden');
  document.getElementById('ats-scanning').classList.add('hidden');
  document.getElementById('ai-section').classList.add('hidden');
  document.getElementById('match-result').innerHTML='<p class="text-slate-500 dark:text-gray-500 text-sm text-center py-8">Click "Analyse Now" to get a deep AI compatibility report.</p>';
  document.getElementById('cover-result').innerHTML='<p class="text-slate-500 dark:text-gray-500 text-sm text-center py-8">Generate a personalized cover letter tailored to this role.</p>';
  document.getElementById('interview-result').innerHTML='<p class="text-slate-500 dark:text-gray-500 text-sm text-center py-8">Get role-specific interview questions with model answers.</p>';

  // Populate
  document.getElementById('d-logo').textContent=job.logo||job.company[0];
  document.getElementById('d-title').textContent=job.title;
  document.getElementById('d-company').textContent=job.company;
  document.getElementById('d-loc').textContent=job.location;
  document.getElementById('d-type').textContent=job.job_type;
  document.getElementById('d-salary').textContent=job.salary||'Salary TBD';
  document.getElementById('d-desc').textContent=job.description||'No description provided.';

  // Skills
  const sb=document.getElementById('req-skills-block');
  const sd=document.getElementById('req-skills');
  let skills=[];
  try{skills=JSON.parse(job.req_skills||'[]');}catch(e){}
  if(skills.length>0){
    sd.innerHTML=skills.map(s=>`<span class="px-3 py-1 rounded-full text-[10px] font-bold bg-pcte-50 dark:bg-pcte-900/20 text-pcte-600 dark:text-pcte-400 border border-pcte-100 dark:border-pcte-500/20">${s}</span>`).join('');
    sb.classList.remove('hidden');
  }else{sb.classList.add('hidden');}

  // Scroll right pane to top
  document.getElementById('detail-pane').scrollTop=0;
}

// AI tab switcher
function switchAiTab(tab){
  ['match','cover','interview'].forEach(t=>{
    document.getElementById('tab-'+t).classList.add('hidden');
    document.querySelectorAll('.ai-tab').forEach((b,i)=>{
      if(['match','cover','interview'][i]===t)b.classList.remove('active');
    });
  });
  document.getElementById('tab-'+tab).classList.remove('hidden');
  document.querySelectorAll('.ai-tab').forEach(b=>{
    if(b.textContent.toLowerCase().includes(tab.substring(0,3)))b.classList.add('active');
  });
}

// Helper: AI API call
async function callAI(action,extra={}){
  const base=(()=>{const l=window.location;const p=l.pathname.split('/');p.pop();return l.origin+p.join('/')+'/api/ai-features.php';})();
  const r=await fetch(base,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action,...extra})});
  return r.json();
}

// ATS Scan
async function runAtsScan(){
  if(!currentJob)return;
  document.getElementById('ats-result').classList.add('hidden');
  document.getElementById('ats-scanning').classList.remove('hidden');
  try{
    const base=(()=>{const l=window.location;const p=l.pathname.split('/');p.pop();return l.origin+p.join('/')+'/api/matcher-api.php';})();
    const resp=await fetch(base,{method:'POST',headers:{'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest'},
      body:JSON.stringify({job_id:currentJob.id,job_description:document.getElementById('d-desc').textContent})});
    const data=await resp.json();
    document.getElementById('ats-scanning').classList.add('hidden');
    if(data.status!=='success'){showToast(data.message||'Scan failed','error');return;}

    const d=data.data;const score=d.overall_score||0;
    // Ring
    const ring=document.getElementById('score-ring');
    const offset=251.33-(score/100)*251.33;
    ring.style.strokeDashoffset=offset;
    ring.style.stroke=score>=80?'#22c55e':score>=60?'#3b82f6':score>=40?'#f59e0b':'#ef4444';
    // Count-up
    const ne=document.getElementById('score-num');let n=0;
    const iv=setInterval(()=>{n=Math.min(n+2,score);ne.textContent=n;if(n>=score)clearInterval(iv);},18);
    // Grade
    const grade=document.getElementById('ats-grade');
    const gc={'Excellent Match':'bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-400 border-green-300 dark:border-green-500/30',
      'Good Match':'bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-400 border-blue-300 dark:border-blue-500/30',
      'Average Match':'bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-400 border-amber-300 dark:border-amber-500/30',
      'Poor Match':'bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-400 border-red-300 dark:border-red-500/30'};
    grade.textContent=d.status||'Analysed';
    grade.className='score-badge '+(gc[d.status]||gc['Average Match']);
    document.getElementById('ats-msg').textContent=score>=80?'Excellent! Your resume aligns very well.':score>=60?'Good match. A few tweaks will help.':score>=40?'Moderate match — add more relevant skills.':'Low match. Consider upskilling before applying.';
    const chip=(t,cl)=>`<span class="px-2 py-0.5 rounded-md text-[9px] font-bold ${cl}">${t}</span>`;
    document.getElementById('ats-matched').innerHTML=(d.hard_skills?.matched||[]).map(s=>chip(s,'bg-green-100 dark:bg-green-900/20 text-green-700 dark:text-green-400')).join('');
    document.getElementById('ats-missing').innerHTML=(d.hard_skills?.missing||[]).map(s=>chip(s,'bg-red-100 dark:bg-red-900/20 text-red-700 dark:text-red-400')).join('');
    document.getElementById('ats-result').classList.remove('hidden');
    document.getElementById('ai-section').classList.remove('hidden');
  }catch(e){document.getElementById('ats-scanning').classList.add('hidden');showToast('Scan failed: '+e.message,'error');}
}

// AI Match analysis
async function runAiMatch(){
  const r=document.getElementById('match-result');
  r.innerHTML=loadingHtml('Running deep AI analysis…');
  try{
    let skills=[];try{skills=JSON.parse(currentJob.req_skills||'[]');}catch(e){}
    const res=await callAI('job_match_ai',{job_id:currentJob.id,job_title:currentJob.title,job_desc:currentJob.description,req_skills:skills});
    if(res.status!=='success'){r.innerHTML=errorHtml(res.message);return;}
    const d=res.data;
    if(d.raw){r.innerHTML=`<pre class="text-xs text-slate-600 dark:text-gray-400 whitespace-pre-wrap leading-relaxed">${escHtml(d.raw)}</pre>`;return;}
    const scoreColor=d.match_color==='green'?'text-green-600 dark:text-green-400':d.match_color==='blue'?'text-blue-600 dark:text-blue-400':d.match_color==='amber'?'text-amber-600 dark:text-amber-400':'text-red-500';
    r.innerHTML=`
      <div class="space-y-4">
        <div class="flex items-center gap-4 p-4 rounded-2xl bg-slate-50 dark:bg-white/[.03] border border-slate-200 dark:border-white/8">
          <div class="text-5xl font-black ${scoreColor}">${d.match_score}<span class="text-2xl">%</span></div>
          <div>
            <p class="font-black text-slate-900 dark:text-white">${d.match_label||''}</p>
            <p class="text-xs text-slate-500 mt-0.5">${d.verdict||''}</p>
            <p class="text-xs font-bold mt-1 ${d.should_apply?'text-green-600 dark:text-green-400':'text-amber-600 dark:text-amber-400'}">${d.should_apply?'✅ Recommended to apply now':'⚠️ '+d.apply_reasoning}</p>
          </div>
        </div>
        ${(d.application_tips||[]).length?`<div><p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Application Tips</p><ul class="space-y-1.5">${(d.application_tips||[]).map(t=>`<li class="text-xs text-slate-600 dark:text-gray-400 flex items-start gap-2"><span class="text-pcte-500 mt-0.5 shrink-0">→</span>${escHtml(t)}</li>`).join('')}</ul></div>`:''}
        ${(d.resume_gaps||[]).length?`<div><p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Resume Gaps</p><div class="space-y-2">${(d.resume_gaps||[]).map(g=>`<div class="flex items-start gap-3 p-3 rounded-xl bg-amber-50 dark:bg-amber-900/10 border border-amber-200 dark:border-amber-500/20"><span class="text-[9px] font-black uppercase ${g.importance==='must-have'?'bg-red-100 dark:bg-red-900/30 text-red-600 dark:text-red-400 px-2 py-0.5 rounded-full':' bg-amber-100 dark:bg-amber-900/30 text-amber-600 dark:text-amber-400 px-2 py-0.5 rounded-full'}">${g.importance}</span><div><p class="text-xs font-bold text-slate-800 dark:text-white">${escHtml(g.gap)}</p><p class="text-[10px] text-slate-500 mt-0.5">${escHtml(g.fix)}</p></div></div>`).join('')}</div></div>`:''}
      </div>`;
  }catch(e){r.innerHTML=errorHtml('AI service unavailable. Try again shortly.');}
}

// Cover letter
async function generateCoverLetter(){
  const r=document.getElementById('cover-result');
  r.innerHTML=loadingHtml('Writing your cover letter…');
  const tone=document.getElementById('cover-tone').value;
  try{
    const res=await callAI('cover_letter',{job_title:currentJob.title,company:currentJob.company,job_desc:currentJob.description,tone});
    if(res.status!=='success'){r.innerHTML=errorHtml(res.message);return;}
    r.innerHTML=`
      <div class="relative">
        <div class="bg-slate-50 dark:bg-white/[.02] rounded-2xl p-5 border border-slate-200 dark:border-white/8 text-sm text-slate-700 dark:text-gray-300 leading-relaxed whitespace-pre-wrap font-mono text-xs">${escHtml(res.data)}</div>
        <button onclick="copyText(this,'${btoa('cover-result')}')" class="mt-3 btn-ghost px-4 py-2 rounded-xl text-xs flex items-center gap-2">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
          Copy to Clipboard
        </button>
      </div>`;
  }catch(e){r.innerHTML=errorHtml('AI service unavailable.');}
}

// Interview prep
async function generateInterviewPrep(){
  const r=document.getElementById('interview-result');
  r.innerHTML=loadingHtml('Generating interview questions…');
  const type=document.getElementById('interview-type').value;
  try{
    const res=await callAI('interview_prep',{job_title:currentJob.title,company:currentJob.company,job_desc:currentJob.description,type});
    if(res.status!=='success'){r.innerHTML=errorHtml(res.message);return;}
    const text=res.data;
    // Parse Q[N]: format
    const blocks=text.split(/(?=^---$|Q\d+:)/m).filter(b=>b.trim()&&b.trim()!=='---');
    const parsed=blocks.map(b=>{
      const q=b.match(/Q\d+:\s*(.+)/)?.[1]||'';
      const why=b.match(/WHY:\s*(.+)/)?.[1]||'';
      const ans=b.match(/ANSWER:\s*([\s\S]+?)(?=TIP:|$)/)?.[1]?.trim()||'';
      const tip=b.match(/TIP:\s*(.+)/)?.[1]||'';
      return{q,why,ans,tip};
    }).filter(x=>x.q);
    if(parsed.length){
      r.innerHTML=`<div class="space-y-4">${parsed.map((x,i)=>`
        <div class="p-4 rounded-2xl border border-slate-200 dark:border-white/8 bg-white dark:bg-white/[.02] hover:border-pcte-300 dark:hover:border-pcte-500/30 transition-colors">
          <div class="flex items-start gap-3 mb-3">
            <span class="w-7 h-7 rounded-xl bg-pcte-100 dark:bg-pcte-900/30 text-pcte-600 dark:text-pcte-400 flex items-center justify-center text-xs font-black shrink-0">${i+1}</span>
            <p class="font-bold text-slate-900 dark:text-white text-sm">${escHtml(x.q)}</p>
          </div>
          ${x.why?`<p class="text-[10px] font-black text-slate-400 uppercase tracking-wider mb-1 ml-10">Why asked: <span class="normal-case font-semibold text-slate-500">${escHtml(x.why)}</span></p>`:''}
          ${x.ans?`<div class="ml-10 mt-2"><p class="text-[10px] font-black text-green-600 dark:text-green-400 uppercase tracking-wider mb-1">Model Answer</p><p class="text-xs text-slate-600 dark:text-gray-400 leading-relaxed">${escHtml(x.ans)}</p></div>`:''}
          ${x.tip?`<div class="ml-10 mt-2 p-2 rounded-lg bg-amber-50 dark:bg-amber-900/10 border border-amber-200 dark:border-amber-500/20"><p class="text-[10px] font-black text-amber-600 dark:text-amber-400">💡 ${escHtml(x.tip)}</p></div>`:''}
        </div>`).join('')}</div>`;
    }else{
      r.innerHTML=`<pre class="text-xs text-slate-600 dark:text-gray-400 whitespace-pre-wrap leading-relaxed">${escHtml(text)}</pre>`;
    }
  }catch(e){r.innerHTML=errorHtml('AI service unavailable.');}
}

// Helpers
function loadingHtml(msg='Loading…'){return `<div class="flex items-center justify-center gap-3 py-10 text-slate-500"><svg class="w-5 h-5 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg><span class="text-sm font-semibold">${msg}</span></div>`;}
function errorHtml(msg){return `<div class="flex items-center gap-3 p-4 rounded-2xl bg-red-50 dark:bg-red-900/10 border border-red-200 dark:border-red-500/20 text-red-600 dark:text-red-400 text-sm font-semibold"><svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>${escHtml(msg)}</div>`;}
function escHtml(s){return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');}
function copyText(btn,id){
  const el=document.getElementById('cover-result');
  const t=el.querySelector('div.font-mono')?.textContent||'';
  navigator.clipboard.writeText(t).then(()=>{btn.textContent='✓ Copied!';setTimeout(()=>{btn.innerHTML='<svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>Copy to Clipboard';},2000);});
}
function showToast(msg,type='info'){
  const t=document.createElement('div');
  t.className=`fixed bottom-24 right-6 z-[9999] px-5 py-3 rounded-2xl text-sm font-bold shadow-2xl transition-all border ${type==='error'?'bg-red-50 dark:bg-red-900/30 text-red-700 dark:text-red-400 border-red-200 dark:border-red-500/30':'bg-green-50 dark:bg-green-900/30 text-green-700 dark:text-green-400 border-green-200 dark:border-green-500/30'}`;
  t.textContent=msg;
  document.body.appendChild(t);
  setTimeout(()=>{t.style.opacity='0';setTimeout(()=>t.remove(),400);},3000);
}
</script>
</body>
</html>
