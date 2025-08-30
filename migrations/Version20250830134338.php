<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250830134338 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    protected array $affectedTables = [
        'council_entry' => 'variables',
        'council_entry_template' => 'variable_types',
        'dig_timer' => 'dig_cache',
        'gazette_log_entry' => 'variables',
        'gazette_entry_template' => 'variable_types',
        'log_entry_template' => 'variable_types',
        'private_message' => 'items',
        'town' => 'conf',
        'town_log_entry' => 'variables',
        'user' => 'name_history',
    ];

    public function up(Schema $schema): void
    {
        foreach ( $this->affectedTables as $table => $column ) {
            $rows = $this->connection->fetchAllAssociative("SELECT id, $column FROM $table");
            foreach ($rows as $row) {
                if ($row[$column] === null) continue;
                $this->connection->executeQuery("UPDATE $table SET $column = :data WHERE id = :id", [
                    'data' => json_encode(unserialize($row[$column])),
                    'id' => $row['id'],
                ]);
            }
        }
    }

    public function down(Schema $schema): void
    {
        foreach ( $this->affectedTables as $table => $column ) {
            $rows = $this->connection->fetchAllAssociative("SELECT id, $column FROM $table");
            foreach ($rows as $row) {
                if ($row[$column] === null) continue;
                $this->connection->executeQuery("UPDATE $table SET $column = :data WHERE id = :id", [
                    'data' => serialize(json_decode($row[$column], true)),
                    'id' => $row['id'],
                ]);
            }
        }
    }
}
