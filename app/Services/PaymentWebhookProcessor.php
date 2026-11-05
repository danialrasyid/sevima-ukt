<?php

namespace App\Services;

use App\Models\Institusi;
use App\Models\Tagihan;
use App\Models\Transaksi;
use App\Models\WebhookLog;
use App\Support\PaymentPayload;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Alur webhook:
 *  1. catat notifikasi mentah ke webhook_logs (apa pun hasilnya, di luar transaksi bisnis)
 *  2. parse -> cari institusi -> verifikasi HMAC dengan secret institusi tsb
 *  3. validasi payload -> cari tagihan (dibatasi institusi)
 *  4. transaksi DB: kunci baris tagihan -> cek duplikat -> terapkan aturan status -> simpan transaksi
 *  5. perbarui outcome log
 *
 * Request webhook tidak punya pengguna login, jadi seluruh query model memakai
 * withoutGlobalScopes() + where('institusi_id', ...) secara eksplisit.
 */
class PaymentWebhookProcessor
{
    public function handle(Request $request): JsonResponse
    {
        $raw = $request->getContent();
        $log = $this->catat($request, $raw);

        try {
            return $this->proses($request, $raw, $log);
        } catch (Throwable $e) {
            report($e);
            $this->selesaikanLog($log, 'error_internal', 500, 'Kesalahan internal: '.class_basename($e));

            return $this->error(500, 'kesalahan_internal', 'Terjadi kesalahan internal. Silakan kirim ulang notifikasi.');
        }
    }

    private function proses(Request $request, string $raw, WebhookLog $log): JsonResponse
    {
        $data = json_decode($raw, true);

        if (! is_array($data)) {
            return $this->tolak($log, 400, 'payload_tidak_valid', 'Body harus berupa JSON object yang valid.');
        }

        $payload = PaymentPayload::fromArray(
            $data,
            $raw,
            $request->header('X-Institusi-Kode') ?? $request->header('X-Institution-Code'),
        );

        $institusi = $payload->kodeInstitusi !== null
            ? Institusi::query()->where('kode', $payload->kodeInstitusi)->first()
            : null;

        $log->update([
            'institusi_id' => $institusi?->id,
            'kode_institusi' => $this->potong($payload->kodeInstitusi, 100),
            'event_id' => $this->potong($payload->eventId, 191),
            'event_type' => $this->potong($payload->event, 50),
            'nomor_tagihan' => $this->potong($payload->nomorTagihan, 50),
        ]);

        // Verifikasi signature SEBELUM mempercayai isi payload.
        $valid = $institusi !== null
            && $this->signatureValid($raw, $request->header('X-Signature'), $institusi->webhook_secret);

        $log->update(['signature_valid' => $valid]);

        if (! $valid) {
            // Respons sama untuk institusi tidak dikenal dan signature salah (tidak membocorkan daftar institusi).
            return $this->tolak(
                $log, 401, 'signature_tidak_valid',
                'Signature tidak valid atau institusi tidak dikenal.', 'signature_ditolak'
            );
        }

        $errors = $payload->errors();
        if ($errors !== []) {
            return $this->tolak($log, 422, 'payload_tidak_valid', implode('; ', $errors), 'payload_ditolak');
        }

        $tagihanId = Tagihan::withoutGlobalScopes()
            ->where('institusi_id', $institusi->id)
            ->where('nomor_tagihan', $payload->nomorTagihan)
            ->value('id');

        if ($tagihanId === null) {
            return $this->tolak(
                $log, 404, 'tagihan_tidak_ditemukan',
                'Tagihan tidak ditemukan untuk institusi ini.', 'tagihan_tidak_ditemukan'
            );
        }

        try {
            $hasil = DB::transaction(fn () => $this->terapkan($institusi, (int) $tagihanId, $payload, $data));
        } catch (UniqueConstraintViolationException) {
            // Dua request identik berbalapan: yang kalah dianggap duplikat.
            $hasil = [
                'result' => 'duplicate',
                'log_outcome' => 'duplikat',
                'tagihan' => Tagihan::withoutGlobalScopes()->find($tagihanId),
                'sebelum' => null,
                'sesudah' => null,
                'catatan' => 'Event sudah pernah diproses (terdeteksi saat penyimpanan).',
            ];
        }

        /** @var Tagihan $tagihan */
        $tagihan = $hasil['tagihan'];

        $this->selesaikanLog($log, $hasil['log_outcome'], 200, $hasil['catatan']);

        Log::info('webhook.'.$hasil['log_outcome'], [
            'log_id' => $log->id,
            'institusi' => $institusi->kode,
            'nomor_tagihan' => $payload->nomorTagihan,
            'event_id' => $payload->eventId,
            'event' => $payload->event,
            'status_sebelum' => $hasil['sebelum'],
            'status_sesudah' => $hasil['sesudah'],
            'catatan' => $hasil['catatan'],
        ]);

        return response()->json([
            'status' => 'ok',
            'result' => $hasil['result'],
            'message' => $hasil['catatan'] ?? 'Notifikasi diproses.',
            'tagihan' => [
                'nomor_tagihan' => $tagihan->nomor_tagihan,
                'status' => $tagihan->status,
            ],
        ]);
    }

