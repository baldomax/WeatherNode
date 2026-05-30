#!/bin/bash
#
# WeatherNode Deployment Script
# Syncs local files to production server via rsync
#
# Usage:
#   ./deploy.sh                    # Dry run (shows what would be synced)
#   ./deploy.sh --execute          # Actually sync files
#   ./deploy.sh --execute --delete # Sync and delete removed files on server
#

# ============================================
# CONFIGURATION - Edit these values
# ============================================

REMOTE_USER="your_username"
REMOTE_HOST="your_server.com"
REMOTE_PATH="/path/to/your/laravel/app"

# SSH port (usually 22)
SSH_PORT="22"

# ============================================
# Don't edit below this line
# ============================================

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Check if configuration is set
if [[ "$REMOTE_USER" == "your_username" ]] || [[ "$REMOTE_HOST" == "your_server.com" ]]; then
    echo -e "${RED}Error: Please configure REMOTE_USER, REMOTE_HOST, and REMOTE_PATH in this script${NC}"
    echo ""
    echo "Edit deploy.sh and set:"
    echo "  REMOTE_USER=\"your_ssh_username\""
    echo "  REMOTE_HOST=\"your-server.com\""
    echo "  REMOTE_PATH=\"/home/user/public_html\"  (or your Laravel root)"
    exit 1
fi

# Parse arguments
DRY_RUN=true
DELETE_FLAG=""

for arg in "$@"; do
    case $arg in
        --execute)
            DRY_RUN=false
            ;;
        --delete)
            DELETE_FLAG="--delete"
            ;;
    esac
done

# Exclusions - files/folders NOT to upload
EXCLUDES=(
    ".git"
    ".github"
    ".cursor"
    ".claude"
    ".idea"
    ".vscode"
    "node_modules"
    "tests"
    "vendor"
    "ecowitt"
    "telemetry-aggregator"
    "logs"
    "bootstrap/cache/*"
    "storage/logs/*"
    "storage/framework/cache/*"
    "storage/framework/views/*"
    "storage/framework/sessions/*"
    "storage/app/radar-tiles/*"
    ".env"
    ".env.backup*"
    "*.log"
    ".DS_Store"
    "Thumbs.db"
    "deploy.sh"
    "docker-compose*.yml"
    "Dockerfile"
    ".editorconfig"
    ".gitattributes"
    ".gitignore"
    "phpunit.xml"
    "*.md"
)

# Build exclude arguments
EXCLUDE_ARGS=""
for exclude in "${EXCLUDES[@]}"; do
    EXCLUDE_ARGS="$EXCLUDE_ARGS --exclude='$exclude'"
done

# Build rsync command
RSYNC_CMD="rsync -avz --progress -e 'ssh -p $SSH_PORT' $EXCLUDE_ARGS $DELETE_FLAG"

if $DRY_RUN; then
    RSYNC_CMD="$RSYNC_CMD --dry-run"
fi

RSYNC_CMD="$RSYNC_CMD ./ $REMOTE_USER@$REMOTE_HOST:$REMOTE_PATH/"

echo ""
echo -e "${GREEN}======================================${NC}"
echo -e "${GREEN}  WeatherNode Deployment${NC}"
echo -e "${GREEN}======================================${NC}"
echo ""
echo -e "Target: ${YELLOW}$REMOTE_USER@$REMOTE_HOST:$REMOTE_PATH${NC}"
echo ""

if $DRY_RUN; then
    echo -e "${YELLOW}DRY RUN MODE${NC} - No files will be changed"
    echo "Run with --execute to actually deploy"
    echo ""
fi

if [[ -n "$DELETE_FLAG" ]]; then
    echo -e "${RED}WARNING: --delete flag is set!${NC}"
    echo "Files on server not in local will be DELETED"
    echo ""
fi

echo "Excluded from sync:"
for exclude in "${EXCLUDES[@]}"; do
    echo "  - $exclude"
done
echo ""

# Check if build exists
if [[ ! -d "public/build" ]]; then
    echo -e "${RED}Warning: public/build/ not found!${NC}"
    echo "Run 'npm run build' first if you made CSS/JS changes"
    echo ""
fi

echo -e "${GREEN}Starting sync...${NC}"
echo ""

# Execute rsync
eval $RSYNC_CMD

RESULT=$?

echo ""
if [ $RESULT -eq 0 ]; then
    if $DRY_RUN; then
        echo -e "${GREEN}Dry run complete!${NC}"
        echo ""
        echo "To actually deploy, run:"
        echo -e "  ${YELLOW}./deploy.sh --execute${NC}"
    else
        echo -e "${GREEN}Deployment complete!${NC}"
        echo ""
        echo "Next steps on the server:"
        echo "  1. Run: composer install --no-dev"
        echo "  2. Run: php artisan migrate"
        echo "  3. Run: php artisan optimize:clear"
        echo "  4. Run: php artisan optimize"
    fi
else
    echo -e "${RED}Deployment failed with error code $RESULT${NC}"
fi

echo ""
