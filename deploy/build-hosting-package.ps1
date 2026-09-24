$ErrorActionPreference = 'Stop'

$projectRoot = Split-Path -Parent $PSScriptRoot
$packageRoot = Join-Path $PSScriptRoot 'hosting-2026-09-24-existing-domain'
if (Test-Path -LiteralPath $packageRoot) {
    throw "Folder output sudah ada: $packageRoot"
}

$frontendStage = Join-Path $packageRoot 'staging\frontend'
$backendStage = Join-Path $packageRoot 'staging\backend'
New-Item -ItemType Directory -Path $frontendStage -Force | Out-Null
New-Item -ItemType Directory -Path $backendStage -Force | Out-Null

Copy-Item -Path (Join-Path $projectRoot 'frontend\dist\*') -Destination $frontendStage -Recurse -Force
Copy-Item -LiteralPath (Join-Path $PSScriptRoot 'frontend\.htaccess') -Destination (Join-Path $frontendStage '.htaccess') -Force

& robocopy (Join-Path $projectRoot 'backend') $backendStage /E /XD .git tests cache debugbar logs session backups /XF .env env composer.phar phpunit.xml.dist phpunit.xml | Out-Null
if ($LASTEXITCODE -ge 8) {
    throw "Penyalinan backend gagal dengan kode $LASTEXITCODE"
}

foreach ($runtimeDir in @('cache', 'debugbar', 'logs', 'session', 'backups')) {
    $runtimePath = Join-Path $backendStage "writable\$runtimeDir"
    New-Item -ItemType Directory -Path $runtimePath -Force | Out-Null
    Set-Content -LiteralPath (Join-Path $runtimePath 'index.html') -Value '<!doctype html><title></title>' -Encoding ascii
}

$envText = @"
CI_ENVIRONMENT = production

app.baseURL = 'https://api-costing.esergeosurvey.com/'
app.forceGlobalSecureRequests = true

cookie.secure = true
cookie.samesite = 'Lax'

database.default.hostname = localhost
database.default.database = YOUR_CPANEL_DATABASE
database.default.username = YOUR_CPANEL_DATABASE_USER
database.default.password = YOUR_CPANEL_DATABASE_PASSWORD
database.default.DBDriver = MySQLi
database.default.DBPrefix =
database.default.port = 3306
"@
$envExample = Join-Path $packageRoot '04-env-production.example'
Set-Content -LiteralPath $envExample -Value $envText -Encoding ascii
Copy-Item -LiteralPath $envExample -Destination (Join-Path $backendStage '.env.example')

$sqlPath = Join-Path $packageRoot '03-database-costestimator-unified.sql'
& 'C:\xampp\mysql\bin\mysqldump.exe' -u root --single-transaction --routines --triggers --hex-blob --default-character-set=utf8mb4 --skip-add-drop-table costestimator_db --result-file=$sqlPath
if ($LASTEXITCODE -ne 0) {
    throw 'Ekspor database gagal.'
}

$guide = @'
PANDUAN HOSTING COST ESTIMATOR DI CPANEL
=========================================

Domain aplikasi : https://cost.esergeosurvey.com
Domain API       : https://api-costing.esergeosurvey.com
Versi PHP        : 8.2 atau lebih baru

A. SEBELUM MULAI
1. Backup file dan database yang saat ini ada di hosting.
2. Aktifkan SSL/HTTPS untuk kedua domain di cPanel.
3. Pastikan ekstensi PHP intl, mbstring, mysqli, json, dan fileinfo aktif.

B. DATABASE
1. Di MySQL Databases cPanel, buat database kosong dan user database.
2. Hubungkan user ke database dan beri ALL PRIVILEGES.
3. Buka phpMyAdmin, pilih database kosong tersebut, lalu Import file:
   03-database-costestimator-unified.sql
4. File SQL ini berisi data aplikasi saat paket dibuat. Jangan impor ke database
   produksi yang sudah berisi data tanpa proses penggabungan/migrasi terlebih dahulu.

