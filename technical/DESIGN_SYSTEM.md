# Design system dan layout TG-008

Dokumen ini menjelaskan implementasi TG-008 di `resources/css/app.css` dan komponen Blade bersama. Referensi HTML menjadi acuan visual; saldo, statistik, produk, transaksi, dan menu simulasi di dalamnya tidak menjadi data aplikasi. Palet mengikuti lampiran keputusan UI di `PRD.md` dan `PROMPT.md`.

Ruang lingkup saat ini adalah kerangka publik, autentikasi, area client, dan panel admin untuk fitur autentikasi yang sudah tersedia. TG-008 tidak menyatakan seluruh alur bisnis atau seluruh gate G-18/G-26 selesai.

## Token visual

Tema tetap terang melalui metadata `color-scheme` dan CSS `color-scheme: light`, termasuk ketika sistem pengguna memilih tema gelap.

| Token CSS | Nilai | Penggunaan |
| --- | --- | --- |
| `--color-forest` | `#102821` | Sidebar, hero, teks dan batas tombol utama |
| `--color-lime` | `#dcff69` | Aksi utama, navigasi aktif, aksen pada forest |
| `--color-bone` | `#f5f3eb` | Latar halaman |
| `--color-coral` | `#f77b60` | Aksen merek dan peringatan |
| `--color-paper` | `#fffdf6` | Permukaan panel, header, input |
| `--color-ink` | `#1b3029` | Teks utama |
| `--color-muted` | `#52685b` | Deskripsi, label kecil, bantuan input |
| `--color-line` | `#c7d2c5` | Pemisah dan batas panel dekoratif |
| `--color-input-line` | `#71816f` | Batas input, tombol secondary, tombol menu |
| `--color-accent-ink` | `#9a321e` | Aksen teks pada latar terang dan fokus umum |
| `--font-sans` | `Arial, Helvetica, sans-serif` | Teks antarmuka |
| `--font-mono` | `ui-monospace, SFMono-Regular, Consolas, monospace` | Kode privat |
| `--radius-panel` | `4px` | Sudut panel |

Teks memakai hierarki heading, bobot, dan jarak; warna bukan satu-satunya pembeda status. Badge menyertakan teks dan simbol. Ikon bersama berupa SVG inline dekoratif dengan `aria-hidden="true"` dan `focusable="false"`, sehingga tidak bergantung pada tersedianya glyph panah pada font sistem.

### Kontras warna

Rasio berikut dihitung dari luminansi relatif sRGB WCAG untuk warna solid, dibulatkan dua desimal. Sasaran adalah minimal 4,5:1 untuk teks biasa dan 3:1 untuk batas kontrol atau indikator fokus yang diperlukan. Nilai ini memverifikasi pasangan warna yang disebutkan, bukan sertifikasi aksesibilitas seluruh aplikasi.

| Pasangan aktual | Rasio |
| --- | ---: |
| Ink pada bone | 12,59:1 |
| Ink pada paper | 13,74:1 |
| Muted pada bone | 5,42:1 |
| Muted pada paper | 5,91:1 |
| Forest pada lime, termasuk tombol dan menu aktif | 13,77:1 |
| Paper pada forest | 15,31:1 |
| Accent ink pada bone | 6,63:1 |
| Accent ink pada paper | 7,24:1 |
| Batas input `#71816f` terhadap paper | 4,07:1 |
| Batas input `#71816f` terhadap bone | 3,73:1 |
| Label sidebar `#b0c5b7` pada forest | 8,55:1 |
| Teks tautan sidebar `#c4d3c7` pada forest | 10,01:1 |
| Fokus tautan `#b45338` terhadap bone | 4,46:1 |
| Fokus tombol autentikasi `#a64732` terhadap bone | 5,30:1 |

| Badge | Teks / latar | Rasio teks |
| --- | --- | ---: |
| Neutral | `#354e3d` / `#e8ede4` | 7,65:1 |
| Success | `#285021` / `#e3f2ce` | 7,90:1 |
| Warning | `#754b3d` / `#fff0e4` | 6,66:1 |
| Danger | `#9a321e` / `#ffe7df` | 6,23:1 |

Lime pada bone hanya 1,02:1 dan coral pada paper 2,59:1; keduanya tidak dipakai sebagai teks biasa pada pasangan latar tersebut. Aksen teks pada permukaan terang memakai warna gelap. Token line terhadap paper hanya 1,53:1 dan dipakai sebagai pemisah dekoratif, bukan sebagai batas input atau satu-satunya penanda kontrol. Tombol lime memiliki batas forest agar bentuk kontrol tetap jelas terhadap permukaan terang.

## Komponen bersama

