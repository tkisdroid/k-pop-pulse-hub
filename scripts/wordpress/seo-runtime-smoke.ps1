[CmdletBinding()]
param()

$ErrorActionPreference = 'Stop'
$baseUrl = 'http://localhost:8088'
$bundle = Invoke-RestMethod -Uri "$baseUrl/wp-json/kpopblog/v1/bundle" -TimeoutSec 30
$article = @($bundle.articles) | Select-Object -First 1
if (-not $article) { throw 'SEO smoke test requires one published WordPress article.' }
$titlePattern = [regex]::Escape([string]$article.title)
$pathPattern = [regex]::Escape('/news/' + [string]$article.slug)

$rss = Invoke-WebRequest -UseBasicParsing -Uri "$baseUrl/rss.xml" -TimeoutSec 30
if ($rss.StatusCode -ne 200 -or $rss.Headers['Content-Type'] -notmatch 'application/rss\+xml') {
    throw 'WordPress RSS endpoint did not return RSS XML.'
}
if ($rss.Content -notmatch '<rss' -or $rss.Content -notmatch $titlePattern) {
    throw 'WordPress RSS endpoint does not contain current WordPress articles.'
}

$sitemap = Invoke-WebRequest -UseBasicParsing -Uri "$baseUrl/sitemap.xml" -TimeoutSec 30
if ($sitemap.StatusCode -ne 200 -or $sitemap.Headers['Content-Type'] -notmatch 'application/xml') {
    throw 'WordPress sitemap endpoint did not return XML.'
}
if ($sitemap.Content -notmatch '<urlset' -or $sitemap.Content -notmatch $pathPattern) {
    throw 'WordPress sitemap does not contain current WordPress content.'
}

$robots = Invoke-WebRequest -UseBasicParsing -Uri "$baseUrl/robots.txt" -TimeoutSec 30
if ($robots.StatusCode -ne 200 -or $robots.Headers['Content-Type'] -notmatch 'text/plain' -or $robots.Content -notmatch 'Sitemap: http://localhost:8088/sitemap.xml') {
    throw 'WordPress robots endpoint is incomplete.'
}

Write-Host 'WordPress SEO runtime smoke test passed.'
