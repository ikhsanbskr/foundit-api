<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateUsersTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'username' => [
                'type'       => 'VARCHAR',
                'constraint' => 50,
                'unique'     => true,
            ],
            'password' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
            ],
            'fullname' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
            ],
            'email' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
                'unique'     => true,
            ],
            'role' => [
                'type'       => 'ENUM',
                'constraint' => ['mahasiswa', 'petugas'],
                'default'    => 'mahasiswa',
            ],
            'phone_number' => [
                'type'       => 'VARCHAR',
                'constraint' => 15,
                'null'       => true,
            ],
            'api_token' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true, // Tempat menyimpan token sementara setelah login
            ],
            'status' => [
                'type'       => 'ENUM',
                'constraint' => ['active', 'nonactive'],
                'default'    => 'active',
            ],
            'created_at datetime default current_timestamp',
            'updated_at datetime default current_timestamp on update current_timestamp',
        ]);

        $this->forge->addKey('id', true);
        $this->forge->createTable('s_users');

        // Indeks gabungan untuk optimasi kueri auth saat login & token validation
        $this->db->query('ALTER TABLE `s_users` ADD INDEX `idx_auth_login` (`username`, `status`)');
        $this->db->query('ALTER TABLE `s_users` ADD INDEX `idx_auth_token` (`api_token`)');
    }

    public function down()
    {
        $this->forge->dropTable('s_users');
    }
}