<?php
declare(strict_types=1);
namespace App\Command;
use App\Integration\Freshdesk\Exception\FreshdeskApiException; use App\Integration\Freshdesk\FreshdeskClientInterface; use Symfony\Component\Console\Attribute\AsCommand; use Symfony\Component\Console\Command\Command; use Symfony\Component\Console\Input\InputInterface; use Symfony\Component\Console\Output\OutputInterface; use Symfony\Component\Console\Style\SymfonyStyle;
#[AsCommand(name:'cardnext:freshdesk:check',description:'Checks the read-only Freshdesk API connection.')]
final class FreshdeskCheckCommand extends Command { public function __construct(private readonly FreshdeskClientInterface $client){parent::__construct();} protected function execute(InputInterface $input,OutputInterface $output):int{$io=new SymfonyStyle($input,$output);try{$this->client->checkConnection();$io->success('Freshdesk API is reachable.');return self::SUCCESS;}catch(FreshdeskApiException $e){$io->error('Freshdesk API connection failed ('.$e::class.').');return self::FAILURE;}} }
