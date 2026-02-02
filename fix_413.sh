#!/bin/bash
SERVER="root@173.249.58.37"
echo "Check PHP & Nginx limits on $SERVER..."

ssh -t $SERVER "
# 1. Fix Nginx client_max_body_size
CONF='/etc/nginx/nginx.conf'
if grep -q 'client_max_body_size' \$CONF; then
    echo 'Updating existing client_max_body_size in Nginx...'
    sed -i 's/client_max_body_size .*/client_max_body_size 128M;/g' \$CONF
else
    echo 'Adding client_max_body_size to Nginx...'
    sed -i '/http {/a \    client_max_body_size 128M;' \$CONF
fi

# Check status
nginx -t
if [ \$? -eq 0 ]; then
    systemctl reload nginx
    echo '✅ Nginx reloaded with 128M limit.'
else
    echo '❌ Nginx configuration invalid.'
fi

# 2. Update PHP limits (commonly located in /etc/php/*/fpm/php.ini or /etc/php/*/cli/php.ini)
# attempting to find the active fpm php.ini
PHP_INI=\$(find /etc/php -name php.ini | grep fpm | head -n 1)
if [ ! -z \"\$PHP_INI\" ]; then
    echo \"Updating PHP config at \$PHP_INI\"
    sed -i 's/upload_max_filesize = .*/upload_max_filesize = 128M/' \$PHP_INI
    sed -i 's/post_max_size = .*/post_max_size = 128M/' \$PHP_INI
    
    # Restart PHP-FPM (try to match version)
    PHP_VER=\$(echo \$PHP_INI | grep -oP 'php/\K[0-9.]+')
    if [ ! -z \"\$PHP_VER\" ]; then
         systemctl restart php\$PHP_VER-fpm
         echo \"✅ PHP \$PHP_VER-fpm restarted.\"
    else
         echo \"⚠️ Could not detect PHP version for restart, please restart manually.\"
    fi
else
    echo '⚠️ Could not find php.ini automatically.'
fi
"
