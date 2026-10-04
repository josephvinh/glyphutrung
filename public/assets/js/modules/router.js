/* ==========================================================
   ROUTER — URL routing for TNTT Super App SPA
   Syncs URL with module state: both directions.
   ========================================================== */
window.TNTT = window.TNTT || {};

window.TNTT.router = {
    // Current parsed state
    currentModule: 'dashboard',
    params: {},

    // URL mode: 'hash' (/#/students) or 'clean' (/students)
    mode: 'hash',

    // List of known modules (must match moduleDefs keys)
    MODULES: [
        'dashboard', 'students', 'student_profile', 'attendance', 'scores',
        'programs', 'reports', 'org', 'staff', 'settings', 'leave',
        'birthdays', 'announcements', 'notes', 'calendar', 'promotion',
        'reporthub', 'qrcard', 'gifts', 'rewards', 'thu_vien', 'guide', 'years',
        'access', 'qrscan', 'analytics', 'stats', 'push', 'library', 'passkey'
    ],

    /**
     * Initialize router - call once after Alpine app is ready.
     * Parses current URL and triggers module change if needed.
     */
    init() {
        // Detect URL mode from current path
        this._detectMode();

        // Listen for browser back/forward
        window.addEventListener('popstate', () => this._onPopState());

        // Fallback: hashchange for older browsers or hash mode
        window.addEventListener('hashchange', () => this._onHashChange());

        // Parse current URL and navigate
        const parsed = this.parse(window.location.href);
        if (parsed.module && parsed.module !== 'dashboard') {
            this._navigateToModule(parsed.module, parsed.params, false);
        }

        // Watch for programmatic module changes via window.TNTT.shell.changeModule
        // We intercept by overriding the changeModule method temporarily
        this._patchChangeModule();
    },

    /**
     * Navigate to a path - updates URL and calls changeModule.
     * @param {string} path - Path like '/students' or '/students/123'
     * @param {boolean} replace - If true, use replaceState instead of pushState
     */
    navigate(path, replace = false) {
        const parsed = this.parse(path);
        if (!parsed.module) return;

        // Update URL
        this._updateUrl(path, replace);

        // Trigger module change
        this._navigateToModule(parsed.module, parsed.params, false);
    },

    /**
     * Sync URL when module changes programmatically (e.g., via sidebar click).
     * This is called internally when changeModule is triggered from the app.
     * @param {string} moduleName - The module being switched to
     */
    onModuleChange(moduleName) {
        if (moduleName === this.currentModule) return;

        const path = this._buildPath(moduleName, this.params);
        this._updateUrl(path, false);
        this.currentModule = moduleName;
    },

    /**
     * Navigate back in history - equivalent to browser back button.
     * Falls back to navigating to dashboard if no history.
     */
    goBack() {
        if (window.history.length > 1) {
            window.history.back();
        } else {
            this.navigate('/dashboard');
        }
    },

    /**
     * Parse a URL or path and return {module, params}.
     * @param {string} input - Full URL or path like '/students/123' or '/#/students'
     * @returns {{module: string, params: object}}
     */
    parse(input) {
        if (!input) return { module: 'dashboard', params: {} };

        let path = input;

        // Extract pathname from full URL
        try {
            const url = new URL(input);
            // Support clean URL mode: /students → index.php?_route=students
            if (url.searchParams.has('_route')) {
                path = '/' + url.searchParams.get('_route');
            } else {
                path = url.pathname;
            }
        } catch (e) {
            // Not a full URL, treat as path
        }

        // Handle hash mode: /#/students or /#/students/123
        const hashMatch = path.match(/#\/(.+)/);
        if (hashMatch) {
            path = '/' + hashMatch[1];
        }

        // Remove leading/trailing slashes and normalize
        path = path.replace(/^\/|\/$/g, '').trim();
        if (!path) return { module: 'dashboard', params: {} };

        // Split into segments
        const segments = path.split('/').filter(Boolean);
        const module = segments[0] || 'dashboard';

        // Validate module name (security: only allow known modules)
        const normalizedModule = this.MODULES.includes(module) ? module : 'dashboard';

        // Extract params from remaining segments
        const params = {};
        if (normalizedModule === 'student_profile' && segments[1]) {
            params.id = segments[1];
        }
        // Add more module-specific param extraction as needed

        return { module: normalizedModule, params };
    },

    /**
     * Update URL without triggering navigation.
     * @private
     */
    _updateUrl(path, replace) {
        // Always use hash mode: /#/module
        const base = window.location.pathname.split('?')[0]; // Remove query params
        const newUrl = base + '#/' + path.replace(/^\//, '');

        // Store module in history state for reliable back/forward
        const state = { module: path.replace(/^\//, '') };

        if (replace) {
            window.history.replaceState(state, '', newUrl);
        } else {
            window.history.pushState(state, '', newUrl);
        }

        // Also update router's current module
        this.currentModule = path.replace(/^\//, '');
    },

    /**
     * Handle browser back/forward navigation.
     * @private
     */
    _onPopState(event) {
        console.log('[Router] popstate triggered, state:', event.state, 'URL:', window.location.href);

        // Use state from history if available, otherwise parse URL
        let module = 'dashboard';
        if (event.state && event.state.module) {
            module = event.state.module;
        } else {
            const parsed = this.parse(window.location.href);
            module = parsed.module;
        }

        console.log('[Router] Navigating to:', module);
        this._navigateToModule(module, {}, true);
    },

    /**
     * Handle hashchange events (fallback for hash mode).
     * @private
     */
    _onHashChange() {
        console.log('[Router] hashchange triggered, hash:', window.location.hash);
        const parsed = this.parse(window.location.href);
        console.log('[Router] parsed:', parsed);
        this._navigateToModule(parsed.module, parsed.params, true);
    },

    /**
     * Navigate to a specific module with params.
     * @private
     */
    _navigateToModule(module, params, isHistoryNav) {
        console.log('[Router] _navigateToModule:', module, 'isHistoryNav:', isHistoryNav);

        // Skip if already on this module (unless navigating with different params)
        if (module === this.currentModule && !isHistoryNav) {
            console.log('[Router] Skipping - already on this module');
            return;
        }

        this.currentModule = module;
        this.params = params || {};
        console.log('[Router] Updated currentModule to:', this.currentModule);

        // Find Alpine component and update its currentModule directly
        // This is needed because shell.changeModule() uses 'this' incorrectly
        const alpineEl = document.querySelector('[x-data="tnttApp"]');
        console.log('[Router] Alpine element:', alpineEl);

        if (alpineEl && alpineEl.__x) {
            // Alpine.js < 3 (uses __x)
            console.log('[Router] Using Alpine __x');
            alpineEl.__x.$data.currentModule = module;
        } else if (alpineEl && alpineEl._xDataStack) {
            // Alpine.js >= 3.4 (uses _xDataStack)
            console.log('[Router] Using Alpine _xDataStack');
            const data = alpineEl._xDataStack[0];
            if (data && 'currentModule' in data) {
                data.currentModule = module;
                console.log('[Router] Updated Alpine data.currentModule');
            } else {
                console.log('[Router] No currentModule in Alpine data');
            }
        } else if (window.Alpine && window.Alpine.store) {
            // Alpine stores
            const stores = window.Alpine.store('tnttApp');
            if (stores && 'currentModule' in stores) {
                stores.currentModule = module;
            }
        } else {
            console.log('[Router] Could not find Alpine component');
        }

        // Also call shell.changeModule for any side effects it might have
        if (window.TNTT?.shell?.changeModule) {
            console.log('[Router] Calling shell.changeModule');
            window.TNTT.shell.changeModule(module);
        }

        // Also update core.currentModule for consistency
        if (window.TNTT?.core) {
            window.TNTT.core.currentModule = module;
        }
    },

    /**
     * Build a path string from module and params.
     * @private
     */
    _buildPath(module, params) {
        let path = module;
        if (params?.id) {
            path += '/' + params.id;
        }
        return path;
    },

    /**
     * Detect whether we're in hash or clean URL mode.
     * @private
     */
    _detectMode() {
        // Always use hash mode for reliable SPA navigation
        // This ensures browser back/forward works correctly
        this.mode = 'hash';
    },

    /**
     * Patch window.TNTT.shell.changeModule to auto-sync URL.
     * @private
     */
    _patchChangeModule() {
        const router = this;
        const originalChangeModule = window.TNTT?.shell?.changeModule;

        if (originalChangeModule) {
            window.TNTT.shell.changeModule = function(moduleName) {
                // Call original
                originalChangeModule.call(this, moduleName);

                // Sync URL (unless it's the same module - changeModule already handles scroll)
                if (moduleName !== router.currentModule) {
                    router.onModuleChange(moduleName);
                }
            };
        }
    }
};
