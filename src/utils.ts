/**
 * TypeScript Utilities cho TNTT
 *
 * Các hàm utility dùng chung, gradually migrated từ JS.
 * Chạy: npx tsc --noEmit
 */

/**
 * Validate Vietnamese phone number
 * Accepts: 0xxx.xxx.xxx, +84xxx.xxx.xxx, xxx.xxx.xxxx
 */
export function isValidPhone(phone: string | null | undefined): boolean {
    if (!phone) return false;
    const cleaned = phone.replace(/[\s.-]/g, '');
    // Vietnamese phone: 10-11 digits, starting with 0 or +84
    return /^(0[1-9]\d{8,9}|84[1-9]\d{8,9})$/.test(cleaned);
}

/**
 * Normalize Vietnamese phone number to 0-prefix format
 */
export function normalizePhone(phone: string | null | undefined): string | null {
    if (!phone) return null;
    const cleaned = phone.replace(/[\s.-]/g, '');
    // Convert +84 to 0
    if (cleaned.startsWith('84') && cleaned.length >= 10) {
        return '0' + cleaned.slice(2);
    }
    return cleaned;
}

/**
 * Format date to Vietnamese locale
 */
export function formatDate(date: Date | string | null | undefined, format: 'short' | 'long' = 'short'): string {
    if (!date) return '';
    const d = typeof date === 'string' ? new Date(date) : date;
    if (isNaN(d.getTime())) return '';

    const options: Intl.DateTimeFormatOptions = format === 'long'
        ? { day: '2-digit', month: 'long', year: 'numeric' }
        : { day: '2-digit', month: '2-digit', year: 'numeric' };

    return d.toLocaleDateString('vi-VN', options);
}

/**
 * Debounce function for performance optimization
 */
export function debounce<T extends (...args: unknown[]) => unknown>(
    fn: T,
    delay: number
): (...args: Parameters<T>) => void {
    let timeoutId: ReturnType<typeof setTimeout> | null = null;

    return function (this: unknown, ...args: Parameters<T>): void {
        if (timeoutId) clearTimeout(timeoutId);
        timeoutId = setTimeout(() => {
            fn.apply(this, args);
        }, delay);
    };
}

/**
 * Throttle function for rate limiting on client side
 */
export function throttle<T extends (...args: unknown[]) => unknown>(
    fn: T,
    limit: number
): (...args: Parameters<T>) => void {
    let inThrottle = false;

    return function (this: unknown, ...args: Parameters<T>): void {
        if (!inThrottle) {
            fn.apply(this, args);
            inThrottle = true;
            setTimeout(() => { inThrottle = false; }, limit);
        }
    };
}

/**
 * Deep clone object (type-safe)
 */
export function deepClone<T>(obj: T): T {
    if (obj === null || typeof obj !== 'object') return obj;
    if (Array.isArray(obj)) return obj.map(item => deepClone(item)) as T;
    const cloned = {} as T;
    for (const key in obj) {
        if (Object.prototype.hasOwnProperty.call(obj, key)) {
            cloned[key] = deepClone(obj[key]);
        }
    }
    return cloned;
}

/**
 * Type guard for API responses
 */
export interface ApiResponse<T> {
    ok: boolean;
    error?: string;
    data?: T;
}

export function isApiSuccess<T>(response: unknown): response is ApiResponse<T> {
    return (
        typeof response === 'object' &&
        response !== null &&
        'ok' in response &&
        response.ok === true
    );
}

/**
 * Safe JSON parse with fallback
 */
export function safeJsonParse<T>(json: string, fallback: T): T {
    try {
        const parsed = JSON.parse(json);
        return parsed as T;
    } catch {
        return fallback;
    }
}

/**
 * Escape HTML to prevent XSS
 */
export function escapeHtml(str: string | null | undefined): string {
    if (!str) return '';
    const map: Record<string, string> = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;',
    };
    return str.replace(/[&<>"']/g, char => map[char] ?? char);
}

/**
 * Truncate string with ellipsis
 */
export function truncate(str: string | null | undefined, maxLength: number): string {
    if (!str) return '';
    if (str.length <= maxLength) return str;
    return str.slice(0, maxLength - 3) + '...';
}

/**
 * Calculate age from birth date
 */
export function calculateAge(birthDate: Date | string | null | undefined): number | null {
    if (!birthDate) return null;
    const birth = typeof birthDate === 'string' ? new Date(birthDate) : birthDate;
    if (isNaN(birth.getTime())) return null;

    const today = new Date();
    let age = today.getFullYear() - birth.getFullYear();
    const monthDiff = today.getMonth() - birth.getMonth();

    if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birth.getDate())) {
        age--;
    }

    return age >= 0 ? age : null;
}

/**
 * Pagination helper
 */
export interface PaginationParams {
    page: number;
    limit: number;
    total: number;
}

export interface PaginatedResult<T> {
    data: T[];
    pagination: {
        page: number;
        limit: number;
        total: number;
        totalPages: number;
        hasMore: boolean;
    };
}

export function createPaginatedResponse<T>(data: T[], params: PaginationParams): PaginatedResult<T> {
    const totalPages = Math.ceil(params.total / params.limit);
    return {
        data,
        pagination: {
            page: params.page,
            limit: params.limit,
            total: params.total,
            totalPages,
            hasMore: params.page * params.limit < params.total,
        },
    };
}

/**
 * Constants
 */
export const PAGINATION = {
    DEFAULT_LIMIT: 100,
    MAX_LIMIT: 500,
    MIN_LIMIT: 1,
} as const;

export const RATE_LIMIT = {
    API_READ: 60,
    API_WRITE: 30,
    LOGIN: 20,
} as const;
