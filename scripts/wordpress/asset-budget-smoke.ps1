[CmdletBinding()]
param()

$ErrorActionPreference = 'Stop'
$repoRoot = (Resolve-Path (Join-Path $PSScriptRoot '..\..')).Path
$assetRoot = Join-Path $repoRoot 'dist\wordpress\assets'

function Get-GzipSize {
    param([Parameter(Mandatory = $true)][string]$Path)

    $input = [System.IO.File]::OpenRead($Path)
    $output = [System.IO.MemoryStream]::new()
    try {
        $gzip = [System.IO.Compression.GZipStream]::new(
            $output,
            [System.IO.Compression.CompressionLevel]::Optimal,
            $true
        )
        try {
            $input.CopyTo($gzip)
        } finally {
            $gzip.Dispose()
        }
        return $output.Length
    } finally {
        $input.Dispose()
        $output.Dispose()
    }
}

$entry = @(Get-ChildItem -LiteralPath $assetRoot -File -Filter 'index-*.js')
$styles = @(Get-ChildItem -LiteralPath $assetRoot -File -Filter 'styles-*.css')
if ($entry.Count -ne 1) {
    throw "Expected one WordPress entry bundle, found $($entry.Count)."
}
if ($styles.Count -ne 1) {
    throw "Expected one WordPress stylesheet bundle, found $($styles.Count)."
}

$entryGzip = Get-GzipSize -Path $entry[0].FullName
$stylesGzip = Get-GzipSize -Path $styles[0].FullName
$entryLimit = 200KB
$stylesLimit = 25KB

if ($entryGzip -gt $entryLimit) {
    throw "WordPress entry bundle exceeds the 200 KiB gzip budget: $entryGzip bytes."
}
if ($stylesGzip -gt $stylesLimit) {
    throw "WordPress stylesheet exceeds the 25 KiB gzip budget: $stylesGzip bytes."
}

Write-Host "Asset budget passed: JS=$entryGzip bytes gzip, CSS=$stylesGzip bytes gzip."
