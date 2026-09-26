/**
 * CDP client for Chrome DevTools Protocol — minimal, Promise-based.
 * Uses Node 24's global WebSocket.
 */
const sleep = ms => new Promise(r => setTimeout(r, ms));

/**
 * Launch a headless Chrome instance at the given debugging port.
 * Returns { child, wsUrl }.
 */
export async function launchChrome({
  port = 9222,
  userDataDir,
  headless = true,
  windowSize = '1440,900',
} = {}) {
  const fs = await import('node:fs');
  fs.mkdirSync(userDataDir, { recursive: true });

  const CHROME = 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe';
  const args = [
    `--remote-debugging-port=${port}`,
    `--user-data-dir=${userDataDir}`,
    '--no-first-run',
    '--no-default-browser-check',
    '--disable-extensions',
    '--disable-background-networking',
    '--disable-sync',
    '--disable-default-apps',
    '--metrics-recording-only',
    '--no-service-autorun',
    '--password-store=basic',
    '--use-mock-keychain',
    '--disable-gpu',
    '--hide-scrollbars',
    `--window-size=${windowSize}`,
    'about:blank',
  ];
  if (headless) args.unshift('--headless=new');

  const { spawn } = await import('node:child_process');
  const child = spawn(CHROME, args, { stdio: 'ignore', detached: false });

  // Wait for DevTools endpoint
  for (let i = 0; i < 60; i++) {
    try {
      const r = await fetch(`http://127.0.0.1:${port}/json/version`);
      const ver = await r.json();
      return { child, wsUrl: ver.webSocketDebuggerUrl, version: ver };
    } catch { await sleep(300); }
  }
  child.kill();
  throw new Error('Chrome DevTools not reachable within timeout');
}

/**
 * Minimal CDP session over a single WebSocket (Browser target).
 *
 * Usage:
 *   const cdp = new CDP(wsUrl);
 *   await cdp.connect();
 *   const { targetId } = await cdp.send('Target.createTarget', { url: 'about:blank' });
 *   const { sessionId } = await cdp.send('Target.attachToTarget', { targetId, flatten: true });
 *   // Now send page commands
 *   const { result } = await cdp.send('Runtime.evaluate', { expression: '1+1' }, sessionId);
 */
export class CDP {
  constructor(wsUrl) {
    this.wsUrl = wsUrl;
    this.ws = null;
    this._id = 1;
    this._pending = new Map();
    this._listeners = new Map();
  }

  connect() {
    return new Promise((resolve, reject) => {
      this.ws = new WebSocket(this.wsUrl);
      this.ws.onopen = () => resolve();
      this.ws.onerror = e => reject(e);
      this.ws.onmessage = ev => {
        const msg = JSON.parse(ev.data);
        // Response
        if (msg.id !== undefined) {
          const cb = this._pending.get(msg.id);
          if (cb) {
            this._pending.delete(msg.id);
            cb(msg);
          }
          return;
        }
        // Event
        const sessionKey = msg.sessionId || '_browser';
        const handlers = this._listeners.get(sessionKey);
        if (handlers) {
          for (const h of handlers) {
            if (h.method === msg.method || h.method === '*') {
              try { h.fn(msg); } catch {}
            }
          }
        }
      };
    });
  }

  /**
   * Send a CDP command. sessionId = undefined for global browser commands.
   */
  send(method, params = {}, sessionId) {
    const id = this._id++;
    const msg = { id, method, params };
    if (sessionId) msg.sessionId = sessionId;

    return new Promise((resolve, reject) => {
      const t = setTimeout(() => {
        this._pending.delete(id);
        reject(new Error(`CDP timeout: ${method}`));
      }, 60000);
      this._pending.set(id, r => {
        clearTimeout(t);
        if (r.error) reject(new Error(JSON.stringify(r.error)));
        else resolve(r);
      });
      this.ws.send(JSON.stringify(msg));
    });
  }

  /**
   * Subscribe to an event. sessionKey = '_browser' for global; omitted for sessionId.
   * Returns unsubscribe function.
   */
  on(method, fn, sessionKey) {
    const key = sessionKey || '_browser';
    if (!this._listeners.has(key)) this._listeners.set(key, []);
    const h = { method, fn };
    this._listeners.get(key).push(h);
    return () => {
      const arr = this._listeners.get(key);
      if (arr) {
        const idx = arr.indexOf(h);
        if (idx >= 0) arr.splice(idx, 1);
      }
    };
  }

  close() {
    if (this.ws) { this.ws.close(); this.ws = null; }
    this._pending.clear();
  }
}