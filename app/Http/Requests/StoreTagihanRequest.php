<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTagihanRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && $user->isAdminKeuangan() && $user->institusi_id !== null;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'nomor_tagihan' => trim((string) $this->input('nomor_tagihan')),
            'semester' => trim((string) $this->input('semester')),
        ]);
    }

    public function rules(): array
    {
        // institusi_id TIDAK pernah dibaca dari input; selalu dari pengguna login.
        $institusiId = $this->user()->institusi_id;
        $mahasiswaId = (int) $this->input('mahasiswa_id');

        return [
            'mahasiswa_id' => [
                'bail', 'required', 'integer',
                Rule::exists('mahasiswa', 'id')->where('institusi_id', $institusiId),
            ],
            'nomor_tagihan' => [
                'required', 'string', 'max:50',
                Rule::unique('tagihan', 'nomor_tagihan')->where('institusi_id', $institusiId),
            ],
            'semester' => [
                'required', 'string', 'max:30',
                Rule::unique('tagihan', 'semester')->where(
                    fn ($q) => $q->where('institusi_id', $institusiId)->where('mahasiswa_id', $mahasiswaId)
                ),
            ],
            'nominal' => ['required', 'integer', 'min:1', 'max:1000000000000'],
            'tanggal_kedaluwarsa' => ['required', 'date', 'after:now'],
        ];
    }

    public function messages(): array
    {
        return [
            'required' => ':attribute wajib diisi.',
            'integer' => ':attribute harus berupa angka bulat.',
            'max' => ':attribute terlalu besar/panjang.',
            'min' => ':attribute minimal :min.',
            'date' => ':attribute bukan tanggal yang valid.',
            'tanggal_kedaluwarsa.after' => 'Tanggal kedaluwarsa harus di masa depan.',
            'mahasiswa_id.exists' => 'Mahasiswa tidak ditemukan di institusi Anda.',
            'nomor_tagihan.unique' => 'Nomor tagihan sudah dipakai di institusi Anda.',
            'semester.unique' => 'Mahasiswa ini sudah memiliki tagihan untuk semester tersebut.',
        ];
    }

    public function attributes(): array
    {
        return [
            'mahasiswa_id' => 'Mahasiswa',
            'nomor_tagihan' => 'Nomor tagihan',
            'semester' => 'Semester',
            'nominal' => 'Nominal',
            'tanggal_kedaluwarsa' => 'Tanggal kedaluwarsa',
        ];
    }
}
