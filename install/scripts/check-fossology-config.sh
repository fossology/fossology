#!/bin/bash
# SPDX-FileCopyrightText: © Fossology contributors
# SPDX-License-Identifier: GPL-2.0-only
#
# Post-installation verification script for FOSSology
# Checks if PHP is configured with the required settings

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "  FOSSology Configuration Checker"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo

# Required minimum values (in bytes for comparison)
REQUIRED_MEMORY=735051776      # 702M
REQUIRED_POST=734003200        # 701M
REQUIRED_UPLOAD=733954048      # 700M

# Function to convert PHP shorthand notation to bytes
php_to_bytes() {
    local value=$1
    local unit=${value: -1}
    local number=${value%?}
    
    case $unit in
        K|k) echo $((number * 1024)) ;;
        M|m) echo $((number * 1024 * 1024)) ;;
        G|g) echo $((number * 1024 * 1024 * 1024)) ;;
        *) echo $value ;;  # Already in bytes
    esac
}

# Check if PHP is installed
if ! command -v php &> /dev/null; then
    echo "❌ ERROR: PHP is not installed or not in PATH"
    exit 1
fi

echo "Checking PHP configuration..."
echo

# Get current PHP settings
memory_limit=$(php -r "echo ini_get('memory_limit');")
post_max=$(php -r "echo ini_get('post_max_size');")
upload_max=$(php -r "echo ini_get('upload_max_filesize');")

echo "Current PHP Settings:"
echo "  memory_limit:         $memory_limit"
echo "  post_max_size:        $post_max"
echo "  upload_max_filesize:  $upload_max"
echo

# Convert to bytes for comparison
memory_bytes=$(php_to_bytes "$memory_limit")
post_bytes=$(php_to_bytes "$post_max")
upload_bytes=$(php_to_bytes "$upload_max")

# Check each setting
errors=0

if [ "$memory_limit" = "-1" ]; then
    echo "✅ memory_limit: Unlimited (OK)"
elif [ $memory_bytes -ge $REQUIRED_MEMORY ]; then
    echo "✅ memory_limit: OK ($memory_limit >= 702M)"
else
    echo "❌ memory_limit: TOO LOW ($memory_limit < 702M)"
    ((errors++))
fi

if [ $post_bytes -ge $REQUIRED_POST ]; then
    echo "✅ post_max_size: OK ($post_max >= 701M)"
else
    echo "❌ post_max_size: TOO LOW ($post_max < 701M)"
    ((errors++))
fi

if [ $upload_bytes -ge $REQUIRED_UPLOAD ]; then
    echo "✅ upload_max_filesize: OK ($upload_max >= 700M)"
else
    echo "❌ upload_max_filesize: TOO LOW ($upload_max < 700M)"
    ((errors++))
fi

echo
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

if [ $errors -eq 0 ]; then
    echo "✅ All checks passed! PHP is configured correctly for FOSSology."
    echo
    exit 0
else
    echo "❌ Configuration issues detected ($errors setting(s) need adjustment)"
    echo
    echo "To fix these issues, run:"
    
    # Get the script directory for reliable path resolution
    SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
    if [ -f "$SCRIPT_DIR/php-conf-fix.sh" ]; then
        echo "  sudo $SCRIPT_DIR/php-conf-fix.sh --overwrite"
    else
        echo "  sudo /path/to/fossology/install/scripts/php-conf-fix.sh --overwrite"
    fi
    echo "  sudo systemctl restart apache2"
    echo
    echo "Or manually edit your php.ini file with these settings:"
    echo "  memory_limit = 702M"
    echo "  post_max_size = 701M"
    echo "  upload_max_filesize = 700M"
    echo
    echo "Then restart your web server (Apache/Nginx)."
    echo
    exit 1
fi
