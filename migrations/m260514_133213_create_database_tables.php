<?php

use yii\db\Migration;

class m260514_133213_create_database_tables extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->execute("CREATE TABLE IF NOT EXISTS `operating_systems` (
            `id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
            `name` varchar(255) NOT NULL
        );");

        $this->execute("CREATE TABLE IF NOT EXISTS `architectures` (
            `id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
            `name` varchar(255) NOT NULL
        );");

        $this->execute("CREATE TABLE IF NOT EXISTS `browsers` (
            `id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
            `name` varchar(255) NOT NULL
        );");

        $this->execute("CREATE TABLE IF NOT EXISTS `logs` (
            `id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
            `ip` varchar(255) NOT NULL,
            `requested_at` DATETIME NULL,
            `url` varchar(255) NOT NULL,
            `user_agent` varchar(255) NOT NULL,
            `operating_system_id` int(11) NULL DEFAULT NULL,
            `architecture_id` int(11) NULL DEFAULT NULL,
            `browser_id` int(11) NULL DEFAULT NULL,
            `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT `FK_architectures` FOREIGN KEY (`architecture_id`)
                        REFERENCES `architectures`(`id`),
            CONSTRAINT `FK_browsers` FOREIGN KEY (`browser_id`)
                        REFERENCES `browsers`(`id`),
            CONSTRAINT `FK_operating_systems` FOREIGN KEY (`operating_system_id`)
                        REFERENCES `operating_systems`(`id`)
        );");
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->execute("DROP TABLE IF EXISTS `logs`");
        $this->execute("DROP TABLE IF EXISTS `operating_systems`");
        $this->execute("DROP TABLE IF EXISTS `architectures`");
        $this->execute("DROP TABLE IF EXISTS `browsers`");
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m260514_133213_create_database_tables cannot be reverted.\n";

        return false;
    }
    */
}
