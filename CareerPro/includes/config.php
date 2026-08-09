<?php
declare(strict_types=1);

/**
 * ============================================================================
 * CareerPro Suite — Application Configuration
 *
 * AI KEY PRIORITY ORDER:
 *   1. Database  (Admin → Platform Config → Google Gemini API Key)
 *   2. GEMINI_API_KEY_FALLBACK below  (local dev only)
 *   3. Pollinations AI  (automatic free fallback — no key needed)
 *
 * Get a valid Gemini key (starts with AIzaSy...) at:
 *   https://aistudio.google.com/app/apikey
 *   → Create API key → Create in NEW project (no service-account checkbox)
 * ============================================================================
 */

// Paste a valid AIzaSy... key here, OR set it via Admin → Settings (preferred).
// Leave empty to rely on the Pollinations AI free fallback automatically.
define('GEMINI_API_KEY_FALLBACK', '');   // e.g. 'AIzaSyXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX'

// Application base URL
define('APP_BASE_URL', 'http://localhost/resume');
