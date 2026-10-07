# Branching & CI

Standar organisasi: branch jangka panjang hanya `main`, `dev`, dan `staging`. Branch fitur/perbaikan bersifat sementara dan dihapus setelah merge; titik penting disimpan sebagai tag.

## Branch yang ada saat ini

| Branch | Fungsi |
|---|---|
| `main` | Produksi / branch utama. Selalu dalam kondisi dapat dirilis. |

Branch `dev` dan `staging` belum dibuat. Buat dari `main` saat repo ini membutuhkan alur dev → staging → produksi.

## Alur kerja

1. Buat branch fitur dari `dev` (atau dari `main` bila `dev` belum ada): `feat/...`, `fix/...`.
2. Buka PR ke `dev`, lalu promosikan `dev` → `staging` → `main` setelah diverifikasi.
3. Hapus branch fitur setelah merge.

## CI/CD

Repo ini belum memiliki GitHub Actions; deploy dilakukan manual atau lewat platform hosting.
