# QA Test Functions for LD Expert Bird
#
# Usage:
#   . .\scripts\qa-functions.ps1  # Load functions
#   qa-admin                      # Run admin tests
#   qa-therapist                  # Run therapist tests
#   qa-e2e                        # Run E2E tests
#   qa-smoke                      # Run smoke tests
#   qa-all                        # Run all tests
#
# To make these persistent, add to your PowerShell profile:
#   . .\scripts\qa-functions.ps1

function qa-admin {
    <#
    .SYNOPSIS
    Run all admin browser tests (includes school, contract, therapist, student, SSA, session approval)

    .DESCRIPTION
    Executes Dusk tests for admin functionality including the 10 new core flow tests (TC-A033-A042)
    Uses test database (bird_test), safe for development

    .EXAMPLE
    qa-admin
    #>
    Write-Host "🚀 Running admin tests..." -ForegroundColor Cyan
    docker compose exec -T app bash -lc 'cd /var/www/html/app && php artisan dusk tests/BrowserQA/Admin/ --env=testing'
}

function qa-therapist {
    <#
    .SYNOPSIS
    Run all therapist browser tests (schedule, session logging, editing, submission)

    .DESCRIPTION
    Executes Dusk tests for therapist functionality including the 15 new core flow tests (TC-TC124-TC-TC138)
    Uses test database (bird_test), safe for development

    .EXAMPLE
    qa-therapist
    #>
    Write-Host "🚀 Running therapist tests..." -ForegroundColor Cyan
    docker compose exec -T app bash -lc 'cd /var/www/html/app && php artisan dusk tests/BrowserQA/Therapist/ --env=testing'
}

function qa-e2e {
    <#
    .SYNOPSIS
    Run end-to-end browser tests (complete workflows across roles)

    .DESCRIPTION
    Executes Dusk tests for complete business flows including the 5 new E2E core flow tests (TC-E017-TC-E021)
    Tests the full journey from admin setup through therapist work to billing
    Uses test database (bird_test), safe for development

    .EXAMPLE
    qa-e2e
    #>
    Write-Host "🚀 Running E2E tests..." -ForegroundColor Cyan
    docker compose exec -T app bash -lc 'cd /var/www/html/app && php artisan dusk tests/BrowserQA/E2E/ --env=testing'
}

function qa-smoke {
    <#
    .SYNOPSIS
    Run fast smoke tests (quick sanity check, ~3 minutes)

    .DESCRIPTION
    Executes critical path tests: login, dashboard load, role isolation
    Fast way to verify the application is alive and accessible

    .EXAMPLE
    qa-smoke
    #>
    Write-Host "🚀 Running smoke tests..." -ForegroundColor Cyan
    docker compose exec -T app bash -lc 'cd /var/www/html/app && php artisan dusk tests/BrowserQA/Smoke/ --env=testing'
}

function qa-all {
    <#
    .SYNOPSIS
    Run all 30+ browser QA tests

    .DESCRIPTION
    Executes complete Dusk test suite across all roles (Admin, Therapist, Student, Finance, E2E)
    Includes all 30 new core flow tests
    Uses test database (bird_test), safe for development

    .EXAMPLE
    qa-all
    #>
    Write-Host "🚀 Running all browser QA tests..." -ForegroundColor Cyan
    docker compose exec -T app bash -lc 'cd /var/www/html/app && php artisan dusk tests/BrowserQA/ --env=testing'
}

function qa-help {
    <#
    .SYNOPSIS
    Display help for QA functions
    #>
    Write-Host "`n📚 QA Test Functions - LD Expert Bird`n" -ForegroundColor Green
    Write-Host "Available Commands:`n"
    Write-Host "  qa-admin      Run all admin tests (10 new core flow tests)" -ForegroundColor Cyan
    Write-Host "  qa-therapist  Run all therapist tests (15 new core flow tests)" -ForegroundColor Cyan
    Write-Host "  qa-e2e        Run all E2E tests (5 new core flow tests)" -ForegroundColor Cyan
    Write-Host "  qa-smoke      Run fast smoke tests (~3 min)" -ForegroundColor Cyan
    Write-Host "  qa-all        Run all 30+ browser QA tests" -ForegroundColor Cyan
    Write-Host "  qa-help       Show this help message`n" -ForegroundColor Cyan

    Write-Host "Setup Instructions:`n"
    Write-Host "  Option 1 - Temporary (current session only):" -ForegroundColor Yellow
    Write-Host "    . .\scripts\qa-functions.ps1`n"

    Write-Host "  Option 2 - Permanent (add to PowerShell profile):" -ForegroundColor Yellow
    Write-Host "    notepad `$PROFILE"
    Write-Host "    # Add this line to the end:"
    Write-Host "    . `$PSScriptRoot\..\scripts\qa-functions.ps1`n"
}

# Display help on first load
Write-Host "✅ QA functions loaded! Type 'qa-help' for usage" -ForegroundColor Green
