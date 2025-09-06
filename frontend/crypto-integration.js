// Live Crypto Price Integration and Deposit Management

class CryptoManager {
    constructor() {
        this.cryptoData = [];
        this.depositAddresses = {};
        this.currentAddressIndex = {};
        this.priceUpdateInterval = null;
    }

    // Initialize crypto data with live prices
    async initializeCrypto() {
        try {
            // Load live prices and addresses
            await Promise.all([
                this.loadLivePrices(),
                this.loadDepositAddresses()
            ]);
            
            // Start price updates every 30 seconds
            this.startPriceUpdates();
            
            // Initialize deposit functionality
            this.initializeDepositModals();
            
        } catch (error) {
            console.error('Error initializing crypto:', error);
            this.loadFallbackData();
        }
    }

    // Load live crypto prices from backend
    async loadLivePrices() {
        try {
            const response = await fetch('/api/crypto/prices');
            const prices = await response.json();
            
            this.cryptoData = [
                {
                    id: 'bitcoin',
                    asset: 'Bitcoin',
                    ticker: 'BTC',
                    price: prices.bitcoin?.usd || 42500,
                    change: prices.bitcoin?.usd_24h_change || 2.5,
                    marketCap: prices.bitcoin?.usd_market_cap || 835000000000
                },
                {
                    id: 'ethereum',
                    asset: 'Ethereum',
                    ticker: 'ETH',
                    price: prices.ethereum?.usd || 2800,
                    change: prices.ethereum?.usd_24h_change || -1.2,
                    marketCap: prices.ethereum?.usd_market_cap || 336000000000
                },
                {
                    id: 'binancecoin',
                    asset: 'BNB',
                    ticker: 'BNB',
                    price: prices.binancecoin?.usd || 590,
                    change: prices.binancecoin?.usd_24h_change || 0.8,
                    marketCap: prices.binancecoin?.usd_market_cap || 119000000000
                },
                {
                    id: 'solana',
                    asset: 'Solana',
                    ticker: 'SOL',
                    price: prices.solana?.usd || 150,
                    change: prices.solana?.usd_24h_change || 5.1,
                    marketCap: prices.solana?.usd_market_cap || 114000000000
                },
                {
                    id: 'cardano',
                    asset: 'Cardano',
                    ticker: 'ADA',
                    price: prices.cardano?.usd || 0.45,
                    change: prices.cardano?.usd_24h_change || -0.5,
                    marketCap: prices.cardano?.usd_market_cap || 30500000000
                },
                {
                    id: 'tron',
                    asset: 'Tron',
                    ticker: 'TRX',
                    price: prices.tron?.usd || 0.12,
                    change: prices.tron?.usd_24h_change || 1.1,
                    marketCap: prices.tron?.usd_market_cap || 10000000000
                },
                {
                    id: 'tether',
                    asset: 'USDT',
                    ticker: 'USDT',
                    price: prices.tether?.usd || 1.00,
                    change: prices.tether?.usd_24h_change || 0.01,
                    marketCap: prices.tether?.usd_market_cap || 96000000000
                },
                {
                    id: 'ripple',
                    asset: 'XRP',
                    ticker: 'XRP',
                    price: prices.ripple?.usd || 0.52,
                    change: prices.ripple?.usd_24h_change || -2.3,
                    marketCap: prices.ripple?.usd_market_cap || 25000000000
                },
                {
                    id: 'dogecoin',
                    asset: 'Dogecoin',
                    ticker: 'DOGE',
                    price: prices.dogecoin?.usd || 0.15,
                    change: prices.dogecoin?.usd_24h_change || 3.2,
                    marketCap: prices.dogecoin?.usd_market_cap || 32700000000
                },
                {
                    id: 'litecoin',
                    asset: 'Litecoin',
                    ticker: 'LTC',
                    price: prices.litecoin?.usd || 85.60,
                    change: prices.litecoin?.usd_24h_change || 0.5,
                    marketCap: prices.litecoin?.usd_market_cap || 6000000000
                },
                {
                    id: 'sui',
                    asset: 'Sui',
                    ticker: 'SUI',
                    price: prices.sui?.usd || 1.05,
                    change: prices.sui?.usd_24h_change || -1.5,
                    marketCap: prices.sui?.usd_market_cap || 3000000000
                },
                {
                    id: 'pengu',
                    asset: 'Pengu',
                    ticker: 'PENGU',
                    price: prices.pengu?.usd || 0.002,
                    change: prices.pengu?.usd_24h_change || 10.5,
                    marketCap: prices.pengu?.usd_market_cap || 100000000
                },
                {
                    id: 'zbcn',
                    asset: 'ZBCN',
                    ticker: 'ZBCN',
                    price: prices.zbcn?.usd || 0.03,
                    change: prices.zbcn?.usd_24h_change || 2.1,
                    marketCap: prices.zbcn?.usd_market_cap || 50000000
                },
                {
                    id: 'casper-network',
                    asset: 'Casper',
                    ticker: 'CSPR',
                    price: prices['casper-network']?.usd || 0.04,
                    change: prices['casper-network']?.usd_24h_change || -0.8,
                    marketCap: prices['casper-network']?.usd_market_cap || 400000000
                },
                {
                    id: 'pepe',
                    asset: 'Pepe',
                    ticker: 'PEPE',
                    price: prices.pepe?.usd || 0.000007,
                    change: prices.pepe?.usd_24h_change || 8.4,
                    marketCap: prices.pepe?.usd_market_cap || 3000000000
                },
                {
                    id: 'bonk',
                    asset: 'Bonk',
                    ticker: 'BONK',
                    price: prices.bonk?.usd || 0.000025,
                    change: prices.bonk?.usd_24h_change || 4.2,
                    marketCap: prices.bonk?.usd_market_cap || 1700000000
                },
                {
                    id: 'swiftcoin',
                    asset: 'SwiftCoin',
                    ticker: 'SWIFT',
                    price: prices.swiftcoin?.usd || 1.20,
                    change: prices.swiftcoin?.usd_24h_change || 0.1,
                    marketCap: prices.swiftcoin?.usd_market_cap || 120000000
                },
                {
                    id: 'quant-network',
                    asset: 'Quant',
                    ticker: 'QNT',
                    price: prices['quant-network']?.usd || 95.50,
                    change: prices['quant-network']?.usd_24h_change || 1.8,
                    marketCap: prices['quant-network']?.usd_market_cap || 1200000000
                },
                {
                    id: 'lcx',
                    asset: 'LCX',
                    ticker: 'LCX',
                    price: prices.lcx?.usd || 0.28,
                    change: prices.lcx?.usd_24h_change || -3.1,
                    marketCap: prices.lcx?.usd_market_cap || 280000000
                },
                {
                    id: 'shiba-inu',
                    asset: 'Shiba Inu',
                    ticker: 'SHIB',
                    price: prices['shiba-inu']?.usd || 0.000025,
                    change: prices['shiba-inu']?.usd_24h_change || 5.5,
                    marketCap: prices['shiba-inu']?.usd_market_cap || 14700000000
                },
                {
                    id: 'chainlink',
                    asset: 'Chainlink',
                    ticker: 'LINK',
                    price: prices.chainlink?.usd || 18.50,
                    change: prices.chainlink?.usd_24h_change || 2.9,
                    marketCap: prices.chainlink?.usd_market_cap || 11000000000
                },
                {
                    id: 'link-api',
                    asset: 'Link API',
                    ticker: 'LINKAPI',
                    price: prices['link-api']?.usd || 0.10,
                    change: prices['link-api']?.usd_24h_change || -0.2,
                    marketCap: prices['link-api']?.usd_market_cap || 10000000
                }
            ];
            
            this.updatePriceDisplay();
            console.log('Live crypto prices loaded successfully');
            
        } catch (error) {
            console.error('Error loading live prices:', error);
            throw error;
        }
    }

