# ACTION_AUTH_MATRIX.md — Action Livewire, Policy dan State Machine

**Daftar URL GET terpisah di `ROUTES_MATRIX.csv` (71 route/endpoint dari PRD).** Dokumen ini menetapkan **aksi mutasi** yang tidak perlu menjadi halaman atau endpoint publik tambahan. Semua aksi di bawah ada di Livewire component/service, memakai session+CSRF, Laravel authorization **di action**, request validation, status dan transaksi yang ketat, audit. Tombol tersembunyi bukan otorisasi.

## 1. Matriks aksi

| ID | Komponen / action | Role / Policy | Validasi dan status sebelum → sesudah | Database/idempotency | Modal & feedback | Tes |
|---|---|---|---|---|---|---|
| A01 | Checkout `createOrder` | Client login; product published/sale_enabled | MT5 akun+server+real/demo; price snapshot; idempotency token; new order waiting | `orders UNIQUE(user_id,idempotency_key)`; transaksi | Loading tombol; setelah commit ke detail order; tidak create dua kali | T-ORD-01 |
| A02 | AdminOrder `confirmPayment` | Admin+TOTP/re-auth; `OrderPolicy.confirm` | `waiting` & `now<expires_at` → `paid`; bukti dana dicek, reference/note wajib | lock order; create/renew license atomik; return existing saat retry | Modal kode/nominal/akun, checkbox dana sudah masuk; loading asli; hasil dari DB | T-ORD-02 |
| A03 | AdminOrder `rejectOrder` | Admin; policy reject | `waiting` → `rejected`; alasan wajib | lock order, audit | Modal alasan dan confirm | T-ORD-03 |
| A04 | AdminRefund `recordRefund` | Admin+TOTP/re-auth | order paid; refund initiated → completed hanya setelah transfer manual | row lock; `refunds` idempotency per order | Modal nominal/metode/bukti; modal hasil | T-REF-01 |
| A05 | AdminLicense `suspend/revoke/reactivate` | Admin+TOTP/re-auth | active ↔ suspended; active/suspended → revoked terminal (tidak reaktivasi revoked tanpa prosedur baru) | lock license; audit, invalidate cache | Modal alasan dan dampak offline grace | T-LIC-01 |
| A06 | ClientLicense `requestAccountChange` | Pemilik license | account baru format MT5; pending request, bukan langsung mengubah license | enforce one pending per license transaction; audit | Modal ringkas | T-LIC-02 |
| A07 | AdminLicense `decideAccountChange` | Admin+reauth | pending → approved/rejected; approve update triple setelah cek uniqueness | lock license+request; check conflicting unique; audit | Modal account before/after | T-LIC-03 |
| A08 | AdminIb `saveBroker` | Admin | nama, https referral allowlisted, rate+date valid | CRUD transactional, audit | Form inline+toast; hapus jika bereferensi historis → disable | T-IB-01 |
| A09 | ClientIb `attachAccount` | Client login | broker aktif, akun unik dan bukti; create pending | unique broker+account; audit | Tombol loading | T-IB-02 |
| A10 | AdminIb `approve/reject` | Admin | pending → approved/rejected; alasan bukti bila reject | lock account; audit | Modal broker, akun, hasil pemeriksaan | T-IB-03 |
| A11 | AdminRebate `recordEntry` | Admin+reauth untuk koreksi negatif besar | akun IB approved; period YYYY-MM; lot >=0; USD signed; unique reference | lock wallet; insert immutable; validate available >=0; audit | Modal ringkasan+loading; sukses tampil di client setelah commit | T-REB-01 |
| A12 | AdminRebate `correctEntry` | Admin+reauth | entri asal tetap; koreksi append row signed dengan correction_of_id | lock wallet; unique new reference; reconciliation | Modal nominal delta dan alasan; no update/delete | T-REB-02 |
| A13 | ClientPayout `requestWithdrawal` | Pemilik wallet; aktif IB | amount > minimum, <=available; snapshot kurs, tujuan dienkripsi; status requested | lock wallet; idempotency; reserved from amount; audit | Modal USD/IDR/tujuan, loading, cek-status timeout | T-PAY-01 |
| A14 | AdminPayout `markProcessing` | Admin | requested → processing | lock wallet+withdrawal; audit | Modal ringkas, update status | T-PAY-02 |
| A15 | AdminPayout `markPaid` | Admin+TOTP/re-auth | requested/processing → paid; bank ref + waktu transfer nyata wajib | lock wallet+withdrawal; idempotent; paid once | Modal rekening masked + jumlah + bukti; status server | T-PAY-03 |
| A16 | AdminPayout `reject` | Admin+TOTP/re-auth | requested/processing → rejected; alasan wajib; release reserve once | lock wallet+withdrawal; audit | Modal alasan; saldo client update sesudah commit | T-PAY-04 |
| A17 | ClientPayout `cancel` | Pemilik; requested only | requested → cancelled, release reserve once | lock wallet+withdrawal; audit | Modal konfirmasi sederhana | T-PAY-05 |
| A18 | AdminContent `publish/disable` | Admin | produk/FAQ/panduan/testimoni/pengumuman valid; testimoni wajib consent | audit; sanitasi; no false profit | confirm untuk publish publik dan hapus; inline upload percent | T-CMS-01 |
| A19 | AdminSettings `update` | Admin+TOTP/re-auth sensitif | Telegram username format, kurs/min payout/harga valid; tidak ada bot token | settings encrypted+audit | Modal saat ubah konfigurasi finansial | T-SET-01 |
| A20 | AdminUser `resetPassword/disable` | Admin+TOTP/re-auth | verifikasi identitas manual; revoke sesi, must_change_password=1; disabled user tidak login | audit, rate limit | Modal data client masked dan alasan | T-AUTH-01 |
| A21 | AdminAnomaly `review/ignore` | Admin | new → reviewed/ignored; catatan wajib | row lock, audit | Modal alasan, status terlihat | T-ANM-01 |
| A22 | Member `downloadVersion` | `LicensePolicy.download` pemilik | lisensi active, belum expired, version milik produk | signed URL max 5 min + recheck policy di GET | Spinner/progress browser; error jelas | T-FILE-01 |
| A23 | AdminAsset `uploadImage/uploadEx5` | Admin | type/size/path, random name, checksum, anti-XSS; EX5 disk privat | file atomic finalization + DB version record; audit | Upload % nyata dari Livewire upload event, lalu status server tanpa persen palsu | T-UP-01 |

