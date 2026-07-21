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
    'src/routes/onboarding.tsx',
    'src/routes/videos.tsx',
    'src/routes/watch.$videoId.tsx',
    'src/routes/admin.tsx'
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

foreach ($relativePath in @('src/components/layout/Breadcrumbs.tsx', 'src/components/layout/KeepExploring.tsx')) {
    $path = Join-Path $repoRoot $relativePath
    $content = Get-Content -LiteralPath $path -Raw
    if ($content -match 'demoData') {
        throw "Runtime navigation component still uses demoData: $relativePath"
    }
    if ($content -notmatch 'useRuntimeData') {
        throw "Runtime navigation component does not consume WordPress data: $relativePath"
    }
}

foreach ($relativePath in @('src/routes/privacy.tsx', 'src/routes/terms.tsx', 'src/routes/contact.tsx', 'src/routes/advertise.tsx', 'src/routes/copyright.tsx', 'src/routes/corrections.tsx')) {
    $content = Get-Content -LiteralPath (Join-Path $repoRoot $relativePath) -Raw
    if ($content -match 'placeholder will be replaced' -or $content -match '@kpopblog\.com') {
        throw "Release-facing policy or contact page contains unfinished content: $relativePath"
    }
}

$watchRoute = Get-Content -LiteralPath (Join-Path $repoRoot 'src/routes/watch.$videoId.tsx') -Raw
if ($watchRoute -match 'localStorage') {
    throw 'Video comments still use browser-only persistence.'
}

$cpt = Get-Content -LiteralPath (Join-Path $repoRoot 'wordpress-plugin/kpopblog/includes/cpt.php') -Raw
$rest = Get-Content -LiteralPath (Join-Path $repoRoot 'wordpress-plugin/kpopblog/includes/rest.php') -Raw
$fields = Get-Content -LiteralPath (Join-Path $repoRoot 'wordpress-plugin/kpopblog/includes/admin-fields.php') -Raw
$communityApi = Get-Content -LiteralPath (Join-Path $repoRoot 'wordpress-plugin/kpopblog/includes/community.php') -Raw
foreach ($content in @($cpt, $rest, $fields)) {
    if ($content -notmatch 'kb_video') {
        throw 'WordPress video content management is incomplete.'
    }
}

$submitRoute = Get-Content -LiteralPath (Join-Path $repoRoot 'src/routes/submit.tsx') -Raw
if ($submitRoute -match 'placeholder — connect storage later' -or $submitRoute -notmatch 'createSubmission') {
    throw 'Public submission form is not connected to WordPress.'
}
foreach ($content in @($cpt, $communityApi)) {
    if ($content -notmatch 'kb_submission') {
        throw 'WordPress submission management is incomplete.'
    }
}

$bundle = Invoke-RestMethod -Uri 'http://localhost:8088/wp-json/kpopblog/v1/bundle' -TimeoutSec 30
if ($null -eq $bundle.articles -or $null -eq $bundle.artists -or $null -eq $bundle.comebacks -or $null -eq $bundle.videos) {
    throw 'WordPress runtime bundle is incomplete.'
}
if ($null -eq $bundle.community -or $null -eq $bundle.categories) {
    throw 'WordPress runtime bundle is missing public community data.'
}

Write-Host 'WordPress runtime content smoke test passed.'
