<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateGmCategoryTable extends Migration
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
            'category_name' => [
                'type'       => 'VARCHAR',
                'constraint' => '100',
                'unique'     => true, // Proteksi data ganda pada tingkat database
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
        $this->forge->createTable('gm_category'); 
    }

    public function down()
    {
        $this->forge->dropTable('gm_category');
    }
}