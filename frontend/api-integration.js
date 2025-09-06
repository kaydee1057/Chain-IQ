// Chain-IQ API Integration Layer
class ChainIQAPI {
    constructor() {
        this.baseURL = window.location.origin + '/api';
        this.currentUser = null;
        this.token = null;
    }

    async request(endpoint, options = {}) {
        const url = `${this.baseURL}${endpoint}`;
        const config = {
            headers: {
                'Content-Type': 'application/json',
                ...options.headers
            },
            ...options
        };

        if (this.token) {
            config.headers['Authorization'] = `Bearer ${this.token}`;
        }

        try {
            const response = await fetch(url, config);
            const data = await response.json();
            
            if (!response.ok) {
                throw new Error(data.error || 'API request failed');
            }
            
            return data;
        } catch (error) {
            console.error('API Error:', error);
            throw error;
        }
    }

    // Authentication
    async login(email, password) {
        const data = await this.request('/auth/login', {
            method: 'POST',
            body: JSON.stringify({ email, password })
        });
        
        this.currentUser = data.user;
        this.token = data.token;
        localStorage.setItem('chainiq_token', this.token);
        localStorage.setItem('chainiq_user', JSON.stringify(this.currentUser));
        
        return data;
    }

    logout() {
        this.currentUser = null;
        this.token = null;
        localStorage.removeItem('chainiq_token');
        localStorage.removeItem('chainiq_user');
    }

    // Load stored session
    loadSession() {
        const token = localStorage.getItem('chainiq_token');
        const user = localStorage.getItem('chainiq_user');
        
        if (token && user) {
            this.token = token;
            this.currentUser = JSON.parse(user);
            return true;
        }
        return false;
    }

    // Users
    async getUsers() {
        return await this.request('/users');
    }

    async createUser(name, email, password) {
        return await this.request('/users', {
            method: 'POST',
            body: JSON.stringify({ name, email, password })
        });
    }

    async updateUser(id, data) {
        return await this.request(`/users/${id}`, {
            method: 'PUT',
            body: JSON.stringify(data)
        });
    }

    async deleteUser(id) {
        return await this.request(`/users/${id}`, {
            method: 'DELETE'
        });
    }

    // Balances
    async getUserBalances(userId) {
        return await this.request(`/balances/${userId}`);
    }

    async updateBalance(userId, currency, balance) {
        return await this.request('/balances', {
            method: 'PUT',
            body: JSON.stringify({ user_id: userId, currency, balance })
        });
    }

    // Cards
    async getUserCards(userId) {
        return await this.request(`/cards/user/${userId}`);
    }

    async getAllCards() {
        return await this.request('/cards');
    }

    async createCard(userId, cardType) {
        return await this.request('/cards', {
            method: 'POST',
            body: JSON.stringify({ user_id: userId, card_type: cardType })
        });
    }

    async updateCard(cardId, data) {
        return await this.request(`/cards/${cardId}`, {
            method: 'PUT',
            body: JSON.stringify(data)
        });
    }

    // Transactions
    async getUserTransactions(userId) {
        return await this.request(`/transactions/user/${userId}`);
    }

    async getAllTransactions() {
        return await this.request('/transactions');
    }

    async createTransaction(userId, type, amount, currency, description) {
        return await this.request('/transactions', {
            method: 'POST',
            body: JSON.stringify({
                user_id: userId,
                type,
                amount,
                currency,
                description
            })
        });
    }

    // Notifications
    async getUserNotifications(userId) {
        return await this.request(`/notifications/user/${userId}`);
    }

    async createNotification(userId, title, message, type = 'info') {
        return await this.request('/notifications', {
            method: 'POST',
            body: JSON.stringify({
                user_id: userId,
                title,
                message,
                type
            })
        });
    }

    // Platform Settings
    async getSettings() {
        return await this.request('/settings');
    }

    async updateSettings(settings) {
        return await this.request('/settings', {
            method: 'PUT',
            body: JSON.stringify(settings)
        });
    }
}

// Global API instance
const chainIQAPI = new ChainIQAPI();

// Helper functions for demo data simulation
function generateDemoUser() {
    const names = ['Alice Johnson', 'Bob Smith', 'Charlie Brown', 'Diana Prince', 'Eve Wilson'];
    const name = names[Math.floor(Math.random() * names.length)];
    const email = name.toLowerCase().replace(' ', '.') + '@example.com';
    return { name, email, password: 'demo123' };
}

async function initializeDemoData() {
    try {
        // Check if users exist, if not create demo users
        const users = await chainIQAPI.getUsers();
        
        if (users.length === 0) {
            console.log('Creating demo users...');
            
            // Create 5 demo users
            for (let i = 0; i < 5; i++) {
                const user = generateDemoUser();
                await chainIQAPI.createUser(user.name, user.email, user.password);
            }
            
            // Get the created users
            const newUsers = await chainIQAPI.getUsers();
            
            // Add demo balances and cards for each user
            for (const user of newUsers) {
                // Add random balances
                await chainIQAPI.updateBalance(user.id, 'BTC', (Math.random() * 2 + 0.1).toFixed(8));
                await chainIQAPI.updateBalance(user.id, 'ETH', (Math.random() * 10 + 1).toFixed(6));
                await chainIQAPI.updateBalance(user.id, 'USDT', (Math.random() * 10000 + 1000).toFixed(2));
                await chainIQAPI.updateBalance(user.id, 'USDC', (Math.random() * 5000 + 500).toFixed(2));
                
                // Create 1-2 cards per user
                const cardTypes = ['platinum', 'gold', 'premium'];
                const numCards = Math.floor(Math.random() * 2) + 1;
                
                for (let j = 0; j < numCards; j++) {
                    const cardType = cardTypes[Math.floor(Math.random() * cardTypes.length)];
                    await chainIQAPI.createCard(user.id, cardType);
                }
                
                // Create demo transactions
                const txTypes = ['deposit', 'withdrawal', 'card_payment'];
                for (let k = 0; k < 5; k++) {
                    const type = txTypes[Math.floor(Math.random() * txTypes.length)];
                    const amount = Math.random() * 1000 + 10;
                    const currency = ['BTC', 'ETH', 'USDT', 'USDC'][Math.floor(Math.random() * 4)];
                    await chainIQAPI.createTransaction(
                        user.id, 
                        type, 
                        amount.toFixed(6), 
                        currency, 
                        `Demo ${type} transaction`
                    );
                }
            }
            
            console.log('Demo data initialized successfully');
        }
    } catch (error) {
        console.error('Error initializing demo data:', error);
    }
}

// Auto-login demo user
async function loginDemoUser() {
    try {
        const users = await chainIQAPI.getUsers();
        if (users.length > 0) {
            // Login as the first user
            await chainIQAPI.login(users[0].email, 'demo123');
            return chainIQAPI.currentUser;
        }
    } catch (error) {
        console.error('Demo login failed:', error);
    }
    return null;
}

// Export for use in HTML files
window.ChainIQAPI = ChainIQAPI;
window.chainIQAPI = chainIQAPI;
window.initializeDemoData = initializeDemoData;
window.loginDemoUser = loginDemoUser;