**Aksi `confirmPayment` adalah satu-satunya pemicu pesanan menjadi paid, tidak ada tombol Telegram yang memanggil sistem.** Chat hanya komunikasi manual. Rebate/withdrawal tidak pernah berubah hanya karena mengirim pesan Telegram.

## 2. State machine resmi

**Order:** `waiting → paid | rejected | expired`. `paid`, `rejected`, `expired` terminal untuk order itu. `expired` boleh diputuskan langsung oleh action `now >= expires_at`, tidak wajib menunggu cron. Permintaan client dapat membuat order baru dengan idempotency token baru. Refund tidak mengubah status paid historis; status refund terpisah.

**Lisensi:** `active ↔ suspended`; `active/suspended → revoked` terminal. Status `expired` adalah *derived* dari `expires_at`, dan dapat aktif lagi setelah renewal yang sah bila tidak revoked. Perpanjangan revoked harus lewat keputusan khusus admin + audit, default **ditolak**.

**IB Account:** `pending → approved | rejected`. Perbaikan data yang ditolak membuat pengajuan baru dengan kontrol uniqueness, jangan mengubah audit lama.

**Withdrawals:** `requested → processing → paid` atau `requested/processing → rejected`; `requested → cancelled` oleh client. `paid/rejected/cancelled` terminal dan tidak dapat dikonversi balik dengan klik berulang.

**Refund:** `initiated → completed | rejected`; uang dianggap sudah dikembalikan hanya setelah transfer manual + bukti/ref. Refund tidak menghapus histori order, rebate, atau license.

