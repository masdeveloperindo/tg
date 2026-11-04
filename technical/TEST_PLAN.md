# TEST_PLAN.md — Kriteria Uji Wajib Trader Garuda v1.7

Status seluruh kasus **NOT RUN** sampai aplikasi Laravel, database, dan EA nyata tersedia. HTML demo yang bisa diklik tidak membuktikan kelayakan produksi.

| ID | Skenario | Hasil yang wajib | Prioritas |
|---|---|---|---|
| T-ORD-01 | Submit checkout dua kali dengan intent sama | 1 order, harga snapshot konsisten | P0 |
| T-ORD-02 | Dua admin action konfirmasi bersamaan; timeout setelah commit | status paid, 1 license/key, retry hanya baca hasil | P0 |
| T-ORD-03 | Konfirmasi order expired/rejected | tidak dapat paid | P0 |
| T-REF-01 | Refund manual tercatat dan revoke terpisah | tidak dianggap refund sampai transfer sudah terjadi | P0 |
| T-LIC-01 | Suspended/revoked/expired secara API | tidak valid; offline grace terdokumentasi | P0 |
| T-LIC-02 | Client mengajukan pergantian akun | pending belum mengubah identitas lisensi | P0 |
| T-LIC-03 | Dua perubahan MT5 akun berbenturan | unique constraint menolak konflik, audit ada | P0 |
| T-IB-01 | Broker redirect disuntik URL non-https | ditolak, tidak open redirect | P0 |
| T-IB-02 | Client B mencoba tautkan akun broker yang sudah dipakai client A | ditolak tanpa bocor data A | P0 |
| T-IB-03 | Admin approve akun IB | client melihat status approved; audit ada | P0 |
| T-REB-01 | Dua input rebate dengan source_reference sama | satu entri ledger, tidak dua kali kredit | P0 |
| T-REB-02 | Koreksi rebate | entri baru signed, histori tidak diedit, saldo konsisten | P0 |
| T-PAY-01 | Dua pengajuan penarikan paralel melebihi available | hanya satu diterima; available tidak negatif | P0 |
| T-PAY-02 | Admin start processing | `requested → processing` | P0 |
| T-PAY-03 | Klik Bayar dua kali / timeout | `paid` satu kali dengan transfer ref/bukti, saldo tidak turun dua kali | P0 |
| T-PAY-04 | Menolak penarikan dua kali | dana reservasi dilepas hanya sekali | P0 |
| T-PAY-05 | Client cancel setelah diproses/paid | ditolak | P0 |
| T-CMS-01 | Publish konten berbahaya/testimoni tanpa izin | blocked, sanitized, audit | P1 |
| T-SET-01 | Ubah Telegram/harga/kurs tanpa reauth | ditolak; setelah reauth & validasi sukses | P0 |
| T-AUTH-01 | Reset manual oleh admin; session lama | session lama invalid dan wajib ganti password | P0 |
| T-ANM-01 | Banyak IP / mismatch | flag dedup, tanpa autosuspend default | P1 |
| T-FILE-01 | Client A download EX5 milik client B/expired | 403/404, file tidak bocor | P0 |
| T-UP-01 | EX5/malicious file/celikan path | file non-allowed ditolak, upload progress berasal event nyata | P0 |
| T-API-01 | Ed25519 signature PHP→pure MQL5, fixture asli | valid lolos, 1 byte berubah gagal | P0 |
| T-API-02 | Replay nonce, request timestamp out of window | tidak menerima replay, ledger logs aman | P0 |
| T-API-03 | EA salah login/server/type, revoked, expired | verifikasi ditolak oleh terminal MT5 nyata | P0 |
| T-API-04 | Timeout/502/DNS/MT5 tester | perilaku grace sesuai kontrak; indicator disabled | P0 |
| T-AUTH-02 | Client A baca/ubah resource milik client B melalui forged Livewire action | 403/404 | P0 |
| T-DEP-01 | Fresh clone/migrate/test/build/route:list | semuanya sukses pada staging | P0 |
| T-DEP-02 | Verifikasi konsistensi aplikasi pada snapshot/restore yang **disediakan oleh infra** | Query integritas saldo, lisensi dan checksum file lulus; pelaksanaan backup/restore fisik bukan task software | P0 — dependensi infra |
| T-UI-01 | 360/768/1280px; keyboard modal; loading tanpa fake percent | layout tidak pecah, focus terjaga, status akurat | P0 |
| T-LEG-01 | Perjanjian legal IB, disclaimer, testimoni berizin | disetujui owner/legal | P0 |

## Bukti minimal tiap test

- Commit/build versi; lingkungan dan tanggal pengujian; data dummy yang digunakan; langkah reproduksi; expected/actual; PASS/FAIL; screenshot/log bersih dari secrets; peninjau.
- P0 kegagalan = status rilis `BLOCKED`. P1 boleh ditunda hanya jika benar-benar tidak termasuk fungsi MVP dan keputusan penundaan tercatat, bukan diam-diam dihilangkan.
- Test paralel wajib menggunakan dua transaksi/koneksi database sungguhan, bukan sekadar dua pemanggilan berurutan. Bukti API signature benar-benar diverifikasi dalam terminal EA.
- Tugas TG-038–TG-044 tidak boleh DONE hanya berdasarkan prototype HTML statis.


## Batas pelaksanaan QA

Tim software menjalankan tes unit, feature, integrasi Laravel, browser E2E, keamanan level aplikasi, concurreny DB dan PoC EA. Pengujian pada staging nyata dilakukan setelah lingkungan **disediakan pihak infra**. Uji TLS/DNS, backup/restore fisik, kapasitas server, cron host dan deployment dilakukan pihak infra; software menyediakan test query, health endpoint dan laporan hasil agar dapat diverifikasi bersama.
