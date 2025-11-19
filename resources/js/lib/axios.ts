import axios from 'axios';

// Create axios instance with default config
const api = axios.create({
    baseURL: '/api',
    headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
    },
    withCredentials: true, // Important for CSRF token and cookies
});

// Request interceptor to add CSRF token
api.interceptors.request.use(
    async (config) => {
        // Use Laravel's XSRF-TOKEN cookie approach (most reliable)
        // Axios can automatically read XSRF-TOKEN cookie and send it as X-XSRF-TOKEN header
        // But Laravel expects X-CSRF-TOKEN, so we need to read the cookie and convert it
        
        // First, try to get from Inertia shared props (always fresh after each visit)
        let token: string | null = null;
        if (typeof window !== 'undefined' && (window as any).Inertia) {
            const page = (window as any).Inertia.page;
            if (page?.props?.csrfToken) {
                token = page.props.csrfToken;
            }
        }
        
        // Fallback to meta tag
        if (!token) {
            token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || null;
        }
        
        // Fallback to XSRF-TOKEN cookie (Laravel sets this automatically)
        if (!token) {
            const cookies = document.cookie.split(';');
            const xsrfCookie = cookies.find(cookie => cookie.trim().startsWith('XSRF-TOKEN='));
            if (xsrfCookie) {
                // Decode the cookie value (Laravel encrypts it)
                token = decodeURIComponent(xsrfCookie.split('=')[1]);
            }
        }
        
        if (token) {
            config.headers['X-CSRF-TOKEN'] = token;
        }
        
        return config;
    },
    (error) => {
        return Promise.reject(error);
    }
);

// Response interceptor for error handling
api.interceptors.response.use(
    (response) => {
        return response;
    },
    (error) => {
        // Handle common errors
        if (error.response?.status === 401) {
            // Unauthorized - redirect to login
            window.location.href = '/login';
        } else if (error.response?.status === 419) {
            // CSRF token mismatch - refresh the page to get a new token
            // This happens when the session changes (logout/login)
            window.location.reload();
        } else if (error.response?.status === 405) {
            // Method not allowed - route mismatch, likely after session change
            // Silently retry the request once with a fresh token
            const originalRequest = error.config;
            if (originalRequest && !originalRequest._retry) {
                originalRequest._retry = true;
                // Get fresh CSRF token and retry immediately
                const freshToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
                if (freshToken) {
                    originalRequest.headers['X-CSRF-TOKEN'] = freshToken;
                    return api(originalRequest);
                }
            }
            // If retry failed or already retried, reload page
            console.warn('Method not allowed after retry - refreshing page');
            window.location.reload();
        } else if (error.response?.status === 422) {
            // Validation errors - return them for component handling
            return Promise.reject(error);
        } else if (error.response?.status === 403) {
            // Forbidden
            return Promise.reject(error);
        } else if (error.response?.status >= 500) {
            // Server error
            console.error('Server error:', error.response?.data);
        }
        return Promise.reject(error);
    }
);

export default api;

