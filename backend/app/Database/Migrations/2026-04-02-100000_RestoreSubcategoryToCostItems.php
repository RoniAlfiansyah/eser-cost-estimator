<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class RestoreSubcategoryToCostItems extends Migration
{
    public function up()
    {
        $this->db->query('ALTER TABLE `cost_items` DROP INDEX `category_id_item_name`');
        $this->db->query('ALTER TABLE `cost_items` ADD COLUMN `subcategory_id` INT(11) UNSIGNED NOT NULL AFTER `category_id`');
        $this->db->query('ALTER TABLE `cost_items` ADD KEY `subcategory_id` (`subcategory_id`)');
        $this->db->query('ALTER TABLE `cost_items` ADD CONSTRAINT `cost_items_subcategory_id_foreign` FOREIGN KEY (`subcategory_id`) REFERENCES `subcategories` (`id`) ON DELETE CASCADE');
        $this->db->query('ALTER TABLE `cost_items` ADD UNIQUE KEY `category_id_subcategory_id_item_name` (`category_id`, `subcategory_id`, `item_name`)');
    }

    public function down()
    {
        $this->db->query('ALTER TABLE `cost_items` DROP FOREIGN KEY `cost_items_subcategory_id_foreign`');
        $this->db->query('ALTER TABLE `cost_items` DROP INDEX `category_id_subcategory_id_item_name`');
        $this->db->query('ALTER TABLE `cost_items` DROP INDEX `subcategory_id`');
        $this->db->query('ALTER TABLE `cost_items` DROP COLUMN `subcategory_id`');
        $this->db->query('ALTER TABLE `cost_items` ADD UNIQUE KEY `category_id_item_name` (`category_id`, `item_name`)');
    }
}