## 3. Pola service dan concurrency

- Semua aksi Livewire memanggil service/use-case dengan DTO tervalidasi dan Laravel Policy; semua pembacaan ownership diwajibkan pada query server.
- Mutasi uang memakai `wallets` lock **terlebih dahulu** sebelum menghitung saldo; penggunaan lock order harus konsisten untuk mencegah deadlock: `wallets → withdrawals/rebate_entries`, sedangkan pembayaran order `orders → licenses`.
- Idempotency key dari form disimpan per intent; client tidak boleh mengenerate token baru saat retry sebuah intent yang statusnya belum pasti.
- Constraint DB mencegah duplicate even if two PHP workers execute concurrently. Tangani deadlock retry terbatas dengan kejelasan status, jangan ulang transfer eksternal.
- `status unknown` pada UI mengarahkan ke `Cek Status` / refresh data sumber. Jangan mengubah angka di frontend sebelum response committed.
- Tindakan yang gagal dicatat `request_id`, error status, tanpa stack trace dan tanpa secrets terlihat client.

## 4. Kontrol UX wajib

- Semua tindakan berbahaya modal khusus; tombol `Batal/Konfirmasi`; `aria-modal`, focus trap, ESC hanya bila tidak dalam proses yang statusnya belum pasti.
- Loading progres navigasi disediakan oleh Livewire; upload byte percent via event `livewire-upload-progress`; proses backend tanpa percent menampilkan spinner/status teks.
- Error validasi field tepat di bawah input; error aplikasi/timeout modal ringkas; sukses kecil toast; status saldo selalu diambil dari server sesudah selesai.
- Client tidak perlu memahami audit, idempotency, nonce, atau ledger. UI cukup menunjukkan aksi jelas.

## 5. Definition of Done

Aksi A01–A23 diuji dengan 403/404 untuk role salah, 422 untuk input invalid, retry/timeout, dua request paralel (yang relevan), dan hasil ledger/status yang konsisten. Setiap action punya screenshot/hasil test yang dapat diaudit di task terkait, tetapi **HTML demo tidak menjadi bukti aplikasi production siap**.

## Realisasi TG-021/TG-022

A04 memakai `ManageRefund`: order paid, initiated reservasi, completed hanya transfer manual terverifikasi+referensi, rejected membebaskan reservasi. Sum initiated/completed dibatasi amount paid dalam lock order; retry terminal tidak menjalankan transfer/mutasi baru. Revoke terpisah.

A06/A07 memakai `ChangeAccount`: owner aktif mengajukan akun+server, satu pending di bawah lock license; admin reauth memutuskan di modal. Identity hash saat request harus masih cocok, triple unik, satu approved per tahun kalender layanan WIB (PRD LIC-07). Audit period_year tetap tahun saat approval, sehingga renewal sebelum Januari tidak mengulang kuota. Key dan paid order historis tidak ditulis ulang. Test matrix dan bukti: `validation/TG019-TG022.md`.

## Realisasi Fase 3

A08–A17 memakai service di App/Actions/Rebates; mutasi admin memerlukan reauth (lebih ketat daripada minimum pada aksi nonfinansial). A09 meminta akun serta referensi bukti broker; unique broker/account dan row lock menjaga satu klaim, keputusan terminal dengan note terpisah. A11 menerima kredit positif, A12 menerima target akhir nonnegative lalu membuat reversal+replacement; referensi unik dan row lama immutable. Seluruh mutasi uang mengunci wallet dahulu, termasuk current reads untuk dedup/saldo.

A13 memakai quote authenticated/encrypted5menit dengan snapshot owner/FX/minimum/IDR/tujuan/intent; request ulang mengembalikan row lama. A14 menahan reserve; A15 membutuhkan checkbox transfer+reference+tanggalWIB, pay-once; UI menawarkan pay setelah processing. A16/A17 melepas reserve hanya pada transisi sah, alasan reject wajib, cancel hanya requested. Browser unknown-response memakai GET, bukan mengulang intent baru. Validasi lengkap: validation/TG023-TG030.md.
