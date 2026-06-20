<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run()
    {
        // 1. Injeksi data ke tabel master gm_category
        $categories = [
            ['id' => 1, 'category_name' => 'Elektronik'],
            ['id' => 2, 'category_name' => 'Dokumen & Kartu'],
            ['id' => 3, 'category_name' => 'Aksesoris & Pakaian'],
            ['id' => 4, 'category_name' => 'Kunci & Kendaraan'],
            ['id' => 5, 'category_name' => 'Lain-lain'],
        ];
        $this->db->table('gm_category')->insertBatch($categories);

        // 2. Injeksi data ke tabel detail gm_category_detail
        $categoryDetails = [
            // Elektronik (ID: 1)
            ['category_id' => 1, 'detail_name' => 'Smartphone'],
            ['category_id' => 1, 'detail_name' => 'Tablet'],
            ['category_id' => 1, 'detail_name' => 'Laptop'],
            ['category_id' => 1, 'detail_name' => 'Charger & Powerbank'],
            ['category_id' => 1, 'detail_name' => 'Earphone & TWS'],
            ['category_id' => 1, 'detail_name' => 'Elektronik lainnya'],

            // Dokumen (ID: 2)
            ['category_id' => 2, 'detail_name' => 'KTM'],
            ['category_id' => 2, 'detail_name' => 'KTP'],
            ['category_id' => 2, 'detail_name' => 'SIM'],
            ['category_id' => 2, 'detail_name' => 'Dompet'],
            ['category_id' => 2, 'detail_name' => 'Kartu ATM'],
            ['category_id' => 2, 'detail_name' => 'Buku Tabungan'],
            ['category_id' => 2, 'detail_name' => 'STNK'],
            ['category_id' => 2, 'detail_name' => 'Dokumen Lainnya'],

            // Aksesoris (ID: 3)
            ['category_id' => 3, 'detail_name' => 'Jam Tangan'],
            ['category_id' => 3, 'detail_name' => 'Jaket & Almamater'],
            ['category_id' => 3, 'detail_name' => 'Kacamata'],
            ['category_id' => 3, 'detail_name' => 'Aksesoris Lainnya'],

            // Kunci (ID: 4)
            ['category_id' => 4, 'detail_name' => 'Kunci Motor'],
            ['category_id' => 4, 'detail_name' => 'Kunci Mobil'],
            ['category_id' => 4, 'detail_name' => 'Jenis Kunci Lainnya'],

            // Lain-lain (ID: 5)
            ['category_id' => 5, 'detail_name' => 'Alat Tulis & Buku'],
            ['category_id' => 5, 'detail_name' => 'Botol Minum'],
            ['category_id' => 5, 'detail_name' => 'Payung'],
            ['category_id' => 5, 'detail_name' => 'Barang Lainnya']
        ];
        $this->db->table('gm_category_detail')->insertBatch($categoryDetails);
    }
}