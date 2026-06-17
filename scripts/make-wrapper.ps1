# PowerShell wrapper for make.bat
# This allows you to use "make" command directly without ".\" prefix

# Get the directory where this script is located
$scriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
$makeFile = Join-Path $scriptDir ".." "make.bat"

function make {
    <#
    .SYNOPSIS
    Windows wrapper for make.bat commands

    .DESCRIPTION
    Allows cross-platform make commands to work on Windows

    .EXAMPLE
    make qa-admin
    make qa-browser
    make up
    #>
    param(
        [Parameter(ValueFromRemainingArguments=$true)]
        [string[]]$Arguments
    )

    & cmd /c "$makeFile $Arguments"
}

# Export the function
Export-ModuleMemberFunction make -Force

Write-Host "✅ Make wrapper loaded! You can now use: make qa-admin, make qa-browser, etc." -ForegroundColor Green
