[CmdletBinding()]
param([string]$ComposeProjectName = 'k-pop-pulse-hub')

$ErrorActionPreference = 'Stop'
$repoRoot = (Resolve-Path (Join-Path $PSScriptRoot '..\..')).Path
. (Join-Path $PSScriptRoot 'compose-context.ps1')
$composeContext = Resolve-KpopBlogComposeContext -RepositoryRoot $repoRoot -ProjectName $ComposeProjectName
$compose = $composeContext.ComposePath
$envFile = $composeContext.EnvironmentPath
$env:COMPOSE_PROJECT_NAME = $composeContext.ProjectName

$settings = @{}
foreach ($line in Get-Content -LiteralPath $envFile) {
    if ($line -match '^\s*#' -or $line -notmatch '=') { continue }
    $name, $value = $line -split '=', 2
    $settings[$name.Trim()] = $value.Trim()
}

$required = @(
    'WORDPRESS_PORT',
    'WORDPRESS_ADMIN_USER',
    'WORDPRESS_ADMIN_PASSWORD',
    'WORDPRESS_ADMIN_EMAIL',
    'WORDPRESS_MEMBER_USER',
    'WORDPRESS_MEMBER_PASSWORD',
    'WORDPRESS_MEMBER_EMAIL'
)
foreach ($name in $required) {
    if (-not $settings.ContainsKey($name) -or [string]::IsNullOrWhiteSpace($settings[$name])) {
        throw "Missing required setting $name in $envFile"
    }
}

function Test-DockerEngine {
    docker info --format '{{.ServerVersion}}' *> $null
    return $LASTEXITCODE -eq 0
}

if (-not (Test-DockerEngine)) {
    $dockerDesktop = 'C:\Program Files\Docker\Docker\Docker Desktop.exe'
    if (-not (Test-Path -LiteralPath $dockerDesktop)) {
        throw 'Docker Desktop is installed but its executable was not found.'
    }
    Start-Process -FilePath $dockerDesktop -WindowStyle Hidden
    $deadline = [DateTime]::UtcNow.AddSeconds(120)
    do {
        Start-Sleep -Seconds 2
        if (Test-DockerEngine) { break }
    } while ([DateTime]::UtcNow -lt $deadline)
    if (-not (Test-DockerEngine)) {
        throw 'Docker Desktop did not become ready within 120 seconds.'
    }
}

function Invoke-Compose {
    param([Parameter(ValueFromRemainingArguments = $true)][string[]]$Arguments)
    & docker compose --env-file $envFile -f $compose @Arguments
    if ($LASTEXITCODE -ne 0) {
        throw "Docker Compose command failed: $($Arguments -join ' ')"
    }
}

function Invoke-Wp {
    param([Parameter(ValueFromRemainingArguments = $true)][string[]]$Arguments)
    & docker compose --env-file $envFile -f $compose run --rm --no-deps cli @Arguments
    if ($LASTEXITCODE -ne 0) {
        throw "WP-CLI command failed: wp $($Arguments -join ' ')"
    }
}

Invoke-Compose -Arguments @('up', '-d', 'db', 'wordpress')
Assert-KpopBlogComposeMount -Context $composeContext

$ready = $false
$deadline = [DateTime]::UtcNow.AddSeconds(180)
do {
    docker compose --env-file $envFile -f $compose run --rm --no-deps cli core version *> $null
    if ($LASTEXITCODE -eq 0) {
        docker compose --env-file $envFile -f $compose run --rm --no-deps cli db check *> $null
        if ($LASTEXITCODE -eq 0) {
            $ready = $true
            break
        }
    }
    Start-Sleep -Seconds 3
} while ([DateTime]::UtcNow -lt $deadline)

if (-not $ready) {
    throw 'WordPress and MariaDB did not become ready within 180 seconds.'
}

$port = $settings['WORDPRESS_PORT']
$siteUrl = "http://localhost:$port"
docker compose --env-file $envFile -f $compose run --rm --no-deps cli core is-installed *> $null
if ($LASTEXITCODE -ne 0) {
    Invoke-Wp -Arguments @(
        'core',
        'install',
        "--url=$siteUrl",
        '--title=K-pop Pulse Hub Local',
        "--admin_user=$($settings['WORDPRESS_ADMIN_USER'])",
        "--admin_password=$($settings['WORDPRESS_ADMIN_PASSWORD'])",
        "--admin_email=$($settings['WORDPRESS_ADMIN_EMAIL'])",
        '--skip-email'
    )
}

Invoke-Wp -Arguments @('plugin', 'activate', 'kpopblog')
Invoke-Wp -Arguments @('rewrite', 'structure', '/%postname%/', '--hard')
Invoke-Wp -Arguments @('option', 'update', 'users_can_register', '1')
Invoke-Wp -Arguments @('option', 'update', 'default_role', 'subscriber')

docker compose --env-file $envFile -f $compose run --rm --no-deps cli user get $settings['WORDPRESS_MEMBER_USER'] --field=ID *> $null
if ($LASTEXITCODE -ne 0) {
    Invoke-Wp -Arguments @(
        'user',
        'create',
        $settings['WORDPRESS_MEMBER_USER'],
        $settings['WORDPRESS_MEMBER_EMAIL'],
        '--role=subscriber',
        "--user_pass=$($settings['WORDPRESS_MEMBER_PASSWORD'])"
    )
}

$frontPage = (& docker compose --env-file $envFile -f $compose run --rm --no-deps cli option get page_on_front 2>$null).Trim()
$frontPageId = 0
if ($LASTEXITCODE -ne 0 -or -not [int]::TryParse($frontPage, [ref]$frontPageId) -or $frontPageId -le 0) {
    $frontPage = (& docker compose --env-file $envFile -f $compose run --rm --no-deps cli post create '--post_type=page' '--post_title=Home' '--post_status=publish' '--porcelain').Trim()
    if ($LASTEXITCODE -ne 0 -or -not [int]::TryParse($frontPage, [ref]$frontPageId)) {
        throw 'Failed to create the local WordPress home page.'
    }
    Invoke-Wp -Arguments @('post', 'meta', 'update', "$frontPageId", '_wp_page_template', 'kpopblog-app')
    Invoke-Wp -Arguments @('option', 'update', 'show_on_front', 'page')
    Invoke-Wp -Arguments @('option', 'update', 'page_on_front', "$frontPageId")
}

Write-Host "K-pop Pulse Hub Local: $siteUrl/"
Write-Host "WordPress admin: $siteUrl/wp-admin/"
