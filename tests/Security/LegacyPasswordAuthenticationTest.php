<?php

declare(strict_types=1);

namespace App\Tests\Security;

use App\Entity\Customer\Customer;
use App\Entity\User\ShopUser;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class LegacyPasswordAuthenticationTest extends WebTestCase
{
    private const EMAIL = 'legacy-login-test@example.test';

    private const LEGACY_HASH = '4ba9b4b88f8253e46bf219306d1f5601';

    private const PASSWORD = 'cawima86';

    private KernelBrowser $client;

    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        $this->client = self::createClient();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        if (!$entityManager instanceof EntityManagerInterface) {
            throw new \LogicException('The test requires Doctrine ORM.');
        }
        $this->entityManager = $entityManager;

        $existingUser = $this->entityManager->getRepository(ShopUser::class)->findOneBy(['username' => self::EMAIL]);
        if ($existingUser instanceof ShopUser) {
            $this->entityManager->remove($existingUser);
            $this->entityManager->flush();
        }
        $existingCustomer = $this->entityManager->getRepository(Customer::class)->findOneBy(['email' => self::EMAIL]);
        if ($existingCustomer instanceof Customer) {
            $this->entityManager->remove($existingCustomer);
            $this->entityManager->flush();
        }

        $customer = new Customer();
        $customer->setEmail(self::EMAIL);

        $user = new ShopUser();
        $user->setCustomer($customer);
        $user->setUsername(self::EMAIL);
        $user->setUsernameCanonical(self::EMAIL);
        $user->setPassword(self::LEGACY_HASH);
        $user->setEnabled(true);

        $this->entityManager->persist($customer);
        $this->entityManager->persist($user);
        $this->entityManager->flush();
    }

    public function testStorefrontLoginAuthenticatesAndUpgradesLegacyPassword(): void
    {
        $this->login('not-the-password');
        self::assertResponseRedirects('/login');

        $this->login(self::PASSWORD);
        self::assertResponseRedirects();
        self::assertNotSame('/login', $this->client->getResponse()->headers->get('Location'));

        $this->entityManager->clear();
        $upgradedUser = $this->entityManager->getRepository(ShopUser::class)->findOneBy(['username' => self::EMAIL]);
        self::assertInstanceOf(ShopUser::class, $upgradedUser);
        self::assertNotSame(self::LEGACY_HASH, $upgradedUser->getPassword());
        self::assertDoesNotMatchRegularExpression('/^[a-f0-9]{32}$/i', (string) $upgradedUser->getPassword());

        $passwordHasher = self::getContainer()->get(UserPasswordHasherInterface::class);
        if (!$passwordHasher instanceof UserPasswordHasherInterface) {
            throw new \LogicException('The Symfony user password hasher is unavailable.');
        }
        self::assertTrue($passwordHasher->isPasswordValid($upgradedUser, self::PASSWORD));

        $this->client->request('GET', '/logout');
        $this->login(self::PASSWORD);
        self::assertResponseRedirects();
        self::assertNotSame('/login', $this->client->getResponse()->headers->get('Location'));
    }

    private function login(string $password): void
    {
        $crawler = $this->client->request('GET', '/login');
        self::assertResponseIsSuccessful();

        $form = $crawler->filter('form[action$="/login-check"]')->form([
            '_username' => self::EMAIL,
            '_password' => $password,
        ]);
        $this->client->submit($form);
    }
}
