// Frontend Chain-IQ Integration with Backend API

// Initialize frontend with backend integration
async function initFrontendIntegration() {
    try {
        // Initialize demo data if needed
        await initializeDemoData();
        
        // Auto-login demo user
        const user = await loginDemoUser();
        if (user) {
            console.log('Demo user logged in:', user.name);
            await loadUserData();
        }
        
    } catch (error) {
        console.error('Error initializing frontend:', error);
    }
}

// Load user data from backend
async function loadUserData() {
    if (!chainIQAPI.currentUser) return;
    
    try {
        const userId = chainIQAPI.currentUser.id;
        
        // Load user balances, cards, transactions
        const [balances, cards, transactions] = await Promise.all([
            chainIQAPI.getUserBalances(userId),
            chainIQAPI.getUserCards(userId),
            chainIQAPI.getUserTransactions(userId)
        ]);
        
        // Update frontend with real data
        updateDashboardWithRealData(balances, cards, transactions);
        
    } catch (error) {
        console.error('Error loading user data:', error);
    }
}

// Update dashboard with real backend data
function updateDashboardWithRealData(balances, cards, transactions) {
    // Update balances display
    updateBalancesDisplay(balances);
    
    // Update cards display
    updateCardsDisplay(cards);
    
    // Update transactions display
    updateTransactionsDisplay(transactions);
}

// Update balances display
function updateBalancesDisplay(balances) {
    const balanceElements = {
        'BTC': { element: document.querySelector('[data-currency="BTC"]'), price: 65000 },
        'ETH': { element: document.querySelector('[data-currency="ETH"]'), price: 3500 },
        'USDT': { element: document.querySelector('[data-currency="USDT"]'), price: 1 },
        'USDC': { element: document.querySelector('[data-currency="USDC"]'), price: 1 }
    };
    
    let totalUSD = 0;
    
    balances.forEach(balance => {
        const currency = balance.currency;
        const amount = parseFloat(balance.balance);
        const priceInfo = balanceElements[currency];
        
        if (priceInfo && priceInfo.element) {
            const usdValue = amount * priceInfo.price;
            totalUSD += usdValue;
            
            // Update balance display
            priceInfo.element.textContent = amount.toFixed(currency === 'BTC' ? 8 : currency === 'ETH' ? 6 : 2);
        }
    });
    
    // Update total balance display
    const totalBalanceElement = document.getElementById('total-balance');
    if (totalBalanceElement) {
        totalBalanceElement.textContent = '$' + totalUSD.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }
}

// Update cards display
function updateCardsDisplay(cards) {
    if (cards.length === 0) return;
    
    const cardStack = document.getElementById('cardStack');
    const cardStackPage = document.getElementById('cardStackPage');
    
    if (cardStack) cardStack.innerHTML = '';
    if (cardStackPage) cardStackPage.innerHTML = '';
    
    cards.forEach((card, index) => {
        const cardHTML = createCardHTML(card, index);
        if (cardStack) cardStack.innerHTML += cardHTML;
        if (cardStackPage) cardStackPage.innerHTML += cardHTML;
    });
}

// Create card HTML
function createCardHTML(card, index) {
    const cardTypeClasses = {
        platinum: 'card-platinum',
        gold: 'card-gold',  
        premium: 'card-premium'
    };
    
    const cardClass = cardTypeClasses[card.card_type] || 'card-platinum';
    const isActive = index === 0 ? 'is-active' : '';
    const isFrozen = card.status === 'frozen' ? 'frozen' : '';
    
    return `
        <div class="virtual-card ${cardClass} ${isActive} ${isFrozen}" data-card-id="${card.id}" style="z-index: ${10 - index};">
            <div class="virtual-card-pattern"></div>
            <div class="card-top">
                <div class="card-chip">
                    <svg width="40" height="30" viewBox="0 0 40 30" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <rect width="40" height="30" rx="4" fill="url(#chip-gradient)"/>
                        <defs>
                            <linearGradient id="chip-gradient" x1="0" y1="0" x2="40" y2="30" gradientUnits="userSpaceOnUse">
                                <stop offset="0%" stop-color="var(--chip-gold)"/>
                                <stop offset="50%" stop-color="var(--chip-light)"/>
                                <stop offset="100%" stop-color="var(--chip-dark)"/>
                            </linearGradient>
                        </defs>
                    </svg>
                </div>
                <div class="card-brand">Chain-IQ</div>
            </div>
            <div class="card-number">**** **** **** ${card.card_number.slice(-4)}</div>
            <div class="card-bottom">
                <div class="card-details">
                    <div class="card-holder">${card.cardholder_name}</div>
                    <div class="card-expiry">${card.expiry_month.toString().padStart(2, '0')}/${card.expiry_year.toString().slice(-2)}</div>
                </div>
                <div class="card-network">VISA</div>
            </div>
        </div>
    `;
}

