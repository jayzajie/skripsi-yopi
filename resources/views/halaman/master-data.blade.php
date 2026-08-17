<div class="grid-dua">
    <section class="panel">
        <div class="judul-panel">
            <h2>Kategori Dokumen</h2>
            <button type="button" class="tombol tombol-primer tombol-mini" data-kategori-baru>
                <svg><use href="#i-tambah"/></svg> Tambah
            </button>
        </div>
        <ul class="daftar-kategori">
            @foreach($kategori as $item)
                <li>
                    <span class="pegangan">⠿</span>
                    <span>{{ $item->nama }}</span>
                    <span class="aksi-tabel">
                        <button data-edit-kategori data-action="{{ route('kategori.perbarui',$item) }}" data-isian="{{ base64_encode($item->toJson()) }}">Edit</button>
                        <form method="post" action="{{ route('kategori.hapus',$item) }}" data-konfirmasi="Hapus kategori ini?">
                            @csrf
                            @method('DELETE')
                            <button class="bahaya" aria-label="Hapus {{ $item->nama }}">Hapus</button>
                        </form>
                    </span>
                </li>
            @endforeach
        </ul>
    </section>
    <section class="panel">
        <h2>Form Master Data</h2>
        <form method="post" action="{{ route('kategori.simpan') }}" class="form-vertikal" data-form-kategori>
            @csrf
            <input type="hidden" name="_method" value="POST" data-metode>
            <label>Nama Kategori<input name="nama" required placeholder="Masukkan nama kategori"></label>
            <label>Kode Kategori<input name="kode" required placeholder="Masukkan kode kategori"></label>
            <label>Deskripsi<textarea name="deskripsi" rows="3" placeholder="Masukkan deskripsi"></textarea></label>
            <label>
                Status
                <select name="aktif">
                    <option value="1">Aktif</option>
                    <option value="0">Nonaktif</option>
                </select>
            </label>
            <div class="kaki-form">
                <button type="reset" class="tombol tombol-sekunder" data-kategori-baru>Batal</button>
                <button class="tombol tombol-primer">Simpan</button>
            </div>
        </form>
    </section>
</div>
