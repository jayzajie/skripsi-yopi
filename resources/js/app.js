import './bootstrap';
import { GlobalWorkerOptions, getDocument } from 'pdfjs-dist';
import pekerjaPdf from 'pdfjs-dist/build/pdf.worker.min.mjs?url';

GlobalWorkerOptions.workerSrc = pekerjaPdf;

const bacaData = (nilai) => {
    const biner = atob(nilai);
    const byte = Uint8Array.from(biner, (karakter) => karakter.charCodeAt(0));
    return JSON.parse(new TextDecoder().decode(byte));
};

const isiForm = (form, data) => {
    for (const [nama, nilai] of Object.entries(data)) {
        const masukan = form.elements.namedItem(nama);
        if (masukan instanceof HTMLInputElement && masukan.type === 'file') continue;
        if (masukan instanceof HTMLInputElement || masukan instanceof HTMLSelectElement || masukan instanceof HTMLTextAreaElement) {
            masukan.value = nama === 'tanggal_surat'
                ? String(nilai).slice(0, 10)
                : String(masukan instanceof HTMLSelectElement && typeof nilai === 'boolean' ? Number(nilai) : nilai ?? '');
        }
    }
};

document.querySelector('[data-buka-sidebar]')?.addEventListener('click', () => document.body.classList.add('sidebar-terbuka'));
document.querySelector('[data-tutup-sidebar]')?.addEventListener('click', () => document.body.classList.remove('sidebar-terbuka'));
document.querySelectorAll('[data-tutup-notifikasi]').forEach((tombol) => tombol.addEventListener('click', () => tombol.parentElement?.remove()));

document.querySelectorAll('[data-buka-modal]').forEach((tombol) => {
    tombol.addEventListener('click', () => document.getElementById(tombol.dataset.bukaModal)?.showModal());
});
document.querySelectorAll('[data-tutup-modal]').forEach((tombol) => {
    tombol.addEventListener('click', () => tombol.closest('dialog')?.close());
});
document.querySelectorAll('form[data-konfirmasi]').forEach((form) => {
    form.addEventListener('submit', (peristiwa) => {
        if (!window.confirm(form.dataset.konfirmasi)) peristiwa.preventDefault();
    });
});

const formSurat = document.querySelector('[data-form-surat]');
document.querySelector('[data-tambah-surat]')?.addEventListener('click', () => {
    if (!(formSurat instanceof HTMLFormElement)) return;
    formSurat.reset();
    formSurat.action = formSurat.dataset.actionBaru;
    formSurat.querySelector('[data-metode]').value = 'POST';
    formSurat.querySelector('[data-judul-modal]').textContent = formSurat.action.includes('masuk') ? 'Tambah Surat Masuk' : 'Tambah Surat Keluar';
});
document.querySelectorAll('[data-edit-surat]').forEach((tombol) => {
    tombol.addEventListener('click', () => {
        if (!(formSurat instanceof HTMLFormElement)) return;
        formSurat.reset();
        formSurat.action = tombol.dataset.action;
        formSurat.querySelector('[data-metode]').value = 'PUT';
        formSurat.querySelector('[data-judul-modal]').textContent = 'Edit Surat';
        isiForm(formSurat, bacaData(tombol.dataset.isian));
    });
});

const formProsesSurat = document.querySelector('[data-form-proses-surat]');
document.querySelectorAll('[data-proses-surat]').forEach((tombol) => {
    tombol.addEventListener('click', () => {
        if (!(formProsesSurat instanceof HTMLFormElement)) return;
        formProsesSurat.reset();
        formProsesSurat.action = tombol.dataset.action;
        formProsesSurat.querySelector('[data-nomor-surat]').textContent = tombol.dataset.nomor;
    });
});

const formKategori = document.querySelector('[data-form-kategori]');
document.querySelectorAll('[data-kategori-baru]').forEach((tombol) => tombol.addEventListener('click', () => {
    if (!(formKategori instanceof HTMLFormElement)) return;
    formKategori.reset();
    formKategori.action = '/master-data';
    formKategori.querySelector('[data-metode]').value = 'POST';
}));
document.querySelectorAll('[data-edit-kategori]').forEach((tombol) => tombol.addEventListener('click', () => {
    if (!(formKategori instanceof HTMLFormElement)) return;
    formKategori.action = tombol.dataset.action;
    formKategori.querySelector('[data-metode]').value = 'PUT';
    isiForm(formKategori, bacaData(tombol.dataset.isian));
}));

const formAkun = document.querySelector('[data-form-akun]');
document.querySelector('[data-akun-baru]')?.addEventListener('click', () => {
    if (!(formAkun instanceof HTMLFormElement)) return;
    formAkun.reset();
    formAkun.action = '/akun';
    formAkun.querySelector('[data-metode]').value = 'POST';
    formAkun.querySelector('[name=password]').required = true;
    formAkun.querySelector('[data-password-wajib]').hidden = false;
    formAkun.querySelector('[data-judul-modal]').textContent = 'Tambah Pengguna';
});
document.querySelectorAll('[data-edit-akun]').forEach((tombol) => tombol.addEventListener('click', () => {
    if (!(formAkun instanceof HTMLFormElement)) return;
    formAkun.reset();
    formAkun.action = tombol.dataset.action;
    formAkun.querySelector('[data-metode]').value = 'PUT';
    formAkun.querySelector('[name=password]').required = false;
    formAkun.querySelector('[data-password-wajib]').hidden = true;
    formAkun.querySelector('[data-judul-modal]').textContent = 'Edit Pengguna';
    isiForm(formAkun, bacaData(tombol.dataset.isian));
    formAkun.querySelector('[name=password]').value = '';
}));

const pratinjauPdf = document.querySelector('[data-pratinjau-pdf]');
if (pratinjauPdf instanceof HTMLElement) {
    const kanvas = pratinjauPdf.querySelector('canvas');
    const gagal = pratinjauPdf.querySelector('[data-gagal-pdf]');
    getDocument(pratinjauPdf.dataset.sumber).promise
        .then((pdf) => pdf.getPage(1))
        .then((halaman) => {
            if (!(kanvas instanceof HTMLCanvasElement)) return;
            const dasar = halaman.getViewport({ scale: 1 });
            const skala = Math.min(1.5, (pratinjauPdf.clientWidth - 24) / dasar.width);
            const tampilan = halaman.getViewport({ scale: skala });
            kanvas.width = tampilan.width;
            kanvas.height = tampilan.height;
            return halaman.render({ canvas: kanvas, canvasContext: kanvas.getContext('2d'), viewport: tampilan }).promise;
        })
        .catch(() => {
            if (gagal instanceof HTMLElement) gagal.hidden = false;
        });
}
