param([switch]$NoBrowser)

$ErrorActionPreference = 'Stop'
$projectRoot = Split-Path $PSScriptRoot -Parent
$xamppRoot = Split-Path (Split-Path $projectRoot -Parent) -Parent
$frontendRoot = Join-Path $projectRoot 'frontend'
$logRoot = Join-Path $projectRoot 'tmp'
$appUrl = 'http://localhost:3000/'
$apiUrl = 'http://localhost/costestimator/backend/public/api/ping'
$launchMutex = New-Object System.Threading.Mutex($false, 'Local\ESERCostingLocalLauncher')
if (-not $launchMutex.WaitOne(0)) {
    Write-Host 'Peluncur lain sedang menyiapkan sistem. Tunggu hingga selesai.'
    $launchMutex.Dispose()
    exit 0
}

function Test-LocalPort([int]$Port) {
    $listeners = [System.Net.NetworkInformation.IPGlobalProperties]::GetIPGlobalProperties().GetActiveTcpListeners()
    return [bool]($listeners | Where-Object { $_.Port -eq $Port } | Select-Object -First 1)
}

function Wait-LocalPort([int]$Port) {
    for ($attempt = 0; $attempt -lt 30; $attempt++) {
        if (Test-LocalPort $Port) { return }
        Start-Sleep -Seconds 1
    }
    throw "Layanan pada port $Port belum aktif. Periksa XAMPP."
}

try {
    Write-Host 'Menyiapkan ESER Costing System lokal...'
    $cliPath = Join-Path $frontendRoot 'node_modules\@vue\cli-service\bin\vue-cli-service.js'
    if (-not (Test-Path -LiteralPath $cliPath)) {
        throw 'Dependensi frontend belum tersedia. Jalankan npm install di folder frontend terlebih dahulu.'
    }
    New-Item -ItemType Directory -Path $logRoot -Force | Out-Null

    if (-not (Test-LocalPort 80)) {
        Write-Host 'Menjalankan Apache...'
        Start-Process -FilePath (Join-Path $xamppRoot 'apache\bin\httpd.exe') -WorkingDirectory $xamppRoot -WindowStyle Hidden
    }
    if (-not (Test-LocalPort 3306)) {
        Write-Host 'Menjalankan MySQL...'
        Start-Process -FilePath (Join-Path $xamppRoot 'mysql\bin\mysqld.exe') -ArgumentList '--defaults-file=mysql\bin\my.ini', '--standalone', '--console' -WorkingDirectory $xamppRoot -WindowStyle Hidden -RedirectStandardOutput (Join-Path $logRoot 'local-mysql.stdout.log') -RedirectStandardError (Join-Path $logRoot 'local-mysql.stderr.log')
    }
    Wait-LocalPort 80
    Wait-LocalPort 3306
    $api = Invoke-RestMethod -Uri $apiUrl -TimeoutSec 15
    if ($api.status -ne 'ok' -or $api.message -ne 'Cost Estimator backend API is reachable.') {
        throw "Backend tidak merespons dengan benar: $apiUrl"
    }

    if (-not (Test-LocalPort 3000)) {
        $nodeCommand = Get-Command node.exe -ErrorAction SilentlyContinue
        $nodePath = if ($nodeCommand) { $nodeCommand.Source } else { $null }
        if (-not $nodePath) {
            $candidates = @(
                (Join-Path $env:ProgramFiles 'nodejs\node.exe'),
                (Join-Path $env:USERPROFILE '.cache\codex-runtimes\codex-primary-runtime\dependencies\node\bin\node.exe')
            )
            $nodePath = $candidates | Where-Object { Test-Path -LiteralPath $_ } | Select-Object -First 1
        }
        if (-not $nodePath) { throw 'Node.js tidak ditemukan. Instal Node.js terlebih dahulu.' }

        $env:VUE_APP_BACKEND = 'true'
        $env:NODE_ENV = 'development'
        $nodeMajor = [int]((& $nodePath --version).TrimStart('v').Split('.')[0])
        if ($nodeMajor -ge 17 -and $env:NODE_OPTIONS -notmatch '--openssl-legacy-provider') {
            $env:NODE_OPTIONS = ($env:NODE_OPTIONS + ' --openssl-legacy-provider').Trim()
        }
        Write-Host 'Menjalankan antarmuka aplikasi. Proses awal dapat memerlukan beberapa menit...'
        Start-Process -FilePath $nodePath -ArgumentList 'node_modules/@vue/cli-service/bin/vue-cli-service.js', 'serve', '--host', 'localhost', '--port', '3000' -WorkingDirectory $frontendRoot -WindowStyle Hidden -RedirectStandardOutput (Join-Path $logRoot 'local-frontend.stdout.log') -RedirectStandardError (Join-Path $logRoot 'local-frontend.stderr.log')
    } else {
        Write-Host 'Port 3000 sudah aktif. Memeriksa aplikasi...'
    }

    $ready = $false
    $deadline = (Get-Date).AddMinutes(3)
    while ((Get-Date) -lt $deadline) {
        try {
            $page = Invoke-WebRequest -Uri $appUrl -UseBasicParsing -TimeoutSec 5
            if ($page.StatusCode -eq 200) {
                if ($page.Content -notmatch '<title>\s*ESER Costing System\s*</title>') {
                    throw 'PORT_CONFLICT'
                }
                $ready = $true
                break
            }
        } catch {
            if ($_.Exception.Message -eq 'PORT_CONFLICT') {
                throw 'Port 3000 digunakan aplikasi lain. Tutup aplikasi tersebut lalu coba lagi.'
            }
        }
        Start-Sleep -Seconds 2
    }
    if (-not $ready) { throw "Antarmuka belum siap. Periksa log pada $logRoot\local-frontend.stderr.log" }

    Write-Host "Sistem siap: $appUrl" -ForegroundColor Green
    Write-Host 'Layanan tetap berjalan setelah jendela ini ditutup.'
    if (-not $NoBrowser) { Start-Process $appUrl }
    exit 0
} catch {
    Write-Host "Gagal: $($_.Exception.Message)" -ForegroundColor Red
    exit 1
} finally {
    $launchMutex.ReleaseMutex()
    $launchMutex.Dispose()
}