| Komponen | Kontrak penggunaan |
| --- | --- |
| `x-layouts.app` | Dokumen berbahasa Indonesia, judul halaman, metadata tema terang, opsi `noindex`, satu pemuatan aset/Livewire, serta tautan lewati navigasi menuju `#konten`. Pemakai layout menyediakan landmark main. |
| `x-site-header` | Brand dan navigasi publik. `compact` digunakan pada layar autentikasi untuk menyederhanakan tautan. Tujuan akun mengikuti peran aktor. |
| `x-layouts.portal` | Area `member` atau `admin`, sidebar sesuai peran, header konteks, drawer mobile, serta satu `main#konten`. Halaman isi menyediakan h1 sendiri. |
| `x-brand` | Tautan beranda dengan identitas Trader Garuda; opsi `inverse` untuk sidebar forest. |
| `x-ui.button` | Varian `secondary` (default), `lime`, dan `quiet`. `href` menghasilkan anchor; tanpa `href` menghasilkan button dengan tipe default `button`. Tipe submit harus dinyatakan pada form. |
| `x-ui.panel` | Permukaan paper dengan judul h2, eyebrow, slot isi, dan slot actions opsional. |
| `x-ui.badge` | Tone `neutral`, `success`, `warning`, atau `danger`; label tekstual tetap wajib bermakna. |
| `x-ui.empty-state` | Judul h3, ikon dekoratif, penjelasan, dan slot actions opsional. Aksi harus menuju fitur yang tersedia. |
| `x-ui.icon` | Ikon `menu`, `close`, `arrow-right`, `check`, serta panah diagonal default. Nama aksesibel berasal dari teks/label kontrol induk. |
| `x-auth-field` | Label yang terkait dengan input, bantuan dan pesan validasi terkait melalui atribut ARIA; dipakai kembali oleh form autentikasi. |

Input autentikasi memiliki tinggi minimal 48px. Tombol utama, brand, menu, dan tautan navigasi memakai target minimal 44px. Input, panel, dan kolom layout memakai lebar yang dapat menyusut; nama, email, dan kode panjang dapat membungkus tanpa memperlebar halaman.

## Perilaku responsif

Breakpoint navigasi berada pada **1024px**. CSS dan `matchMedia('(min-width: 1024px)')` menggunakan batas yang sama.

| Lebar | Perilaku |
| --- | --- |
| Di bawah 1024px, termasuk 360 dan 768 | Tombol Menu tampil. Navigasi publik terbuka sebagai daftar dalam alur header. Sidebar portal menjadi drawer fixed selebar `min(288px, 100% - 48px)` dengan backdrop. Grid panel dan layout autentikasi menjadi satu kolom. |
| Mulai 1024px, termasuk 1280 | Navigasi publik terlihat tanpa tombol Menu. Portal menggunakan sidebar 248px yang sticky dan dapat digulir, dengan tinggi `100dvh`. Grid panel dapat memakai dua kolom. |
| Di bawah 480px | Padding portal mengecil, intro dan header panel bertumpuk, aksi portal melebar, serta daftar recovery code menjadi satu kolom. |

Kontainer publik dibatasi 1200px dan konten portal 1440px. Form portal dibatasi 680px. Pemisahan kolom tidak boleh memaksa overflow horizontal pada viewport acceptance 360/768/1280. Preferensi `prefers-reduced-motion` mengurangi animasi/transisi dan mematikan smooth scrolling.

## Keyboard dan semantik navigasi

- Tautan “Langsung ke konten” menjadi terlihat saat fokus dan menuju satu `main#konten[tabindex="-1"]`. Anchor bagian publik `#produk` dan `#alur` juga dapat menerima fokus.
- Tombol Menu menggunakan `aria-controls` ke navigasi yang benar dan `aria-expanded` sesuai keadaan. Navigasi tertutup memakai `display: none`, sehingga tautannya tidak ikut urutan Tab.
- Saat menu dibuka, fokus dipindah ke tautan atau tombol pertama di navigasi setelah pembaruan Alpine. Escape menutup menu dan mengembalikan fokus ke tombol Menu; tombol tutup dan backdrop melakukan penutupan yang sama.
- Drawer portal membuat `.portal-workspace` menjadi `inert` ketika terbuka. Tab pada elemen terakhir berputar ke elemen pertama, dan Shift+Tab pada elemen pertama berputar ke elemen terakhir. Menu publik tetap berada dalam alur dokumen dan tidak memakai perangkap fokus drawer.
- Mengikuti tautan menu menutup menu. Berpindah ke ukuran desktop menghapus keadaan menu mobile terbuka. Listener media query dan keyboard dibersihkan ketika komponen Alpine dihancurkan.
- Tautan halaman aktif menggunakan `aria-current="page"`. Sidebar memakai warna lime sekaligus keadaan ARIA; navigasi publik menambahkan garis bawah.
- Fokus terlihat menggunakan outline; pada sidebar forest, outline memakai lime. Kontrol keluar adalah form POST dengan CSRF, bukan tautan GET.
- Penangan Escape pada portal mengabaikan menu yang sudah tertutup agar tidak mengambil alih fokus dari dialog reset client yang sudah ada.

