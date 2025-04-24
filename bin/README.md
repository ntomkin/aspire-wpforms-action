# Plugin Development Tools

This directory contains scripts to help with plugin development and release management.

## Setup

To set up the development environment:

1. Make the scripts executable:
   ```
   chmod +x bin/*.sh
   ```

2. Set up Git hooks:
   ```
   bash bin/setup-hooks.sh
   ```

## Automatic ZIP creation

Once set up, the plugin will automatically:

1. Create a properly structured ZIP file when you change the version in the main plugin file
2. Add the ZIP file to your commit

### How it works

- The pre-commit hook detects changes to the main plugin file
- If changes are detected, it runs the build script to create a ZIP
- The ZIP is added to your commit

### Manual build

To manually create a ZIP file:

```
bash bin/build-release.sh
```

The ZIP file will be created in the `dist/` directory.

## GitHub Actions Integration

A GitHub workflow is included that will automatically:

1. Build a properly structured ZIP file when you create a new tag
2. Attach the ZIP file to the GitHub release

To use this feature:

1. Push your changes to GitHub
2. Create a new tag (should match the plugin version)
3. Create a release from the tag
4. The workflow will automatically attach the ZIP file to the release

## Workflow for releasing a new version

1. Update the version number in `wpforms-action.php`
2. Commit your changes (the ZIP will be automatically created and included)
3. Tag the commit with the same version number (e.g., `git tag 1.3.2`)
4. Push the commit and tag to GitHub (`git push && git push --tags`)
5. Create a release on GitHub from the tag
6. The GitHub Actions workflow will attach the ZIP to the release automatically

This process ensures that your plugin is always ready for automatic updates. 