param([switch]$NoBrowser, [ValidateRange(1, 65535)][int]$Port = 8000)
$ErrorActionPreference = 'Stop'
$appRoot = $PSScriptRoot
$logRoot = Join-Path $appRoot 'storage\logs'
$url = "http://127.0.0.1:$Port"
$phpExe = 'C:\xampp\php\php.exe'
if (-not (Test-Path $phpExe)) { $phpExe = (Get-Command php.exe -ErrorAction Stop).Source }

function Test-LocalPort([int]$CheckPort) {
  $client = New-Object System.Net.Sockets.TcpClient
  try { return $client.ConnectAsync('127.0.0.1', $CheckPort).Wait(800) -and $client.Connected }
  catch { return $false }
  finally { $client.Dispose() }
}

try {
  if (-not (Test-LocalPort 3306)) {
    $mysqlStart = 'C:\xampp\mysql_start.bat'
    if (-not (Test-Path $mysqlStart)) { throw 'Start MySQL in XAMPP Control Panel, then run this file again.' }
    Start-Process -FilePath $env:ComSpec -ArgumentList @('/c', $mysqlStart) -WorkingDirectory 'C:\xampp' -WindowStyle Hidden
    for ($attempt = 0; $attempt -lt 30 -and -not (Test-LocalPort 3306); $attempt++) { Start-Sleep -Milliseconds 500 }
    if (-not (Test-LocalPort 3306)) { throw 'MySQL did not start. Check XAMPP Control Panel.' }
  }

  if (-not (Test-Path (Join-Path $appRoot 'vendor\autoload.php'))) { throw 'Laravel dependencies are missing. Run composer install inside this folder.' }
  if (-not (Test-Path (Join-Path $appRoot '.env'))) { throw 'The .env file is missing. Copy .env.example to .env and configure MySQL.' }
  if (-not (Test-Path (Join-Path $appRoot 'public\build\manifest.json'))) { throw 'Frontend assets are missing. Run npm run build inside this folder.' }

  if (Test-LocalPort $Port) {
    $health = Invoke-WebRequest "$url/up" -UseBasicParsing -TimeoutSec 10
    $homeResponse = Invoke-WebRequest "$url/" -UseBasicParsing -TimeoutSec 15
    if ($health.StatusCode -ne 200 -or $homeResponse.StatusCode -ne 200) { throw "Port $Port is open, but the Laravel health check failed." }
    Write-Host "Best Cool Electronics is already running: $url"
  } else {
    New-Item -ItemType Directory -Path $logRoot -Force | Out-Null
    $previousAppUrl = $env:APP_URL
    try {
      $env:APP_URL = $url
      $server = Start-Process -FilePath $phpExe -ArgumentList @('artisan', 'serve', '--host=127.0.0.1', "--port=$Port") -WorkingDirectory $appRoot -WindowStyle Hidden -RedirectStandardOutput (Join-Path $logRoot 'laravel-serve.stdout.log') -RedirectStandardError (Join-Path $logRoot 'laravel-serve.stderr.log') -PassThru
    } finally {
      if ($null -eq $previousAppUrl) { Remove-Item Env:APP_URL -ErrorAction SilentlyContinue } else { $env:APP_URL = $previousAppUrl }
    }

    for ($attempt = 0; $attempt -lt 30 -and -not (Test-LocalPort $Port); $attempt++) {
      Start-Sleep -Milliseconds 500
      if ($server.HasExited) { throw 'Laravel stopped during startup. Check storage\logs\laravel-serve.stderr.log.' }
    }
    if (-not (Test-LocalPort $Port)) { throw 'Laravel did not start. Check storage\logs\laravel-serve.stderr.log.' }
    $health = Invoke-WebRequest "$url/up" -UseBasicParsing -TimeoutSec 10
    $homeResponse = Invoke-WebRequest "$url/" -UseBasicParsing -TimeoutSec 15
    if ($health.StatusCode -ne 200 -or $homeResponse.StatusCode -ne 200) { throw 'Laravel started, but the health check failed. Check the Laravel log.' }
    Write-Host "Best Cool Electronics is running: $url"
  }

  if (-not $NoBrowser) { Start-Process $url }
} catch {
  Write-Host $_.Exception.Message -ForegroundColor Red
  Write-Host "Application logs: $logRoot"
  exit 1
}
