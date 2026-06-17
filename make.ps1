# Simple PowerShell wrapper to call make.bat
# Usage: .\make.ps1 qa-admin

param([string]$Command = "")

if ($Command -eq "") {
    & .\make.bat
} else {
    & .\make.bat $Command
}
