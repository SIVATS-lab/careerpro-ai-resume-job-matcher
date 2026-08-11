<?php
declare(strict_types=1);
session_start();

require_once 'includes/db.php';
$db = Database::getInstance()->getConnection();

$settings = [];
try {
    $stmt = $db->query("SELECT setting_key, setting_value FROM system_settings");
    while ($row = $stmt->fetch()) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
} catch (PDOException $e) {
    $settings['platform_name']    = 'CareerPro Suite';
    $settings['maintenance_mode'] = 'false';
    $settings['support_email']    = 'support@careerpro.com';
}

if (($settings['maintenance_mode'] ?? 'false') === 'true' && !isset($_SESSION['admin_id'])) {
    $pname = htmlspecialchars($settings['platform_name'] ?? 'CareerPro');
    die("<!DOCTYPE html><html><head><title>Maintenance | $pname</title>
    <script src='https://cdn.tailwindcss.com'></script></head>
    <body class='bg-[#050505] flex items-center justify-center h-screen text-white'>
    <div class='text-center'><h1 class='text-4xl font-black mb-4'>System Maintenance</h1>
    <p class='text-gray-400'>We are upgrading CareerPro. Check back soon.</p></div>
    </body></html>");
}

try {
    $stats = [
        'users'       => $db->query("SELECT COUNT(*) FROM users WHERE is_active = 1")->fetchColumn() ?: 850,
        'jobs'        => $db->query("SELECT COUNT(*) FROM jobs WHERE is_active = 1")->fetchColumn() ?: 15,
        'scans'       => $db->query("SELECT COUNT(*) FROM applications")->fetchColumn() ?: 4200,
        'scans_today' => $db->query("SELECT COUNT(*) FROM applications WHERE DATE(applied_at) = CURDATE()")->fetchColumn() ?: 124,
    ];
} catch (PDOException $e) {
    $stats = ['users' => 850, 'jobs' => 15, 'scans' => 4200, 'scans_today' => 124];
}

$analyzedToday  = number_format((int)$stats['scans_today']);
$isLoggedIn     = isset($_SESSION['user_id']) || isset($_SESSION['admin_id']);
$dashboardLink  = isset($_SESSION['admin_id']) ? 'admin/index.php' : 'dashboard.php';
$userName       = $_SESSION['user_name'] ?? '';
$platformName   = htmlspecialchars($settings['platform_name'] ?? 'CareerPro');
$supportEmail   = htmlspecialchars($settings['support_email'] ?? 'support@careerpro.com');
?>
<!DOCTYPE html>
<html lang="en" class="scroll-smooth dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="CareerPro Suite - AI Resume Builder, ATS Scanner, and Job Matcher for students and job seekers.">
    <meta name="theme-color" content="#df3c3c">
    <title><?php echo $platformName; ?> | Next-Gen AI Career Platform</title>

    <script>
        if (localStorage.getItem('color-theme') === 'dark' ||
            (!('color-theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
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
                        pcte: { 50:'#fdf2f2',100:'#fbe4e4',200:'#f8caca',300:'#f2a3a3',400:'#ea6d6d',500:'#df3c3c',600:'#c82626',700:'#a61c1c',800:'#800000',900:'#701616',950:'#3f0707' },
                        dark: { 950:'#020202',900:'#050505',800:'#0a0a0a',700:'#141414',600:'#1f1f1f' }
                    },
                    animation: {
                        'blob':            'blob 10s infinite',
                        'marquee':         'marquee 35s linear infinite',
                        'marquee-reverse': 'marquee-reverse 40s linear infinite',
                        'float':           'float 6s ease-in-out infinite',
                        'float-delayed':   'float 6s ease-in-out 3s infinite',
                        'scan-laser':      'laserScan 2.5s cubic-bezier(0.4,0,0.2,1) infinite',
                        'fade-in-up':      'fadeInUp 0.5s ease-out forwards',
                    },
                    keyframes: {
                        blob:              { '0%':{transform:'translate(0,0) scale(1)'},'33%':{transform:'translate(30px,-50px) scale(1.1)'},'66%':{transform:'translate(-20px,20px) scale(0.9)'},'100%':{transform:'translate(0,0) scale(1)'} },
                        marquee:           { '0%':{transform:'translateX(0%)'},'100%':{transform:'translateX(-100%)'} },
                        'marquee-reverse': { '0%':{transform:'translateX(-100%)'},'100%':{transform:'translateX(0%)'} },
                        float:             { '0%,100%':{transform:'translateY(0)'},'50%':{transform:'translateY(-20px)'} },
                        laserScan:         { '0%':{top:'0',opacity:0},'10%':{opacity:1},'90%':{opacity:1},'100%':{top:'100%',opacity:0} },
                        fadeInUp:          { '0%':{opacity:0,transform:'translateY(20px)'},'100%':{opacity:1,transform:'translateY(0)'} },
                    }
                }
            }
        }
    </script>

    <style>
        *,*::before,*::after{box-sizing:border-box;}
        html{overflow-x:hidden;}
        body{overflow-x:hidden;-webkit-font-smoothing:antialiased;background-color:#f8fafc;color:#0f172a;transition:background-color .5s ease,color .5s ease;}
        .dark body{background-color:#020202;color:#fff;}
        ::-webkit-scrollbar{width:8px}::-webkit-scrollbar-track{background:transparent}::-webkit-scrollbar-thumb{background:#cbd5e1;border-radius:4px}.dark ::-webkit-scrollbar-thumb{background:#1f1f1f}::-webkit-scrollbar-thumb:hover{background:#df3c3c}

        /* Nav */
        .glass-nav{background:rgba(255,255,255,.85);backdrop-filter:blur(24px);-webkit-backdrop-filter:blur(24px);border-bottom:1px solid rgba(0,0,0,.05);transition:background .4s,border-color .4s;}
        .dark .glass-nav{background:rgba(5,5,5,.8);border-bottom:1px solid rgba(255,255,255,.05);}

        /* Cards */
        .glass-card{background:rgba(255,255,255,.75);backdrop-filter:blur(12px);border:1px solid rgba(0,0,0,.05);box-shadow:0 10px 30px -10px rgba(0,0,0,.05);transition:transform .4s,border-color .4s,box-shadow .4s,background .4s;}
        .dark .glass-card{background:linear-gradient(145deg,rgba(255,255,255,.03),rgba(255,255,255,.01));border:1px solid rgba(255,255,255,.04);border-top:1px solid rgba(255,255,255,.08);box-shadow:0 25px 50px -12px rgba(0,0,0,.5);}
        .glass-card:hover{transform:translateY(-5px);border-color:rgba(223,60,60,.3);box-shadow:0 20px 40px -10px rgba(223,60,60,.1);}

        /* Buttons */
        .btn-primary{background:linear-gradient(135deg,#df3c3c,#a61c1c);position:relative;overflow:hidden;z-index:1;transition:all .3s;border:none;color:#fff;cursor:pointer;}
        .dark .btn-primary{background:linear-gradient(135deg,#a61c1c,#800000);}
        .btn-primary::before{content:'';position:absolute;top:0;left:-100%;width:100%;height:100%;background:linear-gradient(90deg,transparent,rgba(255,255,255,.2),transparent);transition:left .5s;z-index:-1;}
        .btn-primary:hover::before{left:100%;}
        .btn-primary:hover{box-shadow:0 10px 30px -10px rgba(223,60,60,.6);transform:translateY(-2px);}
        .btn-outline{background:transparent;border:2px solid rgba(0,0,0,.1);color:#1e293b;transition:all .3s;cursor:pointer;}
        .dark .btn-outline{border-color:rgba(255,255,255,.1);color:#fff;}
        .btn-outline:hover{border-color:#df3c3c;background:rgba(223,60,60,.05);color:#df3c3c;transform:translateY(-2px);}

        /* Glows */
        .mesh-glow{position:absolute;border-radius:50%;filter:blur(120px);z-index:0;opacity:.35;pointer-events:none;}
        .dark .mesh-glow{opacity:.18;}

        /* Gradients */
        .text-gradient-pcte{background:linear-gradient(to right,#ea6d6d,#c82626);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;}
        .dark .text-gradient-pcte{background:linear-gradient(to right,#ea6d6d,#df3c3c);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;}

        /* ATS Upload Zone */
        .upload-zone{border:2px dashed rgba(0,0,0,.2);transition:all .3s;cursor:pointer;position:relative;overflow:hidden;border-radius:2rem;}
        .dark .upload-zone{border-color:rgba(255,255,255,.2);}
        .upload-zone:hover,.upload-zone.dragover{border-color:#df3c3c;background:rgba(223,60,60,.05);}
        .upload-zone.scanning{border-color:transparent;cursor:wait;pointer-events:none;}
        .scan-laser{position:absolute;width:100%;height:2px;background:#df3c3c;box-shadow:0 0 15px 2px rgba(223,60,60,.8);display:none;z-index:10;pointer-events:none;}

        /* Progress ring */
        .progress-ring__circle{transition:stroke-dashoffset 2s cubic-bezier(.4,0,.2,1);transform:rotate(-90deg);transform-origin:50% 50%;stroke-linecap:round;}

        /* Check items */
        .check-item{opacity:0;transform:translateX(-15px);transition:all .5s cubic-bezier(.4,0,.2,1);}
        .check-item.visible{opacity:1;transform:translateX(0);}

        /* FAQ */
        .faq-answer{display:grid;grid-template-rows:0fr;transition:grid-template-rows .3s ease-out;}
        .faq-answer.open{grid-template-rows:1fr;}
        .faq-answer>div{overflow:hidden;}

        /* Theme toggle */
        .theme-toggle-label{width:44px;height:24px;background-color:#cbd5e1;border-radius:9999px;position:relative;cursor:pointer;transition:background-color .3s;display:flex;align-items:center;}
        .dark .theme-toggle-label{background-color:#334155;}
        .theme-toggle-ball{width:18px;height:18px;background-color:#fff;border-radius:50%;position:absolute;top:3px;left:3px;transition:transform .3s cubic-bezier(.4,0,.2,1);box-shadow:0 2px 4px rgba(0,0,0,.2);}
        .dark .theme-toggle-ball{transform:translateX(20px);background-color:#0f172a;}
    </style>
</head>
<body class="selection:bg-pcte-500 selection:text-white">

<!-- ============================================================
     NAVIGATION
============================================================ -->
<nav id="navbar" class="fixed w-full z-50 py-5 px-6 lg:px-10 transition-all duration-300">
    <div class="max-w-[1400px] mx-auto flex items-center justify-between">

        <!-- Logo -->
        <a href="index.php" class="flex items-center gap-3 group">
            <div class="w-10 h-10 rounded-xl bg-pcte-600 dark:bg-pcte-800 flex items-center justify-center shadow-lg shadow-pcte-500/30 group-hover:scale-105 transition-transform">
                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
            </div>
            <span class="text-xl md:text-2xl font-extrabold tracking-tight text-slate-900 dark:text-white">Career<span class="text-pcte-600 dark:text-pcte-500">Pro</span></span>
        </a>

        <!-- Centre links (desktop) -->
        <div class="hidden lg:flex items-center space-x-8 bg-white/50 dark:bg-dark-900/50 px-8 py-3 rounded-full border border-slate-200 dark:border-white/5 backdrop-blur-md shadow-sm">
            <a href="#features"    class="text-sm font-bold text-slate-600 hover:text-pcte-600 dark:text-gray-300 dark:hover:text-white transition-colors">Platform</a>
            <a href="#ats-scanner" class="text-sm font-bold text-slate-600 hover:text-pcte-600 dark:text-gray-300 dark:hover:text-white transition-colors">ATS Scanner</a>
            <a href="jobs.php"     class="text-sm font-bold text-slate-600 hover:text-pcte-600 dark:text-gray-300 dark:hover:text-white transition-colors">Live Jobs</a>
        </div>

        <!-- Right actions (desktop) -->
        <div class="hidden lg:flex items-center space-x-6">
            <button id="theme-toggle" class="relative focus:outline-none" title="Toggle Theme">
                <div class="theme-toggle-label">
                    <div class="theme-toggle-ball">
                        <svg id="theme-toggle-light-icon" class="w-3 h-3 text-yellow-500 hidden dark:block absolute" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 2a1 1 0 011 1v1a1 1 0 11-2 0V3a1 1 0 011-1zm4.22 4.22a1 1 0 011.415 0l.708.708a1 1 0 01-1.414 1.414l-.708-.708a1 1 0 010-1.414zm3.78 3.78a1 1 0 010 2h-1a1 1 0 110-2h1zm-4.22 4.22a1 1 0 010 1.415l-.708.708a1 1 0 011.414-1.414l.708.708a1 1 0 010 1.414zM10 16a1 1 0 011 1v1a1 1 0 11-2 0v-1a1 1 0 011-1zm-4.22-4.22a1 1 0 01-1.415 0l-.708-.708a1 1 0 011.414-1.414l.708.708a1 1 0 010 1.414zM5 10a1 1 0 010-2h-1a1 1 0 110 2h1zm4.22-4.22a1 1 0 010-1.415l-.708-.708a1 1 0 011.414 1.414l-.708.708a1 1 0 01-1.414 0zM10 5a5 5 0 100 10A5 5 0 0010 5z" clip-rule="evenodd"/></svg>
                        <svg id="theme-toggle-dark-icon"  class="w-3 h-3 text-slate-700 block dark:hidden absolute" fill="currentColor" viewBox="0 0 20 20"><path d="M17.293 13.293A8 8 0 016.707 2.707a8.001 8.001 0 1010.586 10.586z"/></svg>
                    </div>
                </div>
            </button>
            <div class="h-6 w-px bg-slate-300 dark:bg-white/10"></div>
            <?php if ($isLoggedIn): ?>
                <a href="<?php echo $dashboardLink; ?>" class="text-sm font-extrabold text-slate-800 dark:text-white hover:text-pcte-600 dark:hover:text-pcte-400 transition-colors flex items-center gap-2">
                    <div class="w-7 h-7 rounded-full bg-pcte-100 dark:bg-dark-800 border border-pcte-200 dark:border-white/10 flex items-center justify-center text-xs font-bold text-pcte-600 dark:text-white"><?php echo strtoupper(substr($userName,0,1)) ?: 'U'; ?></div>
                    My Dashboard
                </a>
            <?php else: ?>
                <a href="login.php"    class="text-sm font-extrabold text-slate-800 dark:text-white hover:text-pcte-600 dark:hover:text-pcte-400 transition-colors">Sign In</a>
                <a href="register.php" class="btn-primary text-sm font-bold px-6 py-2.5 rounded-full shadow-lg shadow-pcte-500/20">Get Started Free</a>
            <?php endif; ?>
        </div>

        <!-- Hamburger (mobile) -->
        <button id="mobile-menu-btn" class="lg:hidden p-2 rounded-lg bg-white dark:bg-dark-800 border border-slate-200 dark:border-white/10 text-slate-600 dark:text-gray-300 focus:outline-none transition-colors">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
        </button>
    </div>

    <!-- Mobile menu -->
    <div id="mobile-menu" class="hidden lg:hidden absolute top-full left-0 w-full glass-nav border-b border-slate-200 dark:border-white/10 py-6 px-6 flex-col space-y-4 shadow-2xl z-50">
        <a href="#features"    class="text-slate-800 dark:text-gray-300 hover:text-pcte-600 dark:hover:text-white font-bold text-lg">Platform Features</a>
        <a href="#ats-scanner" class="text-slate-800 dark:text-gray-300 hover:text-pcte-600 dark:hover:text-white font-bold text-lg">ATS Scanner</a>
        <a href="jobs.php"     class="text-slate-800 dark:text-gray-300 hover:text-pcte-600 dark:hover:text-white font-bold text-lg">Live Job Board</a>
        <div class="h-px w-full bg-slate-200 dark:bg-white/10 my-2"></div>
        <?php if ($isLoggedIn): ?>
            <a href="<?php echo $dashboardLink; ?>" class="block text-center btn-primary text-white font-bold px-6 py-4 rounded-xl w-full">Go to Dashboard</a>
            <a href="api/auth.php?action=logout"   class="block text-center text-red-500 font-bold py-2">Sign Out</a>
        <?php else: ?>
            <a href="login.php"    class="block text-center border border-slate-200 dark:border-white/10 rounded-xl py-3 font-bold text-lg text-slate-800 dark:text-gray-300 bg-white dark:bg-dark-900">Sign In</a>
            <a href="register.php" class="block text-center btn-primary text-white font-bold px-6 py-4 rounded-xl w-full shadow-lg">Get Started Free</a>
        <?php endif; ?>
        <div class="flex items-center justify-between pt-2 border-t border-slate-200 dark:border-white/10">
            <span class="text-xs font-bold text-slate-400 uppercase tracking-widest">Theme</span>
            <button id="mobile-theme-toggle" class="p-2 rounded-lg bg-slate-100 dark:bg-dark-800 border border-slate-200 dark:border-white/10 text-slate-800 dark:text-white">
                <svg class="w-5 h-5 block dark:hidden" fill="currentColor" viewBox="0 0 20 20"><path d="M17.293 13.293A8 8 0 016.707 2.707a8.001 8.001 0 1010.586 10.586z"/></svg>
                <svg class="w-5 h-5 hidden dark:block" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 5a5 5 0 100 10A5 5 0 0010 5z" clip-rule="evenodd"/></svg>
            </button>
        </div>
    </div>
</nav>

<!-- ============================================================
     HERO SECTION
============================================================ -->
<section class="relative pt-36 pb-24 lg:pt-48 lg:pb-32 overflow-hidden min-h-screen flex items-center">

    <!-- Ambient glows -->
    <div class="mesh-glow w-[600px] h-[600px] bg-pcte-500/20 top-0 left-[-200px] animate-blob"></div>
    <div class="mesh-glow w-[500px] h-[500px] bg-red-900/20 bottom-[-100px] right-[-100px] animate-blob" style="animation-delay:2s"></div>

    <!-- Background grids -->
    <div class="absolute inset-0 z-0 bg-[linear-gradient(to_right,rgba(0,0,0,.05)_1px,transparent_1px),linear-gradient(to_bottom,rgba(0,0,0,.05)_1px,transparent_1px)] bg-[size:50px_50px] [mask-image:radial-gradient(circle_at_50%_30%,black,transparent_80%)] dark:hidden"></div>
    <div class="absolute inset-0 z-0 hidden dark:block bg-[linear-gradient(to_right,rgba(255,255,255,.03)_1px,transparent_1px),linear-gradient(to_bottom,rgba(255,255,255,.03)_1px,transparent_1px)] bg-[size:50px_50px] [mask-image:radial-gradient(circle_at_50%_30%,black,transparent_80%)]"></div>

    <div class="max-w-[1400px] mx-auto px-6 lg:px-10 relative z-10 w-full">
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-12 lg:gap-16 items-center">

            <!-- Left: Text content -->
            <div class="lg:col-span-7" data-aos="fade-right" data-aos-duration="1000">

                <div class="inline-flex items-center gap-2 px-4 py-2 rounded-full bg-white dark:bg-pcte-900/20 border border-slate-200 dark:border-pcte-500/30 mb-8 shadow-sm">
                    <span class="flex h-2.5 w-2.5 relative">
                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-pcte-500 opacity-75"></span>
                        <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-pcte-600 dark:bg-pcte-500"></span>
                    </span>
                    <span class="text-[11px] font-extrabold tracking-widest text-slate-800 dark:text-pcte-300 uppercase">100% Free for Students</span>
                </div>

                <h1 class="text-5xl md:text-6xl lg:text-[5.5rem] font-black leading-[1.1] mb-6 tracking-tight text-slate-900 dark:text-white">
                    Build Your Resume,<br>
                    Apply for a better
                    <span class="text-gradient-pcte relative inline-block ml-2">job.
                        <svg class="absolute w-full h-3 -bottom-1 left-0 text-pcte-300 dark:text-pcte-600 opacity-60" viewBox="0 0 100 10" preserveAspectRatio="none"><path d="M0 5 Q 50 10 100 5" stroke="currentColor" stroke-width="4" fill="transparent"/></svg>
                    </span>
                </h1>

                <p class="text-lg lg:text-xl text-slate-600 dark:text-gray-400 mb-10 max-w-2xl leading-relaxed font-medium">
                    Stop guessing what employers want. Build your resume, apply smarter, and get matched with top jobs using our AI-driven ecosystem — built for ambitious students and job seekers.
                </p>

                <div class="flex flex-col sm:flex-row gap-4 mb-12">
                    <?php if (!$isLoggedIn): ?>
                        <a href="register.php" class="btn-primary text-white font-bold text-lg px-8 py-4 rounded-xl flex items-center justify-center gap-3 shadow-xl shadow-pcte-500/20">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                            Create Resume
                        </a>
                    <?php else: ?>
                        <a href="builder.php" class="btn-primary text-white font-bold text-lg px-8 py-4 rounded-xl flex items-center justify-center gap-3 shadow-xl shadow-pcte-500/20">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                            Open Editor
                        </a>
                    <?php endif; ?>
                    <a href="jobs.php" class="btn-outline font-bold text-lg px-8 py-4 rounded-xl flex items-center justify-center gap-3 bg-white/50 dark:bg-dark-900/50 backdrop-blur">
                        <svg class="w-5 h-5 text-pcte-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                        Match Jobs
                    </a>
                </div>

                <!-- Stats row -->
                <div class="grid grid-cols-3 gap-6 pt-8 border-t border-slate-200 dark:border-white/10">
                    <div>
                        <p class="text-slate-800 dark:text-white font-extrabold text-xl mb-0.5"><?php echo number_format((int)$stats['users']); ?>+</p>
                        <p class="text-xs text-slate-500 dark:text-gray-500 font-medium">Students Empowered</p>
                    </div>
                    <div>
                        <p class="text-slate-800 dark:text-white font-extrabold text-xl mb-0.5">80% Faster</p>
                        <p class="text-xs text-slate-500 dark:text-gray-500 font-medium">Interview Callbacks</p>
                    </div>
                    <div>
                        <p class="text-slate-800 dark:text-white font-extrabold text-xl mb-0.5">ATS Optimized</p>
                        <p class="text-xs text-slate-500 dark:text-gray-500 font-medium">Data-driven Formatting</p>
                    </div>
                </div>
            </div>

            <!-- Right: Visual card -->
            <div class="lg:col-span-5 relative flex justify-center lg:justify-end" data-aos="fade-left" data-aos-duration="1200">
                <div class="absolute inset-0 bg-gradient-to-tr from-pcte-500/20 to-transparent rounded-[3rem] blur-3xl rotate-6 pointer-events-none"></div>
                <div class="relative w-full max-w-[500px] aspect-square">
                    <div class="w-full h-full glass-card rounded-[3rem] p-8 border border-white/20 shadow-2xl overflow-hidden flex items-center justify-center">
                        <!-- Subtle inner grid -->
                        <svg class="absolute inset-0 w-full h-full opacity-20 pointer-events-none" viewBox="0 0 100 100" preserveAspectRatio="none">
                            <defs><pattern id="hg" width="10" height="10" patternUnits="userSpaceOnUse"><path d="M 10 0 L 0 0 0 10" fill="none" stroke="currentColor" stroke-width=".5"/></pattern></defs>
                            <rect width="100" height="100" fill="url(#hg)" class="text-slate-300 dark:text-pcte-500"/>
                        </svg>
                        <!-- SVG illustration -->
                        <div class="relative z-10 w-full h-full animate-float">
                            <svg viewBox="0 0 400 400" fill="none" xmlns="http://www.w3.org/2000/svg" class="w-full h-full drop-shadow-2xl">
                                <!-- Background resume card -->
                                <g transform="translate(220,40) rotate(8)">
                                    <rect width="140" height="190" rx="12" fill="#f8fafc" stroke="#e2e8f0" stroke-width="2" class="dark:hidden"/>
                                    <rect width="140" height="190" rx="12" fill="#141414" stroke="#333" stroke-width="2" class="hidden dark:block"/>
                                    <circle cx="70" cy="45" r="22" fill="#e2e8f0" class="dark:hidden"/>
                                    <circle cx="70" cy="45" r="22" fill="#2a2a2a" class="hidden dark:block"/>
                                    <rect x="20" y="85"  width="100" height="6" rx="3" fill="#cbd5e1" class="dark:hidden"/><rect x="20" y="85"  width="100" height="6" rx="3" fill="#333" class="hidden dark:block"/>
                                    <rect x="20" y="100" width="80"  height="6" rx="3" fill="#cbd5e1" class="dark:hidden"/><rect x="20" y="100" width="80"  height="6" rx="3" fill="#333" class="hidden dark:block"/>
                                    <rect x="20" y="125" width="100" height="4" rx="2" fill="#ea6d6d" opacity=".6"/>
                                    <rect x="20" y="135" width="90"  height="4" rx="2" fill="#ea6d6d" opacity=".6"/>
                                    <rect x="20" y="145" width="95"  height="4" rx="2" fill="#ea6d6d" opacity=".6"/>
                                </g>
                                <!-- Foreground resume with score ring -->
                                <g transform="translate(150,110) rotate(-4)">
                                    <rect width="170" height="220" rx="12" fill="#ffffff" stroke="#cbd5e1" stroke-width="1"/>
                                    <circle cx="85" cy="55" r="28" fill="#f1f5f9" class="dark:hidden"/>
                                    <circle cx="85" cy="55" r="28" fill="#f0f0f0" class="hidden dark:block"/>
                                    <circle cx="85" cy="55" r="28" fill="none" stroke="#df3c3c" stroke-width="3"/>
                                    <circle cx="85" cy="45" r="10" fill="#df3c3c"/>
                                    <path d="M85 65 C65 65 55 85 55 85 L115 85 C115 85 105 65 85 65Z" fill="#df3c3c"/>
                                    <rect x="35" y="105" width="100" height="8" rx="4" fill="#e2e8f0"/>
                                    <rect x="35" y="125" width="80"  height="8" rx="4" fill="#e2e8f0"/>
                                    <rect x="35" y="150" width="100" height="4" rx="2" fill="#94a3b8"/>
                                    <rect x="35" y="160" width="90"  height="4" rx="2" fill="#94a3b8"/>
                                    <!-- Pencil -->
                                    <g transform="translate(115,175) rotate(-15)">
                                        <path d="M0 0 L40 -60 L45 -55 L5 5Z" fill="#df3c3c"/>
                                        <path d="M0 0 L-5 5 L5 5Z" fill="#a61c1c"/>
                                    </g>
                                </g>
                                <!-- Score badge -->
                                <g transform="translate(30,60)">
                                    <rect width="90" height="44" rx="22" fill="#df3c3c"/>
                                    <text x="45" y="28" font-family="sans-serif" font-size="15" font-weight="900" fill="white" text-anchor="middle">92% ATS</text>
                                </g>
                                <!-- Floating tag -->
                                <g transform="translate(260,230)" class="animate-float-delayed">
                                    <rect width="110" height="44" rx="12" fill="#ffffff" class="dark:hidden" stroke="#e2e8f0" stroke-width="1"/>
                                    <rect width="110" height="44" rx="12" fill="#1f1f1f" class="hidden dark:block" stroke="#333" stroke-width="1"/>
                                    <circle cx="22" cy="22" r="10" fill="#df3c3c"/>
                                    <text x="38" y="27" font-family="sans-serif" font-size="11" font-weight="700" fill="#0f172a" class="dark:hidden">ATS Ready</text>
                                    <text x="38" y="27" font-family="sans-serif" font-size="11" font-weight="700" fill="#ffffff" class="hidden dark:block">ATS Ready</text>
                                </g>
                            </svg>
                        </div>
                    </div>
                </div>
            </div>

        </div><!-- /grid -->
    </div><!-- /container -->
</section><!-- /hero -->

<!-- ============================================================
     MARQUEE — Hired Companies
============================================================ -->
<section class="py-12 border-y border-slate-200 dark:border-white/5 bg-white/50 dark:bg-dark-900/50 relative overflow-hidden">
    <p class="text-xs font-bold uppercase tracking-widest text-slate-500 dark:text-gray-500 text-center mb-8">Graduates successfully hired by industry leaders</p>
    <div class="flex flex-col space-y-6 overflow-hidden select-none">
        <div class="flex space-x-16 animate-marquee whitespace-nowrap items-center opacity-60 hover:opacity-100 transition-opacity">
            <?php for($i=0;$i<4;$i++): ?>
            <span class="text-2xl font-bold text-slate-800 dark:text-white">🏢 TechCorp</span>
            <span class="text-2xl font-bold text-slate-800 dark:text-white">🌐 GlobalNet</span>
            <span class="text-2xl font-bold text-slate-800 dark:text-white">💡 Innovate LLC</span>
            <span class="text-2xl font-bold text-slate-800 dark:text-white">📊 ApexData</span>
            <span class="text-2xl font-bold text-slate-800 dark:text-white">🔺 Pinnacle</span>
            <?php endfor; ?>
        </div>
        <div class="hidden md:flex space-x-16 animate-marquee-reverse whitespace-nowrap items-center opacity-60 hover:opacity-100 transition-opacity">
            <?php for($i=0;$i<4;$i++): ?>
            <span class="text-2xl font-bold text-slate-800 dark:text-white">🛡 ShieldSecurity</span>
            <span class="text-2xl font-bold text-slate-800 dark:text-white">🎯 Vertex UI</span>
            <span class="text-2xl font-bold text-slate-800 dark:text-white">🧮 DataMatrix</span>
            <span class="text-2xl font-bold text-slate-800 dark:text-white">⚡ NexGen Core</span>
            <span class="text-2xl font-bold text-slate-800 dark:text-white">☁️ CloudBase</span>
            <?php endfor; ?>
        </div>
    </div>
</section>

<!-- ============================================================
     ATS SCANNER v2 — Dynamic, Animated, Accurate
============================================================ -->
<style>
/* ── ATS v2 Extra Styles ── */
.ats-drop-zone{border:2px dashed rgba(0,0,0,.15);transition:all .35s cubic-bezier(.4,0,.2,1);cursor:pointer;border-radius:2rem;position:relative;overflow:hidden;background:rgba(248,250,252,.8);}
.dark .ats-drop-zone{border-color:rgba(255,255,255,.12);background:rgba(10,10,10,.7);}
.ats-drop-zone.dragover,.ats-drop-zone:hover{border-color:#df3c3c;background:rgba(223,60,60,.04);box-shadow:0 0 40px -10px rgba(223,60,60,.25);}
.ats-drop-zone.scanning{border-color:#df3c3c;pointer-events:none;}
/* Laser beam */
.ats-laser{position:absolute;left:0;right:0;height:3px;background:linear-gradient(to right,transparent,#df3c3c,#ff6b6b,#df3c3c,transparent);box-shadow:0 0 20px 4px rgba(223,60,60,.7),0 0 40px 8px rgba(223,60,60,.3);display:none;z-index:20;animation:laserMove 1.8s ease-in-out infinite;}
@keyframes laserMove{0%{top:0;opacity:0}5%{opacity:1}95%{opacity:1}100%{top:100%;opacity:0}}
/* Scan grid overlay */
.ats-scan-grid{position:absolute;inset:0;background:repeating-linear-gradient(0deg,transparent,transparent 24px,rgba(223,60,60,.06) 24px,rgba(223,60,60,.06) 25px);display:none;z-index:5;pointer-events:none;}
.ats-drop-zone.scanning .ats-scan-grid{display:block;}
.ats-drop-zone.scanning .ats-laser{display:block;}
/* Progress bar animation */
.ats-bar-fill{height:100%;border-radius:9999px;transition:width 1.4s cubic-bezier(.4,0,.2,1);position:relative;overflow:hidden;}
.ats-bar-fill::after{content:'';position:absolute;top:0;left:-60%;width:40%;height:100%;background:linear-gradient(to right,transparent,rgba(255,255,255,.4),transparent);animation:barShimmer 2s infinite 1.5s;}
@keyframes barShimmer{0%{left:-60%}100%{left:120%}}
/* Tab button active */
.ats-tab-btn.active{background:rgba(223,60,60,.1);color:#df3c3c;border-color:rgba(223,60,60,.3);}
.dark .ats-tab-btn.active{background:rgba(223,60,60,.15);color:#f87171;}
/* Score glow pulse */
@keyframes scoreGlow{0%,100%{filter:drop-shadow(0 0 8px rgba(223,60,60,.4))}50%{filter:drop-shadow(0 0 22px rgba(223,60,60,.8))}}
.score-glow{animation:scoreGlow 2.5s ease-in-out infinite;}
/* Stat card pop-in */
@keyframes statPop{0%{opacity:0;transform:scale(.85) translateY(10px)}100%{opacity:1;transform:scale(1) translateY(0)}}
.stat-pop{animation:statPop .4s cubic-bezier(.34,1.56,.64,1) forwards;opacity:0;}
/* Keyword badge hover */
.kw-badge{transition:all .2s;cursor:default;}
.kw-badge:hover{transform:translateY(-2px) scale(1.05);}
/* Check row slide-in */
@keyframes checkSlide{0%{opacity:0;transform:translateX(-16px)}100%{opacity:1;transform:translateX(0)}}
.check-row{animation:checkSlide .4s ease-out forwards;opacity:0;}
</style>

<section id="ats-scanner" class="py-24 lg:py-32 relative overflow-hidden" style="background:linear-gradient(180deg,#f8fafc 0%,#fff 100%);">
<div class="dark:hidden absolute inset-0 bg-[radial-gradient(ellipse_at_60%_50%,rgba(223,60,60,.06),transparent_70%)] pointer-events-none"></div>
<div class="hidden dark:block absolute inset-0 pointer-events-none" style="background:linear-gradient(180deg,#070707 0%,#0a0a0a 100%);"></div>
<div class="hidden dark:block absolute inset-0 bg-[radial-gradient(ellipse_at_60%_50%,rgba(223,60,60,.08),transparent_65%)] pointer-events-none"></div>

<div class="max-w-[1400px] mx-auto px-6 lg:px-10 relative z-10">

    <!-- Section header -->
    <div class="text-center mb-14" data-aos="fade-up">
        <div class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-pcte-50 dark:bg-pcte-900/20 border border-pcte-200 dark:border-pcte-500/30 text-pcte-600 dark:text-pcte-400 text-[10px] font-black uppercase tracking-widest mb-5">
            <span class="flex h-2 w-2 relative"><span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-pcte-500 opacity-75"></span><span class="relative inline-flex rounded-full h-2 w-2 bg-pcte-500"></span></span>
            Deep ATS Analysis Engine
        </div>
        <h2 class="text-4xl md:text-5xl font-extrabold mb-4 text-slate-900 dark:text-white">
            Get Your <span class="text-pcte-600 dark:text-pcte-500">ATS Score</span> Instantly
        </h2>
        <p class="text-slate-600 dark:text-gray-400 text-lg max-w-2xl mx-auto">
            8-dimension analysis: keywords, formatting, action verbs, quantified achievements, and more — all in seconds.
        </p>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-5 gap-10 items-start">

        <!-- ── LEFT: Upload Panel (2 cols) ──────────────────────── -->
        <div class="lg:col-span-2" data-aos="fade-right" data-aos-duration="900">

            <!-- Drop Zone -->
            <div id="drop-zone" class="ats-drop-zone mb-5">
                <div class="ats-laser" id="laser"></div>
                <div class="ats-scan-grid"></div>

                <!-- IDLE -->
                <div id="dz-idle" class="p-10 text-center relative z-10">
                    <div class="w-20 h-20 bg-white dark:bg-dark-800 rounded-2xl flex items-center justify-center mx-auto mb-5 shadow-lg border border-slate-200 dark:border-white/8 group-hover:scale-110 transition-transform">
                        <svg class="w-9 h-9 text-pcte-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    </div>
                    <h4 class="text-slate-800 dark:text-white font-bold text-lg mb-1">Drop your resume here</h4>
                    <p class="text-slate-500 dark:text-gray-500 text-sm mb-6">PDF · DOCX &nbsp;(max 5 MB)</p>
                    <input type="file" id="file-input" class="hidden" accept=".pdf,.docx">
                    <button id="choose-file-btn" class="btn-primary text-white font-bold px-8 py-3 rounded-xl shadow-lg shadow-pcte-500/25 cursor-pointer">
                        Choose File
                    </button>
                </div>

                <!-- SCANNING -->
                <div id="dz-scanning" class="hidden p-10 text-center relative z-10">
                    <div class="relative w-16 h-16 mx-auto mb-5">
                        <div class="absolute inset-0 rounded-full border-4 border-pcte-200 dark:border-pcte-900 animate-ping" style="animation-duration:1.4s"></div>
                        <div class="w-16 h-16 rounded-full border-4 border-pcte-500 border-t-transparent animate-spin"></div>
                    </div>
                    <p class="text-slate-800 dark:text-white font-bold mb-1">Analysing Resume…</p>
                    <p id="dz-file-name" class="text-pcte-500 text-xs font-mono truncate max-w-xs mx-auto opacity-80"></p>
                </div>
            </div>

            <!-- Error -->
            <div id="scan-error" class="hidden mb-4 p-4 rounded-xl bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-500/30 text-red-700 dark:text-red-400 text-sm font-bold flex items-center gap-3">
                <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
                <span id="scan-error-text"></span>
            </div>

            <!-- What we check -->
            <div class="glass-card rounded-2xl p-5 bg-white/80 dark:bg-dark-900/80 border border-slate-100 dark:border-white/5">
                <p class="text-[10px] font-black text-slate-400 dark:text-gray-500 uppercase tracking-widest mb-4">8 Dimensions Analysed</p>
                <div class="grid grid-cols-2 gap-2">
                    <?php foreach([['📋','Contact Info'],['📝','Summary'],['💼','Experience'],['⚙️','Skills'],['🎓','Education'],['🎯','Action Verbs'],['📊','Achievements'],['🔍','ATS Format']] as $item): ?>
                    <div class="flex items-center gap-2 text-xs text-slate-600 dark:text-gray-400 font-medium">
                        <span class="text-sm"><?php echo $item[0]; ?></span><?php echo $item[1]; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="flex items-center gap-3 text-sm text-slate-600 dark:text-gray-400 font-medium mt-5">
                <span class="w-2.5 h-2.5 rounded-full bg-green-500 shadow-[0_0_8px_rgba(34,197,94,.6)] shrink-0"></span>
                <strong class="text-slate-900 dark:text-white"><?php echo $analyzedToday; ?></strong>&nbsp;resumes analysed today
            </div>
        </div>

        <!-- ── RIGHT: Results Card (3 cols) ──────────────────────── -->
        <div class="lg:col-span-3 relative" data-aos="fade-left" data-aos-duration="900" data-aos-delay="100">
            <div class="absolute -inset-4 bg-gradient-to-br from-pcte-100/40 to-transparent dark:from-pcte-900/10 rounded-[3rem] blur-3xl pointer-events-none"></div>

            <div id="results-card" class="relative glass-card rounded-[2.5rem] shadow-2xl border border-slate-200 dark:border-white/8 bg-white/98 dark:bg-[#0c0c0c]/98 min-h-[520px] flex flex-col overflow-hidden">

                <!-- Header -->
                <div class="flex justify-between items-center px-8 pt-7 pb-5 border-b border-slate-100 dark:border-white/5">
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-lg bg-pcte-50 dark:bg-pcte-900/30 border border-pcte-200 dark:border-pcte-500/20 flex items-center justify-center">
                            <svg class="w-4 h-4 text-pcte-600 dark:text-pcte-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
                        </div>
                        <h3 class="text-base font-bold text-slate-900 dark:text-white">Resume ATS Analysis</h3>
                    </div>
                    <span id="scan-status-badge" class="px-3 py-1 bg-slate-100 dark:bg-white/5 text-slate-500 dark:text-gray-400 text-[10px] font-bold uppercase tracking-widest rounded-full border border-slate-200 dark:border-white/10 transition-all duration-300">
                        Awaiting Upload
                    </span>
                </div>

                <!-- ──── IDLE ──── -->
                <div id="idle-state" class="flex-1 flex flex-col items-center justify-center text-center p-12">
                    <div class="w-20 h-20 rounded-2xl bg-slate-50 dark:bg-dark-800 border border-slate-100 dark:border-white/5 flex items-center justify-center mb-5 mx-auto shadow-sm">
                        <svg class="w-10 h-10 text-slate-300 dark:text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    </div>
                    <p class="text-slate-500 dark:text-gray-500 font-semibold text-sm max-w-xs leading-relaxed">Upload your resume on the left to get a detailed ATS compatibility report with actionable feedback.</p>
                </div>

                <!-- ──── SCANNING ──── -->
                <div id="scanning-state" class="hidden flex-1 flex flex-col items-center justify-center text-center p-12 space-y-6">
                    <!-- Animated scanner graphic -->
                    <div class="relative w-24 h-24 mx-auto">
                        <div class="absolute inset-0 rounded-full border-[3px] border-pcte-100 dark:border-pcte-900/50"></div>
                        <div class="absolute inset-0 rounded-full border-[3px] border-pcte-400 border-t-transparent animate-spin" style="animation-duration:.9s"></div>
                        <div class="absolute inset-2 rounded-full border-[3px] border-pcte-300 border-b-transparent animate-spin" style="animation-duration:1.4s;animation-direction:reverse"></div>
                        <div class="absolute inset-0 flex items-center justify-center">
                            <svg class="w-8 h-8 text-pcte-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                        </div>
                    </div>
                    <div>
                        <p class="text-slate-800 dark:text-white font-bold text-lg mb-1">Deep Scanning Resume…</p>
                        <p id="scan-progress-text" class="text-slate-500 dark:text-gray-400 text-sm">Parsing document structure…</p>
                    </div>
                    <!-- Step indicators -->
                    <div class="w-full max-w-xs">
                        <div id="scan-steps" class="space-y-2 text-left">
                            <?php foreach(['Extracting text content','Analysing keywords & skills','Scoring action verbs','Checking ATS formatting','Generating feedback'] as $i=>$step): ?>
                            <div class="scan-step flex items-center gap-2 text-xs text-slate-400 dark:text-gray-600" data-step="<?php echo $i; ?>">
                                <div class="w-4 h-4 rounded-full border border-current flex items-center justify-center shrink-0 step-icon">
                                    <div class="w-1.5 h-1.5 rounded-full bg-current hidden step-dot"></div>
                                </div>
                                <?php echo $step; ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- ──── RESULTS ──── -->
                <div id="results-state" class="hidden flex-1 flex flex-col">

                    <!-- Score hero -->
                    <div class="px-8 py-6 flex items-center gap-6 border-b border-slate-100 dark:border-white/5">
                        <!-- Ring -->
                        <div class="relative w-28 h-28 shrink-0 score-glow">
                            <svg class="w-full h-full" viewBox="0 0 100 100" style="transform:rotate(-90deg)">
                                <circle stroke="#e2e8f0" stroke-width="7" cx="50" cy="50" r="40" fill="transparent" class="dark:hidden"/>
                                <circle stroke="#1a1a1a" stroke-width="7" cx="50" cy="50" r="40" fill="transparent" class="hidden dark:block"/>
                                <circle id="ui-score-ring" stroke-width="9" cx="50" cy="50" r="40" fill="transparent" stroke-dasharray="251.33" stroke-dashoffset="251.33" stroke-linecap="round" style="stroke:#df3c3c;transition:stroke-dashoffset 1.8s cubic-bezier(.4,0,.2,1)"/>
                            </svg>
                            <div class="absolute inset-0 flex flex-col items-center justify-center">
                                <span id="ui-score-text" class="text-3xl font-black text-slate-900 dark:text-white leading-none tabular-nums">0</span>
                                <span class="text-[8px] font-bold text-slate-400 uppercase tracking-widest mt-0.5">/ 100</span>
                            </div>
                        </div>
                        <div class="flex-1 min-w-0">
                            <div id="grade-badge" class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-black uppercase tracking-wider mb-2 border"></div>
                            <p id="summary-text" class="text-slate-600 dark:text-gray-400 text-xs leading-relaxed line-clamp-3"></p>
                            <!-- Mini stats -->
                            <div id="mini-stats" class="flex gap-4 mt-3"></div>
                        </div>
                    </div>

                    <!-- Tabs -->
                    <div class="px-8 pt-5 pb-2 flex gap-2 border-b border-slate-100 dark:border-white/5">
                        <button class="ats-tab-btn active text-xs font-bold px-4 py-1.5 rounded-full border border-slate-200 dark:border-white/10 text-slate-600 dark:text-gray-400 transition-all" data-tab="checks">Checks</button>
                        <button class="ats-tab-btn text-xs font-bold px-4 py-1.5 rounded-full border border-slate-200 dark:border-white/10 text-slate-600 dark:text-gray-400 transition-all" data-tab="feedback">Feedback</button>
                        <button class="ats-tab-btn text-xs font-bold px-4 py-1.5 rounded-full border border-slate-200 dark:border-white/10 text-slate-600 dark:text-gray-400 transition-all" data-tab="keywords">Keywords</button>
                    </div>

                    <!-- Tab: Checks -->
                    <div id="tab-checks" class="ats-tab-panel flex-1 overflow-y-auto px-8 py-5 space-y-2" style="max-height:340px;scrollbar-width:thin;"></div>

                    <!-- Tab: Feedback -->
                    <div id="tab-feedback" class="ats-tab-panel hidden flex-1 overflow-y-auto px-8 py-5" style="max-height:340px;scrollbar-width:thin;">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div class="bg-emerald-50 dark:bg-emerald-900/10 border border-emerald-200 dark:border-emerald-500/20 rounded-2xl p-4">
                                <p class="text-[10px] font-black text-emerald-700 dark:text-emerald-400 uppercase tracking-widest mb-3 flex items-center gap-1.5">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                                    Strengths
                                </p>
                                <ul id="strengths-list" class="space-y-2 text-xs text-slate-700 dark:text-gray-300 font-medium"></ul>
                            </div>
                            <div class="bg-amber-50 dark:bg-amber-900/10 border border-amber-200 dark:border-amber-500/20 rounded-2xl p-4">
                                <p class="text-[10px] font-black text-amber-700 dark:text-amber-400 uppercase tracking-widest mb-3 flex items-center gap-1.5">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18"/></svg>
                                    Improvements
                                </p>
                                <ul id="improvements-list" class="space-y-2 text-xs text-slate-700 dark:text-gray-300 font-medium"></ul>
                            </div>
                        </div>
                    </div>

                    <!-- Tab: Keywords -->
                    <div id="tab-keywords" class="ats-tab-panel hidden flex-1 overflow-y-auto px-8 py-5 space-y-5" style="max-height:340px;scrollbar-width:thin;">
                        <div>
                            <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-3 flex items-center gap-1.5">
                                <span class="w-2 h-2 rounded-full bg-green-500 inline-block"></span>Detected in your resume
                            </p>
                            <div id="keywords-found" class="flex flex-wrap gap-2"></div>
                        </div>
                        <div>
                            <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-3 flex items-center gap-1.5">
                                <span class="w-2 h-2 rounded-full bg-red-500 inline-block"></span>Suggested to add
                            </p>
                            <div id="keywords-missing" class="flex flex-wrap gap-2"></div>
                        </div>
                    </div>

                    <!-- Footer -->
                    <div class="px-8 pb-6 pt-4 border-t border-slate-100 dark:border-white/5">
                        <button id="rescan-btn" class="w-full py-2.5 bg-slate-50 dark:bg-dark-800 hover:bg-pcte-50 dark:hover:bg-pcte-900/20 text-slate-500 dark:text-gray-400 hover:text-pcte-600 dark:hover:text-pcte-400 rounded-xl text-xs font-black uppercase tracking-widest border border-slate-200 dark:border-white/5 transition-all cursor-pointer flex items-center justify-center gap-2">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                            Scan Another Resume
                        </button>
                    </div>

                </div><!-- /results-state -->
            </div><!-- /results-card -->
        </div>

    </div><!-- /grid -->
</div>
</section>

<!-- ============================================================
     FEATURES SECTION
============================================================ -->
<section id="features" class="py-24 lg:py-32 relative bg-slate-50 dark:bg-[#050505] border-t border-slate-200 dark:border-white/5">
    <div class="max-w-[1400px] mx-auto px-6 lg:px-10">
        <div class="text-center max-w-3xl mx-auto mb-20" data-aos="fade-up">
            <h2 class="text-4xl md:text-5xl font-extrabold mb-6 text-slate-900 dark:text-white">Enterprise-Grade <span class="text-pcte-600 dark:text-pcte-500">Toolkit.</span></h2>
            <p class="text-slate-600 dark:text-gray-400 text-lg leading-relaxed">We replaced outdated Word documents and blind job applications with an intelligent, centralized suite.</p>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
            <?php
            $features = [
                ['icon'=>'M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4', 'title'=>'JSON Data Architecture', 'body'=>"Your resume isn't just text; it's a structured JSON payload stored securely, natively readable by enterprise ATS parsers.", 'delay'=>0],
                ['icon'=>'M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10', 'title'=>'Proven Templates', 'body'=>'Clean, Harvard-style and modern minimalist PDFs mathematically designed to parse perfectly through any ATS filter.', 'delay'=>100],
                ['icon'=>'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z', 'title'=>'TF-IDF Optimization', 'body'=>'Our matching algorithm uses Term Frequency-Inverse Document Frequency analysis — exactly how Workday and Taleo score resumes.', 'delay'=>200],
            ];
            foreach($features as $f): ?>
            <div class="glass-card rounded-[2rem] p-8 md:p-10 group" data-aos="fade-up" data-aos-delay="<?php echo $f['delay']; ?>">
                <div class="w-14 h-14 rounded-2xl bg-white dark:bg-dark-800 border border-slate-200 dark:border-white/10 flex items-center justify-center text-pcte-600 dark:text-pcte-500 mb-8 shadow-sm group-hover:-translate-y-2 transition-transform">
                    <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="<?php echo $f['icon']; ?>"/></svg>
                </div>
                <h3 class="text-2xl font-bold text-slate-900 dark:text-white mb-3"><?php echo $f['title']; ?></h3>
                <p class="text-sm text-slate-600 dark:text-gray-400 leading-relaxed"><?php echo $f['body']; ?></p>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ============================================================
     TESTIMONIALS
============================================================ -->
<section class="py-24 lg:py-32 relative bg-white dark:bg-dark-900 border-y border-slate-200 dark:border-white/5">
    <div class="max-w-[1400px] mx-auto px-6 lg:px-10">
        <div class="text-center max-w-3xl mx-auto mb-20" data-aos="fade-up">
            <h2 class="text-4xl md:text-5xl font-extrabold mb-6 text-slate-900 dark:text-white">Built for <span class="text-pcte-600 dark:text-pcte-500">Students.</span></h2>
            <p class="text-slate-600 dark:text-gray-400 text-lg">See how students are using CareerPro to secure roles at top tech companies.</p>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
            <?php
            $testimonials = [
                ['init'=>'A','color'=>'blue',  'name'=>'Aman Singh',  'batch'=>"BCA '26", 'quote'=>'"I was getting rejected automatically because my resume was a Canva template. CareerPro\'s scanner showed me a 30% match. I rebuilt it here, hit 85%, and got the interview."', 'hired'=>'Hired at TechCorp',   'delay'=>0],
                ['init'=>'P','color'=>'purple','name'=>'Priya Sharma','batch'=>"MCA '25", 'quote'=>'"The AI Chatbot is incredible. I struggled to explain my final year project professionally, but it rewrote my bullet points to highlight the measurable impact I made."', 'hired'=>'Hired at GlobalNet',  'delay'=>100],
                ['init'=>'R','color'=>'emerald','name'=>'Rahul Verma','batch'=>"B.Tech '26",'quote'=>'"A central dashboard where I can track my resume score against live job postings saved me hours of manual tweaking. Game changer."', 'hired'=>'Hired at Innovate LLC','delay'=>200],
            ];
            foreach($testimonials as $t): ?>
            <div class="glass-card p-8 rounded-3xl flex flex-col" data-aos="fade-up" data-aos-delay="<?php echo $t['delay']; ?>">
                <div class="flex items-center gap-4 mb-6">
                    <div class="w-12 h-12 rounded-full bg-<?php echo $t['color']; ?>-100 dark:bg-<?php echo $t['color']; ?>-900/30 flex items-center justify-center font-bold text-lg text-<?php echo $t['color']; ?>-600 dark:text-<?php echo $t['color']; ?>-400 border border-<?php echo $t['color']; ?>-200 dark:border-transparent shrink-0">
                        <?php echo $t['init']; ?>
                    </div>
                    <div>
                        <h4 class="font-bold text-slate-900 dark:text-white"><?php echo $t['name']; ?></h4>
                        <p class="text-xs text-slate-500 dark:text-gray-400">PCTE <?php echo $t['batch']; ?></p>
                    </div>
                </div>
                <p class="text-slate-700 dark:text-gray-300 italic mb-6 flex-1 leading-relaxed text-sm"><?php echo $t['quote']; ?></p>
                <div class="text-xs font-bold text-pcte-600 dark:text-pcte-400 uppercase tracking-widest"><?php echo $t['hired']; ?></div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ============================================================
     FAQ SECTION
============================================================ -->
<section class="py-24 lg:py-32 bg-slate-50 dark:bg-[#050505] relative">
    <div class="max-w-4xl mx-auto px-6">
        <div class="text-center mb-16" data-aos="fade-up">
            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-green-100 dark:bg-green-900/20 border border-green-200 dark:border-green-500/30 text-green-700 dark:text-green-400 text-[10px] font-bold uppercase tracking-widest mb-6">100% Free for Everyone</div>
            <h2 class="text-3xl md:text-4xl font-extrabold text-slate-900 dark:text-white mb-4">Frequently Asked Questions</h2>
            <p class="text-slate-600 dark:text-gray-400">Everything you need to know about the platform capabilities.</p>
        </div>
        <div class="space-y-4">
            <?php
            $faqs = [
                ['q'=>'Is this platform completely free?', 'a'=>'Yes. CareerPro Suite is completely free to use. Simply create an account with any email address to get unlimited access to the AI Resume Builder, ATS Scanner, and PDF exports.', 'delay'=>0],
                ['q'=>'How accurate is the ATS Scanner?', 'a'=>'Highly accurate. Our algorithm uses TF-IDF (Term Frequency-Inverse Document Frequency) keyword density scanning, mirroring the parsing logic of Taleo, Greenhouse, and Workday.', 'delay'=>100],
                ['q'=>'How does the auto-save work?', 'a'=>'Your resume is stored securely as a JSON object. Every time you pause typing in the builder, it synchronises with the server. Log in from any device and pick up right where you left off.', 'delay'=>200],
                ['q'=>'Can I download my resume as a PDF?', 'a'=>'Yes. Once your resume is complete in the builder, you can export a clean, ATS-optimised PDF with one click. No watermarks, no branding — yours to use freely.', 'delay'=>300],
            ];
            foreach($faqs as $faq): ?>
            <div class="glass-card rounded-2xl overflow-hidden" data-aos="fade-up" data-aos-delay="<?php echo $faq['delay']; ?>">
                <button class="faq-toggle w-full px-8 py-6 text-left flex justify-between items-center hover:bg-slate-100 dark:hover:bg-white/5 transition-colors focus:outline-none group">
                    <span class="font-bold text-lg text-slate-800 dark:text-white group-hover:text-pcte-600 dark:group-hover:text-pcte-400 transition-colors pr-6"><?php echo htmlspecialchars($faq['q']); ?></span>
                    <svg class="w-6 h-6 text-slate-400 shrink-0 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </button>
                <div class="faq-answer bg-slate-50 dark:bg-dark-900 border-t border-slate-200 dark:border-white/5">
                    <div class="px-8 py-6 text-slate-600 dark:text-gray-400 leading-relaxed text-sm"><?php echo htmlspecialchars($faq['a']); ?></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ============================================================
     CALL TO ACTION
============================================================ -->
<section class="py-24 relative overflow-hidden bg-white dark:bg-[#050505] border-t border-slate-200 dark:border-transparent">
    <div class="absolute inset-0 bg-gradient-to-b from-transparent to-pcte-50 dark:to-pcte-900/10 pointer-events-none"></div>
    <div class="max-w-5xl mx-auto px-6">
        <div class="glass-card rounded-[3rem] p-12 md:p-20 text-center relative overflow-hidden border-pcte-200 dark:border-pcte-800/30" data-aos="zoom-in">
            <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[80%] h-[80%] bg-pcte-100 dark:bg-pcte-800/20 blur-[100px] pointer-events-none rounded-full"></div>
            <h2 class="text-4xl md:text-6xl font-black mb-6 relative z-10 text-slate-900 dark:text-white tracking-tight">Stop Applying into the <span class="text-pcte-600 dark:text-pcte-500">Void.</span></h2>
            <p class="text-xl text-slate-600 dark:text-gray-300 mb-10 max-w-2xl mx-auto relative z-10 font-medium leading-relaxed">Join thousands of students building data-driven resumes and landing interviews at top companies today.</p>
            <?php if (!$isLoggedIn): ?>
                <a href="register.php" class="inline-flex items-center gap-3 btn-primary px-10 py-5 rounded-2xl font-bold text-xl text-white shadow-2xl shadow-pcte-500/30 relative z-10">
                    Create Your Free Account
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
                </a>
            <?php else: ?>
                <a href="<?php echo $dashboardLink; ?>" class="inline-flex items-center gap-3 btn-primary px-10 py-5 rounded-2xl font-bold text-xl text-white shadow-2xl shadow-pcte-500/30 relative z-10">
                    Launch Dashboard
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                </a>
            <?php endif; ?>
        </div>
    </div>
</section>

<!-- ============================================================
     FOOTER
============================================================ -->
<footer class="bg-slate-50 dark:bg-dark-950 border-t border-slate-200 dark:border-white/10 pt-20 pb-10 transition-colors">
    <div class="max-w-[1400px] mx-auto px-6 lg:px-10 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-12 mb-16">
        <div class="lg:col-span-2 pr-8">
            <a href="index.php" class="flex items-center gap-3 mb-6">
                <div class="w-10 h-10 rounded-xl bg-pcte-600 dark:bg-pcte-800 flex items-center justify-center shadow-lg">
                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                </div>
                <span class="text-2xl font-extrabold tracking-tight text-slate-900 dark:text-white">Career<span class="text-pcte-600 dark:text-pcte-500">Pro</span></span>
            </a>
            <p class="text-slate-600 dark:text-gray-400 leading-relaxed mb-8 max-w-sm text-sm">Intelligent, data-driven tools for ambitious students and professionals ready to bypass HR filters and land dream roles.</p>
        </div>
        <div>
            <h4 class="text-slate-900 dark:text-white font-bold text-lg mb-6">Product</h4>
            <ul class="space-y-3 text-sm text-slate-600 dark:text-gray-400 font-medium">
                <li><a href="builder.php"   class="hover:text-pcte-600 dark:hover:text-pcte-400 transition-colors">Resume Builder</a></li>
                <li><a href="#ats-scanner"  class="hover:text-pcte-600 dark:hover:text-pcte-400 transition-colors">ATS Scanner</a></li>
                <li><a href="jobs.php"      class="hover:text-pcte-600 dark:hover:text-pcte-400 transition-colors">Job Matcher</a></li>
            </ul>
        </div>
        <div>
            <h4 class="text-slate-900 dark:text-white font-bold text-lg mb-6">Support</h4>
            <ul class="space-y-3 text-sm text-slate-600 dark:text-gray-400 font-medium">
                <li><a href="mailto:<?php echo $supportEmail; ?>" class="hover:text-pcte-600 dark:hover:text-pcte-400 transition-colors">Contact Helpdesk</a></li>
                <li><a href="#" class="hover:text-pcte-600 dark:hover:text-pcte-400 transition-colors">Privacy Policy</a></li>
                <li><a href="#" class="hover:text-pcte-600 dark:hover:text-pcte-400 transition-colors">Terms of Service</a></li>
            </ul>
        </div>
        <div>
            <h4 class="text-slate-900 dark:text-white font-bold text-lg mb-6">Access</h4>
            <ul class="space-y-3 text-sm text-slate-600 dark:text-gray-400 font-medium">
                <li><a href="login.php"    class="hover:text-slate-900 dark:hover:text-white font-bold transition-colors">Student Login</a></li>
                <li><a href="register.php" class="hover:text-slate-900 dark:hover:text-white font-bold transition-colors">Create Account</a></li>
                <li class="pt-4 mt-4 border-t border-slate-200 dark:border-white/10">
                    <a href="admin/index.php" class="flex items-center gap-2 text-pcte-600 dark:text-pcte-400 hover:text-slate-900 dark:hover:text-white font-bold transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                        Admin Dashboard
                    </a>
                </li>
            </ul>
        </div>
    </div>
    <div class="border-t border-slate-200 dark:border-white/5 pt-8 flex flex-col md:flex-row items-center justify-between max-w-[1400px] mx-auto px-6 lg:px-10">
        <p class="text-xs text-slate-500 dark:text-gray-600 font-medium">&copy; <?php echo date('Y'); ?> <?php echo $platformName; ?>. Designed for Institutional Excellence.</p>
        <div class="flex items-center gap-2 mt-4 md:mt-0 text-xs text-slate-500 dark:text-gray-600 font-bold">
            <span class="w-2 h-2 rounded-full bg-green-500 shadow-[0_0_5px_#22c55e]"></span> All Systems Operational
        </div>
    </div>
</footer>

<!-- ============================================================
     JAVASCRIPT
============================================================ -->
<script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
<script>
// 1. AOS
AOS.init({ once: true, offset: 60, duration: 800, easing: 'ease-out-cubic' });

// 2. Theme Toggle
const themeBtn       = document.getElementById('theme-toggle');
const mobileThemeBtn = document.getElementById('mobile-theme-toggle');

function setTheme(isDark) {
    document.documentElement.classList.toggle('dark', isDark);
    localStorage.setItem('color-theme', isDark ? 'dark' : 'light');
}
[themeBtn, mobileThemeBtn].forEach(b => {
    if (b) b.addEventListener('click', () => setTheme(!document.documentElement.classList.contains('dark')));
});

// 3. Navbar scroll shrink
const navbar = document.getElementById('navbar');
window.addEventListener('scroll', () => {
    const scrolled = window.scrollY > 20;
    navbar.classList.toggle('glass-nav',  scrolled);
    navbar.classList.toggle('shadow-md',  scrolled);
    navbar.classList.toggle('py-3',       scrolled);
    navbar.classList.toggle('py-5',       !scrolled);
}, { passive: true });

// 4. Mobile menu
document.getElementById('mobile-menu-btn').addEventListener('click', () => {
    const m = document.getElementById('mobile-menu');
    m.classList.toggle('hidden');
    m.classList.toggle('flex');
});

// 5. ATS Scanner — wired to v2 UI
const dropZone    = document.getElementById('drop-zone');
const fileInput   = document.getElementById('file-input');
const chooseBtn   = document.getElementById('choose-file-btn');
const laser       = document.getElementById('laser');
const dzIdle      = document.getElementById('dz-idle');
const dzScanning  = document.getElementById('dz-scanning');
const dzFileName  = document.getElementById('dz-file-name');
const statusBadge = document.getElementById('scan-status-badge');
const scanError   = document.getElementById('scan-error');
const scanErrTxt  = document.getElementById('scan-error-text');
const idleState     = document.getElementById('idle-state');
const scanningState = document.getElementById('scanning-state');
const resultsState  = document.getElementById('results-state');
const progressTxt   = document.getElementById('scan-progress-text');
const rescanBtn     = document.getElementById('rescan-btn');
const scoreRing     = document.getElementById('ui-score-ring');
const scoreTxt      = document.getElementById('ui-score-text');
const gradeBadge    = document.getElementById('grade-badge');
const summaryTxt    = document.getElementById('summary-text');
const miniStats     = document.getElementById('mini-stats');
const tabChecks     = document.getElementById('tab-checks');
const strengthsList = document.getElementById('strengths-list');
const improvList    = document.getElementById('improvements-list');
const kwFound       = document.getElementById('keywords-found');
const kwMissing     = document.getElementById('keywords-missing');
const CIRCUMFERENCE = 251.33;

// Tab switching
document.querySelectorAll('.ats-tab-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        document.querySelectorAll('.ats-tab-btn').forEach(b => b.classList.remove('active'));
        document.querySelectorAll('.ats-tab-panel').forEach(p => { p.classList.add('hidden'); p.classList.remove('flex'); });
        btn.classList.add('active');
        const panel = document.getElementById('tab-' + btn.dataset.tab);
        if (panel) { panel.classList.remove('hidden'); panel.style.display = 'flex'; panel.style.flexDirection = 'column'; }
    });
});

// Wire up events
if (chooseBtn) chooseBtn.addEventListener('click', () => fileInput && fileInput.click());
if (fileInput) fileInput.addEventListener('change', function() { if (this.files.length) runScan(this.files[0]); });
if (rescanBtn) rescanBtn.addEventListener('click', resetScanner);

if (dropZone) {
    ['dragenter','dragover','dragleave','drop'].forEach(e =>
        dropZone.addEventListener(e, ev => { ev.preventDefault(); ev.stopPropagation(); })
    );
    ['dragenter','dragover'].forEach(e => dropZone.addEventListener(e, () => dropZone.classList.add('dragover')));
    ['dragleave','drop'].forEach(e => dropZone.addEventListener(e, () => dropZone.classList.remove('dragover')));
    dropZone.addEventListener('drop', e => {
        const f = e.dataTransfer?.files[0];
        if (f) runScan(f);
    });
}

function showState(which) {
    [idleState, scanningState, resultsState].forEach(el => {
        if (!el) return;
        el.classList.add('hidden');
        el.classList.remove('flex');
        el.style.display = '';
    });
    if (which === 'idle')     { if (idleState)     { idleState.classList.remove('hidden');     idleState.style.display = 'flex';     } }
    if (which === 'scanning') { if (scanningState) { scanningState.classList.remove('hidden'); scanningState.style.display = 'flex'; } }
    if (which === 'results')  { if (resultsState)  { resultsState.classList.remove('hidden');  resultsState.style.display = 'flex';  resultsState.style.flexDirection = 'column'; } }

    if (dzIdle)     dzIdle.classList.toggle('hidden',     which !== 'idle');
    if (dzScanning) dzScanning.classList.toggle('hidden', which !== 'scanning');
    if (dropZone)   dropZone.classList.toggle('scanning', which === 'scanning');
}

// Ensure correct initial state on page load
showState('idle');

function resetScanner() {
    showState('idle');
    scanError && scanError.classList.add('hidden');
    if (statusBadge) { statusBadge.textContent = 'Awaiting Upload'; setBadgeClass('slate'); }
    if (scoreRing) scoreRing.style.strokeDashoffset = String(CIRCUMFERENCE);
    if (scoreTxt)  scoreTxt.textContent = '0';
    if (fileInput) fileInput.value = '';
    // Reset to first tab
    document.querySelectorAll('.ats-tab-btn').forEach(b => b.classList.remove('active'));
    document.querySelectorAll('.ats-tab-panel').forEach(p => { p.classList.add('hidden'); p.style.display = ''; });
    const firstBtn = document.querySelector('.ats-tab-btn[data-tab="checks"]');
    if (firstBtn) firstBtn.classList.add('active');
    if (tabChecks) { tabChecks.classList.remove('hidden'); tabChecks.style.display = 'flex'; tabChecks.style.flexDirection = 'column'; }
}

function setBadgeClass(color) {
    if (!statusBadge) return;
    const classes = {
        slate:  'px-3 py-1 bg-slate-100 dark:bg-white/5 text-slate-500 dark:text-gray-400 text-[10px] font-bold uppercase tracking-widest rounded-full border border-slate-200 dark:border-white/10 transition-all',
        yellow: 'px-3 py-1 bg-yellow-100 dark:bg-yellow-900/30 text-yellow-700 dark:text-yellow-400 text-[10px] font-bold uppercase tracking-widest rounded-full border border-yellow-200 dark:border-yellow-500/20 transition-all',
        green:  'px-3 py-1 bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-400 text-[10px] font-bold uppercase tracking-widest rounded-full border border-green-200 dark:border-green-500/20 transition-all',
        red:    'px-3 py-1 bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-400 text-[10px] font-bold uppercase tracking-widest rounded-full border border-red-200 dark:border-red-500/20 transition-all',
    };
    statusBadge.className = classes[color] || classes.slate;
}

const progressMessages = [
    'Parsing document structure…',
    'Extracting text content…',
    'Analysing keyword density…',
    'Scoring action verbs…',
    'Checking ATS compatibility…',
    'Generating feedback report…',
];

// Step animator for scanning state
const scanStepEls = document.querySelectorAll('.scan-step');
let stepTimer = null;
function animateScanSteps() {
    let i = 0;
    scanStepEls.forEach(el => {
        el.classList.remove('text-slate-800','dark:text-white');
        el.querySelector?.('.step-dot')?.classList.add('hidden');
    });
    stepTimer = setInterval(() => {
        if (i < scanStepEls.length) {
            const el = scanStepEls[i];
            el.classList.add('text-slate-800');
            const dot = el.querySelector('.step-dot');
            if (dot) dot.classList.remove('hidden');
            if (progressTxt) progressTxt.textContent = progressMessages[i] || '';
            i++;
        } else {
            clearInterval(stepTimer);
        }
    }, 900);
}

async function runScan(file) {
    const allowed = ['pdf','docx'];
    const ext = file.name.split('.').pop().toLowerCase();

    if (!allowed.includes(ext)) {
        showError('Only PDF and DOCX files are supported.');
        return;
    }
    if (file.size > 5 * 1024 * 1024) {
        showError('File exceeds the 5 MB limit.');
        return;
    }

    scanError && scanError.classList.add('hidden');
    if (dzFileName) dzFileName.textContent = file.name;

    showState('scanning');
    setBadgeClass('yellow');
    if (statusBadge) statusBadge.textContent = 'Analysing…';
    animateScanSteps();

    const fd = new FormData();
    fd.append('resume_file', file);

    try {
        const resp = await fetch('api/ats-scanner.php', { method: 'POST', body: fd });

        let data;
        try {
            data = await resp.json();
        } catch {
            throw new Error('Server returned invalid response. Check PHP error logs.');
        }

        clearInterval(stepTimer);

        if (!resp.ok || data.status !== 'success') {
            showState('idle');
            setBadgeClass('slate');
            if (statusBadge) statusBadge.textContent = 'Awaiting Upload';
            showError(data.message || 'Analysis failed. Please try again.');
            return;
        }

        renderResults(data);

    } catch (err) {
        clearInterval(stepTimer);
        showState('idle');
        setBadgeClass('slate');
        if (statusBadge) statusBadge.textContent = 'Awaiting Upload';
        showError('Error: ' + (err.message || 'Check your connection and try again.'));
    }
}

function renderResults(data) {
    showState('results');
    setBadgeClass('green');
    if (statusBadge) statusBadge.textContent = 'Complete';

    const score = Math.max(0, Math.min(100, data.score || 0));
    const ringColor = { Excellent:'#22c55e', Good:'#3b82f6', Average:'#f59e0b', Poor:'#ef4444' }[data.grade] || '#df3c3c';
    const gradeStyle = {
        Excellent: 'bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-400 border-green-300 dark:border-green-500/30',
        Good:      'bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-400 border-blue-300 dark:border-blue-500/30',
        Average:   'bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-400 border-amber-300 dark:border-amber-500/30',
        Poor:      'bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-400 border-red-300 dark:border-red-500/30',
    }[data.grade] || 'bg-slate-100 text-slate-600 border-slate-200';

    // Ring
    if (scoreRing) {
        scoreRing.style.stroke = ringColor;
        setTimeout(() => { scoreRing.style.strokeDashoffset = String(CIRCUMFERENCE - (score / 100) * CIRCUMFERENCE); }, 120);
    }
    // Count-up
    if (scoreTxt) {
        let n = 0;
        const iv = setInterval(() => { n = Math.min(n + Math.ceil(score / 50), score); scoreTxt.textContent = n; if (n >= score) clearInterval(iv); }, 20);
    }
    // Grade badge
    if (gradeBadge) {
        gradeBadge.className = `inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-black uppercase tracking-wider border ${gradeStyle}`;
        gradeBadge.textContent = (data.grade_icon||'') + ' ' + (data.grade||'');
    }
    if (summaryTxt) summaryTxt.textContent = data.summary || '';

    // Mini stats
    if (miniStats && data.stats) {
        const s = data.stats;
        miniStats.innerHTML = [
            {v: s.tech_skills,  l:'Skills'},
            {v: s.action_verbs, l:'Verbs'},
            {v: s.metrics,      l:'Metrics'},
        ].map((x,i) => `<div class="stat-pop text-center" style="animation-delay:${i*100}ms"><div class="text-sm font-black text-slate-900 dark:text-white">${x.v}</div><div class="text-[9px] font-bold text-slate-400 uppercase tracking-wide">${x.l}</div></div>`).join('');
    }

    // ── Checks tab (progress bars) ──
    if (tabChecks) {
        tabChecks.innerHTML = '';
        tabChecks.classList.remove('hidden');
        tabChecks.style.display = 'flex';
        tabChecks.style.flexDirection = 'column';
        const sCfg = {
            pass: { bar:'bg-green-500', ico:`<svg class="w-3.5 h-3.5 text-green-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>`, pct:'text-green-600 dark:text-green-400' },
            warn: { bar:'bg-amber-400', ico:`<svg class="w-3.5 h-3.5 text-amber-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>`, pct:'text-amber-600 dark:text-amber-400' },
            fail: { bar:'bg-red-500',   ico:`<svg class="w-3.5 h-3.5 text-red-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/></svg>`, pct:'text-red-600 dark:text-red-400' },
        };
        (data.checks||[]).forEach((c, idx) => {
            const cfg = sCfg[c.status] || sCfg.warn;
            const sc  = c.score ?? 0;
            const div = document.createElement('div');
            div.className = 'check-row p-3 rounded-xl border border-slate-100 dark:border-white/5 bg-slate-50/50 dark:bg-white/[.02]';
            div.style.animationDelay = (idx * 70) + 'ms';
            div.innerHTML = `
                <div class="flex items-center justify-between gap-2 mb-1.5">
                    <div class="flex items-center gap-1.5 min-w-0">${cfg.ico}<span class="text-xs font-bold text-slate-700 dark:text-gray-300 truncate">${c.label}</span></div>
                    <span class="text-xs font-black ${cfg.pct} shrink-0">${sc}%</span>
                </div>
                <div class="w-full bg-slate-200 dark:bg-white/10 rounded-full h-1.5 mb-1.5 overflow-hidden">
                    <div class="ats-bar-fill ${cfg.bar} h-1.5 rounded-full" style="width:0" data-target="${sc}"></div>
                </div>
                <p class="text-[10px] text-slate-500 dark:text-gray-500 leading-snug">${c.detail}</p>`;
            tabChecks.appendChild(div);
        });
        setTimeout(() => {
            tabChecks.querySelectorAll('.ats-bar-fill').forEach(b => { b.style.width = b.dataset.target + '%'; });
        }, 250);
    }

    // ── Feedback tab ──
    if (strengthsList) strengthsList.innerHTML = (data.strengths||[]).map(s => `<li class="flex items-start gap-1.5 leading-snug"><svg class="w-3 h-3 text-emerald-500 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg><span>${s}</span></li>`).join('');
    if (improvList)    improvList.innerHTML    = (data.improvements||[]).map(i => `<li class="flex items-start gap-1.5 leading-snug"><svg class="w-3 h-3 text-amber-500 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 10l7-7m0 0l7 7m-7-7v18"/></svg><span>${i}</span></li>`).join('');

    // ── Keywords tab ──
    if (kwFound)   kwFound.innerHTML   = (data.keywords_found||[]).map(k => `<span class="kw-badge px-2.5 py-1 rounded-full text-[10px] font-bold bg-emerald-50 dark:bg-emerald-900/20 text-emerald-700 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-500/30">${k}</span>`).join('');
    if (kwMissing) kwMissing.innerHTML = (data.keywords_missing||[]).map(k => `<span class="kw-badge px-2.5 py-1 rounded-full text-[10px] font-bold bg-red-50 dark:bg-red-900/20 text-red-600 dark:text-red-400 border border-red-200 dark:border-red-500/30">${k}</span>`).join('');
}

function showError(msg) {
    if (!scanError || !scanErrTxt) return;
    scanErrTxt.textContent = msg;
    scanError.classList.remove('hidden');
    setTimeout(() => scanError?.classList.add('hidden'), 12000);
}

// 6. FAQ Accordion
document.querySelectorAll('.faq-toggle').forEach(toggle => {
    toggle.addEventListener('click', () => {
        const answer = toggle.nextElementSibling;
        const icon   = toggle.querySelector('svg');
        const isOpen = answer.classList.contains('open');

        document.querySelectorAll('.faq-answer').forEach(a => a.classList.remove('open'));
        document.querySelectorAll('.faq-toggle svg').forEach(i => { i.style.transform = ''; });

        if (!isOpen) {
            answer.classList.add('open');
            icon.style.transform = 'rotate(180deg)';
        }
    });
});

// 7. 3D Card Tilt
document.querySelectorAll('.glass-card').forEach(card => {
    card.addEventListener('mousemove', e => {
        const r = card.getBoundingClientRect();
        const rx = ((e.clientY - r.top  - r.height / 2) / (r.height / 2)) * -8;
        const ry = ((e.clientX - r.left - r.width  / 2) / (r.width  / 2)) *  8;
        card.style.transform = `perspective(1000px) rotateX(${rx}deg) rotateY(${ry}deg) scale3d(1.02,1.02,1.02)`;
    });
    card.addEventListener('mouseleave', () => { card.style.transform = ''; });
});
</script>

<?php if (file_exists('chatbot.php')) include 'chatbot.php'; ?>
</body>
</html>
