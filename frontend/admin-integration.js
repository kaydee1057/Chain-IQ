// Admin Panel Integration with Backend API
let adminData = {
    users: [],
    cards: [],
    transactions: [],
    settings: {}
};

// Load all admin data from API
async function loadAdminData() {
    try {
        const [users, cards, transactions, settings] = await Promise.all([
            chainIQAPI.getUsers(),
            chainIQAPI.getAllCards(),
            chainIQAPI.getAllTransactions(),
            chainIQAPI.getSettings()
        ]);

        adminData = { users, cards, transactions, settings };
        return adminData;
    } catch (error) {
        console.error('Error loading admin data:', error);
        throw error;
    }
}

// Render user management table
function renderUserTables() {
    const userTbody = document.getElementById('user-list-tbody');
    if (!userTbody) return;
    
    userTbody.innerHTML = '';
    
    adminData.users.forEach(user => {
        const row = document.createElement('tr');
        row.innerHTML = `
            <td>${user.name}</td>
            <td>${user.email}</td>
            <td>••••••••</td>
            <td>
                <div class="otp-input-group">
                    <input type="text" class="otp-input" value="123456" data-id="${user.id}">
                    <button class="btn-small btn-otp" data-id="${user.id}">Set</button>
                </div>
            </td>
            <td>
                <button class="btn-small btn-edit-name" data-id="${user.id}"><i class="fas fa-pen"></i> Name</button>
                <button class="btn-small btn-edit-email" data-id="${user.id}"><i class="fas fa-envelope"></i> Email</button>
                <button class="btn-small btn-password" data-id="${user.id}"><i class="fas fa-key"></i> Pass</button>
                <button class="btn-small btn-delete" data-id="${user.id}"><i class="fas fa-trash"></i></button>
            </td>
        `;
        userTbody.appendChild(row);
    });
}

// Render card management table
function renderCardManagementTable() {
    const tbody = document.getElementById('card-management-tbody');
    if (!tbody) return;
    
    tbody.innerHTML = '';
    
    adminData.cards.forEach(card => {
        const user = adminData.users.find(u => u.id == card.user_id);
        const row = document.createElement('tr');
        row.innerHTML = `
            <td>${user ? user.name : 'Unassigned'}</td>
            <td>**** **** **** ${card.card_number.slice(-4)}</td>
            <td>${card.cvv}</td>
            <td>${card.card_type}</td>
            <td class="status-${card.status.toLowerCase()}">${card.status}</td>
            <td>
                <button class="btn-small btn-edit" data-id="${card.id}">Edit</button>
                <button class="btn-small btn-freeze" data-id="${card.id}">${card.status === 'frozen' ? 'Unfreeze' : 'Freeze'}</button>
                <button class="btn-small btn-delete" data-id="${card.id}">Limits</button>
            </td>
        `;
        tbody.appendChild(row);
    });
}

// Handle user management actions
async function handleUserAction(e) {
    const target = e.target.closest('button');
    if (!target) return;
    
    const userId = target.dataset.id;
    const user = adminData.users.find(u => u.id == userId);
    if (!user) return;

    try {
        if (target.classList.contains('btn-edit-name')) {
            const newName = prompt('Enter new name:', user.name);
            if (newName && newName !== user.name) {
                await chainIQAPI.updateUser(userId, { name: newName });
                await loadAdminData(); // Reload all data
                renderUserTables();
                showNotification('Success', 'User name updated');
            }
        } else if (target.classList.contains('btn-edit-email')) {
            const newEmail = prompt('Enter new email:', user.email);
            if (newEmail && newEmail !== user.email) {
                await chainIQAPI.updateUser(userId, { email: newEmail });
                await loadAdminData(); // Reload all data
                renderUserTables();
                showNotification('Success', 'User email updated');
            }
        } else if (target.classList.contains('btn-delete')) {
            if (confirm(`Are you sure you want to delete ${user.name}?`)) {
                await chainIQAPI.deleteUser(userId);
                await loadAdminData(); // Reload all data
                renderUserTables();
                showNotification('Success', `User ${user.name} deleted`);
            }
        }
    } catch (error) {
        console.error('Error handling user action:', error);
        showNotification('Error', 'Failed to update user');
    }
}

