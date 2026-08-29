<?php

declare(strict_types=1);
namespace App\Service\Quote; use Doctrine\DBAL\Connection;
final class QuoteNumberGenerator { public function __construct(private Connection $connection){} public function next():string{$year=(int)date('Y');$this->connection->executeStatement('INSERT INTO cardnext_quote_sequence (`year`, next_value) VALUES (?, LAST_INSERT_ID(1)) ON DUPLICATE KEY UPDATE next_value = LAST_INSERT_ID(next_value + 1)',[$year]);$sequence=(int)$this->connection->lastInsertId();return sprintf('AN-%d-%05d',$year,$sequence);} }
