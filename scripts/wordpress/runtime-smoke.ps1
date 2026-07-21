[CmdletBinding()]
param([int]$Port = 8088)

$ErrorActionPreference = 'Stop'
$repoRoot = (Resolve-Path (Join-Path $PSScriptRoot '..\..')).Path
$compose = Join-Path $repoRoot 'docker-compose.wordpress.yml'
$envFile = Join-Path $repoRoot '.env.wordpress'

if (-not (Test-Path -LiteralPath $compose)) {
    throw "Missing $compose"
}

if (-not (Test-Path -LiteralPath $envFile)) {
    $envFile = Join-Path $repoRoot '.env.wordpress.example'
}

docker compose --env-file $envFile -f $compose config --quiet
if ($LASTEXITCODE -ne 0) {
    throw 'Docker Compose configuration is invalid.'
}

$status = Invoke-RestMethod -Uri "http://localhost:$Port/wp-json/" -TimeoutSec 15
if ($status.name -ne 'K-pop Pulse Hub Local') {
    throw "Unexpected site name: $($status.name)"
}

$bundle = Invoke-RestMethod -Uri "http://localhost:$Port/wp-json/kpopblog/v1/bundle" -TimeoutSec 15
if ($null -eq $bundle.articles) {
    throw 'KpopBlog bundle route is unavailable.'
}

docker compose --env-file $envFile -f $compose run --rm cli plugin is-active kpopblog
if ($LASTEXITCODE -ne 0) {
    throw 'KpopBlog plugin is not active.'
}

Write-Host 'WordPress runtime smoke test passed.'
