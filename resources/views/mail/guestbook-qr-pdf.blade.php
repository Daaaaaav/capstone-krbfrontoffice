<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>QR Code Kunjungan</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Helvetica', 'Arial', sans-serif;
            color: #333;
            background: #fff;
        }

        .page {
            padding: 40px 30px;
        }

        /* ── Header ── */
        .header {
            background: #4A2F24;
            border-radius: 10px;
            padding: 28px 32px;
            text-align: center;
            margin-bottom: 28px;
        }

        .header h1 {
            color: #CDDEA7;
            font-size: 20px;
            font-weight: 700;
            margin: 0 0 4px 0;
        }

        .header p {
            color: rgba(205,222,167,0.7);
            font-size: 11px;
            margin: 0;
        }

        .header .badge {
            display: inline-block;
            background: rgba(205,222,167,0.15);
            border: 1px solid rgba(205,222,167,0.3);
            color: #CDDEA7;
            font-size: 11px;
            font-weight: 600;
            padding: 3px 12px;
            border-radius: 16px;
            margin-top: 10px;
        }

        /* ── Detail Box ── */
        .detail-box {
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 16px 20px;
            margin-bottom: 24px;
        }

        .detail-box table {
            width: 100%;
            border-collapse: collapse;
            font-size: 11px;
        }

        .detail-box td {
            padding: 4px 0;
            vertical-align: top;
        }

        .detail-box td.label {
            color: #6b7280;
            font-weight: 500;
            width: 35%;
        }

        .detail-box td.value {
            color: #111827;
            font-weight: 600;
        }

        /* ── Instructions ── */
        .instructions {
            background: #f0f5ea;
            border: 1px solid #c3d9a8;
            border-radius: 8px;
            padding: 14px 18px;
            margin-bottom: 28px;
        }

        .instructions h3 {
            font-size: 12px;
            font-weight: 700;
            color: #4E653D;
            margin: 0 0 6px 0;
        }

        .instructions ol {
            font-size: 10px;
            color: #555;
            line-height: 1.8;
            padding-left: 18px;
            margin: 0;
        }

        /* ── QR Section ── */
        .qr-section-title {
            font-size: 14px;
            font-weight: 700;
            color: #1a1a1a;
            text-align: center;
            margin: 0 0 4px 0;
        }

        .qr-section-subtitle {
            font-size: 10px;
            color: #6b7280;
            text-align: center;
            margin: 0 0 20px 0;
        }

        .qr-grid {
            width: 100%;
            border-collapse: collapse;
        }

        .qr-grid td {
            padding: 8px;
            vertical-align: top;
            text-align: center;
            width: 50%;
        }

        .qr-card {
            border: 2px solid #e5e7eb;
            border-radius: 10px;
            padding: 14px;
            background: #fafafa;
            text-align: center;
        }

        .qr-card .qr-label {
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            color: #4A2F24;
            background: rgba(74,47,36,0.08);
            padding: 3px 10px;
            border-radius: 5px;
            display: inline-block;
            margin-bottom: 10px;
        }

        .qr-card img {
            display: block;
            margin: 0 auto;
        }

        .qr-card .qr-token {
            font-size: 8px;
            color: #9ca3af;
            margin-top: 6px;
            word-break: break-all;
        }

        /* ── Footer ── */
        .footer {
            border-top: 1px solid #f3f4f6;
            padding-top: 16px;
            margin-top: 28px;
            text-align: center;
            font-size: 9px;
            color: #9ca3af;
        }

        /* Page break for many visitors */
        .page-break {
            page-break-before: always;
        }
    </style>
</head>
<body>
    <div class="page">
        {{-- Header --}}
        <div class="header">
            <h1>Konfirmasi Kunjungan</h1>
            <p>QR Code Tamu &ndash; Buku Tamu Digital</p>
            <div class="badge">{{ $entry->visitor_count }} Pengunjung</div>
        </div>

        {{-- Visit Details --}}
        <div class="detail-box">
            <table>
                <tr>
                    <td class="label">Nama</td>
                    <td class="value">{{ $entry->name }}</td>
                </tr>
                <tr>
                    <td class="label">Tanggal</td>
                    <td class="value">{{ \Carbon\Carbon::parse($entry->date)->translatedFormat('d F Y') }}</td>
                </tr>
                <tr>
                    <td class="label">Jam Masuk</td>
                    <td class="value">{{ $entry->jam_in }}</td>
                </tr>
                @if($entry->instansi)
                <tr>
                    <td class="label">Instansi</td>
                    <td class="value">{{ $entry->instansi }}</td>
                </tr>
                @endif
                @if($entry->keperluan)
                <tr>
                    <td class="label">Keperluan</td>
                    <td class="value">{{ $entry->keperluan }}</td>
                </tr>
                @endif
                <tr>
                    <td class="label">Jumlah Pengunjung</td>
                    <td class="value" style="color:#4E653D;">{{ $entry->visitor_count }} orang</td>
                </tr>
                <tr>
                    <td class="label">Petugas</td>
                    <td class="value">{{ $entry->petugas_penjaga }}</td>
                </tr>
            </table>
        </div>

        {{-- Instructions --}}
        <div class="instructions">
            <h3>&#10003; Petunjuk Penggunaan</h3>
            <ol>
                <li>Setiap pengunjung mendapat <strong>1 QR code unik</strong></li>
                <li>Simpan QR code ini hingga kunjungan selesai</li>
                <li>Saat meninggalkan gedung, tunjukkan QR code Anda ke resepsionis untuk di-scan</li>
                <li>Setelah semua QR code di-scan, kunjungan akan otomatis tercatat selesai</li>
            </ol>
        </div>

        {{-- QR Codes --}}
        <p class="qr-section-title">QR Code Checkout</p>
        <p class="qr-section-subtitle">Bagikan satu QR code ke setiap anggota rombongan. Potong sesuai garis.</p>

        <table class="qr-grid">
            @foreach($pdfItems as $index => $item)
                @if($index % 2 === 0)
                <tr>
                @endif
                    <td>
                        <div class="qr-card">
                            <div class="qr-label">Pengunjung {{ $item['visitor_number'] }}</div>
                            <br>
                            <img src="data:image/png;base64,{{ $item['png_base64'] }}"
                                 alt="QR {{ $item['visitor_number'] }}"
                                 width="160" height="160">
                            <div class="qr-token">{{ substr($item['token'], 0, 16) }}...</div>
                        </div>
                    </td>
                @if($index % 2 === 1 || $loop->last)
                    @if($index % 2 === 0)
                    <td></td>
                    @endif
                </tr>
                @endif
            @endforeach
        </table>

        {{-- Footer --}}
        <div class="footer">
            &copy; {{ date('Y') }} {{ config('app.name') }} &mdash; Buku Tamu Digital<br>
            Dokumen ini digenerate otomatis oleh sistem. QR code hanya berlaku untuk satu kali checkout.
        </div>
    </div>
</body>
</html>