// Handle card management actions
async function handleCardAction(e) {
    const target = e.target.closest('button');
    if (!target) return;
    
    const cardId = target.dataset.id;
    const card = adminData.cards.find(c => c.id == cardId);
    if (!card) return;

    try {
        if (target.classList.contains('btn-freeze')) {
            const newStatus = card.status === 'frozen' ? 'active' : 'frozen';
            await chainIQAPI.updateCard(cardId, { status: newStatus });
            await loadAdminData(); // Reload all data
            renderCardManagementTable();
            showNotification('Success', `Card ${newStatus}`);
        }
    } catch (error) {
        console.error('Error handling card action:', error);
        showNotification('Error', 'Failed to update card');
    }
}

// Create new user
async function createUser(formData) {
    try {
        const result = await chainIQAPI.createUser(
            formData.get('name'),
            formData.get('email'),
            formData.get('password')
        );
        
        // Reload admin data
        await loadAdminData();
        renderUserTables();
        showNotification('Success', 'User created successfully');
    } catch (error) {
        console.error('Error creating user:', error);
        showNotification('Error', 'Failed to create user');
    }
}

// Update balance
async function updateBalance(userId, currency, amount) {
    try {
        await chainIQAPI.updateBalance(userId, currency, amount);
        showNotification('Success', 'Balance updated');
    } catch (error) {
        console.error('Error updating balance:', error);
        showNotification('Error', 'Failed to update balance');
    }
}

// Create transaction
async function createTransaction(userId, type, amount, currency, description) {
    try {
        await chainIQAPI.createTransaction(userId, type, amount, currency, description);
        await loadAdminData();
        showNotification('Success', 'Transaction created');
    } catch (error) {
        console.error('Error creating transaction:', error);
        showNotification('Error', 'Failed to create transaction');
    }
}

// Send notification
async function sendNotification(userId, title, message, type = 'info') {
    try {
        await chainIQAPI.createNotification(userId, title, message, type);
        showNotification('Success', 'Notification sent');
    } catch (error) {
        console.error('Error sending notification:', error);
        showNotification('Error', 'Failed to send notification');
    }
}

// Initialize admin panel
async function initAdminPanel() {
    try {
        // Initialize demo data if needed
        await initializeDemoData();
        
        // Load all admin data
        await loadAdminData();
        
        // Render tables
        renderUserTables();
        renderCardManagementTable();
        
        // Setup event listeners
        const userTbody = document.getElementById('user-list-tbody');
        if (userTbody) {
            userTbody.addEventListener('click', handleUserAction);
        }
        
        const cardTbody = document.getElementById('card-management-tbody');
        if (cardTbody) {
            cardTbody.addEventListener('click', handleCardAction);
        }
        
        // Setup form handlers
        const createUserForm = document.getElementById('create-user-form');
        if (createUserForm) {
            createUserForm.addEventListener('submit', async (e) => {
                e.preventDefault();
                await createUser(new FormData(e.target));
                e.target.reset();
            });
        }
        
        console.log('Admin panel initialized successfully');
        
    } catch (error) {
        console.error('Error initializing admin panel:', error);
        showNotification('Error', 'Failed to initialize admin panel');
    }
}

// Show notification function (fallback if not defined)
window.showNotification = window.showNotification || function(title, message) {
    console.log(`${title}: ${message}`);
    alert(`${title}: ${message}`);
};

// Export functions
window.adminData = adminData;
window.loadAdminData = loadAdminData;
window.initAdminPanel = initAdminPanel;
window.handleUserAction = handleUserAction;
window.handleCardAction = handleCardAction;