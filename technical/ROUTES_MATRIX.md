# ROUTES_MATRIX.md — 71 URL/endpoint PRD v1.7

Semua URL menggunakan base `APP_URL`, tidak ada Telegram webhook. Route name dan handler adalah **target convention**; implementasi Laravel (`php artisan route:list`) harus memverifikasi kesesuaian. Aksi Livewire mutasi tidak membuat route baru di tabel ini.

| Grup | Method | Path | Akses | SEO |
|---|---|---|---|---|
| public | GET | `/` | public_or_guest | index |
| public | GET | `/produk` | public_or_guest | index |
| public | GET | `/produk/{slug}` | public_or_guest | index |
| public | GET | `/broker` | public_or_guest | index |
| public | GET | `/edukasi` | public_or_guest | index |
| public | GET | `/edukasi/kategori/{kategori}` | public_or_guest | index |
| public | GET | `/edukasi/{slug}` | public_or_guest | dynamic_visibility |
| public | GET | `/faq` | public_or_guest | index |
| public | GET | `/panduan` | public_or_guest | index |
| public | GET | `/panduan/{slug}` | public_or_guest | index |
| public | GET | `/tentang` | public_or_guest | index |
| public | GET | `/kontak` | public_or_guest | index |
| public | GET | `/kebijakan-privasi` | public_or_guest | index |
| public | GET | `/syarat-layanan` | public_or_guest | index |
| public | GET | `/disclaimer-risiko` | public_or_guest | index |
| public | GET | `/login` | public_or_guest | noindex |
| public | GET | `/register` | public_or_guest | noindex |
| public | GET | `/checkout/{slug}` | client_or_owner | noindex |
| public | GET | `/pesanan/{kode}` | client_or_owner | noindex |
| public | GET | `/go/telegram` | public_or_guest | noindex |
| public | GET | `/go/broker/{id}` | public_or_guest | noindex |
| member | GET | `/member` | client_or_owner | noindex |
| member | GET | `/member/produk` | client_or_owner | noindex |
| member | GET | `/member/produk/{licenseId}` | client_or_owner | noindex |
| member | GET | `/member/unduhan` | client_or_owner | noindex |
| member | GET | `/member/pesanan` | client_or_owner | noindex |
| member | GET | `/member/rebate` | client_or_owner | noindex |
| member | GET | `/member/rebate/akun` | client_or_owner | noindex |
| member | GET | `/member/penarikan` | client_or_owner | noindex |
| member | GET | `/member/profil` | client_or_owner | noindex |
| admin | GET | `/admin` | admin | noindex |
| admin | GET | `/admin/produk` | admin | noindex |
| admin | GET | `/admin/produk/create` | admin | noindex |
| admin | GET | `/admin/produk/{id}/edit` | admin | noindex |
| admin | GET | `/admin/pesanan` | admin | noindex |
| admin | GET | `/admin/pesanan/{id}` | admin | noindex |
| admin | GET | `/admin/lisensi` | admin | noindex |
| admin | GET | `/admin/lisensi/{id}` | admin | noindex |
| admin | GET | `/admin/anomali` | admin | noindex |
| admin | GET | `/admin/anomali/{id}` | admin | noindex |
| admin | GET | `/admin/client` | admin | noindex |
| admin | GET | `/admin/broker` | admin | noindex |
| admin | GET | `/admin/akun-trading` | admin | noindex |
| admin | GET | `/admin/rebate` | admin | noindex |
| admin | GET | `/admin/penarikan` | admin | noindex |
| admin | GET | `/admin/penarikan/{id}` | admin | noindex |
| admin | GET | `/admin/edukasi` | admin | noindex |
| admin | GET | `/admin/edukasi/create` | admin | noindex |
| admin | GET | `/admin/edukasi/{id}/edit` | admin | noindex |
| admin | GET | `/admin/faq` | admin | noindex |
| admin | GET | `/admin/faq/create` | admin | noindex |
| admin | GET | `/admin/faq/{id}/edit` | admin | noindex |
| admin | GET | `/admin/panduan` | admin | noindex |
| admin | GET | `/admin/panduan/create` | admin | noindex |
| admin | GET | `/admin/panduan/{id}/edit` | admin | noindex |
| admin | GET | `/admin/testimoni` | admin | noindex |
| admin | GET | `/admin/testimoni/create` | admin | noindex |
| admin | GET | `/admin/testimoni/{id}/edit` | admin | noindex |
| admin | GET | `/admin/halaman` | admin | noindex |
| admin | GET | `/admin/pengumuman` | admin | noindex |
| admin | GET | `/admin/pengumuman/create` | admin | noindex |
| admin | GET | `/admin/pengumuman/{id}/edit` | admin | noindex |
| admin | GET | `/admin/pengaturan` | admin | noindex |
| admin | GET | `/admin/log` | admin | noindex |
| system | GET | `/sitemap.xml` | public_or_guest | crawler |
| system | GET | `/robots.txt` | public_or_guest | crawler |
| system | GET | `/member/unduh/{versionId}` | client_or_owner | noindex |
| system | GET | `/health` | internal | noindex |
| system | POST | `/logout` | auth | noindex |
| system | POST | `/api/v1/licenses/verify` | mt5_client_stateless | noindex |
| system | POST | `/api/v1/licenses/deactivate` | mt5_client_stateless | noindex |

## Keamanan

- Semua route member/admin wajib middleware auth; query data milik user wajib policy model.
- Logout hanya POST+CSRF. Unduh EX5 signed URL wajib dicek ulang kepemilikan.
- Redirect broker/Telegram memakai konfigurasi tervalidasi, bukan redirect URL dari user.
- Endpoint MT5 tidak memakai sesi web; punya nonce/rate-limit/signature.
- `/api/v1/licenses/deactivate` default tidak aktif; `/health` minimal dan terlindungi.

Lihat `ROUTES_MATRIX.csv` untuk method/handler/policy yang bisa diimpor ke backlog developer.
