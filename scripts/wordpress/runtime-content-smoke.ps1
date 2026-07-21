[CmdletBinding()]
param()

$ErrorActionPreference = 'Stop'
$repoRoot = (Resolve-Path (Join-Path $PSScriptRoot '..\..')).Path

$runtimeRoutes = @(
    'src/routes/index.tsx',
    'src/routes/latest.tsx',
    'src/routes/news.$slug.tsx',
    'src/routes/comebacks.tsx',
    'src/routes/artists.tsx',
    'src/routes/artist.$slug.tsx',
    'src/routes/member.$slug.tsx',
    'src/routes/category.$slug.tsx',
    'src/routes/tag.$slug.tsx',
    'src/routes/author.$slug.tsx',
    'src/routes/trending.tsx',
    'src/routes/search.tsx',
    'src/routes/polls.tsx',
    'src/routes/polls.$slug.tsx',
    'src/routes/charts.tsx',
    'src/routes/onboarding.tsx'
)

foreach ($relativePath in $runtimeRoutes) {
    $path = Join-Path $repoRoot $relativePath
    $content = Get-Content -LiteralPath $path -Raw
    if ($content -match 'import\s+\{\s*demoData\s*\}') {
        throw "Public CMS route still imports demoData: $relativePath"
    }
    if ($content -notmatch 'useRuntimeData') {
        throw "Public CMS route does not consume the runtime bundle: $relativePath"
    }
}

$bundle = Invoke-RestMethod -Uri 'http://localhost:8088/wp-json/kpopblog/v1/bundle' -TimeoutSec 30
if ($null -eq $bundle.articles -or $null -eq $bundle.artists -or $null -eq $bundle.comebacks) {
    throw 'WordPress runtime bundle is incomplete.'
}
if ($null -eq $bundle.community -or $null -eq $bundle.categories) {
    throw 'WordPress runtime bundle is missing public community data.'
}

Write-Host 'WordPress runtime content smoke test passed.'