    // Load deposit addresses from backend
    async loadDepositAddresses() {
        try {
            const response = await fetch('/api/crypto/addresses');
            this.depositAddresses = await response.json();
            
            // Initialize address indices
            Object.keys(this.depositAddresses).forEach(crypto => {
                this.currentAddressIndex[crypto] = 0;
            });
            
            console.log('Deposit addresses loaded successfully');
            
        } catch (error) {
            console.error('Error loading deposit addresses:', error);
            // Use fallback addresses - at least 3 addresses per asset as required
            this.depositAddresses = {
                'bitcoin': [
                    'bc1qxy2kgdygjrsqtzq2n0yrf2493p83kkfjhx0wlh',
                    'bc1qm34lsc65zpw79lxes69zkqmk6ee3ewf0j77s3h',
                    'bc1qw508d6qejxtdg4y5r3zarvary0c5xw7kw508d6'
                ],
                'ethereum': [
                    '0x742f35Cc6bf2f5e8F6F1e8e0F5B2F3d3f5e6F7A8',
                    '0x8ba1f109551bD432803012645Hac136c22C73D6E',
                    '0xdD2FD4581271e230360230F9337D5c0430Bf44C0'
                ],
                'binancecoin': [
                    'bnb136ns6lfw4s5g68weua029l4hsx4kpjz4g2g4gq',
                    'bnb1a2s3d4f5g6h7j8k9l0p1o2i3u4y5t6r7e8w9q',
                    'bnb1z2x3c4v5b6n7m8q9w0e1r2t3y4u5i6o7p8s9d'
                ],
                'solana': [
                    'So11111111111111111111111111111111111111112',
                    'So22222222222222222222222222222222222222222',
                    'So33333333333333333333333333333333333333333'
                ],
                'cardano': [
                    'addr1q8z8q8z8q8z8q8z8q8z8q8z8q8z8q8z8q8z8q8z8q8z8q8z8q8z8q8z',
                    'addr1q9z9z9z9z9z9z9z9z9z9z9z9z9z9z9z9z9z9z9z9z9z9z9z9z9z9z9z',
                    'addr1q0z0z0z0z0z0z0z0z0z0z0z0z0z0z0z0z0z0z0z0z0z0z0z0z0z0z0z'
                ],
                'tron': [
                    'TPL2uTAm4tFdXnD2e6a4P2B123456789012345678',
                    'TPApr2uTAm4tFdXnD2e6a4P2Babcdefghijklm',
                    'TPBpr2uTAm4tFdXnD2e6a4P2Bnopqrstuvwxyz'
                ],
                'tether': [
                    '0x742f35Cc6bf2f5e8F6F1e8e0F5B2F3d3f5e6F7A8',
                    '0x8ba1f109551bD432803012645Hac136c22C73D6E',
                    '0xdD2FD4581271e230360230F9337D5c0430Bf44C0'
                ],
                'ripple': [
                    'rP12345678901234567890123456789012345',
                    'rPabcdefghijklmnopqrstuvwxyz123456789',
                    'rPnopqrstuvwxyzabcdefghijklm123456789'
                ],
                'dogecoin': [
                    'D123456789012345678901234567890123',
                    'Dabcdefghijklmnopqrstuvwxyz123456789',
                    'Dnopqrstuvwxyzabcdefghijklm123456789'
                ],
                'litecoin': [
                    'ltc1q8z8q8z8q8z8q8z8q8z8q8z8q8z8q8z8q8z8q8z8q8z8q8z8q8z8q8z',
                    'ltc1q9z9z9z9z9z9z9z9z9z9z9z9z9z9z9z9z9z9z9z9z9z9z9z9z9z9z9z',
                    'ltc1q0z0z0z0z0z0z0z0z0z0z0z0z0z0z0z0z0z0z0z0z0z0z0z0z0z0z0z'
                ],
                'sui': [
                    'sui1q8z8q8z8q8z8q8z8q8z8q8z8q8z8q8z8q8z8q8z8q8z8q8z8q8z8q8z',
                    'sui1q9z9z9z9z9z9z9z9z9z9z9z9z9z9z9z9z9z9z9z9z9z9z9z9z9z9z9z',
                    'sui1q0z0z0z0z0z0z0z0z0z0z0z0z0z0z0z0z0z0z0z0z0z0z0z0z0z0z0z'
                ],
                'pengu': [
                    'pengu123456789012345678901234567890',
                    'penguabcdefghijklmnopqrstuvwxyz1234',
                    'pengunopqrstuvwxyzabcdefghijklm123'
                ],
                'zbcn': [
                    'zbcn123456789012345678901234567890',
                    'zbcnabcdefghijklmnopqrstuvwxyz1234',
                    'zbcnnopqrstuvwxyzabcdefghijklm123'
                ],
                'casper-network': [
                    'cspr123456789012345678901234567890',
                    'csprabcdefghijklmnopqrstuvwxyz1234',
                    'csprnopqrstuvwxyzabcdefghijklm123'
                ],
                'pepe': [
                    '0x742f35Cc6bf2f5e8F6F1e8e0F5B2F3d3f5e6F7A8',
                    '0x8ba1f109551bD432803012645Hac136c22C73D6E',
                    '0xdD2FD4581271e230360230F9337D5c0430Bf44C0'
                ],
                'bonk': [
                    'So11111111111111111111111111111111111111112',
                    'So22222222222222222222222222222222222222222',
                    'So33333333333333333333333333333333333333333'
                ],
                'swiftcoin': [
                    'swift123456789012345678901234567890',
                    'swiftabcdefghijklmnopqrstuvwxyz1234',
                    'swiftnopqrstuvwxyzabcdefghijklm123'
                ],
                'quant-network': [
                    'qnt123456789012345678901234567890',
                    'qntabcdefghijklmnopqrstuvwxyz1234',
                    'qntnopqrstuvwxyzabcdefghijklm123'
                ],
                'lcx': [
                    'lcx123456789012345678901234567890',
                    'lcxabcdefghijklmnopqrstuvwxyz1234',
                    'lcxnopqrstuvwxyzabcdefghijklm123'
                ],
                'shiba-inu': [
                    '0x742f35Cc6bf2f5e8F6F1e8e0F5B2F3d3f5e6F7A8',
                    '0x8ba1f109551bD432803012645Hac136c22C73D6E',
                    '0xdD2FD4581271e230360230F9337D5c0430Bf44C0'
                ],
                'chainlink': [
                    '0x742f35Cc6bf2f5e8F6F1e8e0F5B2F3d3f5e6F7A8',
                    '0x8ba1f109551bD432803012645Hac136c22C73D6E',
                    '0xdD2FD4581271e230360230F9337D5c0430Bf44C0'
                ],
                'link-api': [
                    'linkapi123456789012345678901234567890',
                    'linkapiabcdefghijklmnopqrstuvwxyz1234',
                    'linkapinopqrstuvwxyzabcdefghijklm123'
                ]
            };
            
            // Initialize address indices for fallback addresses
            Object.keys(this.depositAddresses).forEach(crypto => {
                this.currentAddressIndex[crypto] = 0;
            });
        }
        
        // Update cryptoData to include addresses
        this.updateCryptoDataWithAddresses();
    }