// Update transactions display
function updateTransactionsDisplay(transactions) {
    const transactionsList = document.getElementById('transactions-list');
    if (!transactionsList || transactions.length === 0) return;
    
    transactionsList.innerHTML = '';
    
    transactions.slice(0, 10).forEach(transaction => {
        const transactionHTML = `
            <div class="transaction-item">
                <div class="transaction-icon">
                    <i class="fas ${getTransactionIcon(transaction.type)}"></i>
                </div>
                <div class="transaction-details">
                    <div class="transaction-title">${formatTransactionType(transaction.type)}</div>
                    <div class="transaction-date">${new Date(transaction.created_at).toLocaleDateString()}</div>
                </div>
                <div class="transaction-amount ${transaction.type === 'deposit' ? 'positive' : 'negative'}">
                    ${transaction.type === 'deposit' ? '+' : '-'}${transaction.amount} ${transaction.currency}
                </div>
            </div>
        `;
        transactionsList.innerHTML += transactionHTML;
    });
}

// Helper functions
function getTransactionIcon(type) {
    const icons = {
        deposit: 'fa-arrow-down',
        withdrawal: 'fa-arrow-up', 
        transfer: 'fa-exchange-alt',
        card_payment: 'fa-credit-card',
        convert: 'fa-sync-alt'
    };
    return icons[type] || 'fa-dollar-sign';
}

function formatTransactionType(type) {
    const types = {
        deposit: 'Deposit',
        withdrawal: 'Withdrawal',
        transfer: 'Transfer', 
        card_payment: 'Card Payment',
        convert: 'Convert'
    };
    return types[type] || type;
}

// Override deposit function to use real API
async function performDeposit(currency, amount, method) {
    if (!chainIQAPI.currentUser) {
        showNotification('Error', 'Please log in first');
        return;
    }
    
    try {
        await chainIQAPI.createTransaction(
            chainIQAPI.currentUser.id,
            'deposit',
            amount,
            currency,
            `${method} deposit`
        );
        
        // Reload user data
        await loadUserData();
        
        showNotification('Success', `Deposit of ${amount} ${currency} successful`);
    } catch (error) {
        console.error('Deposit error:', error);
        showNotification('Error', 'Deposit failed');
    }
}

// Override withdrawal function to use real API
async function performWithdrawal(currency, amount, address) {
    if (!chainIQAPI.currentUser) {
        showNotification('Error', 'Please log in first');
        return;
    }
    
    try {
        await chainIQAPI.createTransaction(
            chainIQAPI.currentUser.id,
            'withdrawal', 
            amount,
            currency,
            `Withdrawal to ${address}`
        );
        
        // Reload user data
        await loadUserData();
        
        showNotification('Success', `Withdrawal of ${amount} ${currency} initiated`);
    } catch (error) {
        console.error('Withdrawal error:', error);
        showNotification('Error', 'Withdrawal failed');
    }
}

// Request new card
async function requestCard(cardType) {
    if (!chainIQAPI.currentUser) {
        showNotification('Error', 'Please log in first');
        return;
    }
    
    try {
        await chainIQAPI.createCard(chainIQAPI.currentUser.id, cardType);
        
        // Reload user data
        await loadUserData();
        
        showNotification('Success', `${cardType} card created successfully`);
    } catch (error) {
        console.error('Card creation error:', error);
        showNotification('Error', 'Failed to create card');
    }
}

// Export functions
window.initFrontendIntegration = initFrontendIntegration;
window.loadUserData = loadUserData;
window.performDeposit = performDeposit;
window.performWithdrawal = performWithdrawal;
window.requestCard = requestCard;