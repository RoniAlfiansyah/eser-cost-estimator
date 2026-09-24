# Alur Git dan Deployment

Repository ini menyimpan source code Cost Estimator. Repository harus privat karena aplikasi merupakan sistem internal.

## Data yang tidak masuk Git

- `backend/.env` dan kredensial hosting
- dump database SQL
- file upload pengguna
- session, log, cache, dan backup runtime
- ZIP hasil deployment
- `frontend/node_modules`, `frontend/dist`, dan `backend/vendor`

## Alur perubahan

1. Kerjakan dan uji perubahan di lokal.
2. Commit perubahan ke branch kerja.
3. Gabungkan perubahan yang sudah diuji ke branch `main`.
4. Buat build produksi dan paket deployment.
5. Deploy ke cPanel dengan script atau release yang sudah diverifikasi.

File `.env` hosting dan database produksi tetap dikelola di server dan tidak ditimpa saat deployment.
