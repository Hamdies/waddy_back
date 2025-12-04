# Waddy Backend - Hostinger Deployment Guide

## Prerequisites

Before deploying, ensure you have:
- ✅ Hostinger hosting account (Business or Premium plan recommended)
- ✅ SSH access enabled in Hostinger
- ✅ MySQL database created in Hostinger
- ✅ Domain configured (optional)

## Step 1: Prepare Hostinger Environment

### 1.1 Enable SSH Access
1. Log in to Hostinger hPanel
2. Go to **Advanced** → **SSH Access**
3. Enable SSH access
4. Note your SSH credentials

### 1.2 Create MySQL Database
1. Go to **Databases** → **MySQL Databases**
2. Click **Create Database**
3. Database name: `waddy_db` (or your preferred name)
4. Create a database user with a strong password
5. Note down:
   - Database name
   - Database username
   - Database password
   - Database host (usually `localhost`)

## Step 2: Connect to Hostinger via SSH

```bash
ssh u123456789@yourdomain.com -p 65002
```
Replace with your actual SSH credentials from Hostinger.

## Step 3: Navigate to Public HTML Directory

```bash
cd domains/yourdomain.com/public_html
# OR if using subdomain
cd domains/api.yourdomain.com/public_html
```

## Step 4: Clone the Repository

```bash
# Remove default files if any
rm -rf *
rm -rf .htaccess

# Clone the repository
git clone https://github.com/Hamdies/waddy_back.git .
```

## Step 5: Install Composer Dependencies

```bash
# If composer is not installed globally, download it
curl -sS https://getcomposer.org/installer | php

# Install dependencies
php composer.phar install --optimize-autoloader --no-dev

# OR if composer is installed globally
composer install --optimize-autoloader --no-dev
```

## Step 6: Configure Environment

```bash
# Copy environment file
cp .env.example .env

# Edit the .env file
nano .env
```

### Update the following in `.env`:

```env
APP_NAME="Waddy Backend"
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=https://yourdomain.com

LOG_CHANNEL=stack
LOG_LEVEL=error

DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=your_database_name
DB_USERNAME=your_database_user
DB_PASSWORD=your_database_password

# Update other settings as needed
BROADCAST_DRIVER=log
CACHE_DRIVER=file
FILESYSTEM_DISK=local
QUEUE_CONNECTION=sync
SESSION_DRIVER=file
SESSION_LIFETIME=120
```

Save and exit (Ctrl+X, then Y, then Enter)

## Step 7: Generate Application Key

```bash
php artisan key:generate
```

## Step 8: Set Up Storage and Cache

```bash
# Create symbolic link for storage
php artisan storage:link

# Clear and cache configuration
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Clear any existing cache
php artisan cache:clear
```

## Step 9: Run Database Migrations

```bash
# Run migrations
php artisan migrate --force

# If you have seeders
php artisan db:seed --force
```

## Step 10: Set Correct Permissions

```bash
# Set permissions for storage and cache
chmod -R 775 storage
chmod -R 775 bootstrap/cache

# Set ownership (replace with your Hostinger user)
chown -R u123456789:u123456789 storage
chown -R u123456789:u123456789 bootstrap/cache
```

## Step 11: Configure Web Server

### Option A: Using .htaccess (Apache - Default for Hostinger)

The project already includes `.htaccess` files. Ensure the document root points to the `public` directory.

In Hostinger hPanel:
1. Go to **Advanced** → **PHP Configuration**
2. Set **Document Root** to: `public_html/public`

### Option B: Manual .htaccess in root (if needed)

Create `.htaccess` in the root directory:

```apache
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteRule ^(.*)$ public/$1 [L]
</IfModule>
```

## Step 12: Configure PHP Settings

In Hostinger hPanel:
1. Go to **Advanced** → **PHP Configuration**
2. Set PHP version to **8.1** or higher
3. Increase the following limits:
   - `memory_limit`: 256M
   - `max_execution_time`: 300
   - `post_max_size`: 100M
   - `upload_max_filesize`: 100M

## Step 13: Set Up SSL Certificate

1. In Hostinger hPanel, go to **Security** → **SSL**
2. Enable **Free SSL Certificate**
3. Wait for activation (usually takes a few minutes)

## Step 14: Test the Deployment

Visit your domain:
```
https://yourdomain.com/api/v1/config
```

You should see the API configuration response.

## Step 15: Set Up Cron Jobs (Optional but Recommended)

In Hostinger hPanel:
1. Go to **Advanced** → **Cron Jobs**
2. Add a new cron job:
   - **Minute**: `*`
   - **Hour**: `*`
   - **Day**: `*`
   - **Month**: `*`
   - **Weekday**: `*`
   - **Command**: `cd /home/u123456789/domains/yourdomain.com/public_html && php artisan schedule:run >> /dev/null 2>&1`

## Updating the Application

When you need to update the application:

```bash
# SSH into your server
ssh u123456789@yourdomain.com -p 65002

# Navigate to project directory
cd domains/yourdomain.com/public_html

# Pull latest changes
git pull origin main

# Install/update dependencies
composer install --optimize-autoloader --no-dev

# Run migrations
php artisan migrate --force

# Clear and rebuild cache
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

## Troubleshooting

### Issue: 500 Internal Server Error
**Solution:**
```bash
# Check Laravel logs
tail -f storage/logs/laravel.log

# Ensure proper permissions
chmod -R 775 storage bootstrap/cache
```

### Issue: Database Connection Error
**Solution:**
- Verify database credentials in `.env`
- Ensure database exists in Hostinger
- Check if database user has proper permissions

### Issue: Routes not working
**Solution:**
```bash
# Clear route cache
php artisan route:clear
php artisan route:cache
```

### Issue: Storage files not accessible
**Solution:**
```bash
# Recreate storage link
php artisan storage:link
```

## Security Checklist

- ✅ Set `APP_DEBUG=false` in production
- ✅ Use strong database passwords
- ✅ Enable SSL certificate
- ✅ Keep `.env` file secure (never commit to Git)
- ✅ Regularly update dependencies
- ✅ Set proper file permissions
- ✅ Enable firewall rules if available

## Support

For issues specific to:
- **Hostinger**: Contact Hostinger support
- **Application**: Check Laravel logs in `storage/logs/`
- **GitHub**: https://github.com/Hamdies/waddy_back

---

**Deployment Date**: 2025-12-04
**Laravel Version**: Check `composer.json`
**PHP Version Required**: 8.1+