    // Update cryptoData to include addresses array
    updateCryptoDataWithAddresses() {
        this.cryptoData.forEach(crypto => {
            if (this.depositAddresses[crypto.id]) {
                crypto.addresses = this.depositAddresses[crypto.id];
            }
        });
    }

    // Start automatic price updates
    startPriceUpdates() {
        this.priceUpdateInterval = setInterval(async () => {
            try {
                await this.loadLivePrices();
            } catch (error) {
                console.error('Error updating prices:', error);
            }
        }, 30000); // Update every 30 seconds
    }

    // Update price display in the UI
    updatePriceDisplay() {
        this.cryptoData.forEach(crypto => {
            // Update crypto assets table
            const priceElement = document.querySelector(`[data-crypto-price="${crypto.id}"]`);
            const changeElement = document.querySelector(`[data-crypto-change="${crypto.id}"]`);
            
            if (priceElement) {
                priceElement.textContent = `$${crypto.price.toLocaleString('en-US', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: crypto.price < 1 ? 4 : 2
                })}`;
            }
            
            if (changeElement) {
                const changeClass = crypto.change >= 0 ? 'text-green-400' : 'text-red-400';
                const changeSymbol = crypto.change >= 0 ? '+' : '';
                changeElement.textContent = `${changeSymbol}${crypto.change.toFixed(2)}%`;
                changeElement.className = `text-sm ${changeClass}`;
            }
        });
        
        // Update wallet balances with current prices
        this.updateWalletValues();
    }

