/**
 * Production Admin API Client
 * Complete implementation following the documentation specifications
 */

class AdminAPI {
    constructor(baseURL = '/api') {
        this.baseURL = baseURL;
        this.accessToken = localStorage.getItem('admin_access_token');
        this.refreshToken = localStorage.getItem('admin_refresh_token');
        this.requestId = null;
    }

    // Generate unique request ID for each API call
    generateRequestId() {
        return 'req_' + Math.random().toString(36).substr(2, 9) + '_' + Date.now();
    }

    // Set authentication tokens
    setTokens(accessToken, refreshToken) {
        this.accessToken = accessToken;
        this.refreshToken = refreshToken;
        localStorage.setItem('admin_access_token', accessToken);
        if (refreshToken) {
            localStorage.setItem('admin_refresh_token', refreshToken);
        }
    }

    // Clear authentication tokens
    clearTokens() {
        this.accessToken = null;
        this.refreshToken = null;
        localStorage.removeItem('admin_access_token');
        localStorage.removeItem('admin_refresh_token');
    }

    // Make HTTP request with automatic token refresh
    async makeRequest(endpoint, options = {}) {
        this.requestId = this.generateRequestId();
        
        const url = `${this.baseURL}${endpoint}`;
        const headers = {
            'Content-Type': 'application/json',
            'X-Request-ID': this.requestId,
            ...options.headers
        };

        // Add authorization header if token exists
        if (this.accessToken && !options.skipAuth) {
            headers['Authorization'] = `Bearer ${this.accessToken}`;
        }

        // Add idempotency key for write operations
        if (['POST', 'PUT', 'PATCH'].includes(options.method?.toUpperCase()) && options.idempotent) {
            headers['Idempotency-Key'] = options.idempotencyKey || this.generateRequestId();
        }

        const requestOptions = {
            method: options.method || 'GET',
            headers,
            ...options
        };

        if (options.body && typeof options.body === 'object') {
            requestOptions.body = JSON.stringify(options.body);
        }

        try {
            const response = await fetch(url, requestOptions);
            
            // Handle token refresh if access token expired
            if (response.status === 401 && this.refreshToken && !options.skipRefresh) {
                const newToken = await this.refreshAccessToken();
                if (newToken) {
                    headers['Authorization'] = `Bearer ${newToken}`;
                    requestOptions.headers = headers;
                    return await fetch(url, requestOptions);
                }
            }

            if (!response.ok) {
                const errorData = await response.json().catch(() => ({}));
                throw new APIError(errorData.error || { 
                    code: 'HTTP_ERROR', 
                    message: `HTTP ${response.status}` 
                }, response.status);
            }

            return response.json();
        } catch (error) {
            if (error instanceof APIError) {
                throw error;
            }
            throw new APIError({
                code: 'NETWORK_ERROR',
                message: 'Network request failed',
                details: error.message
            }, 0);
        }
    }

    // Refresh access token
    async refreshAccessToken() {
        if (!this.refreshToken) {
            this.clearTokens();
            return null;
        }

        try {
            const response = await this.makeRequest('/auth/refresh', {
                method: 'POST',
                body: { refresh_token: this.refreshToken },
                skipAuth: true,
                skipRefresh: true
            });

            this.setTokens(response.access_token, this.refreshToken);
            return response.access_token;
        } catch (error) {
            this.clearTokens();
            return null;
        }
    }

    // Authentication
    async login(email, password) {
        const response = await this.makeRequest('/auth/login', {
            method: 'POST',
            body: { email, password },
            skipAuth: true
        });

        this.setTokens(response.access_token, response.refresh_token);
        return response;
    }

    async logout() {
        if (this.refreshToken) {
            try {
                await this.makeRequest('/auth/logout', {
                    method: 'POST',
                    body: { refresh_token: this.refreshToken }
                });
            } catch (error) {
                // Ignore logout errors
            }
        }
        this.clearTokens();
    }

    // User Management
    async getUsers(params = {}) {
        const queryString = new URLSearchParams(params).toString();
        const url = queryString ? `/users?${queryString}` : '/users';
        return await this.makeRequest(url);
    }

    async getUser(userId) {
        return await this.makeRequest(`/users/${userId}`);
    }

    async createUser(userData) {
        return await this.makeRequest('/users', {
            method: 'POST',
            body: userData,
            idempotent: true
        });
    }

    async updateUser(userId, userData) {
        return await this.makeRequest(`/users/${userId}`, {
            method: 'PUT',
            body: userData
        });
    }

    async deleteUser(userId) {
        return await this.makeRequest(`/users/${userId}`, {
            method: 'DELETE'
        });
    }

