function Resolve-KpopBlogComposeContext {
    param(
        [Parameter(Mandatory = $true)][string]$RepositoryRoot,
        [string]$ProjectName = 'k-pop-pulse-hub'
    )
    $resolvedRoot = (Resolve-Path -LiteralPath $RepositoryRoot).Path
    $composePath = Join-Path $resolvedRoot 'docker-compose.wordpress.yml'
    $environmentPath = Join-Path $resolvedRoot '.env.wordpress'
    if (-not (Test-Path -LiteralPath $environmentPath)) {
        $environmentPath = Join-Path $resolvedRoot '.env.wordpress.example'
    }
    if ([string]::IsNullOrWhiteSpace($ProjectName)) { throw 'Compose project name must not be empty.' }
    if (-not (Test-Path -LiteralPath $composePath)) { throw "Missing $composePath" }
    if (-not (Test-Path -LiteralPath $environmentPath)) { throw "Missing $environmentPath" }
    return [pscustomobject]@{ ProjectName = $ProjectName; ComposePath = $composePath; EnvironmentPath = $environmentPath; PluginPath = (Resolve-Path -LiteralPath (Join-Path $resolvedRoot 'wordpress-plugin\kpopblog')).Path }
}

function Assert-KpopBlogComposeMount {
    param([Parameter(Mandatory = $true)][pscustomobject]$Context)
    $containerId = (& docker compose --project-name $Context.ProjectName --env-file $Context.EnvironmentPath -f $Context.ComposePath ps -q wordpress).Trim()
    if ($LASTEXITCODE -ne 0 -or [string]::IsNullOrWhiteSpace($containerId)) {
        throw "WordPress is not running for Compose project $($Context.ProjectName). Run bootstrap.ps1 with the same -ComposeProjectName first."
    }
    $mountJson = (& docker inspect --format '{{json .Mounts}}' $containerId).Trim()
    if ($LASTEXITCODE -ne 0) { throw 'Could not inspect the running WordPress container mount.' }
    $mounts = @($mountJson | ConvertFrom-Json)
    $pluginMount = @($mounts | Where-Object { [string]$_.Destination -eq '/var/www/html/wp-content/plugins/kpopblog' }) | Select-Object -First 1
    if (-not $pluginMount) { throw 'Running WordPress container has no KpopBlog plugin bind mount.' }
    $actual = [System.IO.Path]::GetFullPath([string]$pluginMount.Source).TrimEnd('\', '/')
    $expected = [System.IO.Path]::GetFullPath([string]$Context.PluginPath).TrimEnd('\', '/')
    if (-not [string]::Equals($actual, $expected, [System.StringComparison]::OrdinalIgnoreCase)) {
        throw "Running WordPress plugin mount is from '$actual', not checkout under test '$expected'."
    }
}
