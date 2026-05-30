#!/bin/bash
# Laravel Scheduler Wrapper Script for DirectAdmin
# This script runs Laravel's scheduler and can be called from cron

# Run from the directory where this script lives (project root).
# This avoids hardcoding personal hosting paths.
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
cd "$SCRIPT_DIR" || exit 1

# Run Laravel scheduler
# Adjust PHP path if needed (find with: which php)
/usr/local/bin/php artisan schedule:run >> /dev/null 2>&1
