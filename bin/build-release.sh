#!/bin/bash
# Script to build a WordPress plugin release ZIP file with proper structure
# Run this before creating a GitHub release

# Exit on errors
set -e

# Get the plugin directory name
PLUGIN_SLUG="aspire-wpforms-action"
PLUGIN_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
MAIN_FILE="$PLUGIN_DIR/wpforms-action.php"
TMP_DIR="/tmp/plugin-build-$PLUGIN_SLUG"
DIST_DIR="$PLUGIN_DIR/dist"

# Create necessary directories
mkdir -p "$TMP_DIR/$PLUGIN_SLUG"
mkdir -p "$DIST_DIR"

# Extract version from the main plugin file
VERSION=$(grep "Version:" "$MAIN_FILE" | sed -E 's/.*Version: +([0-9.]+).*/\1/')

if [ -z "$VERSION" ]; then
    echo "Error: Could not determine plugin version"
    exit 1
fi

echo "Building $PLUGIN_SLUG version $VERSION..."

# Copy plugin files to the temporary directory
rsync -r --exclude=".git" --exclude="dist" --exclude="bin" --exclude=".github" --exclude="node_modules" --exclude=".DS_Store" "$PLUGIN_DIR/" "$TMP_DIR/$PLUGIN_SLUG/"

# Remove any existing ZIP file
rm -f "$DIST_DIR/$PLUGIN_SLUG.zip"

# Create the ZIP file
echo "Creating ZIP file..."
cd "$TMP_DIR" && zip -r "$DIST_DIR/$PLUGIN_SLUG.zip" "$PLUGIN_SLUG" -x "*.DS_Store" -x "*__MACOSX*" -x "*.git*"

# Clean up
rm -rf "$TMP_DIR"

echo "Build completed successfully!"
echo "ZIP file created at: $DIST_DIR/$PLUGIN_SLUG.zip"
echo "Ready for upload to GitHub releases" 