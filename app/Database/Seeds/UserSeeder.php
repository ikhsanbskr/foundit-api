<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run()
    {
        $data = [
            // Akun Utama Kamu (Mahasiswa)
            [
                'username'     => '241011403217', // NIM Riil 
                'password'     => password_hash('Testing123!', PASSWORD_BCRYPT),
                'fullname'     => 'Ikhsan Baskara', // Data Profil 
                'email'        => 'ikhsan.baskara@mahasiswa.unpam.ac.id',
                'role'         => 'mahasiswa',
                'phone_number' => '087840233052',
                'api_token'    => null,
                'status'       => 'active',
            ],
            [
                'username'     => '241011402833',
                'password'     => password_hash('Testing123!', PASSWORD_BCRYPT),
                'fullname'     => 'Rizky Saputra', // Data Profil 
                'email'        => 'rizkysaputra@mahasiswa.unpam.ac.id',
                'role'         => 'mahasiswa',
                'phone_number' => '085156482125',
                'api_token'    => null,
                'status'       => 'active',
            ],
            [
                'username'     => '241011401077',
                'password'     => password_hash('Testing123!', PASSWORD_BCRYPT),
                'fullname'     => 'Dzidan Prasetyo', // Data Profil 
                'email'        => 'dzidanprasetyo@mahasiswa.unpam.ac.id',
                'role'         => 'mahasiswa',
                'phone_number' => '0895334092792',
                'api_token'    => null,
                'status'       => 'active',
            ],
            [
                'username'     => '241011401102',
                'password'     => password_hash('Testing123!', PASSWORD_BCRYPT),
                'fullname'     => 'Kaisar Rizky', // Data Profil 
                'email'        => 'kaisarrizky@mahasiswa.unpam.ac.id',
                'role'         => 'mahasiswa',
                'phone_number' => '085770908836',
                'api_token'    => null,
                'status'       => 'active',
            ],
            // 9 Data Mahasiswa Mock Lainnya dengan Pola NIM Serupa
            [
                'username'     => '241011403218',
                'password'     => password_hash('Testing123!', PASSWORD_BCRYPT),
                'fullname'     => 'Raihan Putra Nusantara',
                'email'        => 'raihan.putra@mahasiswa.unpam.ac.id',
                'role'         => 'mahasiswa',
                'phone_number' => '081234567891',
                'api_token'    => null,
                'status'       => 'active',
            ],
            [
                'username'     => '241011403219',
                'password'     => password_hash('Testing123!', PASSWORD_BCRYPT),
                'fullname'     => 'Andika Pratama',
                'email'        => 'andika.pratama@mahasiswa.unpam.ac.id',
                'role'         => 'mahasiswa',
                'phone_number' => '081234567892',
                'api_token'    => null,
                'status'       => 'active',
            ],
            [
                'username'     => '241011403220',
                'password'     => password_hash('Testing123!', PASSWORD_BCRYPT),
                'fullname'     => 'Siti Aminah',
                'email'        => 'siti.aminah@mahasiswa.unpam.ac.id',
                'role'         => 'mahasiswa',
                'phone_number' => '081234567893',
                'api_token'    => null,
                'status'       => 'active',
            ],
            [
                'username'     => '241011403221',
                'password'     => password_hash('Testing123!', PASSWORD_BCRYPT),
                'fullname'     => 'Rizky Febrian',
                'email'        => 'rizky.febrian@mahasiswa.unpam.ac.id',
                'role'         => 'mahasiswa',
                'phone_number' => '081234567894',
                'api_token'    => null,
                'status'       => 'active',
            ],
            [
                'username'     => '241011403222',
                'password'     => password_hash('Testing123!', PASSWORD_BCRYPT),
                'fullname'     => 'Dewi Lestari',
                'email'        => 'dewi.lestari@mahasiswa.unpam.ac.id',
                'role'         => 'mahasiswa',
                'phone_number' => '081234567895',
                'api_token'    => null,
                'status'       => 'active',
            ],
            [
                'username'     => '241011403223',
                'password'     => password_hash('Testing123!', PASSWORD_BCRYPT),
                'fullname'     => 'Fajar Hidayat',
                'email'        => 'fajar.hidayat@mahasiswa.unpam.ac.id',
                'role'         => 'mahasiswa',
                'phone_number' => '081234567896',
                'api_token'    => null,
                'status'       => 'active',
            ],
            [
                'username'     => '241011403224',
                'password'     => password_hash('Testing123!', PASSWORD_BCRYPT),
                'fullname'     => 'Larasati Putri',
                'email'        => 'larasati.putri@mahasiswa.unpam.ac.id',
                'role'         => 'mahasiswa',
                'phone_number' => '081234567897',
                'api_token'    => null,
                'status'       => 'active',
            ],
            [
                'username'     => '241011403225',
                'password'     => password_hash('Testing123!', PASSWORD_BCRYPT),
                'fullname'     => 'Budi Utomo',
                'email'        => 'budi.utomo@mahasiswa.unpam.ac.id',
                'role'         => 'mahasiswa',
                'phone_number' => '081234567898',
                'api_token'    => null,
                'status'       => 'active',
            ],
            [
                'username'     => '241011403226',
                'password'     => password_hash('Testing123!', PASSWORD_BCRYPT),
                'fullname'     => 'Ayu Tingting',
                'email'        => 'ayu.tingting@mahasiswa.unpam.ac.id',
                'role'         => 'mahasiswa',
                'phone_number' => '081234567899',
                'api_token'    => null,
                'status'       => 'active',
            ],
            [
                'username'     => 'bambangsecure52', 
                'password'     => password_hash('Testing123!', PASSWORD_BCRYPT),
                'fullname'     => 'Bambang',
                'email'        => 'bambang.security@unpam.ac.id',
                'role'         => 'petugas',
                'phone_number' => '089988877766',
                'api_token'    => null,
                'status'       => 'active',
            ]
        ];

        $this->db->table('s_users')->insertBatch($data);
    }
}