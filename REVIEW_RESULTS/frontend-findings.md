# Frontend Security & Quality Review - TNTT Super App

**Date:** September 2026
**Scope:** All JavaScript files in `public/assets/js/`, `public/sw.js`
**Focus Areas:** XSS, Auth Bypass, Input Validation, Sensitive Data, CSRF, eval(), Error Handling, Memory Leaks, Race Conditions, Bundle Size

---

## EXECUTIVE SUMMARY

| Severity | Count |
|----------|-------|
| CRITICAL | 0 |
| HIGH | 2 |
| MEDIUM | 5 |
| LOW | 6 |

**Overall Assessment:** Codebase is well-structured with good security practices. No critical vulnerabilities found. Main concerns are around localStorage usage for sensitive data and some race condition handling.

---

## HIGH SEVERITY

### [HIGH] Sensitive Data Stored in localStorage

**File:** `public/assets/js/modules/core.js:68`
```javascript
$watch: {
    dark(val) {
        this.applyDarkMode();
        localStorage.setItem('darkMode', val);  // OK - only stores preference
    }
}
```
**Issue:** While `darkMode` is benign, the pattern of storing session data in localStorage is present. No sensitive tokens or user data found in localStorage during this review.

**File:** `public/assets/js/modules/shell.js:218`
```javascript
this.defaultPermissions = JSON.parse(JSON.stringify(this.permissions));
```
**Issue:** Deep clone of permissions object. If permissions contain sensitive data, this pattern could be problematic. Currently not persisted to localStorage.

**Recommendation:** Document which data is acceptable for localStorage. Never store:
- Authentication tokens (use httpOnly cookies)
- Session IDs
- User PII (phone numbers, addresses)
- CSRF tokens

---

### [HIGH] CSRF Token Potentially Exposed in Client-Side

**File:** `public/assets/js/modules/core.js:11`
```javascript
window.TNTT.csrfToken = window.TNTT.boot.csrfToken || '';
```

**File:** `public/assets/js/modules/core.js:17-27`
```javascript
window.TNTT.csrfFetch = async (url, options = {}) => {
    return fetch(url, {
        ...options,
        method: options.method || 'GET',
        headers: {
            ...options.headers,
            'X-CSRF-TOKEN': window.TNTT.csrfToken,
            'Content-Type': 'application/json',
        },
    });
};
```

**Issue:** CSRF token is available in JavaScript context (`window.TNTT.csrfToken`). While this is necessary for SPA functionality, it means:
- Token is accessible via XSS attacks
- Token could be read by any JavaScript on the page

**Recommendation:**
1. Ensure CSRF token has short lifespan and is rotated frequently
2. Implement additional CSRF protections (same-site cookies, custom headers verified by server)
3. Consider using httpOnly cookies for session management as backup

---

## MEDIUM SEVERITY

### [MEDIUM] Potential Race Condition in API Responses

**File:** `public/assets/js/modules/attendance.js:328-371`
```javascript
this.save('attendance', 'toggle', {...}).then(r => {
    if (!r || !r.ok) this._attThem(cu);  // Rollback on failure
});
```

**Issue:** Optimistic UI update followed by server confirmation. If multiple rapid toggles occur, response order is not guaranteed. Rollback logic may apply to wrong state.

**Recommendation:** Consider adding request sequence numbers or using a queue pattern for critical operations.

---

### [MEDIUM] Missing Input Validation on Client-Side

**File:** `public/assets/js/modules/login.js:71`
```javascript
const r = await this.post('login', { phone: this.chuanHoaSdt(this.phone), password: this.password });
```

**Issue:** Client-side phone normalization exists (`chuanHoaSdt`), but no validation of:
- Password length minimum (enforced on change password at line 279 with 6 chars)
- Phone number format validation (only normalization, no format check)

**File:** `public/assets/js/modules/students.js:120-121`
```javascript
if (!String(e.name || '').trim())  return window.TNTT.toast.warning('Vui lòng nhập họ và tên.');
if (!e.className)                 return window.TNTT.toast.warning('Vui lòng chọn lớp cho em.');
```

**Issue:** Basic validation exists but no:
- Maximum length checks
- Character whitelist validation
- SQL injection prevention (relies on server)

**Recommendation:** Add comprehensive client-side validation to improve UX, but ALWAYS validate server-side.

---

### [MEDIUM] Error Swallowing in Async Operations

**File:** `public/assets/js/modules/attendance.js:217-219`
```javascript
} catch (e) {
    // Im lặng: lần đồng bộ sau (bấm Làm mới / mở lại app) sẽ tải lại.
}
```

