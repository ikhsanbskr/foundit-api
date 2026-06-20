<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddEventTimeToDiscoveries extends Migration
{
    public function up()
    {
        $this->forge->addColumn('g_item_discoveries', [
            'event_time' => [
                'type' => 'DATETIME',
                'null' => true,
                'after' => 'description'
            ]
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('g_item_discoveries', 'event_time');
    }
}
