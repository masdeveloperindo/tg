# DEPLOYMENT.md — Pengalihan Tanggung Jawab (v1.7.1)

**Tim ini hanya mengembangkan software.** Infrastruktur dan deployment fisik bukan lingkup pekerjaan. Instruksi lama yang meminta developer mengonfigurasi DNS, Nginx, TLS, cron host, backup/restore atau server **tidak berlaku**.

- Untuk artefak dan langkah yang **diserahkan ke infra**: lihat `RELEASE_HANDOFF.md`.
- Untuk kontrak environment dan dependensi eksternal: `technical/ENVIRONMENT.md` dan `.env.example`.
- Untuk batas tanggung jawab: `SCOPE_OF_WORK.md`.
- Untuk kriteria penerimaan pekerjaan developer: `SOFTWARE_ACCEPTANCE.md`.
- Untuk keputusan go-live oleh owner/infra/legal: `PRODUCTION_GATE.md`.

Software developer wajib menyertakan migration plan dan menjalankan tes pada lingkungan yang tersedia; **tidak wajib dan tidak ditugaskan melakukan deployment atau operasi server**.
