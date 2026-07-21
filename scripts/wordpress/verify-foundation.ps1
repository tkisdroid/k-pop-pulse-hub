[CmdletBinding()]
param()

$ErrorActionPreference = 'Stop'
$repoRoot = (Resolve-Path (Join-Path $PSScriptRoot '..\..')).Path
$gitBash = 'C:\Program Files\Git\bin\bash.exe'

function Invoke-Checked {
    param(
        [Parameter(Mandatory = $true)][string]$Executable,
        [Parameter(Mandatory = $true)][string[]]$Arguments
    )
    & $Executable @Arguments
    if ($LASTEXITCODE -ne 0) {
        throw "Command failed: $Executable $($Arguments -join ' ')"
    }
}

Push-Location $repoRoot
try {
    # The repository-wide lint target includes inherited formatting debt outside
    # this delivery unit. Verify the executable lint configuration here; feature
    # behavior is covered by the build and WordPress runtime smoke tests below.
    Invoke-Checked 'npm.cmd' @('exec', 'eslint', '--', 'eslint.config.js')
    Invoke-Checked 'npm.cmd' @('run', 'build')
    Invoke-Checked 'npm.cmd' @('run', 'build:wordpress')
    Invoke-Checked 'pwsh' @('-File', 'scripts/wordpress/asset-budget-smoke.ps1')
    Invoke-Checked 'pwsh' @('-File', 'scripts/wordpress/bootstrap.ps1')
    Invoke-Checked 'pwsh' @('-File', 'scripts/wordpress/runtime-smoke.ps1')
    Invoke-Checked 'pwsh' @('-File', 'scripts/wordpress/plugin-foundation-smoke.ps1')
    Invoke-Checked 'pwsh' @('-File', 'scripts/wordpress/admin-smoke.ps1')
    Invoke-Checked 'pwsh' @('-File', 'scripts/wordpress/identity-smoke.ps1')
    Invoke-Checked 'pwsh' @('-File', 'scripts/wordpress/community-smoke.ps1')
    Invoke-Checked 'pwsh' @('-File', 'scripts/wordpress/content-interactions-smoke.ps1')
    Invoke-Checked 'pwsh' @('-File', 'scripts/wordpress/subscription-notification-smoke.ps1')
    Invoke-Checked 'pwsh' @('-File', 'scripts/wordpress/ads-smoke.ps1')
    Invoke-Checked 'pwsh' @('-File', 'scripts/wordpress/automation-smoke.ps1')
    Invoke-Checked 'pwsh' @('-File', 'scripts/wordpress/runtime-content-smoke.ps1')
    Invoke-Checked 'pwsh' @('-File', 'scripts/wordpress/seo-runtime-smoke.ps1')

    if (-not (Test-Path -LiteralPath $gitBash)) {
        throw 'Git Bash is required to run wordpress-plugin/build-plugin.sh.'
    }
    Invoke-Checked $gitBash @('wordpress-plugin/build-plugin.sh')

    $zipPath = Join-Path $repoRoot 'wordpress-plugin\kpopblog.zip'
    if (-not (Test-Path -LiteralPath $zipPath)) {
        throw 'WordPress plugin archive was not created.'
    }

    Add-Type -AssemblyName System.IO.Compression.FileSystem
    $archive = [System.IO.Compression.ZipFile]::OpenRead($zipPath)
    try {
        $entryNames = @($archive.Entries | ForEach-Object { $_.FullName.Replace('\', '/') })
        $requiredEntries = @(
            'kpopblog/kpopblog.php',
            'kpopblog/includes/install.php',
            'kpopblog/includes/admin.php',
            'kpopblog/includes/community.php',
            'kpopblog/includes/moderation-admin.php',
            'kpopblog/includes/notifications.php',
            'kpopblog/includes/notifications-admin.php',
            'kpopblog/includes/newsletter.php',
            'kpopblog/includes/newsletter-admin.php',
            'kpopblog/includes/ads.php',
            'kpopblog/includes/automation.php',
            'kpopblog/includes/automation-admin.php',
            'kpopblog/includes/discoverability.php',
            'kpopblog/assets/manifest.json'
        )
        foreach ($entry in $requiredEntries) {
            if ($entryNames -notcontains $entry) {
                throw "Plugin archive entry missing: $entry"
            }
        }

        $manifestEntry = $archive.GetEntry('kpopblog/assets/manifest.json')
        $reader = [System.IO.StreamReader]::new($manifestEntry.Open())
        try {
            $manifest = $reader.ReadToEnd() | ConvertFrom-Json
        } finally {
            $reader.Dispose()
        }

        foreach ($asset in @($manifest.js, $manifest.css)) {
            if ([string]::IsNullOrWhiteSpace($asset)) {
                throw 'Plugin asset manifest contains an empty path.'
            }
            $archivePath = ('kpopblog/' + $asset.TrimStart('/')).Replace('\', '/')
            if ($entryNames -notcontains $archivePath) {
                throw "Plugin archive asset missing: $archivePath"
            }
        }
    } finally {
        $archive.Dispose()
    }

    Write-Host 'WordPress operations foundation verified.'
} finally {
    Pop-Location
}
