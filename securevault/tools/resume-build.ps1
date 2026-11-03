$ErrorActionPreference = 'Stop'
$projectRoot = Split-Path -Parent $PSScriptRoot
$workspaceRoot = Split-Path -Parent $projectRoot
$env:COMPOSER_HOME = Join-Path $workspaceRoot '.composer'
$env:COMPOSER_CACHE_DIR = Join-Path $workspaceRoot '.composer-cache'
$env:TEMP = Join-Path $workspaceRoot '.tmp'
$env:TMP = $env:TEMP
New-Item -ItemType Directory -Force -Path $env:TEMP | Out-Null
Set-Location -LiteralPath $projectRoot
composer install --prefer-dist --no-interaction
if ($LASTEXITCODE -ne 0) { throw 'Composer installation failed' }
Invoke-WebRequest -UseBasicParsing -Uri 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css' -OutFile 'public/assets/bootstrap.min.css'
Write-Output 'Dependencies and Bootstrap installed. Run the MySQL test suite before building a release.'
