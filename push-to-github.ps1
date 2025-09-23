# WordPress Salesforce Integration - Push to GitHub Script
# Run this script after creating the repository on GitHub.com

Write-Host "WordPress Salesforce Integration - GitHub Push Script" -ForegroundColor Green
Write-Host "=================================================" -ForegroundColor Green
Write-Host ""

# Check if we're in a git repository
if (-not (Test-Path ".git")) {
    Write-Host "Error: Not in a git repository. Please run 'git init' first." -ForegroundColor Red
    exit 1
}

# Check if we have commits
$commitCount = (git rev-list --count HEAD 2>$null)
if ($commitCount -eq 0) {
    Write-Host "Error: No commits found. Please commit your changes first." -ForegroundColor Red
    exit 1
}

Write-Host "✅ Git repository found with $commitCount commits" -ForegroundColor Green
Write-Host ""

# Get repository URL from user
$repoUrl = Read-Host "Enter your GitHub repository URL (e.g., https://github.com/username/wp-salesforce-integration.git)"

if (-not $repoUrl) {
    Write-Host "Error: Repository URL is required." -ForegroundColor Red
    exit 1
}

Write-Host ""
Write-Host "Setting up remote origin..." -ForegroundColor Yellow

# Add remote origin
git remote add origin $repoUrl 2>$null
if ($LASTEXITCODE -ne 0) {
    Write-Host "Remote origin already exists, updating..." -ForegroundColor Yellow
    git remote set-url origin $repoUrl
}

Write-Host "✅ Remote origin set to: $repoUrl" -ForegroundColor Green
Write-Host ""

# Set main branch
Write-Host "Setting main branch..." -ForegroundColor Yellow
git branch -M main
Write-Host "✅ Main branch set" -ForegroundColor Green
Write-Host ""

# Push to GitHub
Write-Host "Pushing to GitHub..." -ForegroundColor Yellow
Write-Host "This may take a moment..." -ForegroundColor Yellow
Write-Host ""

git push -u origin main

if ($LASTEXITCODE -eq 0) {
    Write-Host ""
    Write-Host "🎉 SUCCESS! Your WordPress Salesforce Integration plugin has been pushed to GitHub!" -ForegroundColor Green
    Write-Host ""
    Write-Host "Repository URL: $repoUrl" -ForegroundColor Cyan
    Write-Host ""
    Write-Host "Next steps:" -ForegroundColor Yellow
    Write-Host "1. Visit your repository on GitHub.com" -ForegroundColor White
    Write-Host "2. Check that all files are uploaded correctly" -ForegroundColor White
    Write-Host "3. Review the README.md file" -ForegroundColor White
    Write-Host "4. Consider enabling Issues and Discussions" -ForegroundColor White
    Write-Host "5. Set up branch protection rules" -ForegroundColor White
    Write-Host ""
} else {
    Write-Host ""
    Write-Host "❌ Error: Failed to push to GitHub" -ForegroundColor Red
    Write-Host "Please check your repository URL and try again." -ForegroundColor Red
    Write-Host ""
    Write-Host "Common issues:" -ForegroundColor Yellow
    Write-Host "- Repository URL is incorrect" -ForegroundColor White
    Write-Host "- Repository doesn't exist on GitHub" -ForegroundColor White
    Write-Host "- Authentication issues" -ForegroundColor White
    Write-Host ""
}
