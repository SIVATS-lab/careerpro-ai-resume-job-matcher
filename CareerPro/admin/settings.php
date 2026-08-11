<?php
declare(strict_types=1);
session_start();

/**
 * ============================================================================
 * CareerPro Suite - Admin Platform Config & API Key Management
 * Version: 5.0.0 (Enterprise UI)
 * Architecture: Admin Session Guard, System Settings CRUD via PDO, 
 * Real-time Database Synchronization.
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

// Generate CSRF token for the settings form
if (empty($_SESSION['admin_csrf_token'])) {
    $_SESSION['admin_csrf_token'] = bin2hex(random_bytes(32));
}

// 2. HANDLE SETTINGS UPDATES (POST REQUEST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF validation
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['admin_csrf_token'], $_POST['csrf_token'])) {
        $msg = "Security token invalid. Please reload the page.";
        $msgType = "error";
    } else {
        $platformName    = trim($_POST['platform_name'] ?? 'CareerPro Suite');
        $supportEmail    = trim($_POST['support_email'] ?? 'support@careerpro.com');
        $maintenanceMode = trim($_POST['maintenance_mode'] ?? 'false');
        $geminiApiKey    = trim($_POST['gemini_api_key'] ?? '');

    try {
        $db->beginTransaction();

        $stmt = $db->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (:key, :val) ON DUPLICATE KEY UPDATE setting_value = :val2");
        
        // Update Platform Name
        $stmt->execute(['key' => 'platform_name', 'val' => $platformName, 'val2' => $platformName]);
        // Update Support Email
        $stmt->execute(['key' => 'support_email', 'val' => $supportEmail, 'val2' => $supportEmail]);
        // Update Maintenance Mode
        $stmt->execute(['key' => 'maintenance_mode', 'val' => $maintenanceMode, 'val2' => $maintenanceMode]);
        // Update Gemini API Key
        $stmt->execute(['key' => 'gemini_api_key', 'val' => $geminiApiKey, 'val2' => $geminiApiKey]);

        $db->commit();
        $msg = "System configuration and API keys synchronized successfully.";
        $msgType = "success";
    } catch (PDOException $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        error_log("Admin Settings Update Error: " . $e->getMessage());
        $msg = "Failed to update system parameters. Database fault.";
        $msgType = "error";
    }
    } // end CSRF check
}

// 3. FETCH CURRENT SYSTEM SETTINGS
$settings = [];
try {
    $settingStmt = $db->query("SELECT setting_key, setting_value FROM system_settings");
    while ($row = $settingStmt->fetch(PDO::FETCH_ASSOC)) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
} catch (PDOException $e) {
    // Fallback defaults (no API key — must be set via admin panel)
    $settings = [
        'platform_name' => 'CareerPro Suite',
        'support_email' => 'support@careerpro.com',
        'maintenance_mode' => 'false',
        'gemini_api_key' => ''
    ];
}
?>
<!DOCTYPE html>
<html lang="en" class="scroll-smooth dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Platform Config | Admin Portal</title>

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
                        dark: { 950: '#020202', 900: '#050505', 850: '#0a0a0a', 800: '#0f111a', 700: '#1e293b' }
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
        .settings-section { animation: fadeInCard 0.5s ease-out 0.2s both; }
        .input-focus-line::after { content:''; display:block; height:2px; background:linear-gradient(90deg,#df3c3c,#ea6d6d); transform:scaleX(0); transition:transform .3s; border-radius:9999px; }
        .input-field:focus-within ~ .input-focus-line::after { transform:scaleX(1); }

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

        .btn-primary { background: linear-gradient(135deg, #df3c3c 0%, #a61c1c 100%); color: white; transition: all 0.3s ease; border: none; cursor: pointer; }
        .btn-primary:hover { box-shadow: 0 10px 25px -5px rgba(223, 60, 60, 0.4); transform: translateY(-1px); }

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
                <a href="users.php" class="nav-link rounded-xl">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                    Student Roster
                </a>
                <a href="settings.php" class="nav-link active rounded-xl">
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
            <h1 class="text-lg font-black text-slate-900 dark:text-white tracking-tight">Platform Configuration & API Keys</h1>
            
            <button id="theme-toggle" class="relative focus:outline-none hover:scale-105 transition-transform">
                <div class="theme-toggle-label shadow-inner border border-slate-300 dark:border-white/10">
                    <div class="theme-toggle-ball">
                        <svg id="theme-toggle-light-icon" class="w-3.5 h-3.5 text-amber-500 hidden dark:block absolute" fill="currentColor" viewBox="0 0 20 20"><path d="M10 2a1 1 0 011 1v1a1 1 0 11-2 0V3a1 1 0 011-1zm4.22 4.22a1 1 0 011.415 0l.708.708a1 1 0 01-1.414 1.414l-.708-.708a1 1 0 010-1.414zm3.78 3.78a1 1 0 010 2h-1a1 1 0 110-2h1zm-4.22 4.22a1 1 0 010 1.415l-.708.708a1 1 0 011.414-1.414l.708.708a1 1 0 010 1.414zm-3.78-3.78a1 1 0 010-2h1a1 1 0 110 2h-1zm4.22-4.22a1 1 0 010-1.415l.708-.708a1 1 0 011.414 1.414l.708.708a1 1 0 01-1.414 0zM10 16a1 1 0 011 1v1a1 1 0 11-2 0v-1a1 1 0 011-1zm-4.22-4.22a1 1 0 01-1.415 0l-.708-.708a1 1 0 011.414-1.414l.708.708a1 1 0 010 1.414zm-3.78-3.78a1 1 0 010-2h1a1 1 0 110 2h-1zm4.22-4.22a1 1 0 010-1.415l.708-.708a1 1 0 011.414 1.414l.708.708a1 1 0 01-1.414 0zM10 5a5 5 0 100 10 5 5 0 000-10z"></path></svg>
                        <svg id="theme-toggle-dark-icon" class="w-3.5 h-3.5 text-slate-700 block dark:hidden absolute" fill="currentColor" viewBox="0 0 20 20"><path d="M17.293 13.293A8 8 0 016.707 2.707a8.001 8.001 0 1010.586 10.586z"></path></svg>
                    </div>
                </div>
            </button>
        </header>

        <div class="flex-1 overflow-y-auto p-6 lg:p-10 pb-40 relative z-10">
            <div class="max-w-4xl mx-auto space-y-8">
                
                <?php if(!empty($msg)): ?>
                <div class="p-4 rounded-2xl text-sm font-bold border flex items-center gap-3 shadow-sm <?php echo $msgType === 'success' ? 'bg-green-50 dark:bg-green-900/20 border-green-200 dark:border-green-500/30 text-green-700 dark:text-green-400' : 'bg-red-50 dark:bg-red-900/20 border-red-200 dark:border-red-500/30 text-red-700 dark:text-red-400'; ?>">
                    <?php echo htmlspecialchars($msg); ?>
                </div>
                <?php endif; ?>

                <div class="glass-card p-8 md:p-10 rounded-3xl settings-section">
                    <h3 class="text-xl font-black uppercase tracking-wider text-slate-900 dark:text-white mb-2">Global System Parameters</h3>
                    <p class="text-xs text-slate-500 dark:text-gray-400 mb-8 font-medium">Configure network-wide behaviors, support endpoints, and AI integration keys.</p>
                    
                    <form action="settings.php" method="POST" class="space-y-6">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['admin_csrf_token']); ?>">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="space-y-2">
                                <label class="text-[10px] font-black uppercase tracking-widest text-slate-400">Platform Name</label>
                                <input type="text" name="platform_name" value="<?php echo htmlspecialchars($settings['platform_name'] ?? 'CareerPro Suite'); ?>" required class="input-field w-full rounded-xl px-4 py-3 text-sm font-semibold">
                            </div>
                            <div class="space-y-2">
                                <label class="text-[10px] font-black uppercase tracking-widest text-slate-400">Support Helpdesk Email</label>
                                <input type="email" name="support_email" value="<?php echo htmlspecialchars($settings['support_email'] ?? 'support@careerpro.com'); ?>" required class="input-field w-full rounded-xl px-4 py-3 text-sm font-semibold">
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="space-y-2">
                                <label class="text-[10px] font-black uppercase tracking-widest text-slate-400">Maintenance Mode</label>
                                <select name="maintenance_mode" class="input-field w-full rounded-xl px-4 py-3 text-sm font-semibold">
                                    <option value="false" class="bg-dark-900" <?php echo ($settings['maintenance_mode'] ?? 'false') === 'false' ? 'selected' : ''; ?>>Disabled (Normal Operation)</option>
                                    <option value="true" class="bg-dark-900" <?php echo ($settings['maintenance_mode'] ?? 'false') === 'true' ? 'selected' : ''; ?>>Enabled (Lockdown Portal)</option>
                                </select>
                            </div>
                            <div class="space-y-2">
                                <label class="text-[10px] font-black uppercase tracking-widest text-slate-400">Google Gemini API Key</label>
                                <div class="relative">
                                    <input type="password" id="gemini-key" name="gemini_api_key" value="<?php echo htmlspecialchars($settings['gemini_api_key'] ?? ''); ?>" class="input-field w-full rounded-xl px-4 py-3 pr-12 text-sm font-semibold tracking-widest" placeholder="AIzaSy...">
                                    <button type="button" onclick="toggleKey()" class="absolute right-3 top-1/2 -translate-y-1/2 p-2 text-slate-400 hover:text-pcte-500 transition-colors cursor-pointer">
                                        <svg id="eye-show" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                        <svg id="eye-hide" class="w-5 h-5 hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.29 3.29m0 0a10.05 10.05 0 015.188-1.583c4.477 0 8.268 2.943 9.542 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/></svg>
                                    </button>
                                </div>
                                <p class="text-[10px] text-slate-400 font-medium">Get a free key at <a href="https://aistudio.google.com/app/apikey" target="_blank" class="text-pcte-500 hover:underline">aistudio.google.com</a>. Leave blank to use free Pollinations AI fallback.</p>
                            </div>
                        </div>

                        <div class="pt-4 border-t border-slate-200 dark:border-white/10 flex items-center justify-between gap-4 flex-wrap">
                            <div class="flex items-center gap-2 text-xs text-slate-500 dark:text-gray-400">
                                <svg class="w-4 h-4 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                                <span class="font-semibold">CSRF Protected · PDO Secured · Transaction-Safe</span>
                            </div>
                            <button type="submit" class="btn-primary font-black px-10 py-4 rounded-xl uppercase text-xs tracking-widest shadow-lg cursor-pointer flex items-center gap-2">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                                Save Platform Configuration
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Info Card -->
                <div class="glass-card p-6 rounded-2xl settings-section">
                    <h4 class="text-sm font-black uppercase tracking-wider text-slate-700 dark:text-gray-300 mb-4">System Information</h4>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                        <div class="p-4 rounded-xl bg-slate-50 dark:bg-white/5 border border-slate-100 dark:border-white/5">
                            <p class="text-[10px] font-black uppercase tracking-wider text-slate-400 mb-1">PHP Version</p>
                            <p class="text-sm font-bold text-slate-800 dark:text-white"><?php echo PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION; ?></p>
                        </div>
                        <div class="p-4 rounded-xl bg-slate-50 dark:bg-white/5 border border-slate-100 dark:border-white/5">
                            <p class="text-[10px] font-black uppercase tracking-wider text-slate-400 mb-1">Database</p>
                            <p class="text-sm font-bold text-slate-800 dark:text-white">MySQL / MariaDB</p>
                        </div>
                        <div class="p-4 rounded-xl bg-slate-50 dark:bg-white/5 border border-slate-100 dark:border-white/5">
                            <p class="text-[10px] font-black uppercase tracking-wider text-slate-400 mb-1">AI Provider</p>
                            <p class="text-sm font-bold text-slate-800 dark:text-white"><?php echo !empty($settings['gemini_api_key']) ? 'Gemini AI' : 'Pollinations (Free)'; ?></p>
                        </div>
                        <div class="p-4 rounded-xl bg-slate-50 dark:bg-white/5 border border-slate-100 dark:border-white/5">
                            <p class="text-[10px] font-black uppercase tracking-wider text-slate-400 mb-1">Maintenance</p>
                            <p class="text-sm font-bold <?php echo ($settings['maintenance_mode']??'false')==='true' ? 'text-red-500' : 'text-green-500'; ?>"><?php echo ($settings['maintenance_mode']??'false')==='true' ? '🔒 Active' : '✅ Normal'; ?></p>
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

        function toggleKey() {
            const inp = document.getElementById('gemini-key');
            const show = document.getElementById('eye-show');
            const hide = document.getElementById('eye-hide');
            if (inp.type === 'password') {
                inp.type = 'text';
                show.classList.add('hidden'); hide.classList.remove('hidden');
            } else {
                inp.type = 'password';
                hide.classList.add('hidden'); show.classList.remove('hidden');
            }
        }
    </script>
</body>
</html>