// Crypto Address Management for Admin Panel

class CryptoAddressManager {
    constructor() {
        this.addresses = {
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
            'tether': [
                '0x742f35Cc6bf2f5e8F6F1e8e0F5B2F3d3f5e6F7A8',
                '0x8ba1f109551bD432803012645Hac136c22C73D6E',
                '0xdD2FD4581271e230360230F9337D5c0430Bf44C0'
            ],
            'usd-coin': [
                '0x742f35Cc6bf2f5e8F6F1e8e0F5B2F3d3f5e6F7A8',
                '0x8ba1f109551bD432803012645Hac136c22C73D6E',
                '0xdD2FD4581271e230360230F9337D5c0430Bf44C0'
            ]
        };
        
        this.initializeAdminPanel();
    }
    
    initializeAdminPanel() {
        // Wait for page to load
        document.addEventListener('DOMContentLoaded', () => {
            this.setupEventListeners();
            this.displayCurrentAddresses();
        });
    }
    
    setupEventListeners() {
        // Asset selection change
        const assetSelect = document.getElementById('crypto-asset-select');
        if (assetSelect) {
            assetSelect.addEventListener('change', () => {
                this.displayCurrentAddresses();
            });
        }
        
        // Add new address form
        const addForm = document.getElementById('add-crypto-address-form');
        if (addForm) {
            addForm.addEventListener('submit', (e) => {
                e.preventDefault();
                this.addNewAddress();
            });
        }
    }
    
    displayCurrentAddresses() {
        const assetSelect = document.getElementById('crypto-asset-select');
        const addressList = document.getElementById('current-addresses-list');
        
        if (!assetSelect || !addressList) return;
        
        const selectedAsset = assetSelect.value;
        const addresses = this.addresses[selectedAsset] || [];
        
        // Clear current display
        addressList.innerHTML = '';
        
        if (addresses.length === 0) {
            addressList.innerHTML = '<p class="text-sm text-secondary">No addresses configured for this asset.</p>';
            return;
        }
        
        // Create address list
        addresses.forEach((address, index) => {
            const addressDiv = document.createElement('div');
            addressDiv.className = 'form-group';
            addressDiv.innerHTML = `
                <div style="display: flex; align-items: center; gap: 12px; padding: 12px; background: var(--bg-gray); border-radius: 8px; margin-bottom: 8px;">
                    <span style="flex: 1; font-family: monospace; font-size: 13px;">${address}</span>
                    <button type="button" onclick="cryptoAddressManager.removeAddress('${selectedAsset}', ${index})" 
                            style="padding: 6px 12px; background: var(--accent-red); color: white; border: none; border-radius: 4px; cursor: pointer;">
                        Remove
                    </button>
                </div>
            `;
            addressList.appendChild(addressDiv);
        });
    }
    
    addNewAddress() {
        const assetSelect = document.getElementById('crypto-asset-select');
        const addressInput = document.getElementById('new-crypto-address');
        
        if (!assetSelect || !addressInput) return;
        
        const selectedAsset = assetSelect.value;
        const newAddress = addressInput.value.trim();
        
        if (!newAddress) {
            alert('Please enter a valid address');
            return;
        }
        
        // Validate address format (basic validation)
        if (!this.validateAddress(selectedAsset, newAddress)) {
            alert('Invalid address format for selected cryptocurrency');
            return;
        }
        
        // Check if address already exists
        if (this.addresses[selectedAsset] && this.addresses[selectedAsset].includes(newAddress)) {
            alert('This address is already in the list');
            return;
        }
        
        // Add the address
        if (!this.addresses[selectedAsset]) {
            this.addresses[selectedAsset] = [];
        }
        
        this.addresses[selectedAsset].push(newAddress);
        
        // Clear input
        addressInput.value = '';
        
        // Refresh display
        this.displayCurrentAddresses();
        
        // Save to backend (simulate API call)
        this.saveAddressesToBackend();
        
        alert('Address added successfully!');
    }
    
    removeAddress(assetId, index) {
        if (this.addresses[assetId] && this.addresses[assetId][index] !== undefined) {
            this.addresses[assetId].splice(index, 1);
            this.displayCurrentAddresses();
            this.saveAddressesToBackend();
            alert('Address removed successfully!');
        }
    }
    
    validateAddress(assetId, address) {
        // Basic address format validation
        switch (assetId) {
            case 'bitcoin':
                return /^(bc1|[13])[a-zA-HJ-NP-Z0-9]{25,87}$/.test(address);
            case 'ethereum':
            case 'tether':
            case 'usd-coin':
                return /^0x[a-fA-F0-9]{40}$/.test(address);
            default:
                return false;
        }
    }
    
    async saveAddressesToBackend() {
        try {
            // Simulate API call to save addresses
            const response = await fetch('/api/crypto/addresses', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(this.addresses)
            });
            
            if (!response.ok) {
                console.warn('Failed to save addresses to backend');
            }
        } catch (error) {
            console.error('Error saving addresses:', error);
        }
    }
    
    // Get addresses for use by other parts of the app
    getAddresses() {
        return this.addresses;
    }
}

// Initialize the crypto address manager
const cryptoAddressManager = new CryptoAddressManager();

// Make it globally available
window.cryptoAddressManager = cryptoAddressManager;