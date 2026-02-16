#!/bin/bash
# Load environment variables
source .env

# Configuration
REMOTE="${SSH_USER}@${SSH_HOST}"
LOCAL_PATH="."

# rsync options
RSYNC_OPTS="-avzP --delete --exclude-from=.rsyncignore"

# SSH options
SSH_OPTS="-p ${SSH_PORT:-22}"
if [ -n "$SSH_KEY" ]; then
    SSH_OPTS="$SSH_OPTS -i $SSH_KEY"
fi

# Commands
case "$1" in
    push)
        echo "🚀 Deploying to production..."
        if [ "$2" = "--dry-run" ]; then
            RSYNC_OPTS="$RSYNC_OPTS --dry-run"
            echo "DRY RUN MODE"
        fi
        rsync $RSYNC_OPTS -e "ssh $SSH_OPTS" $LOCAL_PATH/ $REMOTE:$REMOTE_PATH/
        echo "✅ Deployment complete"
        ;;
    pull)
        echo "⬇️  Pulling from production..."
        read -p "This will overwrite local files. Continue? (y/N): " confirm
        if [ "$confirm" = "y" ]; then
            rsync $RSYNC_OPTS -e "ssh $SSH_OPTS" $REMOTE:$REMOTE_PATH/ $LOCAL_PATH/
            echo "✅ Pull complete"
        fi
        ;;
    *)
        echo "Usage: $0 {push|pull} [--dry-run]"
        exit 1
        ;;
esac
