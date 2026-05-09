# Skill: Release Plugin

This skill outlines the standard operating procedure for releasing a new version of the **NHR Advanced Options Table Manager** plugin.

## Prerequisites
- Ensure you are on the `dev` branch.
- Ensure all tests pass.

## Step 1: Sync Branches
Merge the `staging` branch into `dev` to ensure all latest tested changes are included.
```bash
git checkout dev
git merge staging
```

## Step 2: Version Bumping
1. **Main Plugin File**: Update the `Version:` header in `nhrrob-options-table-manager.php`.
2. **Readme File**: 
   - Update `Stable tag:` in `readme.txt`.
   - Add a new entry under `== Changelog ==` following the existing format:
     ```text
     = X.Y.Z =
     * Feature: Description of change.
     * Fix: Description of fix.
     ```

## Step 3: Commit and PR
1. Commit the version bump:
   ```bash
   git add nhrrob-options-table-manager.php readme.txt
   git commit -m "Chore: Bump version to X.Y.Z"
   ```
2. Push and create a Pull Request to `master`:
   ```bash
   git push origin dev
   # Create PR via GitHub CLI or Web UI
   ```

## Step 4: Finalize Release
1. Approve and merge the PR into `master`.
2. Checkout `master` and pull changes:
   ```bash
   git checkout master
   git pull origin master
   ```
3. Create a git tag for the version:
   ```bash
   git tag -a vX.Y.Z -m "Release version X.Y.Z"
   git push origin vX.Y.Z
   ```