    /**
     * Dijalankan di dalam transaksi database.
     *
     * @return array{result: string, log_outcome: string, tagihan: Tagihan, sebelum: ?string, sesudah: ?string, catatan: ?string}
     */
    private function terapkan(Institusi $institusi, int $tagihanId, PaymentPayload $p, array $data): array
    {
        // Kunci baris: dua webhook untuk tagihan yang sama dijalankan berurutan.
        $tagihan = Tagihan::withoutGlobalScopes()
            ->where('institusi_id', $institusi->id)
            ->whereKey($tagihanId)
            ->lockForUpdate()
            ->firstOrFail();

        $sudahAda = Transaksi::withoutGlobalScopes()
            ->where('institusi_id', $institusi->id)
            ->where('event_id', $p->eventId)
            ->exists();

        if ($sudahAda) {
            return [
                'result' => 'duplicate',
                'log_outcome' => 'duplikat',
                'tagihan' => $tagihan,
                'sebelum' => $tagihan->status,
                'sesudah' => $tagihan->status,
                'catatan' => 'Event sudah pernah diproses; tidak ada perubahan.',
            ];
        }

        $sebelum = $tagihan->status;
        $hasil = Transaksi::HASIL_DITERAPKAN;
        $catatan = null;
        $nominalEvent = $p->amount();

        if ($p->event === PaymentPayload::EVENT_SUCCESS) {
            if ($nominalEvent !== null && $nominalEvent !== (int) $tagihan->nominal) {
                $hasil = Transaksi::HASIL_DITANDAI;
                $catatan = "Nominal pembayaran ({$nominalEvent}) tidak sama dengan nominal tagihan ({$tagihan->nominal}); status tidak diubah, perlu ditinjau.";
                $this->tandaiReview($tagihan, $catatan);
            } elseif ($tagihan->status === Tagihan::LUNAS) {
                $hasil = Transaksi::HASIL_DIABAIKAN;
                $catatan = 'Pembayaran ganda: tagihan sudah lunas. Perlu ditinjau untuk pengembalian dana.';
                $this->tandaiReview($tagihan, $catatan);
            } else {
                if ($tagihan->status === Tagihan::KEDALUWARSA) {
                    $catatan = 'Pembayaran diterima setelah tagihan kedaluwarsa; status dikoreksi menjadi lunas.';
                }
                $tagihan->status = Tagihan::LUNAS;
                $tagihan->paid_at = $p->paidAt() ?? now();
            }
        } else { // payment.expired
            if ($tagihan->status === Tagihan::LUNAS) {
                $hasil = Transaksi::HASIL_DIABAIKAN;
                $catatan = 'Event kedaluwarsa diabaikan: tagihan sudah lunas.';
            } elseif ($tagihan->status === Tagihan::KEDALUWARSA) {
                $hasil = Transaksi::HASIL_DIABAIKAN;
                $catatan = 'Tagihan sudah berstatus kedaluwarsa.';
            } else {
                $tagihan->status = Tagihan::KEDALUWARSA;
            }
        }

        if ($tagihan->isDirty()) {
            $tagihan->save();
        }

        Transaksi::create([
            'institusi_id' => $institusi->id,
            'tagihan_id' => $tagihan->id,
            'event_id' => $p->eventId,
            'event_type' => $p->event,
            'nominal' => $nominalEvent,
            'status_sebelum' => $sebelum,
            'status_sesudah' => $tagihan->status,
            'hasil' => $hasil,
            'keterangan' => $catatan,
            'payload' => $data,
            'occurred_at' => $p->paidAt() ?? now(),
        ]);

        return [
            'result' => match ($hasil) {
                Transaksi::HASIL_DITERAPKAN => 'processed',
                Transaksi::HASIL_DIABAIKAN => 'ignored',
                default => 'flagged',
            },
            'log_outcome' => match ($hasil) {
                Transaksi::HASIL_DITERAPKAN => 'diproses',
                Transaksi::HASIL_DIABAIKAN => 'diabaikan',
                default => 'ditandai',
            },
            'tagihan' => $tagihan,
            'sebelum' => $sebelum,
            'sesudah' => $tagihan->status,
            'catatan' => $catatan,
        ];
    }