**Issue:** Silent catch blocks without any logging. If persistent failures occur, debugging becomes difficult.

**Recommendation:** Log errors at minimum:
```javascript
} catch (e) {
    console.warn('[TNTT] loadHeavy failed:', e);
}
```

---

### [MEDIUM] Service Worker Cache Poisoning Risk

**File:** `public/sw.js:29-44`
```javascript
self.addEventListener('install', (e) => {
    e.waitUntil((async () => {
        const kho = await caches.open(KHO);
        try {
            await Promise.allSettled([...]);
        } catch (err) {
            console.warn('[SW] Precache thất bại:', err);
        }
    })());
    self.skipWaiting();
});
```

**Issue:** Cache-first strategy could serve stale content:
```javascript
const CHO_GIU = /.(js|css|png|svg|jpg|jpeg|webp|woff2?)$/i;
```

**File:** `public/sw.js:109-121`
```javascript
const dangTai = fetch(req).then((res) => {
    if (res && res.ok) {
        kho.put(req, res.clone());
    }
    return res;
}).catch(() => null);

if (cu) {
    dangTai;  // Fire and forget - no await
    return cu;
}
```

**Issue:** Failed background updates are silently ignored. No retry mechanism.

**Recommendation:**
1. Add version header validation
2. Implement retry logic for failed updates
3. Consider adding content integrity checks

---

### [MEDIUM] QR Scan Lookup Table Scope

**File:** `public/assets/js/modules/qrscan.js:100-119`
```javascript
async _qrTaiBangTra() {
    const r = await this.api('attendance', 'lookup', {
        programId: this.activeSession.programId,
        date: this.activeSession.date
    });
    // Returns [code, id, name, className] - minimal data
    this._qrTraMa = new Map();
    (r.items || []).forEach(([ma, id, ten, lop]) => {
        this._qrTraMa.set(String(ma).trim(), { code: key, id, name: ten, className: lop });
    });
}
```

**Issue:** Lookup table contains all students in block scope, loaded into memory. For large organizations (500+ students), this could be a memory concern on mobile devices.

**Recommendation:** Consider paginated loading or limiting scope further for very large organizations.

---

## LOW SEVERITY

### [LOW] Hardcoded Default User Data

**File:** `public/assets/js/modules/core.js:33-42`
```javascript
user: {
    memberId: 1,
    holyName: 'Phêrô',
    fullName: 'Nguyễn Văn A',
    phone: '0901000001',
    role: 'admin',
    roleTitle: 'Quản Trị Hệ Thống',
    managedBlock: 'Khai Tâm',
    assignedClass: 'Khai Tâm 1A'
},
```

**Issue:** Default user object exists as fallback. While this appears to be for development/debugging, it could confuse if accidentally used in production.

**Recommendation:** Remove or mark clearly as DEV_ONLY.

---

### [LOW] No Request Timeout

**File:** `public/assets/js/modules/core.js:89-111`
```javascript
async api(file, action, body) {
    try {
        const headers = { 'Content-Type': 'application/json' };
        const res = await fetch('api/' + file + '.php?action=' + action, {
            method: 'POST',
            headers,
            body: JSON.stringify(body || {})
        });
        // No AbortController timeout
```

**Issue:** Fetch requests have no timeout. Network issues could leave UI in loading state indefinitely.

**Recommendation:** Add AbortController with reasonable timeout (e.g., 30 seconds).

---

### [LOW] Potential Memory Leak in MutationObserver

**File:** `public/assets/js/modules/shell.js:149-164`
```javascript
initIconWatcher() {
    let scheduled = false;
    const render = () => {
        scheduled = false;
        if (document.querySelector('i[data-lucide]')) lucide.createIcons();
    };
    const schedule = () => {
        if (scheduled) return;
        scheduled = true;
        requestAnimationFrame(render);
    };
    new MutationObserver(schedule).observe(document.body, { childList: true, subtree: true });
    schedule();
}
```

**Issue:** MutationObserver is created but never disconnected. If module is somehow re-initialized multiple times, multiple observers could accumulate.

**Recommendation:** Store observer reference and disconnect in cleanup if applicable.

---

### [LOW] Missing Rate Limiting on API Calls

**File:** `public/assets/js/modules/qrscan.js:344-346`
```javascript
if (this._qrHang.length >= this.LO_TOI_DA) this._qrGuiLo();
else if (!this._qrHenGui) {
    this._qrHenGui = setTimeout(() => this._qrGuiLo(), this.CHU_KY_GUI_MS);
}
```

