<?php

namespace App\Support;

use Carbon\Carbon;
use Illuminate\Support\Arr;
use Throwable;

/**
 * Adapter format payload mock payment gateway.
 *
 * ASUMSI: nama field di bawah adalah tebakan berdasarkan dokumen tes. Setelah
 * membaca dokumentasi mock gateway dari panitia, SESUAIKAN daftar KEYS di sini.
 * Ini satu-satunya tempat yang tahu bentuk payload gateway.
 */
final class PaymentPayload
{
    public const EVENT_SUCCESS = 'payment.success';

    public const EVENT_EXPIRED = 'payment.expired';

    /** Daftar kemungkinan nama field (notasi titik untuk nested), diprioritaskan dari kiri. */
    private const KEYS = [
        'institusi' => ['institusi', 'kode_institusi', 'institution', 'institution_code', 'institusi_kode', 'data.institusi', 'data.kode_institusi'],
        'event' => ['event', 'event_type', 'type'],
        'nomor' => ['nomor_tagihan', 'invoice_number', 'invoice_no', 'data.nomor_tagihan', 'data.invoice_number'],
        'event_id' => ['event_id', 'id', 'transaction_id', 'data.transaction_id'],
        'amount' => ['amount', 'nominal', 'data.amount', 'data.nominal'],
        'paid_at' => ['paid_at', 'paidAt', 'timestamp', 'data.paid_at'],
    ];

    private function __construct(
        public readonly ?string $kodeInstitusi,
        public readonly ?string $event,
        public readonly ?string $nomorTagihan,
        public readonly string $eventId,
        private readonly mixed $amountRaw,
        private readonly mixed $paidAtRaw,
    ) {}

    public static function fromArray(array $data, string $rawBody, ?string $kodeDariHeader = null): self
    {
        $event = self::text(self::pick($data, 'event'));

        // Jika gateway tidak menyertakan id event, body mentah yang identik (retry) dianggap event yang sama.
        $eventId = self::text(self::pick($data, 'event_id')) ?? 'sha256:'.hash('sha256', $rawBody);

        return new self(
            kodeInstitusi: self::text(self::pick($data, 'institusi')) ?? self::text($kodeDariHeader),
            event: $event !== null ? strtolower($event) : null,
            nomorTagihan: self::text(self::pick($data, 'nomor')),
            eventId: $eventId,
            amountRaw: self::pick($data, 'amount'),
            paidAtRaw: self::pick($data, 'paid_at'),
        );
    }

    public function amount(): ?int
    {
        if ($this->amountRaw === null || ! is_numeric($this->amountRaw)) {
            return null;
        }

        return (int) round((float) $this->amountRaw);
    }

    public function paidAt(): ?Carbon
    {
        if ($this->paidAtRaw === null || $this->paidAtRaw === '') {
            return null;
        }

        try {
            return is_numeric($this->paidAtRaw)
                ? Carbon::createFromTimestamp((int) $this->paidAtRaw)
                : Carbon::parse((string) $this->paidAtRaw);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @return list<string>
     */
    public function errors(): array
    {
        $errors = [];

        if ($this->nomorTagihan === null || $this->nomorTagihan === '') {
            $errors[] = 'nomor tagihan wajib diisi';
        } elseif (strlen($this->nomorTagihan) > 50) {
            $errors[] = 'nomor tagihan terlalu panjang (maks 50 karakter)';
        }

        if (! in_array($this->event, [self::EVENT_SUCCESS, self::EVENT_EXPIRED], true)) {
            $errors[] = 'jenis event tidak dikenal: '.($this->event ?? '(kosong)');
        }

        if ($this->amountRaw !== null && ! is_numeric($this->amountRaw)) {
            $errors[] = 'amount harus berupa angka';
        }

        if (strlen($this->eventId) > 191) {
            $errors[] = 'event_id terlalu panjang (maks 191 karakter)';
        }

        return $errors;
    }

    private static function pick(array $data, string $field): mixed
    {
        foreach (self::KEYS[$field] as $key) {
            $value = Arr::get($data, $key);

            if ($value !== null && $value !== '') {
                return $value;
            }
        }

        return null;
    }

    private static function text(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
