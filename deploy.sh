#!/bin/bash

# Waddy Backend - Hostinger Deployment Script
# This script automates the deployment process on Hostinger

echo "======================================"
echo "Waddy Backend Deployment Script"
echo "======================================"
echo ""

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Function to print colored output
print_success() {
    echo -e "${GREEN}✓ $1${NC}"
}

print_error() {
    echo -e "${RED}✗ $1${NC}"
}

print_info() {
    echo -e "${YELLOW}ℹ $1${NC}"
}

# Check if we're in the right directory
if [ ! -f "artisan" ]; then
    print_error "Error: artisan file not found. Are you in the Laravel project root?"
    exit 1
fi

print_info "Starting deployment process..."
echo ""

# Step 1: Pull latest changes
print_info "Step 1: Pulling latest changes from GitHub..."
git pull origin main
if [ $? -eq 0 ]; then
    print_success "Code updated successfully"
else
    print_error "Failed to pull changes"
    exit 1
fi
echo ""

# Step 2: Install/Update Composer dependencies
print_info "Step 2: Installing Composer dependencies..."
if command -v composer &> /dev/null; then
    composer install --optimize-autoloader --no-dev
else
    php composer.phar install --optimize-autoloader --no-dev
fi

if [ $? -eq 0 ]; then
    print_success "Dependencies installed successfully"
else
    print_error "Failed to install dependencies"
    exit 1
fi
echo ""

# Step 3: Clear caches
print_info "Step 3: Clearing caches..."
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear
print_success "Caches cleared"
echo ""

# Step 4: Run migrations
print_info "Step 4: Running database migrations..."
read -p "Do you want to run migrations? (y/n): " run_migrations
if [ "$run_migrations" = "y" ] || [ "$run_migrations" = "Y" ]; then
    php artisan migrate --force
    if [ $? -eq 0 ]; then
        print_success "Migrations completed successfully"
    else
        print_error "Migration failed"
        exit 1
    fi
else
    print_info "Skipping migrations"
fi
echo ""

# Step 5: Optimize application
print_info "Step 5: Optimizing application..."
php artisan config:cache
php artisan route:cache
php artisan view:cache
print_success "Application optimized"
echo ""

# Step 6: Set permissions
print_info "Step 6: Setting correct permissions..."
chmod -R 775 storage
chmod -R 775 bootstrap/cache
print_success "Permissions set"
echo ""

# Step 7: Create storage link if not exists
print_info "Step 7: Creating storage symbolic link..."
php artisan storage:link
print_success "Storage link created"
echo ""

print_success "======================================"
print_success "Deployment completed successfully!"
print_success "======================================"
echo ""
print_info "Next steps:"
echo "  1. Test your application: https://yourdomain.com"
echo "  2. Check logs if any issues: tail -f storage/logs/laravel.log"
echo "  3. Monitor application performance"
echo ""