    // Balance Management
    async getUserBalances(userId) {
        return await this.makeRequest(`/balances/user/${userId}`);
    }

    async updateBalance(userId, asset, amount, type = 'credit') {
        return await this.makeRequest('/balances', {
            method: 'POST',
            body: { user_id: userId, asset, amount, type },
            idempotent: true
        });
    }

    async getAllBalances(params = {}) {
        const queryString = new URLSearchParams(params).toString();
        const url = queryString ? `/balances?${queryString}` : '/balances';
        return await this.makeRequest(url);
    }

    // Transaction Management
    async getTransactions(params = {}) {
        const queryString = new URLSearchParams(params).toString();
        const url = queryString ? `/transactions?${queryString}` : '/transactions';
        return await this.makeRequest(url);
    }

    async getUserTransactions(userId, params = {}) {
        const queryString = new URLSearchParams(params).toString();
        const url = queryString ? `/transactions/user/${userId}?${queryString}` : `/transactions/user/${userId}`;
        return await this.makeRequest(url);
    }

    async createTransaction(transactionData) {
        return await this.makeRequest('/transactions', {
            method: 'POST',
            body: transactionData,
            idempotent: true,
            idempotencyKey: transactionData.transaction_uuid || this.generateRequestId()
        });
    }

    // Card Management
    async getCards(params = {}) {
        const queryString = new URLSearchParams(params).toString();
        const url = queryString ? `/cards?${queryString}` : '/cards';
        return await this.makeRequest(url);
    }

    async getUserCards(userId) {
        return await this.makeRequest(`/cards/user/${userId}`);
    }

    async issueCard(cardData) {
        return await this.makeRequest('/cards', {
            method: 'POST',
            body: cardData,
            idempotent: true
        });
    }

    async updateCardStatus(cardId, status) {
        return await this.makeRequest(`/cards/${cardId}/status`, {
            method: 'PUT',
            body: { status }
        });
    }

    async freezeCard(cardId) {
        return await this.updateCardStatus(cardId, 'frozen');
    }

    async unfreezeCard(cardId) {
        return await this.updateCardStatus(cardId, 'active');
    }

    // KYC Management
    async getKYCSubmissions(params = {}) {
        const queryString = new URLSearchParams(params).toString();
        const url = queryString ? `/kyc?${queryString}` : '/kyc';
        return await this.makeRequest(url);
    }

    async getKYCSubmission(submissionId) {
        return await this.makeRequest(`/kyc/${submissionId}`);
    }

    async approveKYC(submissionId, reason = '') {
        return await this.makeRequest(`/kyc/${submissionId}/approve`, {
            method: 'POST',
            body: { reason }
        });
    }

    async rejectKYC(submissionId, reason) {
        return await this.makeRequest(`/kyc/${submissionId}/reject`, {
            method: 'POST',
            body: { reason }
        });
    }

    // Deposit Address Management
    async assignDepositAddress(userId, asset) {
        return await this.makeRequest('/crypto/assign-address', {
            method: 'POST',
            body: { user_id: userId, asset },
            idempotent: true
        });
    }

    async getDepositAddresses(params = {}) {
        const queryString = new URLSearchParams(params).toString();
        const url = queryString ? `/crypto/addresses?${queryString}` : '/crypto/addresses';
        return await this.makeRequest(url);
    }

    async addDepositAddress(asset, address) {
        return await this.makeRequest('/crypto/addresses', {
            method: 'POST',
            body: { asset, address }
        });
    }

    // Notification Management
    async getNotifications(params = {}) {
        const queryString = new URLSearchParams(params).toString();
        const url = queryString ? `/notifications?${queryString}` : '/notifications';
        return await this.makeRequest(url);
    }

    async createNotification(notificationData) {
        return await this.makeRequest('/notifications', {
            method: 'POST',
            body: notificationData
        });
    }

    async markNotificationRead(notificationId) {
        return await this.makeRequest(`/notifications/${notificationId}/read`, {
            method: 'PUT'
        });
    }

    // Audit Logs
    async getAuditLogs(params = {}) {
        const queryString = new URLSearchParams(params).toString();
        const url = queryString ? `/audit?${queryString}` : '/audit';
        return await this.makeRequest(url);
    }

    async getAdminActivity(adminId, days = 30) {
        return await this.makeRequest(`/audit/admin/${adminId}?days=${days}`);
    }

    async getSystemStats() {
        return await this.makeRequest('/audit/stats');
    }

