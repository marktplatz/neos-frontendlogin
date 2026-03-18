<?php

declare(strict_types=1);

namespace Neos\Flow\Persistence\Doctrine\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260318111128 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE upassist_neos_frontendlogin_domain_model_autoapproveddomain (persistence_object_identifier VARCHAR(40) NOT NULL, domain VARCHAR(255) NOT NULL, PRIMARY KEY(persistence_object_identifier)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_35C1A1D4A7A91E0B ON upassist_neos_frontendlogin_domain_model_autoapproveddomain (domain)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX UNIQ_35C1A1D4A7A91E0B ON upassist_neos_frontendlogin_domain_model_autoapproveddomain');
        $this->addSql('DROP TABLE upassist_neos_frontendlogin_domain_model_autoapproveddomain');
    }
}
