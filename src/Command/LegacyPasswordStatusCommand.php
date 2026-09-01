<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\User\ShopUser;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'cardnext:customers:legacy-password-status', description: 'Shows password migration counts without exposing hashes.')]
final class LegacyPasswordStatusCommand extends Command
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var list<ShopUser> $users */
        $users = $this->entityManager->getRepository(ShopUser::class)->findAll();
        $legacy = count(array_filter($users, static fn (ShopUser $user): bool => preg_match('/^[a-fA-F0-9]{32}$/D', (string) $user->getPassword()) === 1));
        (new SymfonyStyle($input, $output))->table(['Password type', 'Count'], [['Shop users', count($users)], ['Modern password hashes', count($users) - $legacy], ['Legacy interaktiv.shop hashes', $legacy]]);

        return Command::SUCCESS;
    }
}
