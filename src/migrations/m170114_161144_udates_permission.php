<?php

namespace craft\migrations;

use craft\db\Migration;

/**
 * m170114_161144_udates_permission migration.
 */
class m170114_161144_udates_permission extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $this->update('{{%userpermissions}}', ['name' => 'utility:updates'], ['name' => 'performupdates']);

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m170114_161144_udates_permission cannot be reverted.\n";

        return false;
    }
}