**Issue:** Batch processing exists but no client-side rate limiting. Malicious user could bypass batch limits.

**Recommendation:** Implement server-side rate limiting as primary defense (already likely present), client-side as UX enhancement.

---

### [LOW] No CSP Headers in JavaScript

**Issue:** Content Security Policy should be set server-side, but JavaScript code doesn't enforce or check for CSP.

**Recommendation:** Verify CSP is properly configured on server:
- `default-src 'self'`
- `script-src 'self' 'nonce-{random}'` (if using nonces)
- `object-src 'none'`
- `base-uri 'self'`

---

### [LOW] Incomplete Error Messages to Users

**File:** `public/assets/js/modules/core.js:109`
```javascript
return { ok: false, error: 'Mất kết nối máy chủ. Kiểm tra lại mạng.' };
```

**Issue:** Generic error messages don't help users or developers diagnose specific issues.

**Recommendation:** For non-sensitive errors, include more context:
- Network timeout vs connection refused
- Server 500 errors vs validation errors
- Consider a debug mode that shows more details

---

## SECURITY BEST PRACTICES - POSITIVE FINDINGS

### XSS Prevention - GOOD
- **File:** `public/assets/js/modules/toast.js:62`
```javascript
text.textContent = message;  // textContent instead of innerHTML
```
- All user input rendered via `textContent`, not `innerHTML`
- No `dangerouslySetInnerHTML` equivalent found
- No `eval()` or `Function()` usage found

### CSRF Protection - ADEQUATE
- CSRF tokens included in fetch headers
- Server-side validation expected (not verified in this review)

### Authentication Flow - GOOD
- Password change enforced on first login
- Session expiry handling with redirect
- Passkey/WebAuthn implementation follows standards

### Input Handling - GOOD
- Phone number normalization
- CSV parsing with proper escaping
- URL encoding in exports

---

## QUALITY ISSUES

### [LOW] Inconsistent Date Parsing

**File:** `public/assets/js/modules/shell.js:33-39`
```javascript
parseDate(value) {
    if (!value) return '';
    const dmy = value.match(/^(\d{1,2})[\/\-.](\d{1,2})[\/\-.](\d{4})$/);
    // Handles both DD/MM/YYYY and DD-MM-YYYY
```

**File:** `public/assets/js/modules/shell.js:75-79`
```javascript
formatFullDate(dateStr) {
    if (!dateStr) return '';
    const d = new Date(dateStr + 'T00:00:00');  // Assumes yyyy-mm-dd
```

**Issue:** `parseDate` accepts DD/MM/YYYY but `formatFullDate` assumes yyyy-mm-dd. Works but could be clearer.

---

### [LOW] Magic Numbers

**File:** `public/assets/js/modules/shell.js:234`
```javascript
setInterval(() => { this.nowTs = Date.now(); }, 30000);  // 30 seconds
```

**File:** `public/assets/js/modules/shell.js:239`
```javascript
Date.now() - (this._lastLoadAt || 0) > 45000  // 45 seconds
```

**Recommendation:** Extract to named constants:
```javascript
const CLOCK_UPDATE_INTERVAL_MS = 30000;
const MIN_RELOAD_INTERVAL_MS = 45000;
```

---

## BUNDLE SIZE OBSERVATIONS

No minification/bundling analysis performed. Key files:

| File | Lines | Notes |
|------|-------|-------|
| core.js | ~938 | Large - contains core logic |
| attendance.js | ~430 | Moderate |
| stats.js | ~478 | Contains HTML generation |
| qrscan.js | ~440 | Large - QR processing |
| toast.js | ~200 | Well-contained |

**Recommendation:** Consider:
1. Tree-shaking vendor libraries
2. Lazy loading non-critical modules
3. Code splitting by route/module

---

## SUMMARY & RECOMMENDATIONS

### Immediate Actions
1. **Verify CSRF token rotation policy** on server
2. **Add request timeouts** to fetch operations
3. **Log async errors** instead of silent catch blocks

### Short-term Improvements
4. **Document localStorage usage** - what data is acceptable
5. **Add input validation** - length limits, format checks
6. **Consider Service Worker retry logic** for failed cache updates

### Long-term Enhancements
7. **Implement CSP** if not already configured
8. **Add error tracking** (Sentry, etc.) for production
9. **Consider code splitting** for bundle size optimization

---

*Review completed by Claude Opus 5.5*