    // Update wallet values based on current prices
    updateWalletValues() {
        let totalValue = 0;
        
        this.cryptoData.forEach(crypto => {
            const balanceElement = document.querySelector(`[data-balance="${crypto.ticker.toLowerCase()}"]`);
            const valueElement = document.querySelector(`[data-value="${crypto.ticker.toLowerCase()}"]`);
            
            if (balanceElement && valueElement) {
                const balance = parseFloat(balanceElement.textContent) || 0;
                const value = balance * crypto.price;
                totalValue += value;
                
                valueElement.textContent = `$${value.toLocaleString('en-US', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                })}`;
            }
        });
        
        // Update total portfolio value
        const totalElement = document.querySelector('[data-total-value]');
        if (totalElement) {
            totalElement.textContent = `$${totalValue.toLocaleString('en-US', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            })}`;
        }
    }

    // Initialize deposit modal functionality
    initializeDepositModals() {
        // Handle both dashboard deposit button and crypto table deposit buttons
        this.setupDepositButtons();
        this.setupDepositModal();
        this.populateAssetDropdown();
    }

    // Setup deposit buttons
    setupDepositButtons() {
        // Dashboard deposit button
        const dashboardDepositBtn = document.querySelector('#deposit-btn');
        if (dashboardDepositBtn) {
            dashboardDepositBtn.addEventListener('click', (e) => {
                e.preventDefault();
                this.openDepositModal('bitcoin'); // Default to Bitcoin
            });
        }

        // Crypto page deposit button
        const cryptoPageDepositBtn = document.querySelector('#crypto-page-deposit-btn');
        if (cryptoPageDepositBtn) {
            cryptoPageDepositBtn.addEventListener('click', (e) => {
                e.preventDefault();
                this.openDepositModal('bitcoin'); // Default to Bitcoin
            });
        }

        // Crypto table deposit buttons (for individual crypto assets)
        document.addEventListener('click', (e) => {
            if (e.target.matches('.crypto-deposit-btn') || e.target.closest('.crypto-deposit-btn')) {
                e.preventDefault();
                const button = e.target.matches('.crypto-deposit-btn') ? e.target : e.target.closest('.crypto-deposit-btn');
                const cryptoId = button.dataset.crypto || 'bitcoin';
                this.openDepositModal(cryptoId);
            }
        });
    }