    // Admin Management
    async getAdmins(params = {}) {
        const queryString = new URLSearchParams(params).toString();
        const url = queryString ? `/admin/users?${queryString}` : '/admin/users';
        return await this.makeRequest(url);
    }

    async createAdmin(adminData) {
        return await this.makeRequest('/admin/users', {
            method: 'POST',
            body: adminData
        });
    }

    async updateAdmin(adminId, adminData) {
        return await this.makeRequest(`/admin/users/${adminId}`, {
            method: 'PUT',
            body: adminData
        });
    }

    async resetPassword(userId, newPassword) {
        return await this.makeRequest('/admin/reset-password', {
            method: 'POST',
            body: { user_id: userId, new_password: newPassword }
        });
    }

    // Crypto Prices
    async getCryptoPrices() {
        return await this.makeRequest('/crypto/prices');
    }

    async updateCryptoPrice(asset, price) {
        return await this.makeRequest('/crypto/prices', {
            method: 'POST',
            body: { asset, price_usd: price }
        });
    }

    // File Upload (KYC Documents)
    async uploadKYCDocument(file, userId) {
        const formData = new FormData();
        formData.append('file', file);
        formData.append('user_id', userId);

        return await this.makeRequest('/kyc/upload', {
            method: 'POST',
            body: formData,
            headers: {
                // Don't set Content-Type, let browser set it for FormData
            }
        });
    }

    // Background Jobs
    async getBackgroundJobs(params = {}) {
        const queryString = new URLSearchParams(params).toString();
        const url = queryString ? `/admin/jobs?${queryString}` : '/admin/jobs';
        return await this.makeRequest(url);
    }

    async createBackgroundJob(jobType, payload) {
        return await this.makeRequest('/admin/jobs', {
            method: 'POST',
            body: { job_type: jobType, payload }
        });
    }

    async retryJob(jobId) {
        return await this.makeRequest(`/admin/jobs/${jobId}/retry`, {
            method: 'POST'
        });
    }

    // CSV Export/Import
    async exportUsersCSV(filters = {}) {
        const queryString = new URLSearchParams(filters).toString();
        const url = queryString ? `/admin/export/users?${queryString}` : '/admin/export/users';
        
        const response = await fetch(`${this.baseURL}${url}`, {
            headers: {
                'Authorization': `Bearer ${this.accessToken}`,
                'X-Request-ID': this.generateRequestId()
            }
        });

        if (!response.ok) {
            throw new APIError({ code: 'EXPORT_FAILED', message: 'Export failed' }, response.status);
        }

        return response.blob();
    }

    async importUsersCSV(file) {
        const formData = new FormData();
        formData.append('csv_file', file);

        return await this.makeRequest('/admin/import/users', {
            method: 'POST',
            body: formData,
            headers: {
                // Don't set Content-Type for FormData
            }
        });
    }

    // System Health & Monitoring
    async getSystemHealth() {
        return await this.makeRequest('/admin/health');
    }

    async getDatabaseStats() {
        return await this.makeRequest('/admin/stats/database');
    }

    async getPerformanceMetrics() {
        return await this.makeRequest('/admin/stats/performance');
    }

    // Utility methods for optimistic UI updates
    optimisticUpdate(cacheKey, updateFn, rollbackFn) {
        return {
            apply: () => updateFn(),
            rollback: () => rollbackFn()
        };
    }

    // Batch operations
    async batchUpdateBalances(updates) {
        return await this.makeRequest('/balances/batch', {
            method: 'POST',
            body: { updates },
            idempotent: true
        });
    }

    async batchCreateTransactions(transactions) {
        return await this.makeRequest('/transactions/batch', {
            method: 'POST',
            body: { transactions },
            idempotent: true
        });
    }
}

// Custom error class for API errors
class APIError extends Error {
    constructor(errorData, status) {
        super(errorData.message || 'API Error');
        this.name = 'APIError';
        this.code = errorData.code || 'UNKNOWN_ERROR';
        this.status = status;
        this.details = errorData.details || {};
        this.requestId = errorData.request_id;
    }

    isNetworkError() {
        return this.code === 'NETWORK_ERROR';
    }

    isAuthError() {
        return this.status === 401 || this.code === 'AUTH_REQUIRED' || this.code === 'TOKEN_INVALID';
    }

    isValidationError() {
        return this.code === 'VALIDATION_ERROR';
    }

    isRateLimitError() {
        return this.code === 'RATE_LIMIT_EXCEEDED';
    }
}

// Global instance
const adminApi = new AdminAPI();

// Export for use in modules
if (typeof module !== 'undefined' && module.exports) {
    module.exports = { AdminAPI, APIError, adminApi };
}