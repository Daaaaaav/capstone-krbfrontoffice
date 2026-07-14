<!DOCTYPE html>
<html lang="id" xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>QR Code Kunjungan</title>
    <!--[if mso]>
    <style type="text/css">
        table { border-collapse: collapse; }
        td { font-family: Arial, sans-serif; }
    </style>
    <![endif]-->
    <style type="text/css">
        /* Reset */
        body, table, td, p, a, li { -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; }
        table, td { mso-table-lspace: 0pt; mso-table-rspace: 0pt; }
        img { -ms-interpolation-mode: bicubic; border: 0; outline: none; text-decoration: none; }

        body {
            margin: 0 !important;
            padding: 0 !important;
            width: 100% !important;
            background-color: #f4f6f8;
            font-family: 'Segoe UI', Arial, Helvetica, sans-serif;
            color: #333333;
        }

        @media only screen and (max-width: 600px) {
            .wrapper-table { width: 100% !important; }
            .body-cell { padding: 24px 16px !important; }
            .header-cell { padding: 24px 16px !important; }
            .qr-card-td { display: block !important; width: 100% !important; padding: 0 0 16px 0 !important; }
            .qr-card-table { width: 100% !important; max-width: 280px !important; margin: 0 auto !important; }
        }
    </style>
</head>
<body style="margin:0; padding:0; background-color:#f4f6f8;">
    <!-- Outer wrapper table for centering -->
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#f4f6f8;">
        <tr>
            <td align="center" style="padding: 20px 10px;">

                <!-- Main content table -->
                <table role="presentation" class="wrapper-table" width="600" cellpadding="0" cellspacing="0" border="0" style="background-color:#ffffff; border-radius:12px; overflow:hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.08);">

                    {{-- ===== HEADER ===== --}}
                    <tr>
                        <td class="header-cell" align="center" style="background-color:#4A2F24; padding:32px 40px;">
                            <p style="color:#CDDEA7; font-size:22px; font-weight:700; letter-spacing:0.3px; margin:0 0 6px 0;">&#128210; Konfirmasi Kunjungan</p>
                            <p style="color:rgba(205,222,167,0.75); font-size:13px; margin:0 0 12px 0;">QR Code Tamu &ndash; Buku Tamu Digital</p>
                            <table role="presentation" cellpadding="0" cellspacing="0" border="0">
                                <tr>
                                    <td style="background:rgba(205,222,167,0.15); border:1px solid rgba(205,222,167,0.3); color:#CDDEA7; font-size:12px; font-weight:600; padding:4px 14px; border-radius:20px;">
                                        {{ $entry->visitor_count }} Pengunjung
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    {{-- ===== BODY ===== --}}
                    <tr>
                        <td class="body-cell" style="padding:36px 40px;">

                            {{-- Greeting --}}
                            <p style="font-size:16px; font-weight:600; color:#1a1a1a; margin:0 0 8px 0;">Halo, {{ $entry->name }}!</p>
                            <p style="font-size:14px; color:#555; line-height:1.6; margin:0 0 28px 0;">
                                Pendaftaran kunjungan Anda telah berhasil dicatat oleh resepsionis kami.
                                Berikut adalah <strong>{{ $entry->visitor_count }} QR code unik</strong> untuk setiap anggota rombongan Anda.
                                Setiap pengunjung harus menunjukkan QR code masing-masing saat meninggalkan gedung.
                            </p>

                            {{-- Visit Details --}}
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#f9fafb; border:1px solid #e5e7eb; border-radius:10px; margin-bottom:28px;">
                                <tr>
                                    <td style="padding:20px 24px;">
                                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="font-size:13px;">
                                            <tr>
                                                <td style="color:#6b7280; font-weight:500; padding:5px 0; width:38%;">Tanggal</td>
                                                <td style="color:#111827; font-weight:600; padding:5px 0;">{{ \Carbon\Carbon::parse($entry->date)->translatedFormat('d F Y') }}</td>
                                            </tr>
                                            <tr>
                                                <td style="color:#6b7280; font-weight:500; padding:5px 0;">Jam Masuk</td>
                                                <td style="color:#111827; font-weight:600; padding:5px 0;">{{ $entry->jam_in }}</td>
                                            </tr>
                                            @if($entry->instansi)
                                            <tr>
                                                <td style="color:#6b7280; font-weight:500; padding:5px 0;">Instansi</td>
                                                <td style="color:#111827; font-weight:600; padding:5px 0;">{{ $entry->instansi }}</td>
                                            </tr>
                                            @endif
                                            @if($entry->keperluan)
                                            <tr>
                                                <td style="color:#6b7280; font-weight:500; padding:5px 0;">Keperluan</td>
                                                <td style="color:#111827; font-weight:600; padding:5px 0;">{{ $entry->keperluan }}</td>
                                            </tr>
                                            @endif
                                            <tr>
                                                <td style="color:#6b7280; font-weight:500; padding:5px 0;">Jumlah Pengunjung</td>
                                                <td style="color:#4E653D; font-weight:700; padding:5px 0;">{{ $entry->visitor_count }} orang</td>
                                            </tr>
                                            <tr>
                                                <td style="color:#6b7280; font-weight:500; padding:5px 0;">Petugas</td>
                                                <td style="color:#111827; font-weight:600; padding:5px 0;">{{ $entry->petugas_penjaga }}</td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>

                            {{-- Instructions --}}
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#f0f5ea; border:1px solid #c3d9a8; border-radius:10px; margin-bottom:28px;">
                                <tr>
                                    <td style="padding:16px 20px;">
                                        <p style="font-size:13px; font-weight:700; color:#4E653D; margin:0 0 8px 0;">&#9989; Petunjuk Penggunaan</p>
                                        <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="font-size:12px; color:#555; line-height:1.8;">
                                            <tr><td style="padding:0 8px 0 0; vertical-align:top; color:#4E653D; font-weight:700;">1.</td><td>Setiap pengunjung mendapat <strong>1 QR code unik</strong></td></tr>
                                            <tr><td style="padding:0 8px 0 0; vertical-align:top; color:#4E653D; font-weight:700;">2.</td><td>Simpan QR code ini hingga kunjungan selesai</td></tr>
                                            <tr><td style="padding:0 8px 0 0; vertical-align:top; color:#4E653D; font-weight:700;">3.</td><td>Saat meninggalkan gedung, tunjukkan QR code Anda ke resepsionis untuk di-scan</td></tr>
                                            <tr><td style="padding:0 8px 0 0; vertical-align:top; color:#4E653D; font-weight:700;">4.</td><td>Setelah semua QR code di-scan, kunjungan akan otomatis tercatat selesai</td></tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>

                            {{-- QR Code Section Title --}}
                            <p style="font-size:14px; font-weight:700; color:#1a1a1a; margin:0 0 6px 0; text-align:center;">QR Code Checkout</p>
                            <p style="font-size:12px; color:#6b7280; margin:0 0 20px 0; text-align:center;">Bagikan satu QR code ke setiap anggota rombongan</p>

                            {{-- QR Code Cards - stacked vertically for max compatibility --}}
                            @foreach($qrItems as $item)
                            <table role="presentation" class="qr-card-table" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:0 auto 16px auto; max-width:320px;">
                                <tr>
                                    <td align="center" style="border:2px solid #e5e7eb; border-radius:12px; padding:16px; background:#fafafa;">
                                        {{-- Label --}}
                                        <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin-bottom:12px;">
                                            <tr>
                                                <td style="font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:0.5px; color:#4A2F24; background:rgba(74,47,36,0.08); padding:4px 14px; border-radius:6px;">
                                                    Pengunjung {{ $item['visitor_number'] }}
                                                </td>
                                            </tr>
                                        </table>
                                        {{-- QR Code Image (CID-embedded PNG) --}}
                                        <img src="{{ $message->embedData($item['png'], 'qr-visitor-' . $item['visitor_number'] . '.png', 'image/png') }}"
                                             alt="QR Code Pengunjung {{ $item['visitor_number'] }}"
                                             width="200" height="200"
                                             style="display:block; margin:0 auto; width:200px; height:200px; border:1px solid #eee;">
                                    </td>
                                </tr>
                            </table>
                            @endforeach

                            {{-- PDF Note --}}
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#eef6ff; border:1px solid #bdd7f5; border-radius:10px; margin-bottom:28px; margin-top:12px;">
                                <tr>
                                    <td style="padding:14px 20px;">
                                        <p style="font-size:12px; color:#1e5a9e; margin:0; line-height:1.6;">
                                            &#128206; <strong>File PDF terlampir</strong> &mdash; Kami juga melampirkan file PDF yang berisi semua QR code.
                                            Anda dapat mencetak atau menyimpannya untuk kemudahan saat checkout.
                                        </p>
                                    </td>
                                </tr>
                            </table>

                            {{-- Footer Note --}}
                            <p style="font-size:12px; color:#9ca3af; line-height:1.6; border-top:1px solid #f3f4f6; padding-top:20px; margin:0;">
                                Email ini dikirim otomatis oleh sistem. Harap simpan QR code ini hingga kunjungan selesai.
                                Setiap QR code hanya dapat digunakan sekali untuk proses checkout.
                                Jika Anda tidak merasa mendaftar kunjungan ini, abaikan email ini atau hubungi resepsionis kami.
                            </p>
                        </td>
                    </tr>

                    {{-- ===== FOOTER ===== --}}
                    <tr>
                        <td align="center" style="background:#f9fafb; border-top:1px solid #f3f4f6; padding:20px 40px; font-size:11px; color:#9ca3af;">
                            &copy; {{ date('Y') }} {{ config('app.name') }} &mdash; Buku Tamu Digital
                        </td>
                    </tr>

                </table>
                <!-- /Main content table -->

            </td>
        </tr>
    </table>
</body>
</html>
