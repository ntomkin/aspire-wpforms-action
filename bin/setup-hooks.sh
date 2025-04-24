#!/bin/bash
# This script sets up Git hooks for the plugin

# Exit on errors
set -e

PLUGIN_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
HOOKS_DIR="$PLUGIN_DIR/.git/hooks"
HOOKS_SOURCE="$PLUGIN_DIR/bin/hooks"

# Create hooks directory if it doesn't exist
mkdir -p "$HOOKS_DIR"
mkdir -p "$HOOKS_SOURCE"

# Create pre-commit hook
echo "Creating pre-commit hook..."
cat > "$HOOKS_SOURCE/pre-commit" << 'EOF'
#!/bin/bash

# Get plugin directory
PLUGIN_DIR=$(git rev-parse --show-toplevel)
BUILD_SCRIPT="$PLUGIN_DIR/bin/build-release.sh"

# Check if version was changed
VERSION_FILE="$PLUGIN_DIR/wpforms-action.php"
if git diff --cached --name-only | grep -q "wpforms-action.php"; then
    # Version might have changed, build release
    echo "Plugin version file changed, building release package..."
    bash "$BUILD_SCRIPT"
    
    # Add the generated ZIP to the commit if it exists
    DIST_DIR="$PLUGIN_DIR/dist"
    ZIP_FILE="$DIST_DIR/aspire-wpforms-action.zip"
    if [ -f "$ZIP_FILE" ]; then
        echo "Adding ZIP file to the commit..."
        git add "$ZIP_FILE"
    fi
else
    echo "No version changes detected, skipping release build"
fi

exit 0
EOF

# Make hook executable
chmod +x "$HOOKS_SOURCE/pre-commit"

# Create symlinks to hooks
ln -sf "$HOOKS_SOURCE/pre-commit" "$HOOKS_DIR/pre-commit"

echo "Git hooks setup complete."
echo "The pre-commit hook will automatically build a release ZIP when version changes are detected."
echo "To use it, run: bash $PLUGIN_DIR/bin/setup-hooks.sh" 