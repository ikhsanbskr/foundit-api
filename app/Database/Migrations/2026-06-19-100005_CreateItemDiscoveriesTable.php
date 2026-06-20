<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateGItemDiscoveriesTable extends Migration
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
            'user_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
            ],
            'category_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
            ],
            'category_detail_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
            ],
            // MENJADI INI (Samakan constraint-nya dengan ID standar CI4 yaitu 11):
            'bank_id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'null'           => true,
            ],
            'report_type' => [
                'type'       => 'ENUM',
                'constraint' => ['LOST', 'FOUND'],
            ],
            'status' => [
                'type'       => 'ENUM',
                'constraint' => ['REPORTED', 'SECURED', 'RESOLVED'],
                'default'    => 'REPORTED',
            ],
            'location_found' => [
                'type'       => 'VARCHAR',
                'constraint' => '255',
            ],
            'description' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'verification_description' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'bounty_amount' => [
                'type'       => 'DECIMAL',
                'constraint' => '15,2',
                'default'    => 0.00,
            ],
            'account_number' => [
                'type'       => 'VARCHAR',
                'constraint' => '50',
                'null'       => true,
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

        // Foreign Keys
        $this->forge->addForeignKey('user_id', 's_users', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->addForeignKey('category_id', 'gm_category', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->addForeignKey('category_detail_id', 'gm_category_detail', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->addForeignKey('bank_id', 'gm_bank_type', 'id', 'SET NULL', 'CASCADE');

        $this->forge->createTable('g_item_discoveries');
    }

    public function down()
    {
        $this->forge->dropTable('g_item_discoveries');
    }
}

