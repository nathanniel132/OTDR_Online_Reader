# Product Requirements Document (PRD)
## Web OTDR Viewer & Analyzer (.SOR Reader)
### Versi: 1.2 (Laravel Framework, Zero-Database Architecture, Multi-Trace Grouping, Tagging & Project Import/Export)

---

### 1. Ringkasan Eksekutif
Aplikasi web berbasis framework **Laravel (PHP 8.3)** dan Javascript modern untuk membaca, mem-parsing data biner OTDR format Bellcore / Telcordia SR-4731 (GR-196), memvisualisasikan kurva atenuasi (trace curve), tabel event sambungan/konektor, serta mengelola metadata serat optik.

Aplikasi dirancang dengan **arsitektur tanpa database server (Zero-Database Architecture)**:
- Manajemen file berbasis sesi dan client-side storage (LocalStorage / IndexedDB).
- Fitur pengelompokan (Grouping) banyak trace/core kabel dalam satu wadah proyek (contoh: "Kabel Feeder Ruas A-B").
- Penandaan (Tagging & Custom Annotation) pada titik atau event tertentu (misal: penanda Joint Closure, ODP, OTB, catatan teknisi).
- Mekanisme **Export Project** (menyimpan seluruh state, group, trace, dan penanda ke file `.otdrproj` / `.json`) serta **Import Project** (memuat ulang project yang tersimpan tanpa perlu database).

---

### 2. Analisis Sampel Data (`Doc/kabel1 cbd c1.sor`)
- **Format**: Telcordia SR-4731 Revision 2.00 (Little-Endian).
- **Ukuran File**: 33.831 byte.
- **Struktur Blok**:
  1. `Map` (124 byte): Index dan offset semua blok data.
  2. `GenParams` (75 byte): Informasi kabel, serat optik, lokasi start/end.
  3. `SupParams` (56 byte): Vendor (`FIBERCLOUD`), Model (`FC4000`), Modul (`3537`), SN (`0901001`), Software (`V1.01`).
  4. `FxdParams` (140 byte): Unit jarak (`km`), wavelength (1310/1550 nm), group refractive index (IOR), pulse width, sample spacing, rentang jarak, jumlah titik data.
  5. `KeyEvents` (288 byte): 2 event terdeteksi (sambungan fusi/splice dan ujung kabel `1E9999LS`), attenuation loss, reflectance dB.
  6. `DataPts` (32.788 byte): 16.384 titik data nilai atenuasi uint16 ($y = raw \times factor \times 0.001$).
  7. `SpclProprietary` (352 byte): Parameter proprietary vendor.
  8. `Cksum` (8 byte): Checksum validasi integritas file.

---

### 3. Arsitektur Sistem (Zero-Database Architecture)

```
[User Browser]
  ├── Upload File .SOR (Single / Batch Multi-Core)
  │      ▼
  │  [PHP API Backend] -> Parse Biner SR-4731 (SorParser.php)
  │      ▼
  │  [JSON Trace Data]
  │      ▼
  ├── [Frontend Workspace State Manager]
  │      ├── Grouping (Kabel / Ruas / Segmen)
  │      ├── Tagging & Anotasi (Marker ODP, Joint Closure, Catatan)
  │      ├── Auto-Save (Browser LocalStorage / IndexedDB)
  │      ├── Trace Chart (Plotly.js / Chart.js Multi-trace)
  │      └── Events Table
  │
  ├── [Export Engine] ──► File Project (.otdrproj / .json), PDF Laporan, CSV, PNG
  └── [Import Engine] ◄── Load File Project (.otdrproj / .json) (Restore State 100%)
```

- **Backend (PHP Laragon)**:
  - Berfungsi murni sebagai **Stateless High-Performance Binary Parser**.
  - Menerima file upload `.sor`, mengekstrak metadata + event + data points, mengembalikan JSON.
  - Tidak menyimpan state ke MySQL/database, sehingga ringan, cepat, dan zero-configuration.
- **Frontend & Storage (Client-Side State)**:
  - Manajemen state grup, daftar trace, penanda, dan catatan teknisi disimpan dalam struktur JSON di memory browser dan disinkronkan ke `LocalStorage` / `IndexedDB`.
  - Data tidak hilang saat halaman di-refresh.
- **Portabilitas**:
  - File proyek hasil ekspor (`.otdrproj` atau format JSON) dapat dipindahkan antar komputer, dikirim via email, dan dibuka kembali di aplikasi kapan saja.

---

### 4. Fitur Utama & Kebutuhan Fungsional (FR)