Factory `tgNavigation()` didefinisikan sebelum Alpine dimulai agar interaksi tidak bergantung pada urutan unduh modul. Fallback `no-js` menampilkan navigasi tanpa tombol menu apabila JavaScript dinonaktifkan.

## Rute dan batas akses saat ini

Menu hanya menampilkan tujuan yang sudah diimplementasikan di `routes/web.php`.

| Konteks | Tujuan navigasi |
| --- | --- |
| Publik | Beranda `/`, bagian `/#produk` dan `/#alur`; guest dapat menuju `/login` dan `/register`. Pengguna terautentikasi mendapat tautan Akun saya sesuai peran. |
| Client | Ringkasan `/member`, Profil & kata sandi `/member/profil`, serta kembali ke beranda. Halaman profil saat ini menjalankan form ganti kata sandi. `/password/change` tersedia untuk alur ganti kata sandi. |
| Admin terverifikasi | Ringkasan `/admin`, Client `/admin/client`, Verifikasi ulang `/admin/reauth`, Ganti kata sandi `/password/change`, serta kembali ke beranda. |
| Keluar | `POST /logout` dengan CSRF, tersedia pada portal dan header autentikasi untuk aktor yang sudah masuk. |

Layar `/admin/2fa/setup` dan `/admin/2fa/challenge` tetap memakai layout autentikasi; admin yang belum lolos faktor kedua tidak mendapat portal admin. Aktor yang wajib mengganti password juga tetap berada pada layar autentikasi wajib. Pemilihan layout tidak menggantikan middleware, Gate, policy, atau pemeriksaan ulang pada aksi Livewire.

Halaman hasil `/admin/2fa/recovery` dan `/admin/client/password-result` adalah respons privat sekali tampil, bukan tujuan menu tetap. Keduanya mempertahankan pembacaan session sekali pakai, otorisasi yang berlaku, serta header privat. Layout privat memakai `noindex`; middleware respons privat tetap mengirim `no-store`, `private`, dan `X-Robots-Tag`. Password/OTP tidak ditambahkan sebagai state baru untuk kebutuhan layout. Screenshot browser tidak mengambil layar setup, challenge, recovery, atau hasil password sementara.

Tidak ada menu produk, pesanan, lisensi, IB, rebate, atau pencairan yang menuju fitur belum tersedia. Fitur tersebut mengikuti task bisnis masing-masing.

## Data kosong dan status yang jujur

Beranda dan dashboard menjelaskan bahwa pemesanan serta layanan transaksi belum tersedia. Dashboard menampilkan nama dan status aktor dari akun aktual; tidak menampilkan saldo, jumlah transaksi, profit, testimoni, atau angka simulasi dari demo HTML. Katalog yang belum memiliki produk ditampilkan sebagai empty state dengan penjelasan yang sesuai.

Tombol pada empty state hanya membuka informasi produk yang sudah ada atau pengelolaan kata sandi. Badge akses admin menyatakan hasil autentikasi, bukan kesiapan transaksi. Pesan loading yang sudah ada mengikuti request Livewire dan tidak memakai persentase buatan.

## Bukti dan pekerjaan lanjutan

`tests/Feature/LayoutTest.php` memeriksa struktur dokumen, landmark, navigasi aktif dan daftar tujuan yang tersedia, POST logout dengan CSRF, header privat, batas client/admin, faktor kedua, forced password change, serta akun nonaktif. Ini melengkapi suite autentikasi yang sudah ada; pengujian markup server tidak membuktikan perilaku browser.

`tests/Browser/layout.cjs` memakai Chromium dan autentikasi melalui UI untuk viewport 360/768/1280 dengan preferensi sistem gelap. Fixture di `tests/Browser/seed.php` hanya boleh dijalankan pada lingkungan `e2e` dengan database MySQL 8.4 `tg_test` yang diverifikasi sebelum migrasi. Fixture seluruhnya sintetis. Artefak lokal berada di `/workspace/.tg-cache/tg008-browser/` dan tidak menjadi data aplikasi.

**Browser final PASS: 139 pemeriksaan, 32 screenshot, tanpa error JavaScript.** Bukti dan batas verifikasi tersedia di [`validation/TG008.md`](validation/TG008.md).

TG-009 tetap mencakup sistem modal/loading/error/toast bersama, status transaksi belum pasti, tombol Cek Status ketika timeout, dan kontrak interaksi kritis. Dialog reset autentikasi yang sudah ada tetap dipertahankan; keberadaannya tidak menandai TG-009 selesai. Audit aksesibilitas menyeluruh, seluruh alur bisnis, QA lintas perangkat/browser, serta gate G-18/G-26 secara penuh masih mengikuti task dan acceptance berikutnya. Review layout TG-008 bukan izin go-live.
