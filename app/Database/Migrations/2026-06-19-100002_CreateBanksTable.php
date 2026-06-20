<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateBanksTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'        => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'bank_name' => ['type' => 'VARCHAR', 'constraint' => '100'],
            'bank_code' => ['type' => 'VARCHAR', 'constraint' => '20'],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->createTable('gm_bank_type');
    }

    public function down()
    {
        $this->forge->dropTable('gm_bank_type');
    }
}