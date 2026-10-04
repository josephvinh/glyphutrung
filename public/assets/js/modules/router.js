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
        let newUrl;

        if (this.mode === 'hash') {
            // Hash mode: /#/students
            const base = window.location.pathname;
            newUrl = base + '#/' + path.replace(/^\//, '');
        } else {
            // Clean mode: /students
            newUrl = '/' + path.replace(/^\//, '');
        }

        if (replace) {
            window.history.replaceState(null, '', newUrl);
        } else {
            window.history.pushState(null, '', newUrl);
        }
    },

    /**
     * Handle browser back/forward navigation.
     * @private
     */
    _onPopState() {
        const parsed = this.parse(window.location.href);
        this._navigateToModule(parsed.module, parsed.params, true);
    },

    /**
     * Handle hashchange events (fallback for hash mode).
     * @private
     */
    _onHashChange() {
        if (this.mode !== 'hash') return;
        const parsed = this.parse(window.location.href);
        this._navigateToModule(parsed.module, parsed.params, true);
    },

    /**
     * Navigate to a specific module with params.
     * @private
     */
    _navigateToModule(module, params, isHistoryNav) {
        // Skip if already on this module (unless navigating with different params)
        if (module === this.currentModule && !isHistoryNav) return;

        this.currentModule = module;
        this.params = params || {};

        // Call the shell's changeModule to switch the view
        if (window.TNTT?.shell?.changeModule) {
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
        // If current URL has hash, use hash mode
        if (window.location.hash && window.location.hash.match(/^#\/.+/)) {
            this.mode = 'hash';
        } else {
            // Check if server supports clean URLs via .htaccess
            // For now, default to hash if no hash present
            this.mode = window.location.hash ? 'hash' : 'clean';
        }
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
