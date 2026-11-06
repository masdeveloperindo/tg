# UAT owner — Fase 5

Status: **BELUM DIJALANKAN / BELUM DISETUJUI OWNER**. Checklist ini bukan hasil persetujuan. Gunakan lingkungan staging yang disediakan infra, data akun/bukti transfer sintetis dan akun MT5 test; jangan transfer atau membuka transaksi nyata selama UAT. Catat commit kandidat dan URL staging tanpa token/secret. Bukti terminal menunggu source EA dan MT5 sesuai penundaan owner.

| Pemeriksaan oleh owner/QA | Expected | Hasil/bukti/penerima |
| --- | --- | --- |
| Register/login/reset client; login admin + TOTP; role silang | Session dan policy benar; client tidak dapat panel admin/data orang lain | PENDING |
| Produk, harga, minimum, kalender expiry dan renewal | Nilai sesuai keputusan bisnis resmi; preview checkout sesuai snapshot server | PENDING |
| Checkout dan Telegram pribadi | Pesan merujuk order benar; tidak ada bot/auto-konfirmasi | PENDING |
| Konfirmasi transfer simulasi di admin; ulangi aksi | Satu paid order/lisensi; retry membaca hasil yang sama | PENDING |
| Unduh EX5 dengan client pemilik dan client lain | Pemilik berhak; pihak lain/expired ditolak; checksum cocok | PENDING |
| Suspend/revoke/pergantian akun/refund simulasi | State dan audit konsisten, persetujuan manual; refund bukan transfer otomatis | PENDING |
| Broker/IB, penolakan/approval, input/koreksi rebate | Referral host disetujui; ledger append-only; saldo cocok client/admin | PENDING |
| Pengajuan/pemrosesan/pembayaran/reject/cancel pencairan | Reservasi/paid benar, bukti manual, retry tidak bayar dua kali | PENDING |
| Modal, validasi, upload, koneksi gagal dan timeout | Feedback jujur; status belum pasti tidak mengulang mutasi | PENDING |
| Mobile360/tablet768/desktop1280 dan keyboard | Tidak overflow; navigasi/modal/fokus dapat dipakai | PENDING |
| Edukasi/panduan/FAQ, audience pengumuman dan testimoni | Publik/client terpisah; hanya materi nyata dengan hak/consent | PENDING |
| Nama, Telegram, rekening, produk, broker, minimum dan kurs resmi | Diverifikasi owner; secret tidak dicantumkan pada laporan | PENDING |
| About/contact/privacy/terms/disclaimer dan perjanjian broker | Disetujui owner/legal; tidak ada fixture terpublikasi | PENDING |
| EA MT5 valid/invalid/replay/mismatch/revoke/expiry/offline | Bukti terminal asli sesuai API_CONTRACT; indikator tetap ditutup sampai PoC | DEFERRED EA |
| Handoff manifest/checksum, env, migration, scheduler, health, rollback | Penerima infra mencatat hasil instalasi/smoke dan menerima paket | PENDING |

Isi setelah pelaksanaan: tanggal, versi/commit, lingkungan, reviewer/owner, bukti tiap baris, defect dan keputusan ACCEPT/REJECT. Jangan menulis password, OTP, nomor rekening lengkap, lisensi atau private key pada laporan publik. Persetujuan software tidak otomatis merupakan izin go-live; gunakan SOFTWARE_ACCEPTANCE.md dan PRODUCTION_GATE.md.