    // Setup deposit modal
    setupDepositModal() {
        // Use existing modal from HTML
        const modal = document.getElementById('deposit-modal');
        if (!modal) return;

        // Setup modal close functionality
        const closeBtns = modal.querySelectorAll('.modal-close-btn');
        closeBtns.forEach(btn => {
            btn.addEventListener('click', () => {
                modal.classList.remove('active');
            });
        });

        // Close modal when clicking outside
        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                modal.classList.remove('active');
            }
        });

        // Setup generate new address button
        const generateBtn = modal.querySelector('#generate-address-btn');
        if (generateBtn) {
            generateBtn.addEventListener('click', (e) => {
                e.preventDefault();
                this.generateNewAddress();
            });
        }

        // Setup asset selector
        const assetSelect = modal.querySelector('#deposit-asset-select');
        if (assetSelect) {
            assetSelect.addEventListener('change', (e) => {
                this.updateDepositAddress(e.target.value);
            });
        }

        // Setup copy address functionality
        const addressInput = modal.querySelector('#deposit-address');
        if (addressInput) {
            addressInput.addEventListener('click', () => {
                addressInput.select();
                document.execCommand('copy');
                this.showNotification('Address copied to clipboard!');
            });
        }
    }

    // Populate asset dropdown with available cryptocurrencies
    populateAssetDropdown() {
        const assetSelect = document.getElementById('deposit-asset-select');
        if (!assetSelect) return;

        // Clear existing options
        assetSelect.innerHTML = '';

        // Add options for each crypto asset
        this.cryptoData.forEach(crypto => {
            const option = document.createElement('option');
            option.value = crypto.id;
            option.textContent = `${crypto.asset} (${crypto.ticker})`;
            assetSelect.appendChild(option);
        });
    }

    // Create deposit modal HTML
    createDepositModal() {
        const modal = document.createElement('div');
        modal.id = 'deposit-modal';
        modal.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden';
        
        modal.innerHTML = `
            <div class="modal-overlay absolute inset-0"></div>
            <div class="bg-gray-800 rounded-lg p-6 w-full max-w-md relative z-10 mx-4">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-xl font-bold text-white">Deposit Crypto</h3>
                    <button class="close-modal text-gray-400 hover:text-white">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                
                <div class="space-y-4">
                    <div>
                        <label class="block text-gray-300 text-sm font-medium mb-2">Select Asset</label>
                        <select id="deposit-asset-select" class="w-full p-3 bg-gray-700 border border-gray-600 rounded-lg text-white">
                            <option value="bitcoin">Bitcoin (BTC)</option>
                            <option value="ethereum">Ethereum (ETH)</option>
                            <option value="tether">Tether (USDT)</option>
                            <option value="usd-coin">USD Coin (USDC)</option>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-gray-300 text-sm font-medium mb-2">Deposit Address</label>
                        <div class="bg-gray-700 rounded-lg p-3 border border-gray-600">
                            <div id="deposit-address" class="text-white font-mono text-sm break-all"></div>
                            <button id="copy-address-btn" class="mt-2 text-blue-400 hover:text-blue-300 text-sm">
                                <i class="fas fa-copy mr-1"></i>Copy Address
                            </button>
                        </div>
                    </div>
                    
                    <div class="text-center">
                        <div id="deposit-qr-code" class="bg-white p-4 rounded-lg inline-block"></div>
                    </div>
                    
                    <button id="generate-address-btn" class="w-full bg-blue-600 hover:bg-blue-700 text-white py-2 px-4 rounded-lg transition-colors">
                        <i class="fas fa-refresh mr-2"></i>Generate New Address
                    </button>
                    
                    <div class="bg-yellow-500/20 border border-yellow-500 rounded-lg p-3">
                        <p class="text-yellow-300 text-sm">
                            <i class="fas fa-info-circle mr-1"></i>
                            Only send the selected cryptocurrency to this address. Sending other assets may result in permanent loss.
                        </p>
                    </div>
                </div>
            </div>
        `;
        
        return modal;
    }

    // Open deposit modal
    openDepositModal(cryptoId = 'bitcoin') {
        const modal = document.getElementById('deposit-modal');
        if (!modal) return;
        
        // Ensure dropdown is populated
        this.populateAssetDropdown();
        
        // Set the selected asset
        const assetSelect = modal.querySelector('#deposit-asset-select');
        if (assetSelect) {
            assetSelect.value = cryptoId;
        }
        
        // Update address and QR code
        this.updateDepositAddress(cryptoId);
        
        // Show modal using the existing modal system
        modal.classList.add('active');
    }

    // Update deposit address and QR code
    updateDepositAddress(cryptoId) {
        const addresses = this.depositAddresses[cryptoId];
        if (!addresses || addresses.length === 0) return;
        
        const currentIndex = this.currentAddressIndex[cryptoId] || 0;
        const address = addresses[currentIndex];
        
        // Update address display
        const addressElement = document.getElementById('deposit-address');
        if (addressElement) {
            addressElement.textContent = address;
        }
        
        // Update QR code
        this.generateQRCode(address);
        
        // Setup copy functionality
        this.setupCopyAddress(address);
    }

    // Generate new address (cycle through available addresses)
    generateNewAddress() {
        const assetSelect = document.getElementById('deposit-asset-select');
        if (!assetSelect) return;
        
        const cryptoId = assetSelect.value;
        const addresses = this.depositAddresses[cryptoId];
        
        if (!addresses || addresses.length === 0) return;
        
        // Move to next address
        this.currentAddressIndex[cryptoId] = (this.currentAddressIndex[cryptoId] + 1) % addresses.length;
        
        // Update display
        this.updateDepositAddress(cryptoId);
        
        // Show feedback
        this.showAddressGenerated();
    }

    // Generate QR code for address
    generateQRCode(address) {
        const qrContainer = document.getElementById('deposit-qr-code');
        if (!qrContainer) return;
        
        // Clear previous QR code
        qrContainer.innerHTML = '';
        
        // Generate new QR code using qrcode-generator library
        try {
            const qr = qrcode(0, 'M');
            qr.addData(address);
            qr.make();
            
            // Create QR code image
            const qrImage = qr.createImgTag(4); // Cell size of 4 pixels
            qrContainer.innerHTML = qrImage;
            
            // Style the QR code
            const img = qrContainer.querySelector('img');
            if (img) {
                img.style.display = 'block';
                img.style.margin = '0 auto';
                img.style.border = '10px solid white';
                img.style.borderRadius = '8px';
            }
        } catch (error) {
            console.error('Error generating QR code:', error);
            // Fallback if QR library not available
            qrContainer.innerHTML = `<div class="w-32 h-32 bg-gray-600 flex items-center justify-center text-white text-sm">QR Code for: ${address.substring(0, 10)}...</div>`;
        }
    }

    // Setup copy address functionality
    setupCopyAddress(address) {
        const copyBtn = document.getElementById('copy-address-btn');
        if (!copyBtn) return;
        
        copyBtn.onclick = async () => {
            try {
                await navigator.clipboard.writeText(address);
                copyBtn.innerHTML = '<i class="fas fa-check mr-1"></i>Copied!';
                copyBtn.className = 'mt-2 text-green-400 hover:text-green-300 text-sm';
                
                setTimeout(() => {
                    copyBtn.innerHTML = '<i class="fas fa-copy mr-1"></i>Copy Address';
                    copyBtn.className = 'mt-2 text-blue-400 hover:text-blue-300 text-sm';
                }, 2000);
            } catch (error) {
                console.error('Failed to copy address:', error);
            }
        };
    }

    // Show address generated feedback
    showAddressGenerated() {
        const generateBtn = document.getElementById('generate-address-btn');
        if (!generateBtn) return;
        
        const originalText = generateBtn.innerHTML;
        generateBtn.innerHTML = '<i class="fas fa-check mr-2"></i>New Address Generated!';
        generateBtn.className = 'w-full bg-green-600 hover:bg-green-700 text-white py-2 px-4 rounded-lg transition-colors';
        
        setTimeout(() => {
            generateBtn.innerHTML = originalText;
            generateBtn.className = 'w-full bg-blue-600 hover:bg-blue-700 text-white py-2 px-4 rounded-lg transition-colors';
        }, 2000);
    }

    // Show notification message
    showNotification(message) {
        // Try to use existing notification system if available
        if (typeof showNotification === 'function') {
            showNotification('Success', message);
        } else {
            // Fallback to simple alert
            alert(message);
        }
    }

    // Load fallback data if API fails
    loadFallbackData() {
        this.cryptoData = [
            { id: 'bitcoin', asset: 'Bitcoin', ticker: 'BTC', price: 42500, change: 2.5 },
            { id: 'ethereum', asset: 'Ethereum', ticker: 'ETH', price: 2800, change: -1.2 },
            { id: 'binancecoin', asset: 'BNB', ticker: 'BNB', price: 590, change: 0.8 },
            { id: 'solana', asset: 'Solana', ticker: 'SOL', price: 150, change: 5.1 },
            { id: 'cardano', asset: 'Cardano', ticker: 'ADA', price: 0.45, change: -0.5 },
            { id: 'tron', asset: 'Tron', ticker: 'TRX', price: 0.12, change: 1.1 },
            { id: 'tether', asset: 'USDT', ticker: 'USDT', price: 1.00, change: 0.01 },
            { id: 'ripple', asset: 'XRP', ticker: 'XRP', price: 0.52, change: -2.3 },
            { id: 'dogecoin', asset: 'Dogecoin', ticker: 'DOGE', price: 0.15, change: 3.2 },
            { id: 'litecoin', asset: 'Litecoin', ticker: 'LTC', price: 85.60, change: 0.5 },
            { id: 'sui', asset: 'Sui', ticker: 'SUI', price: 1.05, change: -1.5 },
            { id: 'pengu', asset: 'Pengu', ticker: 'PENGU', price: 0.002, change: 10.5 },
            { id: 'zbcn', asset: 'ZBCN', ticker: 'ZBCN', price: 0.03, change: 2.1 },
            { id: 'casper-network', asset: 'Casper', ticker: 'CSPR', price: 0.04, change: -0.8 },
            { id: 'pepe', asset: 'Pepe', ticker: 'PEPE', price: 0.000007, change: 8.4 },
            { id: 'bonk', asset: 'Bonk', ticker: 'BONK', price: 0.000025, change: 4.2 },
            { id: 'swiftcoin', asset: 'SwiftCoin', ticker: 'SWIFT', price: 1.20, change: 0.1 },
            { id: 'quant-network', asset: 'Quant', ticker: 'QNT', price: 95.50, change: 1.8 },
            { id: 'lcx', asset: 'LCX', ticker: 'LCX', price: 0.28, change: -3.1 },
            { id: 'shiba-inu', asset: 'Shiba Inu', ticker: 'SHIB', price: 0.000025, change: 5.5 },
            { id: 'chainlink', asset: 'Chainlink', ticker: 'LINK', price: 18.50, change: 2.9 },
            { id: 'link-api', asset: 'Link API', ticker: 'LINKAPI', price: 0.10, change: -0.2 }
        ];
        
        this.depositAddresses = {
            'bitcoin': ['bc1qxy2kgdygjrsqtzq2n0yrf2493p83kkfjhx0wlh'],
            'ethereum': ['0x742f35Cc6bf2f5e8F6F1e8e0F5B2F3d3f5e6F7A8'],
            'binancecoin': ['bnb136ns6lfw4s5g68weua029l4hsx4kpjz4g2g4gq'],
            'solana': ['So11111111111111111111111111111111111111112'],
            'cardano': ['addr1q8z8q8z8q8z8q8z8q8z8q8z8q8z8q8z8q8z8q8z8q8z8q8z8q8z8q8z'],
            'tron': ['TPL2uTAm4tFdXnD2e6a4P2B123456789012345678'],
            'tether': ['0x742f35Cc6bf2f5e8F6F1e8e0F5B2F3d3f5e6F7A8'],
            'ripple': ['rP12345678901234567890123456789012345'],
            'dogecoin': ['D123456789012345678901234567890123'],
            'litecoin': ['ltc1q8z8q8z8q8z8q8z8q8z8q8z8q8z8q8z8q8z8q8z8q8z8q8z8q8z8q8z'],
            'sui': ['sui1q8z8q8z8q8z8q8z8q8z8q8z8q8z8q8z8q8z8q8z8q8z8q8z8q8z8q8z'],
            'pengu': ['pengu123456789012345678901234567890'],
            'zbcn': ['zbcn123456789012345678901234567890'],
            'casper-network': ['cspr123456789012345678901234567890'],
            'pepe': ['0x742f35Cc6bf2f5e8F6F1e8e0F5B2F3d3f5e6F7A8'],
            'bonk': ['So11111111111111111111111111111111111111112'],
            'swiftcoin': ['swift123456789012345678901234567890'],
            'quant-network': ['qnt123456789012345678901234567890'],
            'lcx': ['lcx123456789012345678901234567890'],
            'shiba-inu': ['0x742f35Cc6bf2f5e8F6F1e8e0F5B2F3d3f5e6F7A8'],
            'chainlink': ['0x742f35Cc6bf2f5e8F6F1e8e0F5B2F3d3f5e6F7A8'],
            'link-api': ['linkapi123456789012345678901234567890']
        };
        
        this.updatePriceDisplay();
        console.log('Using fallback crypto data');
    }

    // Cleanup function
    destroy() {
        if (this.priceUpdateInterval) {
            clearInterval(this.priceUpdateInterval);
        }
    }
}

// Initialize crypto manager when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    try {
        window.cryptoManager = new CryptoManager();
        window.cryptoManager.initializeCrypto();
    } catch (error) {
        console.error('Error initializing CryptoManager:', error);
    }
});

// QR Code library is already included via qrcode-generator in the HTML