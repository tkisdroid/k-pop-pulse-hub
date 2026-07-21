[CmdletBinding()]
param()

$ErrorActionPreference = 'Stop'
$repoRoot = (Resolve-Path (Join-Path $PSScriptRoot '..\..')).Path
$compose = Join-Path $repoRoot 'docker-compose.wordpress.yml'
$envFile = Join-Path $repoRoot '.env.wordpress'
if (-not (Test-Path -LiteralPath $envFile)) {
    $envFile = Join-Path $repoRoot '.env.wordpress.example'
}
$baseUrl = 'http://localhost:8088'
$temporaryScheduleId = 0

function Invoke-WpCli {
    param([Parameter(ValueFromRemainingArguments = $true)][string[]]$Arguments)
    $output = & docker compose --env-file $envFile -f $compose run --rm --no-deps cli @Arguments
    if ($LASTEXITCODE -ne 0) {
        throw "WP-CLI command failed: wp $($Arguments -join ' ')"
    }
    return @($output)
}

try {
$bundle = Invoke-RestMethod -Uri "$baseUrl/wp-json/kpopblog/v1/bundle" -TimeoutSec 30
$article = @($bundle.articles) | Select-Object -First 1
$schedule = @($bundle.comebacks) | Select-Object -First 1
if (-not $article) { throw 'SEO smoke test requires one published WordPress article.' }
if (-not $schedule) {
    $fixture = @'
$post_id = wp_insert_post( array(
    'post_type'    => 'kb_comeback',
    'post_title'   => 'KpopBlog SEO runtime temporary schedule',
    'post_content' => 'Temporary schedule created and removed by seo-runtime-smoke.ps1.',
    'post_status'  => 'publish',
), true );
if ( is_wp_error( $post_id ) ) {
    throw new Exception( 'failed to create temporary SEO schedule: ' . $post_id->get_error_message() );
}
update_post_meta( $post_id, 'kb_release_at', '2030-01-15T12:00:00+09:00' );
update_post_meta( $post_id, 'kb_type', 'album' );
update_post_meta( $post_id, 'kb_source', 'seo-runtime-smoke' );
echo $post_id;
'@
    $fixtureOutput = Invoke-WpCli 'eval' $fixture
    $fixtureIdText = [string](@($fixtureOutput) | Select-Object -Last 1)
    if (-not [int]::TryParse($fixtureIdText.Trim(), [ref]$temporaryScheduleId) -or $temporaryScheduleId -le 0) {
        throw 'SEO smoke test could not determine the temporary schedule ID.'
    }
    $bundle = Invoke-RestMethod -Uri "$baseUrl/wp-json/kpopblog/v1/bundle" -TimeoutSec 30
    $schedule = @($bundle.comebacks) | Where-Object { [string]$_.id -eq [string]$temporaryScheduleId } | Select-Object -First 1
    if (-not $schedule) { throw 'SEO smoke test temporary schedule is unavailable from WordPress.' }
}

$articlePath = '/news/' + [string]$article.slug
$articlePattern = [regex]::Escape($articlePath)
$scheduleAnchorPattern = [regex]::Escape('/comebacks#event-' + [string]$schedule.id)

$rss = Invoke-WebRequest -UseBasicParsing -Uri "$baseUrl/rss.xml" -TimeoutSec 30
if ($rss.StatusCode -ne 200 -or $rss.Headers['Content-Type'] -notmatch 'application/rss\+xml') {
    throw 'WordPress RSS endpoint did not return RSS XML.'
}
try { [xml]$rssXml = $rss.Content } catch { throw 'WordPress RSS endpoint returned malformed XML.' }
if ($rss.Content -notmatch $articlePattern -or $rss.Content -notmatch $scheduleAnchorPattern) {
    throw 'WordPress RSS endpoint is missing published article or schedule content.'
}

$sitemap = Invoke-WebRequest -UseBasicParsing -Uri "$baseUrl/sitemap.xml" -TimeoutSec 30
if ($sitemap.StatusCode -ne 200 -or $sitemap.Headers['Content-Type'] -notmatch 'application/xml') {
    throw 'WordPress sitemap endpoint did not return XML.'
}
try { [xml]$sitemapXml = $sitemap.Content } catch { throw 'WordPress sitemap endpoint returned malformed XML.' }
if ($sitemap.Content -notmatch $articlePattern -or $sitemap.Content -notmatch '<loc>http://localhost:8088/comebacks</loc>') {
    throw 'WordPress sitemap is missing public article or comeback calendar URLs.'
}
if (-not $sitemap.Headers['ETag'] -or -not $sitemap.Headers['Last-Modified']) {
    throw 'WordPress sitemap is missing cache validators.'
}
$notModified = Invoke-WebRequest -UseBasicParsing -Uri "$baseUrl/sitemap.xml" -Headers @{ 'If-None-Match' = [string]$sitemap.Headers['ETag'] } -SkipHttpErrorCheck -TimeoutSec 30
if ($notModified.StatusCode -ne 304) { throw 'WordPress sitemap did not honor its ETag.' }

$robots = Invoke-WebRequest -UseBasicParsing -Uri "$baseUrl/robots.txt" -TimeoutSec 30
$agents = @('OAI-SearchBot', 'GPTBot', 'ClaudeBot', 'Claude-SearchBot', 'Claude-User', 'PerplexityBot', 'Perplexity-User', 'Google-Extended')
foreach ($token in $agents) {
    if ($robots.Content -notmatch [regex]::Escape("User-agent: $token")) {
        throw "WordPress robots endpoint does not explicitly allow $token."
    }
}
$privatePaths = @(
    '/wp-admin/',
    '/wp-login.php',
    '/admin',
    '/moderation',
    '/onboarding',
    '/wp-json/kpopblog/v1/admin',
    '/wp-json/kpopblog/v1/auth',
    '/wp-json/kpopblog/v1/subscriptions',
    '/wp-json/kpopblog/v1/notifications',
    '/wp-json/kpopblog/v1/events',
    '/wp-json/kpopblog/v1/moderation'
)
foreach ($privatePath in $privatePaths) {
    $rulePattern = '(?m)^' + [regex]::Escape("Disallow: $privatePath") + '\r?$'
    $ruleCount = [regex]::Matches($robots.Content, $rulePattern).Count
    if ($ruleCount -ne ($agents.Count + 1)) {
        throw "WordPress robots endpoint does not apply $privatePath to every crawler group."
    }
}
foreach ($endpoint in @('sitemap.xml', 'rss.xml', 'llms.txt')) {
    if ($robots.Content -notmatch [regex]::Escape("http://localhost:8088/$endpoint")) {
        throw "WordPress robots endpoint does not advertise $endpoint."
    }
}

$llms = Invoke-WebRequest -UseBasicParsing -Uri "$baseUrl/llms.txt" -TimeoutSec 30
if ($llms.StatusCode -ne 200 -or $llms.Headers['Content-Type'] -notmatch 'text/plain') {
    throw 'WordPress llms endpoint did not return plain text.'
}
if ($llms.Content -notmatch $articlePattern -or $llms.Content -notmatch $scheduleAnchorPattern) {
    throw 'WordPress llms endpoint is missing published article or schedule links.'
}

} finally {
    if ($temporaryScheduleId -gt 0) {
        & docker compose --env-file $envFile -f $compose run --rm --no-deps cli post delete ([string]$temporaryScheduleId) --force
        if ($LASTEXITCODE -ne 0) {
            throw "SEO smoke test failed to delete temporary schedule $temporaryScheduleId."
        }
    }
}

Write-Host 'WordPress SEO runtime smoke test passed.'
