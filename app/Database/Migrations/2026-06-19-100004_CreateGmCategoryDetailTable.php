<?php

namespace App\Database\Migrations;

 use CodeIgniter\Database\Migration;

class CreateGmCategoryDetailTable extends Migration
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
            'category_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true, // Struktur tipe wajib identik dengan induknya
            ],
            'detail_name' => [
                'type'       => 'VARCHAR',
                'constraint' => '100',
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'updated_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addKey('category_id'); // Menambahkan indeks untuk optimasi join query
        
        // Deklarasi Integritas Data Relasional
        $this->forge->addForeignKey('category_id', 'gm_category', 'id', 'CASCADE', 'CASCADE');
        
        $this->forge->createTable('gm_category_detail'); // Nama tabel detail baru
    }

    public function down()
    {
        $this->forge->dropTable('gm_category_detail');
    }
}