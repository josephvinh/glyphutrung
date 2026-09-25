/**
 * Lazy Loading Manager
 *
 * Dynamically loads heavy JS modules only when needed.
 * Reduces initial bundle size significantly.
 */
(function() {
    'use strict';

    window.TNTT_LAZY = {
        loaded: {},
        loading: {},

        /**
         * Load a module dynamically
         * @param {string} moduleName - Name of module to load
         * @returns {Promise} Resolves when module is loaded
         */
        async load(moduleName) {
            // Already loaded
            if (this.loaded[moduleName]) {
                return Promise.resolve(this.loaded[moduleName]);
            }

            // Currently loading
            if (this.loading[moduleName]) {
                return this.loading[moduleName];
            }

            // Start loading
            this.loading[moduleName] = new Promise((resolve, reject) => {
                const script = document.createElement('script');
                script.src = `assets/js/modules/${moduleName}.js?v=${window.TNTT_BUILD || Date.now()}`;
                script.onload = () => {
                    this.loaded[moduleName] = window.TNTT && window.TNTT[moduleName];
                    delete this.loading[moduleName];
                    resolve(this.loaded[moduleName]);
                };
                script.onerror = (e) => {
                    delete this.loading[moduleName];
                    console.error(`[TNTT] Failed to load module: ${moduleName}`);
                    reject(e);
                };
                document.head.appendChild(script);
            });

            return this.loading[moduleName];
        },

        /**
         * Preload a module (non-blocking)
         * @param {string} moduleName
         */
        preload(moduleName) {
            // Preload without blocking
            setTimeout(() => this.load(moduleName), 100);
        }
    };

    // Export for convenience
    window.loadModule = window.TNTT_LAZY.load.bind(window.TNTT_LAZY);
})();
