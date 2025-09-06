# Chain-IQ Crypto Wallet Platform

## Overview
Chain-IQ is a complete crypto wallet platform with both a user-facing frontend and an administrative backoffice. The platform is built as a fully functional demo system that can handle 20,000+ users with persistent data storage.

## Architecture
- **Frontend**: frontend/ folder - Main crypto wallet interface and admin panel
- **Backend**: backend/ folder - PHP API endpoints 
- **Database**: database/ folder - SQLite database and schema
- **Server**: server.php - Main routing and authentication

## Project Structure
```
/
├── server.php (Main server with routing)
├── backend/
│   ├── CompleteAPI.php (Production REST API with JWT auth)
│   ├── JWTManager.php (JWT token handling)
│   ├── SecurityManager.php (Rate limiting, validation, CSRF)
│   ├── AuditLogger.php (Admin action logging)
│   ├── BackgroundJobs.php (Async job processing)
│   └── api.php (Original API - kept for compatibility)
├── database/
│   ├── MySQLDatabase.php (Production MySQL/SQLite database layer)
│   ├── database.php (Original - kept for compatibility)
│   └── chainiq.db / admin_app_mysql.db (SQLite databases)
├── frontend/
│   ├── adminApi.js (Complete admin API client)
│   ├── login.html (Login page)
│   ├── register.html (Registration page)
│   ├── Chain-IQ.html (Main wallet interface)
│   ├── Backoffice.html (Admin panel)
│   ├── crypto-integration.js (Live prices & deposits)
│   └── *.js (Integration files)
└── hostinger_deployment.md (Complete deployment guide)
```

## Features
### User Features
- Multi-cryptocurrency wallet (BTC, ETH, USDT, USDC)
- Live crypto price updates from CoinGecko API
- Deposit functionality with QR codes and address cycling
- Virtual card management (Platinum, Gold, Premium)
- Transaction history and balance tracking
- User registration and password management
- Settings and profile management

### Admin Features  
- User management (create, edit, delete users)
- Password management for any user account
- Balance management (adjust user balances)
- Card management (create, freeze/unfreeze cards)
- Transaction monitoring
- Platform customization
- Crypto address management

## Authentication & Routing
### Login Routes
- `/` or `/login` - Login page with user/admin tabs
- `/register` - User registration
- `/dashboard` - Main user interface (requires login)
- `/admin` - Admin panel (requires admin login)
- `/logout` - Logout and session destroy

### Demo Credentials
- **User**: user@demo.com / password123
- **Admin**: admin@chainiq.com / admin123

### API Endpoints
- `/api/auth/*` - Authentication (login, register, change-password)
- `/api/admin/*` - Admin functions (login, set-password)
- `/api/crypto/*` - Live prices and deposit addresses
- `/api/users/*` - User management
- `/api/balances/*` - Balance management
- `/api/cards/*` - Card management
- `/api/transactions/*` - Transaction management

## Live Data Integration
- **Crypto Prices**: Real-time from CoinGecko API with 30-second updates
- **Deposit Addresses**: Multiple addresses per crypto with cycling
- **QR Codes**: Generated for each deposit address
- **Persistent Storage**: All data saved to SQLite database

## Recent Changes
- Reorganized into proper folder structure (backend, database, frontend)
- Implemented complete authentication system with sessions
- Added user registration and password management
- Integrated live crypto price API from CoinGecko
- Built deposit functionality with QR codes and address management
- Added admin password control features
- **NEW**: Complete production MySQL-compatible architecture implemented
- **NEW**: JWT authentication with refresh tokens for admin API
- **NEW**: Comprehensive adminApi.js client with all CRUD operations
- **NEW**: Security hardening with rate limiting, CORS, audit logging
- **NEW**: Background job system for heavy operations
- **NEW**: Complete Hostinger deployment guide with MySQL setup
- **NEW**: Production-ready API endpoints with idempotency and error handling
- Configured for both Replit development and Hostinger production deployment

## Security Features
- Password hashing with PHP password_hash()
- Session-based authentication
- Input validation and sanitization
- SQL injection prevention with prepared statements
- Admin access controls

The platform is designed for enterprise-level usage with proper security, scalability, and real-time data integration.