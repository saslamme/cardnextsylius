<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\User\ShopUser;
use App\Security\Hasher\LegacyInteraktivShopPasswordHasher;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\PasswordUpgradeBadge;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

/**
 * Sylius' UsernameOrEmailProvider is not a PasswordUpgraderInterface. Upgrade the
 * already-authenticated legacy credential before Symfony erases its migration badge.
 */
#[AsEventListener(event: LoginSuccessEvent::class, priority: 20)]
final readonly class LegacyPasswordUpgradeListener
{
    public function __construct(
        private LegacyInteraktivShopPasswordHasher $legacyHasher,
        private UserPasswordHasherInterface $passwordHasher,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function __invoke(LoginSuccessEvent $event): void
    {
        $user = $event->getUser();
        if (!$user instanceof ShopUser || !$this->legacyHasher->isLegacyHash((string) $user->getPassword())) {
            return;
        }
        $passport = $event->getPassport();
        if (!$passport->hasBadge(PasswordUpgradeBadge::class)) {
            return;
        }
        /** @var PasswordUpgradeBadge $badge */
        $badge = $passport->getBadge(PasswordUpgradeBadge::class);
        $plainPassword = $badge->getAndErasePlaintextPassword();
        if ($plainPassword === '' || !$this->legacyHasher->verify((string) $user->getPassword(), $plainPassword)) {
            return;
        }

        $user->setPassword($this->passwordHasher->hashPassword($user, $plainPassword));
        $this->entityManager->flush();
    }
}
