<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddItemNameToDiscoveries extends Migration
{
    public function up()
    {
        $fields = [
            'item_name' => [
                'type'       => 'VARCHAR',
                'constraint' => '150',
                'after'      => 'status',
                'null'       => false,
            ],
        ];

        $this->forge->addColumn('g_item_discoveries', $fields);
    }

    public function down()
    {
        $this->forge->dropColumn('g_item_discoveries', 'item_name');
    }
}
