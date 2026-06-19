<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReferensiHargaProduksiRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        // Mendapatkan ID record saat ini (untuk update ignore)
        $id = $this->route('referensi_harga_produksi')?->id ?? $this->route('referensi_harga_produksi');

        $uniqueRule = Rule::unique('referensi_harga_produksi', 'id_ukuran')
            ->where('id_jenis_kayu', $this->id_jenis_kayu)
            ->where('jenis_barang', $this->jenis_barang)
            ->where('kw', $this->kw);

        if ($id) {
            $uniqueRule->ignore($id);
        }

        return [
            'nama' => [
                'nullable',
                'string',
                'max:255',
            ],
            'id_ukuran' => [
                'nullable',
                'exists:ukurans,id',
                $uniqueRule
            ],
            'id_jenis_kayu' => [
                'nullable',
                'exists:jenis_kayus,id'
            ],
            'id_sub_anak_akun' => [
                'nullable',
                'exists:sub_anak_akuns,id'
            ],
            'jenis_barang' => [
                'nullable',
                'string',
                'max:100'
            ],
            'kw' => [
                'nullable',
                'string',
                'max:50'
            ],
            'harga' => [
                'nullable',
                'numeric',
                'min:0'
            ],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'id_ukuran.required' => 'Ukuran wajib diisi.',
            'id_ukuran.exists' => 'Ukuran tidak valid.',
            'id_ukuran.unique' => 'Kombinasi Ukuran, Jenis Kayu, Jenis Barang, dan KW sudah terdaftar di sistem.',
            'id_jenis_kayu.required' => 'Jenis kayu wajib diisi.',
            'id_jenis_kayu.exists' => 'Jenis kayu tidak valid.',
            'jenis_barang.required' => 'Jenis barang wajib dipilih.',
            'jenis_barang.in' => 'Jenis barang tidak valid.',
            'kw.required' => 'KW wajib diisi.',
            'harga.required' => 'Harga wajib diisi.',
            'harga.numeric' => 'Harga harus berupa angka.',
            'harga.min' => 'Harga tidak boleh bernilai negatif.',
        ];
    }
}