#### FR-1: Binary Parsing Engine (PHP)
- Membaca blok `Map` untuk memetakan seluruh offset blok secara dinamis.
- Ekstraksi blok `GenParams`: Kabel ID, Nomor Fiber, Operator, Lokasi Awal, Lokasi Akhir.
- Ekstraksi blok `SupParams`: Nama Vendor, Model Unit, Nomor Seri, Versi Firmware.
- Ekstraksi blok `FxdParams`:
  - Tanggal & waktu pengujian.
  - Jarak total / span length ($km$).
  - Panjang gelombang ($nm$, contoh: 1310 nm / 1550 nm).
  - Indeks Bias / Refractive Index ($IOR$).
  - Pulse width ($ns$).
  - Sample spacing ($m$).
  - Total titik ukur.
- Ekstraksi blok `KeyEvents`:
  - Nomor urut event.
  - Posisi jarak ($km$) berdasarkan waktu propagasi dan IOR.
  - Tipe event (Reflektif / Non-reflektif / Ujung serat optik).
  - Event Loss ($dB$).
  - Reflectance ($dB$).
  - Attenuation slope ($dB/km$).
  - Cumulative Loss ($dB$).
- Ekstraksi blok `DataPts`:
  - Parsing 16.384 titik biner uint16 little-endian.
  - Konversi skala atenuasi ke desibel ($dB$) dengan scaling factor.
  - Pemetaan jarak titik $x_i = i \times sample\_spacing$.

#### FR-2: Multi-File Upload & Demo Sampel
- Upload single file atau multi-file `.sor` secara bersamaan (batch upload banyak core kabel).
- Tombol cepat **"Muat Sampel (Doc/kabel1 cbd c1.sor)"** untuk demo instan tanpa perlu mencari file.
- Validasi ekstensi `.sor` dan magic byte validasi integritas format.

#### FR-3: Fitur Grouping (Pengelompokan Trace)
- User dapat membuat **Group / Folder Proyek** (contoh: "Ruas Gedung A - B", "Feeder 24 Core", "Distribution Segmen 3").
- Memindahkan atau mengelompokkan trace hasil upload ke dalam group tertentu.
- Informasi agregat per group: Jumlah core, total jarak kabel terukur, rata-rata loss.
- Kemampuan beralih antar trace dalam group dengan cepat (Trace Selector list).
- Mode **Multi-Trace Compare / Overlay**: Menampilkan lebih dari 1 kurva trace dalam satu grafik untuk komparasi kualitas antar core serat optik.

