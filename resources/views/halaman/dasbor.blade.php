<section class="grid-statistik">
    @foreach($kartu as [$label, $nilai, $ikon, $warna])
        <article class="kartu-statistik"><span class="ikon-statistik {{ $warna }}"><svg><use href="#{{ $ikon }}"/></svg></span><div><small>{{ $label }}</small><strong>{{ number_format($nilai, 0, ',', '.') }}</strong></div></article>
    @endforeach
</section>
<section class="panel">
    <div class="judul-panel"><h2>Dokumen Terbaru</h2><a href="{{ route('arsip') }}">Lihat semua</a></div>
    <div class="bungkus-tabel"><table><thead><tr><th>No</th><th>Jenis</th><th>Nomor Agenda</th><th>Perihal</th><th>Tanggal</th><th>Status</th></tr></thead><tbody>
        @forelse($terbaru as $item)<tr><td>{{ $loop->iteration }}</td><td>Surat {{ ucfirst($item->jenis) }}</td><td>{{ $item->nomor_agenda }}</td><td>{{ $item->perihal }}</td><td>{{ $item->tanggal_surat->format('d/m/y') }}</td><td><span class="lencana {{ strtolower($item->status) }}">{{ $item->status }}</span></td></tr>
        @empty<tr><td colspan="6" class="kosong">Belum ada dokumen.</td></tr>@endforelse
    </tbody></table></div>
</section>
<section class="panel ringkasan">
    <h2>Ringkasan Arsip</h2>
    @php $maks = max(1, ...array_column($kartu, 1)); @endphp
    <div class="area-grafik">
        <div class="grafik-batang" role="img" aria-label="Grafik jumlah arsip">
            @foreach(array_slice($kartu, 0, 4) as [$label,$nilai])
                <div class="batang-item"><span class="batang" style="--tinggi: {{ max(5, round(($nilai/$maks)*100)) }}%"><i>{{ $nilai }}</i></span><small>{{ $label }}</small></div>
            @endforeach
        </div>
        <ul class="legenda">@foreach(array_slice($kartu, 0, 4) as [$label,$nilai,$ikon,$warna])<li><i class="titik {{ $warna }}"></i> {{ $label }} <strong>{{ $nilai }}</strong></li>@endforeach</ul>
    </div>
</section>
