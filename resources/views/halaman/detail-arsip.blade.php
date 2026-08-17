<div class="grid-detail">
    <section class="panel informasi-arsip">
        <h2>Informasi Arsip</h2>
        <dl>
            <dt>Jenis</dt>
            <dd>Surat {{ ucfirst($surat->jenis) }}</dd>
            <dt>Kategori</dt>
            <dd>{{ $surat->kategori?->nama ?? 'Belum dikategorikan' }}</dd>
            <dt>No. Agenda</dt>
            <dd>{{ $surat->nomor_agenda }}</dd>
            <dt>No. Surat</dt>
            <dd>{{ $surat->nomor_surat }}</dd>
            <dt>Tanggal Surat</dt>
            <dd>{{ $surat->tanggal_surat->format('d/m/Y') }}</dd>
            <dt>Asal/Tujuan</dt>
            <dd>{{ $surat->pihak }}</dd>
            <dt>Perihal</dt>
            <dd>{{ $surat->perihal }}</dd>
            <dt>Penanggung Jawab</dt>
            <dd>{{ $surat->penanggungJawab?->name ?? 'Belum ditugaskan' }}</dd>
            <dt>Status</dt>
            <dd><span class="lencana {{ strtolower($surat->status) }}">{{ $surat->status }}</span></dd>
            @if($surat->disposisi)
                <dt>Arahan / Disposisi</dt>
                <dd>{{ $surat->disposisi }}</dd>
            @endif
        </dl>
        @if(auth()->user()->peran === 'pegawai' && $surat->jenis === 'masuk' && $surat->status === 'Didisposisikan')
            <form method="post" action="{{ route('surat.selesaikan', $surat) }}" data-konfirmasi="Tandai tugas surat ini selesai?">
                @csrf
                <button class="tombol tombol-primer tombol-penuh">Tandai Tugas Selesai</button>
            </form>
        @endif
    </section>
    <section class="panel preview-arsip">
        <h2>Preview Dokumen</h2>
        @if($surat->file)
            <div class="dokumen-pdf" data-pratinjau-pdf data-sumber="{{ route('arsip.pratinjau', $surat) }}">
                <canvas aria-label="Halaman pertama dokumen PDF"></canvas>
                <p data-gagal-pdf hidden>Dokumen tidak dapat dirender. <a href="{{ route('arsip.unduh', $surat) }}">Unduh dokumen</a>.</p>
            </div>
        @else
            <article class="lembar-surat">
                <div class="kop-surat">
                    <span class="segel">S</span>
                    <strong>PEMERINTAH KOTA SAMARINDA<br>DINAS PENDIDIKAN</strong>
                </div>
                <hr>
                <h3>{{ strtoupper($surat->perihal) }}</h3>
                <p>Kepada Yth.<br>{{ $surat->pihak }}<br>Di Tempat</p>
                <p>Dengan hormat,<br>Dokumen fisik untuk {{ strtolower($surat->perihal) }} belum diunggah ke sistem.</p>
                <p class="tanda-tangan">Informasi arsip<br><br>{{ $surat->nomor_surat }}</p>
            </article>
        @endif
        @if($surat->file)
            <a class="tombol tombol-primer tombol-penuh" href="{{ route('arsip.unduh', $surat) }}">
                <svg><use href="#i-unduh"/></svg> Unduh Arsip
            </a>
        @else
            <button class="tombol tombol-primer tombol-penuh" disabled>Dokumen belum diunggah</button>
        @endif
        <a class="tombol tombol-sekunder tombol-penuh" href="{{ route('arsip') }}">← Kembali</a>
    </section>
</div>
