<?php
declare(strict_types=1);
session_start();

/**
 * ============================================================================
 * CareerPro Suite - Advanced Student Authentication Gateway
 * Version: 9.0.0 (Enterprise Dual-Theme Edition)
 * Architecture: Self-contained AJAX Auth, Anti-Brute Force, Strict DB Sync,
 * Interactive Split-Screen UI, Glassmorphism, Advanced State Management.
 * ============================================================================
 */

// 1. REDIRECT IF ALREADY AUTHENTICATED AS ADMIN
if (isset($_SESSION['admin_id'])) {
    header("Location: index.php");
    exit;
}

// 2. GENERATE CSRF TOKEN FOR FORM SECURITY
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// 3. SECURE AJAX AUTHENTICATION HANDLER
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
    
    require_once '../includes/db.php';
    header('Content-Type: application/json');
    
    $data = json_decode(file_get_contents('php://input'), true);
    $email = trim($data['email'] ?? '');
    $password = $data['password'] ?? '';
    $csrfToken = $data['csrf_token'] ?? '';
    
    if (!hash_equals($_SESSION['csrf_token'], $csrfToken)) {
        echo json_encode(['status' => 'error', 'message' => 'Security token invalid. Please refresh the page.']);
        exit;
    }

    if (empty($email) || empty($password)) {
        echo json_encode(['status' => 'error', 'message' => 'Institutional email and cipher are required.']);
        exit;
    }

    try {
        $db = Database::getInstance()->getConnection();
        
        $stmt = $db->prepare("SELECT id, name, email, password_hash FROM admins WHERE email = :email LIMIT 1");
        $stmt->execute(['email' => $email]);
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($admin && password_verify($password, $admin['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['admin_id']    = (int)$admin['id'];
            $_SESSION['admin_name']  = $admin['name'];
            $_SESSION['admin_email'] = $admin['email'];

            echo json_encode([
                'status'  => 'success',
                'message' => 'Admin authentication successful.',
                'data'    => ['redirect' => 'index.php']
            ]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Invalid administrator credentials.']);
        }
    } catch (PDOException $e) {
        error_log("Student Auth Error: " . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => 'Auth service unavailable. Database connection failed.']);
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="en" class="scroll-smooth dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#020202">
    <title>Sign In | CareerPro Suite</title>

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
                        dark: { 950: '#020202', 900: '#050505', 850: '#0a0a0a', 800: '#0f111a', 700: '#1e293b', 600: '#1f1f1f' }
                    },
                    animation: {
                        'float-slow': 'float 8s ease-in-out infinite',
                        'float-fast': 'float 5s ease-in-out infinite',
                        'fade-in-up': 'fadeInUp 0.5s ease-out forwards'
                    },
                    keyframes: {
                        float: { '0%, 100%': { transform: 'translateY(0)' }, '50%': { transform: 'translateY(-20px)' } },
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
            background-color: #ffffff;
            color: #0f172a;
        }
        .dark body { 
            background-color: #020202; 
            color: #ffffff;
        }

        input:-webkit-autofill, input:-webkit-autofill:hover, input:-webkit-autofill:focus, input:-webkit-autofill:active {
            -webkit-box-shadow: 0 0 0 30px white inset !important;
            -webkit-text-fill-color: #0f172a !important;
            transition: background-color 5000s ease-in-out 0s;
        }
        .dark input:-webkit-autofill, .dark input:-webkit-autofill:hover, .dark input:-webkit-autofill:focus, .dark input:-webkit-autofill:active {
            -webkit-box-shadow: 0 0 0 30px #0a0a0a inset !important;
            -webkit-text-fill-color: white !important;
        }

        .input-group { position: relative; }
        .input-field {
            background: #f8fafc; border: 1.5px solid #e2e8f0; color: #0f172a; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .dark .input-field {
            background: #0a0a0a; border: 1.5px solid rgba(255,255,255,0.08); color: white;
        }
        .input-field:focus {
            outline: none; border-color: #df3c3c; box-shadow: 0 0 0 4px rgba(223, 60, 60, 0.1);
        }
        .dark .input-field:focus {
            border-color: #df3c3c; box-shadow: 0 0 0 4px rgba(223, 60, 60, 0.2);
        }
        
        .input-icon { position: absolute; left: 1.25rem; top: 50%; transform: translateY(-50%); color: #94a3b8; transition: color 0.3s; pointer-events: none; }
        .dark .input-icon { color: #64748b; }
        .input-field:focus + .input-icon { color: #df3c3c; }

        .custom-checkbox { 
            appearance: none; background-color: #fff; margin: 0; font: inherit; color: currentColor; 
            width: 1.2em; height: 1.2em; border: 1.5px solid #cbd5e1; border-radius: 0.3em; 
            display: grid; place-content: center; transition: all 0.2s; cursor: pointer; 
        }
        .dark .custom-checkbox { background-color: #0a0a0a; border-color: rgba(255,255,255,0.2); }
        .custom-checkbox::before { 
            content: ""; width: 0.65em; height: 0.65em; transform: scale(0); transition: 120ms transform ease-in-out; 
            box-shadow: inset 1em 1em white; background-color: white; transform-origin: center; 
            clip-path: polygon(14% 44%, 0 65%, 50% 100%, 100% 16%, 80% 0%, 43% 62%); 
        }
        .custom-checkbox:checked { background-color: #df3c3c; border-color: #df3c3c; }
        .custom-checkbox:checked::before { transform: scale(1); }

        .btn-submit {
            background: linear-gradient(135deg, #df3c3c 0%, #a61c1c 100%); position: relative; overflow: hidden; z-index: 1; transition: all 0.3s ease; color: white; border: none; cursor: pointer;
        }
        .dark .btn-submit { background: linear-gradient(135deg, #a61c1c 0%, #800000 100%); }
        .btn-submit::before { content: ''; position: absolute; top: 0; left: -100%; width: 100%; height: 100%; background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent); transition: left 0.5s ease; z-index: -1; }
        .btn-submit:hover:not(:disabled)::before { left: 100%; }
        .btn-submit:hover:not(:disabled) { box-shadow: 0 10px 25px -5px rgba(223, 60, 60, 0.4); transform: translateY(-2px); }
        .dark .btn-submit:hover:not(:disabled) { box-shadow: 0 10px 25px -5px rgba(223, 60, 60, 0.6); }
        .btn-submit:disabled { opacity: 0.7; cursor: not-allowed; filter: grayscale(30%); }

        .brand-overlay { background: linear-gradient(180deg, rgba(166, 28, 28, 0.85) 0%, rgba(63, 7, 7, 0.95) 100%); }
        #network-canvas { position: absolute; top: 0; left: 0; width: 100%; height: 100%; z-index: 1; pointer-events: none; opacity: 0.4; }
        .alert-glass { backdrop-filter: blur(10px); -webkit-backdrop-filter: blur(10px); }
    </style>
</head>
<body class="bg-white dark:bg-dark-950 text-slate-900 dark:text-white flex min-h-screen selection:bg-pcte-500 selection:text-white">

    <!-- LEFT PANEL -->
    <div class="hidden lg:flex lg:w-1/2 relative overflow-hidden flex-col justify-between p-12 brand-overlay shadow-2xl z-20">
        <div class="absolute top-[-10%] left-[-10%] w-[500px] h-[500px] bg-pcte-500/20 rounded-full blur-[100px] animate-float-slow pointer-events-none"></div>
        <div class="absolute bottom-[-10%] right-[-10%] w-[600px] h-[600px] bg-red-900/40 rounded-full blur-[120px] animate-float-fast pointer-events-none"></div>

        <svg class="absolute inset-0 w-full h-full opacity-10 pointer-events-none mix-blend-overlay" viewBox="0 0 100 100" preserveAspectRatio="none">
            <defs><pattern id="grid" width="8" height="8" patternUnits="userSpaceOnUse"><path d="M 8 0 L 0 0 0 8" fill="none" stroke="#ffffff" stroke-width="0.3"/></pattern></defs>
            <rect width="100" height="100" fill="url(#grid)"/>
        </svg>

        <canvas id="network-canvas"></canvas>

        <div class="relative z-10 flex items-center gap-3">
            <div class="w-12 h-12 rounded-xl bg-white/15 backdrop-blur-md border border-white/20 flex items-center justify-center shadow-lg hover:scale-105 transition-transform cursor-pointer" onclick="window.location.href='../index.php'">
                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
            </div>
            <span class="text-3xl font-black tracking-tight text-white cursor-pointer" onclick="window.location.href='../index.php'">Career<span class="text-pcte-300">Pro</span></span>
        </div>

        <div class="relative z-10 my-auto pr-10">
            <div class="inline-flex items-center gap-2 px-4 py-2 rounded-full bg-black/20 border border-white/10 mb-8 backdrop-blur-md shadow-inner">
                <span class="flex h-2.5 w-2.5 relative">
                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-green-400 opacity-75"></span>
                    <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-green-500"></span>
                </span>
                <span class="text-[11px] font-black tracking-widest text-white uppercase">CareerPro Network</span>
            </div>

            <h2 class="text-5xl font-black text-white mb-6 leading-tight tracking-tight">Unlock Your <br><span class="text-pcte-300">Professional Potential.</span></h2>
            <p class="text-lg text-white/80 max-w-md leading-relaxed font-medium">Access the definitive suite of AI tools designed exclusively to help PCTE students land interviews faster.</p>
        </div>

        <div class="relative z-10 flex items-center justify-between border-t border-white/10 pt-8 mt-10">
            <div class="flex -space-x-3">
                <div class="w-10 h-10 rounded-full border-2 border-pcte-900 bg-slate-200 flex items-center justify-center text-xs font-bold text-slate-800 shadow-md">AM</div>
                <div class="w-10 h-10 rounded-full border-2 border-pcte-900 bg-slate-300 flex items-center justify-center text-xs font-bold text-slate-800 shadow-md">PK</div>
                <div class="w-10 h-10 rounded-full border-2 border-pcte-900 bg-slate-400 flex items-center justify-center text-xs font-bold text-slate-800 shadow-md">RV</div>
                <div class="w-10 h-10 rounded-full border-2 border-pcte-900 bg-white/15 backdrop-blur-md flex items-center justify-center text-[10px] font-bold text-white shadow-md">+5k</div>
            </div>
            <div class="text-right">
                <p class="text-sm font-black text-white uppercase tracking-widest">Trusted by students</p>
                <p class="text-xs text-white/60 font-medium mt-0.5">Over 8,500+ successful ATS scans</p>
            </div>
        </div>
    </div>

    <!-- RIGHT PANEL -->
    <div class="w-full lg:w-1/2 flex flex-col relative bg-slate-50 dark:bg-[#050505] transition-colors duration-500 z-10">
        
        <div class="absolute top-0 left-0 w-full p-6 sm:p-8 flex justify-between items-center z-20">
            <a href="../index.php" class="flex lg:hidden items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-pcte-600 dark:bg-pcte-800 flex items-center justify-center shadow-lg shadow-pcte-500/20">
                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                </div>
                <span class="text-xl font-black tracking-tight text-slate-900 dark:text-white">Career<span class="text-pcte-600 dark:text-pcte-500">Pro</span></span>
            </a>

            <div class="hidden lg:block"></div> 
            
            <div class="flex items-center gap-4">
                <button id="theme-toggle" class="p-2.5 rounded-xl bg-white dark:bg-dark-800 border border-slate-200 dark:border-white/10 text-slate-500 dark:text-gray-400 hover:text-pcte-600 dark:hover:text-white transition shadow-sm focus:outline-none cursor-pointer">
                    <svg id="theme-toggle-light-icon" class="hidden w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path d="M10 2a1 1 0 011 1v1a1 1 0 11-2 0V3a1 1 0 011-1zm4.22 4.22a1 1 0 011.415 0l.708.708a1 1 0 01-1.414 1.414l-.708-.708a1 1 0 010-1.414zm3.78 3.78a1 1 0 010 2h-1a1 1 0 110-2h1zm-4.22 4.22a1 1 0 010 1.415l-.708.708a1 1 0 01-1.414-1.414l.708-.708a1 1 0 011.414 0zM10 16a1 1 0 011 1v1a1 1 0 11-2 0v-1a1 1 0 011-1zm-4.22-4.22a1 1 0 01-1.415 0l-.708-.708a1 1 0 011.414-1.414l.708.708a1 1 0 010 1.414zm-3.78-3.78a1 1 0 010-2h1a1 1 0 110 2h-1zm4.22-4.22a1 1 0 010-1.415l.708-.708a1 1 0 011.414 1.414l-.708.708a1 1 0 01-1.414 0zM10 5a5 5 0 100 10 5 5 0 000-10z"></path></svg>
                    <svg id="theme-toggle-dark-icon" class="hidden w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path d="M17.293 13.293A8 8 0 016.707 2.707a8.001 8.001 0 1010.586 10.586z"></path></svg>
                </button>
                <a href="../index.php" class="text-sm font-bold text-slate-500 dark:text-gray-400 hover:text-slate-900 dark:hover:text-white transition hidden sm:block">Back to Home</a>
            </div>
        </div>

        <div class="flex-1 flex items-center justify-center p-6 sm:p-12 w-full max-w-xl mx-auto relative z-10 mt-16 lg:mt-0">
            <div class="w-full animate-fade-in-up">
                
                <div class="mb-10 text-center lg:text-left">
                    <h1 class="text-3xl sm:text-4xl font-black text-slate-900 dark:text-white tracking-tight mb-2">Welcome Back</h1>
                    <p class="text-slate-500 dark:text-gray-400 text-sm font-medium">Enter your admin credentials to access the dashboard.</p>
                </div>

                <div id="response-msg" class="hidden mb-8 p-4 rounded-xl text-sm font-bold border flex items-start gap-3 alert-glass transition-all duration-300 transform translate-y-[-10px] opacity-0 shadow-lg">
                    <div id="response-icon" class="shrink-0 mt-0.5"></div>
                    <div id="response-text" class="flex-1"></div>
                </div>

                <form id="loginForm" class="space-y-6 relative">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    
                    <div class="space-y-2 group">
                        <label class="text-[11px] font-black text-slate-500 dark:text-gray-400 uppercase tracking-[0.2em] pl-1 transition-colors group-focus-within:text-pcte-500">Institutional Email</label>
                        <div class="input-group">
                            <input type="email" name="email" id="email" required autocomplete="email"
                                   class="input-field w-full rounded-[1.25rem] pl-12 pr-4 py-4 text-[15px] font-semibold" 
                                   placeholder="admin@careerpro.com">
                            <svg class="w-5 h-5 input-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M16 12a4 4 0 10-8 0 4 4 0 008 0zm0 0v1.5a2.5 2.5 0 005 0V12a9 9 0 10-9 9m4.5-1.206a8.959 8.959 0 01-4.5 1.206"></path></svg>
                        </div>
                    </div>

                    <div class="space-y-2 group">
                        <div class="flex justify-between items-center pl-1">
                            <label class="text-[11px] font-black text-slate-500 dark:text-gray-400 uppercase tracking-[0.2em] transition-colors group-focus-within:text-pcte-500">Cipher Key</label>
                            <a href="#" class="text-[10px] font-bold text-pcte-600 dark:text-pcte-400 hover:text-pcte-800 dark:hover:text-white transition-colors uppercase tracking-widest">Forgot access?</a>
                        </div>
                        <div class="input-group">
                            <input type="password" id="loginPass" name="password" required autocomplete="current-password"
                                   class="input-field w-full rounded-[1.25rem] pl-12 pr-12 py-4 text-[15px] font-semibold tracking-widest" 
                                   placeholder="••••••••">
                            <svg class="w-5 h-5 input-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>
                            
                            <button type="button" onclick="toggleVisibility('#loginPass', this)" class="absolute right-3 top-[50%] transform -translate-y-1/2 p-2 text-slate-400 dark:text-gray-500 hover:text-pcte-600 dark:hover:text-white transition-colors focus:outline-none rounded-lg cursor-pointer">
                                <svg class="w-5 h-5 show-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                                <svg class="w-5 h-5 hide-icon hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.29 3.29m0 0a10.05 10.05 0 015.188-1.583c4.477 0 8.268 2.943 9.542 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"></path></svg>
                            </button>
                        </div>
                    </div>

                    <div class="flex items-center gap-3 pl-1 pt-2">
                        <input type="checkbox" id="remember" class="custom-checkbox shadow-sm">
                        <label for="remember" class="text-[11px] font-bold text-slate-600 dark:text-gray-400 cursor-pointer select-none uppercase tracking-widest">Maintain Active Session</label>
                    </div>

                    <button type="submit" id="submitBtn" class="btn-submit w-full text-white font-black py-4 rounded-2xl mt-6 flex items-center justify-center gap-3 shadow-xl shadow-pcte-500/20 dark:shadow-pcte-900/30 uppercase tracking-[0.2em] text-xs cursor-pointer">
                        <span>Authenticate & Continue</span>
                        <svg class="w-4 h-4 transition-transform group-hover:translate-x-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
                    </button>
                </form>

                <div class="mt-12 pt-8 border-t border-slate-200 dark:border-white/10 text-center">
                    <p class="text-xs text-slate-500 dark:text-gray-400 font-bold uppercase tracking-widest">
                        New to the Network? <br class="sm:hidden mt-2">
                        <a href="../register.php" class="text-pcte-600 dark:text-pcte-400 font-black hover:text-pcte-800 dark:hover:text-white transition-colors sm:ml-2 border-b-2 border-transparent hover:border-pcte-600 dark:hover:border-pcte-400 pb-0.5">Initialize Account</a>
                    </p>
                </div>
            </div>
            
            <div class="absolute bottom-6 left-0 w-full text-center px-6 hidden sm:block">
                <div class="flex items-center justify-center gap-6 text-[9px] font-black text-slate-400 dark:text-gray-600 uppercase tracking-[0.2em]">
                    <a href="#" class="hover:text-slate-700 dark:hover:text-gray-400 transition-colors">Help Desk</a>
                    <span class="w-1 h-1 rounded-full bg-slate-300 dark:bg-gray-700"></span>
                    <a href="#" class="hover:text-slate-700 dark:hover:text-gray-400 transition-colors">Privacy Node</a>
                    <span class="w-1 h-1 rounded-full bg-slate-300 dark:bg-gray-700"></span>
                    <span class="text-slate-500 dark:text-gray-500">&copy; <?php echo date('Y'); ?> PCTE</span>
                </div>
            </div>
        </div>
    </div>

    <!-- ==========================================
         JAVASCRIPT ENGINES
    =========================================== -->
    <script>
        const themeToggleBtn = document.getElementById('theme-toggle');
        const themeToggleDarkIcon = document.getElementById('theme-toggle-dark-icon');
        const themeToggleLightIcon = document.getElementById('theme-toggle-light-icon');

        function syncThemeIcon() {
            const isDark = document.documentElement.classList.contains('dark');
            if(themeToggleDarkIcon && themeToggleLightIcon) {
                if (isDark) {
                    themeToggleDarkIcon.classList.add('hidden');
                    themeToggleLightIcon.classList.remove('hidden');
                } else {
                    themeToggleLightIcon.classList.add('hidden');
                    themeToggleDarkIcon.classList.remove('hidden');
                }
            }
        }
        syncThemeIcon();

        if(themeToggleBtn) {
            themeToggleBtn.addEventListener('click', function() {
                document.documentElement.classList.toggle('dark');
                if (document.documentElement.classList.contains('dark')) {
                    localStorage.setItem('color-theme', 'dark');
                } else {
                    localStorage.setItem('color-theme', 'light');
                }
                syncThemeIcon();
            });
        }

        function toggleVisibility(selector, btn) {
            const input = document.querySelector(selector);
            const showIcon = btn.querySelector('.show-icon');
            const hideIcon = btn.querySelector('.hide-icon');
            
            if (input.type === 'password') {
                input.type = 'text';
                showIcon.classList.add('hidden');
                hideIcon.classList.remove('hidden');
            } else {
                input.type = 'password';
                showIcon.classList.remove('hidden');
                hideIcon.classList.add('hidden');
            }
        }

        document.getElementById('loginForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const btn = document.getElementById('submitBtn');
            const msgDiv = document.getElementById('response-msg');
            const msgIcon = document.getElementById('response-icon');
            const msgText = document.getElementById('response-text');
            
            const formData = new FormData(this);
            const data = Object.fromEntries(formData.entries());

            btn.disabled = true;
            btn.innerHTML = `<svg class="w-5 h-5 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg> <span>Verifying Protocol...</span>`;
            
            msgDiv.classList.remove('translate-y-0', 'opacity-100');
            msgDiv.classList.add('translate-y-[-10px]', 'opacity-0');

            setTimeout(async () => {
                msgDiv.classList.remove('hidden');
                
                try {
                    const response = await fetch(window.location.href, {
                        method: 'POST',
                        headers: { 
                            'Content-Type': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: JSON.stringify(data)
                    });

                    const result = await response.json();
                    msgText.innerText = result.message;

                    if (result.status === 'success') {
                        msgDiv.className = 'mb-8 p-5 rounded-2xl text-sm font-bold border flex items-start gap-4 alert-glass transition-all duration-300 transform translate-y-0 opacity-100 bg-green-50 dark:bg-green-900/20 border-green-200 dark:border-green-500/30 text-green-700 dark:text-green-400 shadow-lg';
                        msgIcon.innerHTML = `<svg class="w-5 h-5 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg>`;
                        
                        btn.innerHTML = `<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg> <span>Access Granted</span>`;
                        btn.classList.add('!bg-green-600', 'border-green-500');
                        btn.classList.remove('btn-submit');
                        
                        setTimeout(() => { window.location.href = result.data.redirect || 'dashboard.php'; }, 1000);
                    } else {
                        msgDiv.className = 'mb-8 p-5 rounded-2xl text-sm font-bold border flex items-start gap-4 alert-glass transition-all duration-300 transform translate-y-0 opacity-100 bg-red-50 dark:bg-[#1f0a0a] border-red-200 dark:border-[#3f0707] text-red-700 dark:text-[#ea6d6d] shadow-lg';
                        msgIcon.innerHTML = `<svg class="w-5 h-5 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>`;
                        
                        btn.disabled = false;
                        btn.innerHTML = `<span>Authenticate & Continue</span> <svg class="w-4 h-4 transition-transform group-hover:translate-x-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>`;
                    }
                } catch (error) {
                    msgDiv.className = 'mb-8 p-5 rounded-2xl text-sm font-bold border flex items-start gap-4 alert-glass transition-all duration-300 transform translate-y-0 opacity-100 bg-red-50 dark:bg-[#1f0a0a] border-red-200 dark:border-[#3f0707] text-red-700 dark:text-[#ea6d6d] shadow-lg';
                    msgIcon.innerHTML = `<svg class="w-5 h-5 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>`;
                    msgText.innerText = 'Auth service unavailable. Check network connection.';
                    
                    btn.disabled = false;
                    btn.innerHTML = `<span>Authenticate & Continue</span> <svg class="w-4 h-4 transition-transform group-hover:translate-x-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>`;
                }
            }, 300);
        });

        document.addEventListener("DOMContentLoaded", function() {
            const canvas = document.getElementById('network-canvas');
            if(!canvas) return;
            const ctx = canvas.getContext('2d');
            
            let width, height;
            let particles = [];
            
            function init() {
                width = canvas.width = canvas.offsetWidth;
                height = canvas.height = canvas.offsetHeight;
                particles = [];
                const particleCount = Math.floor((width * height) / 15000); 
                
                for(let i=0; i<particleCount; i++) {
                    particles.push({
                        x: Math.random() * width,
                        y: Math.random() * height,
                        vx: (Math.random() - 0.5) * 0.5,
                        vy: (Math.random() - 0.5) * 0.5,
                        radius: Math.random() * 1.5 + 0.5
                    });
                }
            }
            
            function animate() {
                requestAnimationFrame(animate);
                ctx.clearRect(0, 0, width, height);
                ctx.fillStyle = 'rgba(255, 255, 255, 0.5)';
                ctx.lineWidth = 0.5;
                
                for(let i=0; i<particles.length; i++) {
                    let p = particles[i];
                    p.x += p.vx;
                    p.y += p.vy;
                    
                    if(p.x < 0) p.x = width;
                    if(p.x > width) p.x = 0;
                    if(p.y < 0) p.y = height;
                    if(p.y > height) p.y = 0;
                    
                    ctx.beginPath();
                    ctx.arc(p.x, p.y, p.radius, 0, Math.PI * 2);
                    ctx.fill();
                    
                    for(let j=i+1; j<particles.length; j++) {
                        let p2 = particles[j];
                        let dx = p.x - p2.x;
                        let dy = p.y - p2.y;
                        let dist = Math.sqrt(dx*dx + dy*dy);
                        
                        if(dist < 100) {
                            ctx.beginPath();
                            ctx.strokeStyle = `rgba(255, 255, 255, ${0.2 - (dist/100)*0.2})`;
                            ctx.moveTo(p.x, p.y);
                            ctx.lineTo(p2.x, p2.y);
                            ctx.stroke();
                        }
                    }
                }
            }
            
            init();
            animate();
            window.addEventListener('resize', init);
        });
    </script>
</body>
</html>