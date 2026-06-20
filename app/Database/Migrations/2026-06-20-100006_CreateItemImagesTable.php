<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateGItemImagesTable extends Migration
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
            'item_id' => [ // Foreign key ke g_item_discoveries
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
            ],
            'image_path' => [
                'type'       => 'VARCHAR',
                'constraint' => '255',
            ],
            'is_primary' => [ // Penting untuk UI: foto mana yang ditampilkan sebagai thumbnail?
                'type'       => 'BOOLEAN',
                'default'    => false,
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addForeignKey('item_id', 'g_item_discoveries', 'id', 'CASCADE', 'CASCADE');
        
        $this->forge->createTable('g_item_images');
    }

    public function down()
    {
        $this->forge->dropTable('g_item_images');
    }
}