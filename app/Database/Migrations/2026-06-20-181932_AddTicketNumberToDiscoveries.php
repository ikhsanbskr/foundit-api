<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddTicketNumberToDiscoveries extends Migration
{
    public function up()
    {
        $this->forge->addColumn('g_item_discoveries', [
            'ticket_number' => [
                'type' => 'VARCHAR',
                'constraint' => '50',
                'null' => true,
                'after' => 'id'
            ]
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('g_item_discoveries', 'ticket_number');
    }
}
