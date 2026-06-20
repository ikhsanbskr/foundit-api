<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateGMatchesTable extends Migration
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
            'lost_ticket' => [
                'type'       => 'VARCHAR',
                'constraint' => '50',
            ],
            'found_ticket' => [
                'type'       => 'VARCHAR',
                'constraint' => '50',
            ],
            'confidence_score' => [
                'type'       => 'DECIMAL',
                'constraint' => '5,2',
                'default'    => 0.00,
            ],
            'ai_reason' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'timestamp' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);
        
        $this->forge->addKey('id', true);
        $this->forge->createTable('g_matches');
    }

    public function down()
    {
        $this->forge->dropTable('g_matches');
    }
}