C. BACKEND / API
1. Upload 02-backend-api-costing-esergeosurvey-com.zip ke folder
   /home/USERNAME/api-costing.esergeosurvey.com/
2. Extract ZIP. Pastikan app, public, system, vendor, writable, dan spark berada
   langsung di folder costestimator-api.
3. Pastikan Document Root api-costing.esergeosurvey.com mengarah ke:
   /home/USERNAME/api-costing.esergeosurvey.com/public
4. Salin .env.example menjadi .env di folder costestimator-api, lalu isi:
   - database.default.database
   - database.default.username
   - database.default.password
   Jangan mengubah tanda kutip pada app.baseURL.
5. Umumnya folder memakai permission 755. Bila aplikasi tidak dapat menulis,
   ubah folder writable beserta subfoldernya menjadi 775 sesuai aturan hosting.
6. Jangan gunakan permission 777 kecuali diwajibkan dan dipahami risikonya.

D. FRONTEND
1. Buka document root cost.esergeosurvey.com di File Manager.
2. Backup atau pindahkan file situs lama.
3. Upload dan Extract 01-frontend-cost-esergeosurvey-com.zip.
4. Pastikan index.html dan .htaccess berada langsung di document root domain,
   bukan terbungkus satu folder tambahan.

E. PENGUJIAN
1. Buka https://api-costing.esergeosurvey.com/api/ping
2. Buka https://cost.esergeosurvey.com dan lakukan login.
3. Uji Scheduler/Gantt, Basic Cost, Cost Proposal, upload dokumen,
   export Excel, dan export PDF.
4. Jika semua berfungsi, hapus ZIP dan SQL dari folder web yang dapat diakses publik.

CATATAN KEAMANAN
- Paket backend tidak memuat .env lokal atau password database lokal.
- File SQL memuat data operasional dan hash password pengguna. Simpan secara privat.
- Simpan .env hanya di root backend, jangan di folder public.
'@
Set-Content -LiteralPath (Join-Path $packageRoot 'PANDUAN-HOSTING-CPANEL.txt') -Value $guide -Encoding utf8

$manifest = @"
Cost Estimator - Paket Hosting
Dibuat: $(Get-Date -Format 'yyyy-MM-dd HH:mm:ss zzz')
Frontend: https://cost.esergeosurvey.com
API: https://api-costing.esergeosurvey.com
Database sumber: costestimator_db
Scheduler asset version: 4.9.10
"@
Set-Content -LiteralPath (Join-Path $packageRoot 'MANIFEST.txt') -Value $manifest -Encoding utf8

Compress-Archive -Path (Join-Path $frontendStage '*') -DestinationPath (Join-Path $packageRoot '01-frontend-cost-esergeosurvey-com.zip') -CompressionLevel Optimal
Compress-Archive -Path (Join-Path $backendStage '*') -DestinationPath (Join-Path $packageRoot '02-backend-api-costing-esergeosurvey-com.zip') -CompressionLevel Optimal

$checksumTargets = Get-ChildItem -LiteralPath $packageRoot -File | Where-Object Name -ne 'CHECKSUMS-SHA256.txt'
$checksumLines = foreach ($file in $checksumTargets) {
    $hash = Get-FileHash -LiteralPath $file.FullName -Algorithm SHA256
    "{0}  {1}" -f $hash.Hash.ToLowerInvariant(), $file.Name
}
Set-Content -LiteralPath (Join-Path $packageRoot 'CHECKSUMS-SHA256.txt') -Value $checksumLines -Encoding ascii

$bundleStage = Join-Path $packageRoot 'bundle'
New-Item -ItemType Directory -Path $bundleStage | Out-Null
Get-ChildItem -LiteralPath $packageRoot -File | Copy-Item -Destination $bundleStage
Compress-Archive -Path (Join-Path $bundleStage '*') -DestinationPath (Join-Path $packageRoot 'costestimator-hosting-complete-2026-09-24.zip') -CompressionLevel Optimal

Write-Output $packageRoot
