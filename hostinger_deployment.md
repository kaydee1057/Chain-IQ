# Hostinger Deployment Guide for Chain-IQ

## Prerequisites

1. **Hostinger Account** with PHP hosting plan
2. **MySQL Database** access
3. **FTP/File Manager** access
4. **Domain** configured

## Step 1: Database Setup

### Create MySQL Database on Hostinger

1. Log into Hostinger control panel
2. Go to **MySQL Databases**
3. Create new database: `chainiq_production`
4. Create database user with full privileges
5. Note down:
   - Database name
   - Username  
   - Password
   - Host (usually localhost)

### Environment Configuration

Create `.env` file in root directory:

```env
# Production Environment
APP_ENV=production
APP_DEBUG=false
APP_URL=https://yourdomain.com

# Database Configuration
DB_HOST=localhost
DB_PORT=3306
DB_NAME=chainiq_production
DB_USER=your_db_user
DB_PASS=your_db_password

# JWT Configuration
JWT_SECRET=your-super-secret-jwt-key-32-chars-min
JWT_ALGO=HS256

# Security
CSRF_TOKEN_NAME=_token

# Mail Configuration (optional)
MAIL_HOST=smtp.hostinger.com
MAIL_PORT=587
MAIL_USER=noreply@yourdomain.com
MAIL_PASS=your_email_password

# Logging
LOG_LEVEL=error
```

## Step 2: File Upload Structure

Upload files to your Hostinger hosting in this structure:

```
public_html/
├── .env
├── .htaccess
├── server.php (main entry point)
├── backend/
│   ├── CompleteAPI.php
│   ├── JWTManager.php
│   ├── SecurityManager.php
│   ├── AuditLogger.php
│   └── BackgroundJobs.php
├── database/
│   └── MySQLDatabase.php
├── frontend/
│   ├── adminApi.js
│   ├── Chain-IQ.html
│   ├── Backoffice.html
│   ├── register.html
│   └── *.js files
└── uploads/ (create empty folder, 755 permissions)
```

## Step 3: Apache Configuration

Create `.htaccess` file in public_html:

```apache
RewriteEngine On

# Security Headers
Header always set X-Content-Type-Options nosniff
Header always set X-Frame-Options DENY
Header always set X-XSS-Protection "1; mode=block"
Header always set Strict-Transport-Security "max-age=31536000; includeSubDomains"

# Force HTTPS (if SSL is enabled)
RewriteCond %{HTTPS} off
RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]

# Route all requests to server.php
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ server.php [QSA,L]

# Protect sensitive files
<Files ".env">
    Order allow,deny
    Deny from all
</Files>

<Files "*.log">
    Order allow,deny
    Deny from all
</Files>

# Enable gzip compression
<IfModule mod_deflate.c>
    AddOutputFilterByType DEFLATE text/plain
    AddOutputFilterByType DEFLATE text/html
    AddOutputFilterByType DEFLATE text/xml
    AddOutputFilterByType DEFLATE text/css
    AddOutputFilterByType DEFLATE application/xml
    AddOutputFilterByType DEFLATE application/xhtml+xml
    AddOutputFilterByType DEFLATE application/rss+xml
    AddOutputFilterByType DEFLATE application/javascript
    AddOutputFilterByType DEFLATE application/x-javascript
</IfModule>

# Cache static files
<IfModule mod_expires.c>
    ExpiresActive on
    ExpiresByType text/css "access plus 1 year"
    ExpiresByType application/javascript "access plus 1 year"
    ExpiresByType image/png "access plus 1 year"
    ExpiresByType image/jpg "access plus 1 year"
    ExpiresByType image/jpeg "access plus 1 year"
</IfModule>
```

## Step 4: Database Schema Migration

### Update MySQLDatabase.php for Production

Modify the database connection in `MySQLDatabase.php`:

```php
private function __construct() {
    $this->isProduction = true; // Force production mode
    
    // Load environment variables
    if (file_exists(__DIR__ . '/../.env')) {
        $env = parse_ini_file(__DIR__ . '/../.env');
        foreach ($env as $key => $value) {
            $_ENV[$key] = $value;
        }
    }
    
    // Rest of constructor...
}
```

### Initialize Database

1. Upload files to Hostinger
2. Access: `https://yourdomain.com/api/admin/install`
3. This will create all tables and default data

Or manually run database creation by accessing the site - the MySQLDatabase class will auto-create schema.

## Step 5: Server Configuration

Update `server.php` for production:

```php
<?php
// Production server configuration
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error.log');

// Load environment
if (file_exists(__DIR__ . '/.env')) {
    $env = parse_ini_file(__DIR__ . '/.env');
    foreach ($env as $key => $value) {
        $_ENV[$key] = $value;
    }
}

// Production cache headers
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

session_start();

// Route handling
$request_uri = $_SERVER['REQUEST_URI'];
$path = parse_url($request_uri, PHP_URL_PATH);
$path = rtrim($path, '/');
if ($path === '') $path = '/';

switch ($path) {
    case '/':
    case '/dashboard':
        include 'frontend/Chain-IQ.html';
        break;
        
    case '/register':
        include 'frontend/register.html';
        break;
        
    case '/admin':
        include 'frontend/Backoffice.html';
        break;
        
    default:
        if (strpos($path, '/api/') === 0) {
            include 'backend/CompleteAPI.php';
        } elseif (strpos($path, '/frontend/') === 0) {
            $file_path = '.' . $path;
            if (file_exists($file_path)) {
                $mime_type = mime_content_type($file_path);
                header('Content-Type: ' . $mime_type);
                readfile($file_path);
            } else {
                http_response_code(404);
                echo "File not found";
            }
        } else {
            http_response_code(404);
            echo "Page not found";
        }
        break;
}
?>
```

## Step 6: SSL Certificate

1. In Hostinger control panel, go to **SSL**
2. Enable **Let's Encrypt SSL** for your domain
3. Verify HTTPS is working

## Step 7: Background Jobs (Optional)

### Setup Cron Job for Background Processing

1. In Hostinger control panel, go to **Cron Jobs**
2. Add new cron job:
   - **Command**: `php /home/username/public_html/cron_jobs.php`
   - **Schedule**: Every 5 minutes (`*/5 * * * *`)

### Create cron_jobs.php

```php
<?php
require_once __DIR__ . '/database/MySQLDatabase.php';
require_once __DIR__ . '/backend/BackgroundJobs.php';

$processor = new BackgroundJobProcessor();
$processed = $processor->processJobs();

echo "Processed $processed jobs\n";
?>
```

## Step 8: Security Hardening

### File Permissions

Set correct permissions:
```bash
# Folders: 755
chmod 755 public_html
chmod 755 public_html/backend
chmod 755 public_html/database
chmod 755 public_html/frontend

# PHP files: 644
chmod 644 public_html/*.php
chmod 644 public_html/backend/*.php
chmod 644 public_html/database/*.php

# Uploads folder: 755 (writable)
chmod 755 public_html/uploads

# Environment file: 600 (secure)
chmod 600 public_html/.env
```

### Additional Security

1. **Change default admin credentials** immediately after deployment
2. **Enable fail2ban** if available on your hosting
3. **Regular backups** of database and files
4. **Monitor logs** regularly for suspicious activity

## Step 9: Testing Production Deployment

### Test Checklist

1. **Database Connection**: Visit site, check for errors
2. **Admin Login**: Test with default credentials
3. **API Endpoints**: Test key API functions
4. **SSL Certificate**: Verify HTTPS works
5. **Performance**: Check page load times
6. **Security Headers**: Use online tools to verify headers

### Default Credentials

- **Admin**: admin@chainiq.com / admin123
- **User**: user@demo.com / password123

**IMPORTANT**: Change these immediately after deployment!

## Step 10: Monitoring & Maintenance

### Logging

Monitor these log files:
- `error.log` - PHP errors
- Hostinger access logs
- Database slow query logs

### Backup Strategy

1. **Database**: Use phpMyAdmin to export daily
2. **Files**: Download via FTP weekly
3. **Automated**: Set up hosting backup if available

### Performance Optimization

1. **Enable OPcache** in PHP settings
2. **Use CDN** for static files
3. **Optimize images** before upload
4. **Monitor database** performance

## Troubleshooting

### Common Issues

1. **500 Internal Server Error**
   - Check error.log
   - Verify file permissions
   - Check .htaccess syntax

2. **Database Connection Failed**
   - Verify database credentials in .env
   - Check database host settings
   - Ensure database user has proper privileges

3. **SSL Issues**
   - Verify SSL certificate is active
   - Check mixed content warnings
   - Update any hardcoded HTTP links

4. **API Not Working**
   - Check .htaccess rewrite rules
   - Verify backend files are uploaded
   - Check PHP error logs

### Support

For Hostinger-specific issues:
- Use Hostinger support chat/tickets
- Check Hostinger knowledge base
- Verify hosting plan includes required PHP features

---

This deployment guide provides a complete production setup for Chain-IQ on Hostinger with MySQL database, security features, and monitoring capabilities.