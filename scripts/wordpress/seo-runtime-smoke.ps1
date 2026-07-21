[CmdletBinding()]
param()

$ErrorActionPreference = 'Stop'
$baseUrl = 'http://localhost:8088'
$bundle = Invoke-RestMethod -Uri "$baseUrl/wp-json/kpopblog/v1/bundle" -TimeoutSec 30
$article = @($bundle.articles) | Select-Object -First 1
$schedule = @($bundle.comebacks) | Select-Object -First 1
if (-not $article) { throw 'SEO smoke test requires one published WordPress article.' }
if (-not $schedule) { throw 'SEO smoke test requires one published WordPress schedule.' }

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
foreach ($token in @('OAI-SearchBot', 'GPTBot', 'ClaudeBot', 'Claude-SearchBot', 'Claude-User', 'PerplexityBot', 'Perplexity-User', 'Google-Extended')) {
    if ($robots.Content -notmatch [regex]::Escape("User-agent: $token")) {
        throw "WordPress robots endpoint does not explicitly allow $token."
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

Write-Host 'WordPress SEO runtime smoke test passed.'
