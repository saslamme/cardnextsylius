<?php

declare(strict_types=1);

namespace App\Controller\Shop;

use App\Dto\Configurator\ConfiguratorConfiguration;
use App\Entity\Channel\Channel;
use App\Entity\Configurator\SavedConfiguratorConfiguration;
use App\Exception\Configurator\InvalidConfigurationException;
use App\Exception\Configurator\MissingPriceRuleException;
use App\Repository\Configurator\ConfiguratorRepository;
use App\Repository\Configurator\SavedConfiguratorConfigurationRepository;
use App\Service\Configurator\ConfiguratorPriceCalculator;
use Doctrine\ORM\EntityManagerInterface;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Currency\Context\CurrencyContextInterface;
use Sylius\Component\Locale\Context\LocaleContextInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class SavedConfiguratorConfigurationController extends AbstractController
{
    public function __construct(private readonly ConfiguratorRepository $configurators, private readonly SavedConfiguratorConfigurationRepository $savedConfigurations, private readonly ConfiguratorPriceCalculator $calculator, private readonly ChannelContextInterface $channelContext, private readonly CurrencyContextInterface $currencyContext, private readonly LocaleContextInterface $localeContext, private readonly EntityManagerInterface $entityManager)
    {
    }

    #[Route('/configurators/{configuratorCode}/saved-configurations', name: 'cardnext_shop_configurator_save', methods: ['POST'])]
    public function __invoke(Request $request, string $configuratorCode): JsonResponse
    {
        $configurator = $this->configurators->findEnabledByCode($configuratorCode);
        $channel = $this->channelContext->getChannel();
        if ($configurator === null || !$channel instanceof Channel || !$configurator->hasChannel($channel) || $channel->getCode() === null) {
            return $this->json(['ok' => false], Response::HTTP_NOT_FOUND);
        }
        if (!$this->isCsrfTokenValid('saved_configurator_'.$configuratorCode, $request->headers->get('X-CSRF-TOKEN'))) {
            return $this->json(['ok' => false, 'errors' => [['field' => null, 'message' => 'Invalid CSRF token.']]], Response::HTTP_FORBIDDEN);
        }
        try {
            $payload = $request->toArray();
        } catch (\Throwable) {
            return $this->invalid();
        }
        $quantity = $payload['quantity'] ?? null;
        $selections = $payload['selections'] ?? null;
        $leadTimeCode = $payload['leadTimeCode'] ?? null;
        if (!is_int($quantity) || !is_array($selections) || ($leadTimeCode !== null && !is_string($leadTimeCode))) {
            return $this->invalid();
        }
        try {
            // Deliberately discard the result: current pricing is only the validity gate,
            // never data stored in or restored from the immutable snapshot.
            $this->calculator->calculate(new ConfiguratorConfiguration($configuratorCode, $quantity, $this->currencyContext->getCurrencyCode(), $channel->getCode(), $selections, [], $leadTimeCode), $channel, $this->currencyContext->getCurrencyCode());
        } catch (InvalidConfigurationException|MissingPriceRuleException|\DomainException) {
            return $this->invalid();
        }
        do {
            $token = SavedConfiguratorConfiguration::generateToken();
        } while ($this->savedConfigurations->findOneBy(['token' => $token]) !== null);

        $saved = new SavedConfiguratorConfiguration($token, $configurator, $channel, $this->localeContext->getLocaleCode(), $quantity, $selections, $leadTimeCode);
        $this->entityManager->persist($saved);
        $this->entityManager->flush();

        return $this->json(['ok' => true, 'token' => $token], Response::HTTP_CREATED);
    }

    private function invalid(): JsonResponse
    {
        return $this->json(['ok' => false, 'errors' => [['field' => null, 'message' => 'The configuration is incomplete or invalid.']]], Response::HTTP_UNPROCESSABLE_ENTITY);
    }
}
