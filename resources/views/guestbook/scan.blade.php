<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Konfirmasi Kehadiran Tamu</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { background: #f3f4f6; }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center p-4">

<div class="w-full max-w-md">

    {{-- ====== Already checked-out ====== --}}
    @if($entry->jam_out)
    <div class="bg-white rounded-2xl shadow-lg overflow-hidden">
        <div class="bg-gray-500 p-6 text-center">
            <div class="w-14 h-14 bg-white/20 rounded-full flex items-center justify-center mx-auto mb-3">
                <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                </svg>
            </div>
            <h1 class="text-xl font-bold text-white">Kunjungan Selesai</h1>
            <p class="text-white/75 text-sm mt-1">Tamu telah keluar</p>
        </div>
        <div class="p-6 text-center text-gray-500 text-sm">
            Kunjungan ini sudah dicatat selesai pada pukul <strong>{{ $entry->jam_out }}</strong>.
            QR code tidak dapat digunakan lagi.
        </div>
    </div>

    {{-- ====== Active entry – show form ====== --}}
    @else

    @if(session('scan_success'))
    {{-- Success banner --}}
    <div class="mb-4 bg-green-50 border border-green-200 rounded-xl px-5 py-3 flex items-start gap-3">
        <svg class="w-5 h-5 text-green-500 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
        </svg>
        <div>
            <p class="text-sm font-semibold text-green-800">{{ session('scan_success') }}</p>
            <p class="text-xs text-green-600 mt-0.5">Scan ke-{{ $entry->visitor_count }} berhasil dicatat.</p>
        </div>
    </div>
    @endif

    @if($errors->any())
    <div class="mb-4 bg-red-50 border border-red-200 rounded-xl px-5 py-3 text-sm text-red-700">
        <ul class="list-disc list-inside space-y-1">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
    @endif

    <div class="bg-white rounded-2xl shadow-lg overflow-hidden">
        {{-- Header --}}
        <div class="bg-[#4A2F24] p-6">
            <div class="flex items-center gap-3 mb-1">
                <div class="w-10 h-10 bg-[#CDDEA7]/15 rounded-xl flex items-center justify-center border border-[#CDDEA7]/25">
                    <svg class="w-5 h-5 text-[#CDDEA7]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                    </svg>
                </div>
                <div>
                    <h1 class="text-lg font-bold text-[#CDDEA7]">Konfirmasi Kehadiran</h1>
                    <p class="text-[#CDDEA7]/70 text-xs">Buku Tamu Digital</p>
                </div>
            </div>
        </div>

        {{-- Visit summary --}}
        <div class="px-6 pt-5 pb-2">
            <div class="bg-gray-50 rounded-xl border border-gray-100 p-4 space-y-2 text-sm">
                <div class="flex justify-between">
                    <span class="text-gray-500">Nama Pendaftar</span>
                    <span class="font-semibold text-gray-900">{{ $entry->name }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-500">Tanggal</span>
                    <span class="font-semibold text-gray-900">{{ \Carbon\Carbon::parse($entry->date)->translatedFormat('d M Y') }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-500">Jam Masuk</span>
                    <span class="font-semibold text-gray-900">{{ $entry->jam_in }}</span>
                </div>
                @if($entry->keperluan)
                <div class="flex justify-between">
                    <span class="text-gray-500">Keperluan</span>
                    <span class="font-semibold text-gray-900 text-right max-w-[55%]">{{ $entry->keperluan }}</span>
                </div>
                @endif
                <div class="flex justify-between pt-1 border-t border-gray-200 mt-1">
                    <span class="text-gray-500">Pengunjung Tercatat</span>
                    <span class="font-bold text-[#4E653D]">{{ $entry->visitor_count }} orang</span>
                </div>
            </div>
        </div>

        {{-- Scan form --}}
        <form method="POST" action="{{ route('guestbook.scan.submit', ['token' => $entry->qr_token]) }}" class="px-6 pb-6 pt-4 space-y-4">
            @csrf
            <p class="text-sm text-gray-600 font-medium">
                Isi data Anda untuk mencatat kehadiran. Setiap anggota rombongan mengisi satu kali.
            </p>

            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-gray-500 mb-1.5">
                    Nama Lengkap <span class="text-rose-500">*</span>
                </label>
                <input type="text" name="visitor_name" value="{{ old('visitor_name') }}"
                    required maxlength="255"
                    placeholder="Nama lengkap Anda"
                    class="w-full h-10 px-3.5 rounded-lg border border-gray-300 bg-white text-sm text-gray-800 focus:outline-none focus:ring-2 focus:ring-[#4A2F24]/20 focus:border-[#4A2F24] transition-all">
            </div>

            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-gray-500 mb-1.5">
                    No. KTP / ID Karyawan
                </label>
                <input type="text" name="visitor_id_number" value="{{ old('visitor_id_number') }}"
                    maxlength="100"
                    placeholder="Opsional"
                    class="w-full h-10 px-3.5 rounded-lg border border-gray-300 bg-white text-sm text-gray-800 focus:outline-none focus:ring-2 focus:ring-[#4A2F24]/20 focus:border-[#4A2F24] transition-all">
            </div>

            <button type="submit"
                class="w-full h-11 bg-[#4E653D] hover:bg-[#354C2B] text-white text-sm font-semibold rounded-lg transition flex items-center justify-center gap-2 shadow-sm">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                </svg>
                Konfirmasi Kehadiran Saya
            </button>
        </form>

        {{-- Recent scans on this entry --}}
        @if($scans->count() > 0)
        <div class="border-t border-gray-100 px-6 py-4">
            <p class="text-xs font-semibold uppercase tracking-wider text-gray-400 mb-3">Sudah Hadir</p>
            <ul class="space-y-2">
                @foreach($scans as $scan)
                <li class="flex items-center gap-2.5 text-sm">
                    <div class="w-7 h-7 rounded-full bg-[#4E653D]/10 flex items-center justify-center flex-shrink-0">
                        <svg class="w-3.5 h-3.5 text-[#4E653D]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                        </svg>
                    </div>
                    <div class="min-w-0">
                        <p class="font-semibold text-gray-900 truncate">{{ $scan->visitor_name ?? 'Tamu' }}</p>
                        <p class="text-xs text-gray-400">{{ \Carbon\Carbon::parse($scan->scanned_at)->format('H:i') }}</p>
                    </div>
                </li>
                @endforeach
            </ul>
        </div>
        @endif
    </div>

    @endif {{-- end not checked-out --}}

</div>
</body>
</html>
