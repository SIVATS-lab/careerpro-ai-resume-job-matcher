<?php 
declare(strict_types=1);
session_start();

/**
 * ============================================================================
 * CareerPro Suite - Advanced Split-Screen Registration Portal
 * Version: 9.0.0 (Enterprise Dual-Theme Edition)
 * Handles: Secure Onboarding, Client-side Validation, Theme Management
 * Architecture: Glassmorphism, AJAX Form Submission, Password Strength Meter
 * ============================================================================
 */

// Redirect already authenticated users to the dashboard securely
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit;
}

// Generate CSRF token if it doesn't exist
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>
<!DOCTYPE html>
<html lang="en" class="scroll-smooth dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Create Account | CareerPro Suite</title>

    <!-- Theme Initialization Script to prevent FOUC -->
    <script>
        if (localStorage.getItem('color-theme') === 'dark' || (!('color-theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }
    </script>

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    
    <!-- Tailwind CSS -->
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
                        'float-delayed': 'float 6s ease-in-out 3s infinite',
                        'pulse-glow': 'pulseGlow 4s cubic-bezier(0.4, 0, 0.6, 1) infinite',
                        'fade-in-up': 'fadeInUp 0.5s ease-out forwards',
                        'spin-slow': 'spin 8s linear infinite'
                    },
                    keyframes: {
                        float: { '0%, 100%': { transform: 'translateY(0)' }, '50%': { transform: 'translateY(-20px)' } },
                        pulseGlow: { '0%, 100%': { opacity: 0.4 }, '50%': { opacity: 0.8 } },
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
        }

        /* Autofill Overrides to maintain theme integrity */
        input:-webkit-autofill, input:-webkit-autofill:hover, input:-webkit-autofill:focus, input:-webkit-autofill:active{
            -webkit-box-shadow: 0 0 0 30px white inset !important;
            -webkit-text-fill-color: #0f172a !important;
            transition: background-color 5000s ease-in-out 0s;
        }
        .dark input:-webkit-autofill, .dark input:-webkit-autofill:hover, .dark input:-webkit-autofill:focus, .dark input:-webkit-autofill:active{
            -webkit-box-shadow: 0 0 0 30px #0a0a0a inset !important;
            -webkit-text-fill-color: white !important;
        }

        /* Interactive Input Fields */
        .input-group { position: relative; }
        .input-field {
            background: #f8fafc; border: 1.5px solid #e2e8f0; color: #0f172a; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .dark .input-field {
            background: #0a0a0a; border: 1.5px solid rgba(255,255,255,0.1); color: white;
        }
        .input-field:focus {
            outline: none; border-color: #df3c3c; box-shadow: 0 0 0 4px rgba(223, 60, 60, 0.1);
        }
        .dark .input-field:focus {
            border-color: #df3c3c; box-shadow: 0 0 0 4px rgba(223, 60, 60, 0.2);
        }
        
        .input-icon { position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); color: #94a3b8; transition: color 0.3s; pointer-events: none; }
        .dark .input-icon { color: #64748b; }
        .input-field:focus + .input-icon { color: #df3c3c; }
        .dark .input-field:focus + .input-icon { color: #df3c3c; }

        /* Premium Buttons */
        .btn-submit {
            background: linear-gradient(135deg, #df3c3c 0%, #a61c1c 100%); position: relative; overflow: hidden; z-index: 1; transition: all 0.3s ease;
        }
        .dark .btn-submit { background: linear-gradient(135deg, #a61c1c 0%, #800000 100%); }
        .btn-submit::before { content: ''; position: absolute; top: 0; left: -100%; width: 100%; height: 100%; background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent); transition: left 0.5s ease; z-index: -1; }
        .btn-submit:hover:not(:disabled)::before { left: 100%; }
        .btn-submit:hover:not(:disabled) { box-shadow: 0 10px 25px -5px rgba(223, 60, 60, 0.4); transform: translateY(-2px); }
        .dark .btn-submit:hover:not(:disabled) { box-shadow: 0 10px 25px -5px rgba(223, 60, 60, 0.6); }
        .btn-submit:disabled { opacity: 0.5; cursor: not-allowed; filter: grayscale(50%); }

        /* Custom Checkbox */
        .custom-checkbox { 
            appearance: none; background-color: #f8fafc; margin: 0; font: inherit; color: currentColor; 
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

        /* Left Panel Brand Overlay */
        .brand-overlay { background: linear-gradient(180deg, rgba(166, 28, 28, 0.9) 0%, rgba(63, 7, 7, 0.95) 100%); }
        
        /* Glass Alert Box */
        .alert-glass { backdrop-filter: blur(10px); -webkit-backdrop-filter: blur(10px); }

        /* Password Strength Meter */
        .strength-bar { height: 6px; border-radius: 3px; transition: all 0.4s ease; width: 0%; background-color: #ef4444; }
    </style>
</head>
<body class="bg-white dark:bg-dark-950 text-slate-900 dark:text-white flex min-h-screen selection:bg-pcte-500 selection:text-white">

    <!-- ==========================================
         LEFT PANEL: Branding & Visuals (Hidden on Mobile)
    =========================================== -->
    <div class="hidden lg:flex lg:w-[45%] relative overflow-hidden flex-col justify-between p-12 brand-overlay shadow-2xl z-20">
        
        <!-- Ambient Glowing Orbs -->
        <div class="absolute top-[-10%] left-[-10%] w-[500px] h-[500px] bg-pcte-500/30 rounded-full blur-[80px] animate-float-slow pointer-events-none"></div>
        <div class="absolute bottom-[-10%] right-[-10%] w-[600px] h-[600px] bg-red-900/50 rounded-full blur-[100px] animate-float-fast pointer-events-none"></div>

        <!-- Architectural Grid SVG Overlay -->
        <svg class="absolute inset-0 w-full h-full opacity-10 pointer-events-none mix-blend-overlay" viewBox="0 0 100 100" preserveAspectRatio="none">
            <defs><pattern id="grid" width="8" height="8" patternUnits="userSpaceOnUse"><path d="M 8 0 L 0 0 0 8" fill="none" stroke="#ffffff" stroke-width="0.3"/></pattern></defs>
            <rect width="100" height="100" fill="url(#grid)"/>
        </svg>

        <!-- Header / Logo -->
        <div class="relative z-10 flex items-center justify-between">
            <a href="index.php" class="flex items-center gap-3">
                <div class="w-12 h-12 rounded-xl bg-white/10 backdrop-blur-md border border-white/20 flex items-center justify-center shadow-lg hover:scale-105 transition-transform">
                    <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                </div>
                <span class="text-3xl font-black tracking-tight text-white">Career<span class="text-pcte-300">Pro</span></span>
            </a>
            <a href="index.php" class="text-sm font-bold text-white/70 hover:text-white transition-colors flex items-center gap-2 bg-white/5 px-4 py-2 rounded-full border border-white/10 backdrop-blur">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                Back to Home
            </a>
        </div>

        <!-- Dynamic 3D Graphic -->
        <div class="relative z-10 w-full flex-1 flex flex-col items-center justify-center mt-12 mb-8">
            <div class="w-full max-w-[450px] aspect-square relative flex items-center justify-center">
                <svg viewBox="0 0 400 400" fill="none" xmlns="http://www.w3.org/2000/svg" class="w-full h-full drop-shadow-2xl animate-float-slow">
                    
                    <!-- Nodes -->
                    <g transform="translate(100, 250)">
                        <path d="M100 0 L200 40 L200 120 L100 160 L0 120 L0 40 Z" fill="#4a0f0f" stroke="#ea6d6d" stroke-width="2" opacity="0.6"/>
                        <path d="M100 0 L200 40 L100 80 L0 40 Z" fill="#701616" stroke="#ea6d6d" stroke-width="2" opacity="0.8"/>
                        <path d="M100 80 L200 40" stroke="#ea6d6d" stroke-width="2" opacity="0.8"/>
                        <path d="M100 80 L100 160" stroke="#ea6d6d" stroke-width="2" opacity="0.8"/>
                    </g>

                    <path d="M150 200 Q 200 150 250 180" fill="none" stroke="#ea6d6d" stroke-width="3" stroke-dasharray="8 8" class="animate-pulse"/>
                    <circle cx="150" cy="200" r="5" fill="#fff" class="animate-ping"/>
                    
                    <g transform="translate(40, 100) rotate(-10)" class="animate-float-delayed">
                        <path d="M50 0 L90 20 L90 60 C90 90 70 110 50 120 C30 110 10 90 10 60 L10 20 Z" fill="#800000" stroke="#ea6d6d" stroke-width="3"/>
                        <path d="M35 60 L45 70 L70 40" fill="none" stroke="#fff" stroke-width="5" stroke-linecap="round" stroke-linejoin="round"/>
                    </g>

                    <!-- Abstract Application Profile -->
                    <g transform="translate(140, 60)">
                        <rect width="180" height="220" rx="16" fill="#ffffff" stroke="#cbd5e1" stroke-width="2"/>
                        <rect x="20" y="20" width="140" height="40" rx="8" fill="#f1f5f9"/>
                        <circle cx="45" cy="40" r="12" fill="#df3c3c"/>
                        <rect x="70" y="35" width="70" height="10" rx="5" fill="#cbd5e1"/>
                        
                        <rect x="20" y="80" width="140" height="8" rx="4" fill="#e2e8f0"/>
                        <rect x="20" y="100" width="110" height="8" rx="4" fill="#e2e8f0"/>
                        <rect x="20" y="120" width="130" height="8" rx="4" fill="#e2e8f0"/>
                        
                        <!-- Simulated Scanner -->
                        <rect x="10" y="140" width="160" height="4" fill="#df3c3c" opacity="0.8">
                            <animate attributeName="y" values="20; 200; 20" dur="3s" repeatCount="indefinite" />
                        </rect>
                        <polygon points="10,140 170,140 190,160 -10,160" fill="#df3c3c" opacity="0.2">
                            <animate attributeName="points" values="10,20 170,20 190,40 -10,40; 10,200 170,200 190,220 -10,220; 10,20 170,20 190,40 -10,40" dur="3s" repeatCount="indefinite" />
                        </polygon>

                        <!-- Score Match -->
                        <g transform="translate(130, 160)">
                            <circle cx="20" cy="20" r="25" fill="#ffffff" stroke="#e2e8f0" stroke-width="4"/>
                            <circle cx="20" cy="20" r="25" fill="none" stroke="#22c55e" stroke-width="5" stroke-dasharray="157" stroke-dashoffset="30" stroke-linecap="round"/>
                            <text x="20" y="25" font-family="sans-serif" font-size="14" font-weight="bold" fill="#0f172a" text-anchor="middle">92%</text>
                        </g>
                    </g>
                </svg>
            </div>

            <div class="text-center mt-4">
                <h2 class="text-4xl font-black text-white mb-4 leading-tight tracking-tight">Join the <span class="text-pcte-300">1%</span> of <br>Optimized Applicants.</h2>
                <p class="text-white/80 max-w-md mx-auto leading-relaxed font-medium">Create your free institutional account to unlock the AI Resume Builder, automated ATS Scanner, and exclusive PCTE job matches.</p>
            </div>
        </div>

        <div class="relative z-10 flex items-center justify-between border-t border-white/10 pt-6">
            <div class="flex items-center gap-3">
                <div class="w-2 h-2 rounded-full bg-green-400 animate-pulse"></div>
                <p class="text-[10px] font-black tracking-widest uppercase text-white">End-to-End Encryption</p>
            </div>
            <div class="text-right">
                <p class="text-[10px] font-bold text-white/60 tracking-wider uppercase">Bcrypt Hash Protocol</p>
            </div>
        </div>
    </div>

    <!-- ==========================================
         RIGHT PANEL: Registration Form
    =========================================== -->
    <div class="w-full lg:w-[55%] flex flex-col relative transition-colors duration-500 bg-white dark:bg-[#050505] overflow-y-auto">
        
        <!-- Mobile Top Utility Bar (Theme Toggle & Logo) -->
        <div class="lg:hidden absolute top-0 left-0 w-full p-6 flex justify-between items-center z-20 bg-white/80 dark:bg-dark-950/80 backdrop-blur-md border-b border-slate-100 dark:border-white/5">
            <a href="index.php" class="flex items-center gap-2">
                <div class="w-8 h-8 rounded-lg bg-pcte-600 dark:bg-pcte-800 flex items-center justify-center shadow-lg">
                    <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                </div>
                <span class="text-lg font-extrabold tracking-tight text-slate-900 dark:text-white">Career<span class="text-pcte-600 dark:text-pcte-500">Pro</span></span>
            </a>
            
            <button id="mobile-theme-toggle" class="p-2 rounded-xl bg-slate-100 dark:bg-dark-800 border border-slate-200 dark:border-white/10 text-slate-500 dark:text-gray-400">
                <svg class="w-4 h-4 block dark:hidden" fill="currentColor" viewBox="0 0 20 20"><path d="M10 2a1 1 0 011 1v1a1 1 0 11-2 0V3a1 1 0 011-1zm4.22 4.22a1 1 0 011.415 0l.708.708a1 1 0 01-1.414 1.414l-.708-.708a1 1 0 010-1.414zm3.78 3.78a1 1 0 010 2h-1a1 1 0 110-2h1zm-4.22 4.22a1 1 0 010 1.415l-.708.708a1 1 0 011.414-1.414l.708.708a1 1 0 010 1.414zm-3.78-3.78a1 1 0 010-2h1a1 1 0 110 2h-1zm4.22-4.22a1 1 0 010-1.415l.708-.708a1 1 0 011.414 1.414l-.708.708a1 1 0 01-1.414 0zM10 5a5 5 0 100 10 5 5 0 000-10z"></path></svg>
                <svg class="w-4 h-4 hidden dark:block" fill="currentColor" viewBox="0 0 20 20"><path d="M17.293 13.293A8 8 0 016.707 2.707a8.001 8.001 0 1010.586 10.586z"></path></svg>
            </button>
        </div>

        <!-- Desktop Theme Toggle -->
        <div class="hidden lg:flex absolute top-6 right-8 z-20">
            <button id="theme-toggle" class="p-2.5 rounded-xl bg-white dark:bg-dark-800 border border-slate-200 dark:border-white/10 text-slate-500 dark:text-gray-400 hover:text-pcte-600 dark:hover:text-white transition shadow-sm focus:outline-none group">
                <svg id="theme-toggle-light-icon" class="hidden w-5 h-5 group-hover:rotate-45 transition-transform" fill="currentColor" viewBox="0 0 20 20"><path d="M10 2a1 1 0 011 1v1a1 1 0 11-2 0V3a1 1 0 011-1zm4.22 4.22a1 1 0 011.415 0l.708.708a1 1 0 01-1.414 1.414l-.708-.708a1 1 0 010-1.414zm3.78 3.78a1 1 0 010 2h-1a1 1 0 110-2h1zm-4.22 4.22a1 1 0 010 1.415l-.708.708a1 1 0 011.414-1.414l.708.708a1 1 0 010 1.414zm-3.78-3.78a1 1 0 010-2h1a1 1 0 110 2h-1zm4.22-4.22a1 1 0 010-1.415l.708-.708a1 1 0 011.414 1.414l-.708.708a1 1 0 01-1.414 0zM10 5a5 5 0 100 10 5 5 0 000-10z"></path></svg>
                <svg id="theme-toggle-dark-icon" class="hidden w-5 h-5 group-hover:-rotate-12 transition-transform" fill="currentColor" viewBox="0 0 20 20"><path d="M17.293 13.293A8 8 0 016.707 2.707a8.001 8.001 0 1010.586 10.586z"></path></svg>
            </button>
        </div>

        <div class="flex-1 flex flex-col justify-center px-6 sm:px-16 lg:px-24 py-24 w-full max-w-2xl mx-auto relative z-10 min-h-screen">
            
            <div class="animate-fade-in-up w-full">
                
                <div class="mb-10 text-center lg:text-left">
                    <h1 class="text-3xl sm:text-4xl font-black text-slate-900 dark:text-white tracking-tight mb-3">Provision Account</h1>
                    <p class="text-slate-500 dark:text-gray-400 text-sm font-medium leading-relaxed">Set up your institutional profile to gain access to the CareerPro ecosystem.</p>
                </div>

                <!-- API Response Toast -->
                <div id="response-msg" class="hidden mb-6 p-4 rounded-xl text-sm font-bold border flex items-start gap-3 alert-glass transition-all duration-300 transform translate-y-[-10px] opacity-0 shadow-sm">
                    <div id="response-icon" class="shrink-0 mt-0.5"></div>
                    <div id="response-text" class="flex-1"></div>
                </div>

                <!-- Main Registration Form -->
                <form id="registerForm" class="space-y-5">
                    <input type="hidden" name="action" value="register">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        
                        <!-- Full Name -->
                        <div class="space-y-2 col-span-1 md:col-span-2 group">
                            <label class="text-[11px] font-black text-slate-500 dark:text-gray-400 uppercase tracking-widest pl-1 transition-colors group-focus-within:text-pcte-500">Legal Full Name</label>
                            <div class="input-group">
                                <input type="text" name="name" required 
                                       class="input-field w-full rounded-[1.25rem] pl-12 pr-4 py-4 text-sm font-semibold shadow-sm" 
                                       placeholder="e.g. Alex Developer">
                                <svg class="w-5 h-5 input-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                            </div>
                        </div>

                        <!-- Email Address -->
                        <div class="space-y-2 col-span-1 md:col-span-2 group">
                            <label class="text-[11px] font-black text-slate-500 dark:text-gray-400 uppercase tracking-widest pl-1 transition-colors group-focus-within:text-pcte-500">Email Address</label>
                            <div class="input-group relative">
                                <input type="email" name="email" id="email" required 
                                       class="input-field w-full rounded-[1.25rem] pl-12 pr-4 py-4 text-sm font-semibold shadow-sm" 
                                       placeholder="you@gmail.com">
                                <svg class="w-5 h-5 input-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5 pt-2">
                        
                        <!-- Secure Password -->
                        <div class="space-y-2 relative group">
                            <label class="text-[11px] font-black text-slate-500 dark:text-gray-400 uppercase tracking-widest pl-1 transition-colors group-focus-within:text-pcte-500">Secure Password</label>
                            <div class="input-group">
                                <input type="password" id="regPass" name="password" required 
                                       class="input-field w-full rounded-[1.25rem] pl-12 pr-12 py-4 text-[15px] font-semibold tracking-widest shadow-sm" 
                                       placeholder="••••••••">
                                <svg class="w-5 h-5 input-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>
                                
                                <button type="button" onclick="toggleVisibility('#regPass', this)" class="absolute right-3 top-[50%] transform -translate-y-1/2 p-2 text-slate-400 dark:text-gray-500 hover:text-pcte-600 dark:hover:text-white transition-colors focus:outline-none rounded-lg">
                                    <svg class="w-5 h-5 show-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                                    <svg class="w-5 h-5 hide-icon hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.29 3.29m0 0a10.05 10.05 0 015.188-1.583c4.477 0 8.268 2.943 9.542 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"></path></svg>
                                </button>
                            </div>
                            
                            <div class="w-full bg-slate-200 dark:bg-dark-800 rounded-full h-1.5 mt-2 overflow-hidden shadow-inner">
                                <div id="pass-meter" class="strength-bar"></div>
                            </div>
                            <p id="pass-text" class="text-[9px] text-slate-400 dark:text-gray-500 font-bold uppercase tracking-widest text-right mt-1">Weak</p>
                        </div>

                        <!-- Confirm Identity -->
                        <div class="space-y-2 group relative">
                            <label class="text-[11px] font-black text-slate-500 dark:text-gray-400 uppercase tracking-widest pl-1 transition-colors group-focus-within:text-pcte-500">Confirm Identity</label>
                            <div class="input-group">
                                <input type="password" id="confirmPass" name="confirm_password" required 
                                       class="input-field w-full rounded-[1.25rem] pl-12 pr-12 py-4 text-[15px] font-semibold tracking-widest shadow-sm" 
                                       placeholder="••••••••">
                                <svg class="w-5 h-5 input-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
                                
                                <div id="match-icon" class="absolute right-4 top-[50%] transform -translate-y-1/2 hidden">
                                    <svg class="w-5 h-5 text-green-500 drop-shadow-md" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg>
                                </div>
                            </div>
                            <p id="match-text" class="text-[10px] text-red-500 font-bold mt-1 hidden pl-1 uppercase tracking-wider">Passwords do not match</p>
                        </div>
                    </div>

                    <div class="flex items-start gap-3 pl-1 pt-4">
                        <input type="checkbox" id="terms" required class="custom-checkbox mt-0.5">
                        <label for="terms" class="text-xs font-bold text-slate-600 dark:text-gray-400 leading-relaxed cursor-pointer select-none">
                            I agree to the <a href="#" class="text-pcte-600 dark:text-pcte-400 hover:underline font-black">Terms of Service</a> and confirm I want to create a CareerPro account.
                        </label>
                    </div>

                    <button type="submit" id="submitBtn" disabled class="btn-submit w-full text-white font-black py-4 rounded-2xl mt-4 flex items-center justify-center gap-3 transition-all uppercase tracking-[0.2em] text-xs shadow-xl shadow-pcte-500/20 dark:shadow-pcte-900/30">
                        <span>Provision Account</span>
                        <svg class="w-4 h-4 transition-transform group-hover:translate-x-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
                    </button>
                </form>

                <div class="mt-10 pt-8 border-t border-slate-200 dark:border-white/10 text-center">
                    <p class="text-xs text-slate-500 dark:text-gray-400 font-bold uppercase tracking-widest">
                        Already have an institutional account? <br class="sm:hidden mt-2">
                        <a href="login.php" class="text-pcte-600 dark:text-pcte-400 font-black hover:text-pcte-800 dark:hover:text-white transition-colors sm:ml-2 border-b-2 border-transparent hover:border-pcte-600 dark:hover:border-pcte-400 pb-0.5">Sign In Here</a>
                    </p>
                </div>

            </div>
        </div>
    </div>

    <!-- ==========================================
         JAVASCRIPT ENGINES
    =========================================== -->
    <script>
        // --- 1. Theme Toggle Engine ---
        const themeToggleBtn = document.getElementById('theme-toggle');
        const mobileThemeToggleBtn = document.getElementById('mobile-theme-toggle');
        const themeToggleDarkIcon = document.getElementById('theme-toggle-dark-icon');
        const themeToggleLightIcon = document.getElementById('theme-toggle-light-icon');

        // Set initial icon state
        if (localStorage.getItem('color-theme') === 'dark' || (!('color-theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            if(themeToggleLightIcon) themeToggleLightIcon.classList.remove('hidden');
        } else {
            if(themeToggleDarkIcon) themeToggleDarkIcon.classList.remove('hidden');
        }

        function toggleThemeLogic() {
            if(themeToggleDarkIcon) themeToggleDarkIcon.classList.toggle('hidden');
            if(themeToggleLightIcon) themeToggleLightIcon.classList.toggle('hidden');

            if (localStorage.getItem('color-theme')) {
                if (localStorage.getItem('color-theme') === 'light') {
                    document.documentElement.classList.add('dark');
                    localStorage.setItem('color-theme', 'dark');
                } else {
                    document.documentElement.classList.remove('dark');
                    localStorage.setItem('color-theme', 'light');
                }
            } else {
                if (document.documentElement.classList.contains('dark')) {
                    document.documentElement.classList.remove('dark');
                    localStorage.setItem('color-theme', 'light');
                } else {
                    document.documentElement.classList.add('dark');
                    localStorage.setItem('color-theme', 'dark');
                }
            }
        }

        if(themeToggleBtn) themeToggleBtn.addEventListener('click', toggleThemeLogic);
        if(mobileThemeToggleBtn) mobileThemeToggleBtn.addEventListener('click', toggleThemeLogic);

        // --- 2. Password Visibility Toggle ---
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

        // --- 3. Advanced Client-Side Validation ---
        const passInput = document.getElementById('regPass');
        const confirmInput = document.getElementById('confirmPass');
        const meter = document.getElementById('pass-meter');
        const meterText = document.getElementById('pass-text');
        const matchIcon = document.getElementById('match-icon');
        const matchText = document.getElementById('match-text');
        const submitBtn = document.getElementById('submitBtn');
        const termsCheck = document.getElementById('terms');

        let isPasswordStrong = false;
        let isPasswordMatch = false;

        // Strength Calculation
        passInput.addEventListener('input', () => {
            const val = passInput.value;
            let strength = 0;
            
            if(val.length >= 8) strength += 25;
            if(/[A-Z]/.test(val)) strength += 25;
            if(/[0-9]/.test(val)) strength += 25;
            if(/[^A-Za-z0-9]/.test(val)) strength += 25;

            meter.style.width = strength + '%';
            
            // Visual Feedback
            if(strength <= 25) {
                meter.style.backgroundColor = '#ef4444'; // Red
                meterText.innerText = 'Weak';
                meterText.style.color = '#ef4444';
                isPasswordStrong = false;
            } else if(strength <= 50) {
                meter.style.backgroundColor = '#eab308'; // Yellow
                meterText.innerText = 'Fair';
                meterText.style.color = '#eab308';
                isPasswordStrong = false;
            } else if(strength <= 75) {
                meter.style.backgroundColor = '#3b82f6'; // Blue
                meterText.innerText = 'Good';
                meterText.style.color = '#3b82f6';
                isPasswordStrong = true;
            } else {
                meter.style.backgroundColor = '#22c55e'; // Green
                meterText.innerText = 'Strong';
                meterText.style.color = '#22c55e';
                isPasswordStrong = true;
            }

            checkMatch();
            validateForm();
        });

        // Match Calculation
        function checkMatch() {
            if(confirmInput.value.length > 0) {
                if(passInput.value === confirmInput.value) {
                    matchIcon.classList.remove('hidden');
                    matchText.classList.add('hidden');
                    confirmInput.style.borderColor = '#22c55e';
                    isPasswordMatch = true;
                } else {
                    matchIcon.classList.add('hidden');
                    matchText.classList.remove('hidden');
                    confirmInput.style.borderColor = '#ef4444';
                    isPasswordMatch = false;
                }
            } else {
                matchIcon.classList.add('hidden');
                matchText.classList.add('hidden');
                confirmInput.style.borderColor = ''; // Reset
                isPasswordMatch = false;
            }
        }
        
        confirmInput.addEventListener('input', () => {
            checkMatch();
            validateForm();
        });

        termsCheck.addEventListener('change', validateForm);

        function validateForm() {
            // Must be strong, matching, and terms agreed to enable button
            if(isPasswordStrong && isPasswordMatch && termsCheck.checked) {
                submitBtn.disabled = false;
            } else {
                submitBtn.disabled = true;
            }
        }

        // --- 4. AJAX Registration Submission ---
        document.getElementById('registerForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            // Double check validation before proceeding
            if(!isPasswordMatch || !isPasswordStrong) return;

            const msgDiv = document.getElementById('response-msg');
            const msgIcon = document.getElementById('response-icon');
            const msgText = document.getElementById('response-text');
            const formData = new FormData(this);
            const data = Object.fromEntries(formData.entries());

            // Loading State UI
            submitBtn.disabled = true;
            submitBtn.innerHTML = `<svg class="w-5 h-5 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg> <span>Provisioning...</span>`;
            
            // Hide previous alerts with animation
            msgDiv.classList.remove('translate-y-0', 'opacity-100');
            msgDiv.classList.add('translate-y-[-10px]', 'opacity-0');

            setTimeout(async () => {
                msgDiv.classList.remove('hidden');
                
                try {
                    const response = await fetch('api/auth.php', {
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
                        // Success UI
                        msgDiv.className = 'mb-6 p-5 rounded-2xl text-sm font-bold border flex items-start gap-4 alert-glass transition-all duration-300 transform translate-y-0 opacity-100 bg-green-50 dark:bg-green-900/20 border-green-200 dark:border-green-500/30 text-green-700 dark:text-green-400 shadow-md';
                        msgIcon.innerHTML = `<svg class="w-5 h-5 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg>`;
                        
                        submitBtn.innerHTML = `<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg> <span>Account Created Successfully</span>`;
                        submitBtn.classList.add('!bg-green-600', 'dark:!bg-green-600', 'border-green-500');
                        submitBtn.classList.remove('btn-submit');
                        
                        // Redirect directly to dashboard — user is already logged in
                        setTimeout(() => { 
                            window.location.href = result.data?.redirect || 'dashboard.php'; 
                        }, 1200);
                    } else {
                        // Error UI (e.g. Email already exists)
                        msgDiv.className = 'mb-6 p-5 rounded-2xl text-sm font-bold border flex items-start gap-4 alert-glass transition-all duration-300 transform translate-y-0 opacity-100 bg-red-50 dark:bg-[#1f0a0a] border-red-200 dark:border-[#3f0707] text-red-700 dark:text-[#ea6d6d] shadow-md';
                        msgIcon.innerHTML = `<svg class="w-5 h-5 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>`;
                        
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = `<span>Provision Account</span> <svg class="w-5 h-5 transition-transform group-hover:translate-x-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>`;
                    }
                } catch (error) {
                    // Critical Crash UI
                    msgDiv.className = 'mb-6 p-5 rounded-2xl text-sm font-bold border flex items-start gap-4 alert-glass transition-all duration-300 transform translate-y-0 opacity-100 bg-red-50 dark:bg-[#1f0a0a] border-red-200 dark:border-[#3f0707] text-red-700 dark:text-[#ea6d6d] shadow-md';
                    msgIcon.innerHTML = `<svg class="w-5 h-5 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>`;
                    msgText.innerText = 'Connection to Provisioning Server failed. Check network.';
                    
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = `<span>Provision Account</span> <svg class="w-5 h-5 transition-transform group-hover:translate-x-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>`;
                }
            }, 300);
        });
    </script>

</body>
</html>