#### FR-4: Fitur Tagging & Custom Marker (Penandaan)
- **Tagging Event Bawaan**: Menandai event bawaan alat dengan label khusus (contoh: Event #1 ditandai sebagai "Joint Closure 1 (JC-01)", Event #2 sebagai "OTB Ruang Server").
- **Custom Marker Bebas**:
  - User dapat menambahkan marker kustom pada koordinat jarak ($km$) tertentu di grafik.
  - Tipe marker: Lokasi ODP, Slack Cable, Titik Crossing, Potensi Bending, Batas Toleransi.
- **Field Catatan / Anotasi**:
  - Catatan deskripsi teknis per-marker dan per-trace (contoh: "Core redaman tinggi di km 1.25, perlu re-splicing").

#### FR-5: Export & Import Project (State Persistence Tanpa Database)
- **Export Project (`.otdrproj` / `.json`)**:
  - Mengekspor seluruh data proyek dalam satu file bundle:
    - Informasi group & nama proyek.
    - Semua data trace yang di-parse (metadata, data points kurva, tabel event).
    - Semua penanda (marker), tag, dan catatan kustom teknisi.
  - File dapat diunduh langsung ke komputer user.
- **Import Project**:
  - User dapat mengunggah file `.otdrproj` / `.json` hasil export sebelumnya.
  - Aplikasi merestore 100% kondisi proyek: struktur group, seluruh trace, kurva grafik, event, marker kustom, dan catatan tanpa perlu koneksi database.
- **Auto-Save Local Storage**:
  - Perubahan group dan tag otomatis tersimpan di LocalStorage / IndexedDB browser.
  - Tersedia tombol "Clear / Reset Workspace" untuk memulai sesi baru.

#### FR-6: Visualisasi & Reporting
- **Grafik Trace Interaktif**:
  - Sumbu X: Jarak ($km$), Sumbu Y: Atenuasi ($dB$).
  - Garis vertikal penanda untuk Event OTDR dan Custom Tag Marker.
  - Tooltip koordinat presisi tinggi saat kursor diarahkan ke kurva.
  - Kontrol Zoom, Pan, Box-Select, Reset View, Toggle Grid.
- **Tabel Event OTDR Terintegrasi**:
  - Kolom: No Event, Jarak ($km$), Tipe Event, Custom Tag/Nama Titik, Loss ($dB$), Reflectance ($dB$), Slope ($dB/km$), Kumulatif Loss ($dB$), Aksi (Edit Tag/Hapus Marker).
  - Klik baris tabel otomatis memfokuskan grafik ke titik tersebut.
- **Ekspor Dokumen Laporan**:
  - Ekspor Grafik (PNG) resolusi tinggi.
  - Ekspor Tabel Event & Trace (CSV / Excel friendly).
  - Ekspor Laporan Resmi (PDF / Print layout siap cetak) memuat kop laporan, ringkasan group, parameter ukur, grafik kurva, dan tabel event beranotasi.

---

### 5. Format File Project Bundle (`.otdrproj` / `.json`)
Struktur data file export/import project:
```json
{
  "version": "1.0",
  "projectName": "Pengukuran Kabel CBD Feeder 1",
  "createdAt": "2026-10-20T10:00:00Z",
  "groups": [
    {
      "id": "grp-1",
      "name": "Kabel Feeder Ruas A-B",
      "description": "Kabel 24 Core Single Mode"
    }
  ],
  "traces": [
    {
      "id": "trc-1",
      "groupId": "grp-1",
      "fileName": "kabel1 cbd c1.sor",
      "metadata": {
        "cableId": "",
        "fiberId": "",
        "wavelength": 1550,
        "pulseWidth": 50,
        "ior": 1.4677,
        "totalDistance": 1.491,
        "totalLoss": 0.35,
        "supplier": "FIBERCLOUD",
        "model": "FC4000",
        "serialNumber": "0901001",
        "dateTime": "2024-03-01 14:30:00"
      },
      "customTags": [
        { "distanceKm": 0.50, "label": "Joint Closure 01", "notes": "Fusi splice tiang no 24" },
        { "distanceKm": 1.49, "label": "OTB End", "notes": "Ujung kabel di rak server" }
      ],
      "events": [],
      "dataPoints": []
    }
  ]
}
```

---

### 6. Kebutuhan Non-Fungsional (NFR)
- **Zero Database Server**: Tidak memerlukan MySQL, MariaDB, atau konfigurasi database lainnya.
- **Kecepatan Parsing**: < 100 ms per file `.sor` di PHP.
- **Kapasitas Penyimpanan**: File `.otdrproj` compact (ukuran trace terkompresi atau downsampled jika data points sangat banyak).
- **Kompatibilitas**: Berjalan di PHP 7.4 / 8.x pada web server Laragon (Apache) dan semua browser modern (Chrome, Edge, Firefox, Safari).

---

### 7. Rencana Struktur File Proyek
```
d:/Laragon/Otdr_Reader/
├── .github/
│   └── agents/                # Custom agents: Leader, Coder, Reviewer
├── api/
│   ├── parse.php              # Stateless SOR binary parser endpoint (POST)
│   └── sample.php             # Endpoint pembaca sampel Doc/kabel1 cbd c1.sor (GET)
├── assets/
│   ├── css/
│   │   └── style.css          # Styling dashboard & CSS print layout
│   └── js/
│       ├── app.js             # Controller utama, upload handler, UI interaction
│       ├── stateManager.js    # Manajemen Group, Tagging, LocalStorage, Export/Import Project
│       └── traceChart.js      # Plotly.js / Chart.js wrapper & marker annotator
├── Doc/
│   └── kabel1 cbd c1.sor      # Data sampel
├── src/
│   └── SorParser.php          # Engine biner parser Telcordia SR-4731
├── index.php                  # Web dashboard UI
├── PRD.md                     # Dokumen PRD
└── README.md                  # Petunjuk instalasi & penggunaan
```

---

### 8. Milestone & Roadmap Eksekusi
1. **Milestone 1**: Pembangunan binary parser `src/SorParser.php` dan validasi output CLI terhadap file sampel.
2. **Milestone 2**: Pembuatan endpoint API `api/parse.php` dan `api/sample.php`.
3. **Milestone 3**: UI Dashboard `index.php` dan modul State Manager (`stateManager.js`) untuk Grouping, Tagging, Custom Markers, Auto-save.
4. **Milestone 4**: Integrasi visualisasi grafik trace interaktif multi-marker dan sinkronisasi tabel event.
5. **Milestone 5**: Implementasi fitur Export Project (`.otdrproj` / JSON), Import Project, PDF/Print report, dan pengujian end-to-end.