    private function tandaiReview(Tagihan $tagihan, string $catatan): void
    {
        $tagihan->perlu_review = true;
        $tagihan->catatan_review = trim(($tagihan->catatan_review ? $tagihan->catatan_review."\n" : '').$catatan);
    }

    /**
     * HMAC-SHA256 dihitung dari BODY MENTAH (byte asli), dibandingkan constant-time.
     * Header boleh berupa hex polos atau berawalan "sha256=".
     */
    private function signatureValid(string $raw, ?string $header, string $secret): bool
    {
        $signature = strtolower(trim((string) $header));

        if (str_starts_with($signature, 'sha256=')) {
            $signature = substr($signature, 7);
        }

        if ($signature === '' || $secret === '') {
            return false;
        }

        return hash_equals(hash_hmac('sha256', $raw, $secret), $signature);
    }

    private function catat(Request $request, string $raw): WebhookLog
    {
        $signature = $request->header('X-Signature');

        $headers = [];
        foreach (Arr::only($request->headers->all(), ['content-type', 'accept', 'user-agent', 'x-signature', 'x-institusi-kode', 'x-institution-code']) as $nama => $nilai) {
            $headers[$nama] = $this->bersihkan((string) ($nilai[0] ?? ''), 256);
        }

        return WebhookLog::create([
            'headers' => $headers,
            'raw_body' => $this->bersihkan($raw),
            'signature_header' => $signature !== null ? $this->bersihkan($signature, 512) : null,
            'ip' => $request->ip(),
            'outcome' => 'diterima',
            'received_at' => now(),
        ]);
    }

    private function selesaikanLog(WebhookLog $log, string $outcome, int $status, ?string $catatan): void
    {
        $log->update([
            'outcome' => $outcome,
            'http_status' => $status,
            'catatan' => $catatan,
        ]);
    }

    private function tolak(WebhookLog $log, int $status, string $code, string $message, string $outcome = 'ditolak'): JsonResponse
    {
        $this->selesaikanLog($log, $outcome, $status, $message);

        Log::warning('webhook.'.$outcome, [
            'log_id' => $log->id,
            'code' => $code,
            'institusi_id' => $log->institusi_id,
            'kode_institusi' => $log->kode_institusi,
            'nomor_tagihan' => $log->nomor_tagihan,
            'event_id' => $log->event_id,
            'ip' => $log->ip,
            'pesan' => $message,
        ]);

        return $this->error($status, $code, $message);
    }

    private function error(int $status, string $code, string $message): JsonResponse
    {
        return response()->json(['status' => 'error', 'code' => $code, 'message' => $message], $status);
    }

    /**
     * Postgres menolak byte NUL dan UTF-8 tidak valid pada kolom teks/jsonb.
     */
    private function bersihkan(string $nilai, int $maks = 65535): string
    {
        $nilai = str_replace("\0", '', mb_scrub($nilai, 'UTF-8'));

        return mb_strcut($nilai, 0, $maks, 'UTF-8');
    }

    private function potong(?string $nilai, int $maks): ?string
    {
        return $nilai === null ? null : Str::limit($nilai, $maks, '');
    }
}
