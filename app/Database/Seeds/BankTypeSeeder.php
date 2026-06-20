<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class BankTypeSeeder extends Seeder
{
  public function run()
  {
    $data = [
      [
        'bank_name' => 'Bank Central Asia (BCA)',
        'bank_code' => 'BCA'
      ],
      [
        'bank_name' => 'Bank Mandiri',
        'bank_code' => 'MANDIRI'
      ],
      [
        'bank_name' => 'Bank Rakyat Indonesia (BRI)',
        'bank_code' => 'BRI'
      ],
      [
        'bank_name' => 'Bank Negara Indonesia (BNI)',
        'bank_code' => 'BNI'
      ],
      [
        'bank_name' => 'Bank Syariah Indonesia (BSI)',
        'bank_code' => 'BSI'
      ],
      [
        'bank_name' => 'SeaBank',
        'bank_code' => 'SEABANK'
      ],
      [
        'bank_name' => 'Bank Jago',
        'bank_code' => 'JAGO'
      ],
      [
        'bank_name' => 'Dana',
        'bank_code' => 'DANA'
      ],
      [
        'bank_name' => 'Gopay',
        'bank_code' => 'GOPAY'
      ],
      [
        'bank_name' => 'ShopeePay',
        'bank_code' => 'SHOPEEPAY'
      ],
    ];

    // Menggunakan insertBatch untuk efisiensi performa
    $this->db->table('gm_bank_type')->insertBatch($data);
  }
}
