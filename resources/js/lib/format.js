const formatRupiah = new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    maximumFractionDigits: 0,
});

const formatTanggal = new Intl.DateTimeFormat('id-ID', {
    dateStyle: 'medium',
    timeStyle: 'short',
    timeZone: 'Asia/Jakarta',
});

export const rupiah = (nilai) =>
    nilai === null || nilai === undefined ? '-' : formatRupiah.format(nilai);

export const tanggal = (iso) => (iso ? formatTanggal.format(new Date(iso)) : '-');

export const labelHasil = {
    diterapkan: 'Diterapkan',
    diabaikan: 'Diabaikan',
    ditandai: 'Perlu ditinjau',
};

export const labelEvent = {
    'payment.success': 'Pembayaran berhasil',
    'payment.expired': 'Pembayaran kedaluwarsa',
    'system.expired': 'Kedaluwarsa otomatis',
};
