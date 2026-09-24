# Database Setup

Database aplikasi untuk user management sudah disiapkan dengan nama `costestimator_db`.

## Konfigurasi aktif

- Host: `localhost`
- Port: `3306`
- Database: `costestimator_db`
- Username: `root`
- Password: kosong
- Driver: `MySQLi`

Konfigurasi ini juga sudah tersimpan di [.env](/C:/xampp82/htdocs/costestimator/.env).

## Tabel awal

- `users`
- `migrations`

## Akun demo

Sebelum menjalankan `UserSeeder`, isi `SEED_DEFAULT_PASSWORD` di `.env` dengan
password sementara minimal 12 karakter. Ganti password setiap akun setelah login.

## Opsi setup ulang

Pakai migration dan seeder:

```powershell
C:\xampp82\php\php.exe spark migrate
C:\xampp82\php\php.exe spark db:seed DatabaseSeeder
```

Atau impor file SQL siap pakai:

```powershell
C:\xampp82\mysql\bin\mysql.exe -u root < database\setup_user_management.sql
```
