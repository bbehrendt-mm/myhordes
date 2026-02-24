<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260215154040 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE user DROP FOREIGN KEY `FK_8D93D64986383B10`');
        $this->addSql('DROP INDEX UNIQ_8D93D64986383B10 ON user');
        $this->addSql('ALTER TABLE user DROP avatar_id');
        $this->addSql('DROP TABLE avatar');
        $this->addSql('ALTER TABLE award DROP custom_icon, DROP custom_icon_name, DROP custom_icon_format');
        $this->addSql('ALTER TABLE external_app DROP image, DROP image_name, DROP image_format');
        $this->addSql('ALTER TABLE official_group DROP icon, DROP avatar_name, DROP avatar_ext');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE TABLE avatar (
              id INT AUTO_INCREMENT NOT NULL,
              format VARCHAR(9) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`,
              filename VARCHAR(32) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`,
              changed DATETIME NOT NULL,
              image LONGBLOB NOT NULL,
              small_image LONGBLOB DEFAULT NULL,
              small_name VARCHAR(32) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`,
              x INT NOT NULL,
              y INT NOT NULL,
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = ''
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              award
            ADD
              custom_icon LONGBLOB DEFAULT NULL,
            ADD
              custom_icon_name VARCHAR(32) DEFAULT NULL,
            ADD
              custom_icon_format VARCHAR(9) DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              external_app
            ADD
              image LONGBLOB DEFAULT NULL,
            ADD
              image_name VARCHAR(32) DEFAULT NULL,
            ADD
              image_format VARCHAR(9) DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              official_group
            ADD
              icon LONGBLOB DEFAULT NULL,
            ADD
              avatar_name VARCHAR(32) DEFAULT NULL,
            ADD
              avatar_ext VARCHAR(9) DEFAULT NULL
        SQL);
        $this->addSql('ALTER TABLE `user` ADD avatar_id INT DEFAULT NULL');
        $this->addSql(<<<'SQL'
            ALTER TABLE
              `user`
            ADD
              CONSTRAINT `FK_8D93D64986383B10` FOREIGN KEY (avatar_id) REFERENCES avatar (id)
        SQL);
        $this->addSql('CREATE UNIQUE INDEX UNIQ_8D93D64986383B10 ON `user` (avatar_id)');
    }
}
