<?php

declare(strict_types=1);

namespace App\Controller\Shop;

use App\Dto\Configurator\ConfiguratorConfiguration;
use App\Exception\Configurator\InvalidConfigurationException;
use App\Exception\Configurator\MissingPriceRuleException;
use App\Repository\Configurator\ConfiguratorRepository;
use App\Service\Configurator\ConfiguratorPriceCalculator;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Currency\Context\CurrencyContextInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ConfiguratorCalculateController extends AbstractController
{
    public function __construct(
        private readonly ConfiguratorRepository $configurators,
        private readonly ConfiguratorPriceCalculator $calculator,
        private readonly ChannelContextInterface $channelContext,
        private readonly CurrencyContextInterface $currencyContext,
    ) {
    }

    #[Route('/configurators/{configuratorCode}/calculate', name: 'cardnext_shop_configurator_calculate', methods: ['POST'])]
    public function __invoke(Request $request, string $configuratorCode): JsonResponse
    {
        $configurator = $this->configurators->findEnabledByCode($configuratorCode);
        if ($configurator === null) {
            return $this->error(null, 'Der Konfigurator ist nicht verfügbar.', Response::HTTP_NOT_FOUND);
        }

        try {
            $payload = $request->toArray();
        } catch (\Throwable) {
            return $this->error(null, 'Die Anfrage enthält kein gültiges JSON.', Response::HTTP_BAD_REQUEST);
        }

        $quantity = $payload['quantity'] ?? null;
        $selections = $payload['selections'] ?? null;
        $leadTimeCode = $payload['leadTimeCode'] ?? null;
        if ($leadTimeCode !== null && !is_string($leadTimeCode)) {
            return $this->error('leadTime', 'Die Produktionszeit hat ein ungültiges Format.');
        }
        if (!is_int($quantity) || !is_array($selections) || !array_is_list($selections) && array_filter(array_keys($selections), 'is_string') !== array_keys($selections)) {
            return $this->error(null, 'Menge und Auswahl haben ein ungültiges Format.');
        }

        $channel = $this->channelContext->getChannel();
        if (!$channel instanceof \App\Entity\Channel\Channel || !$configurator->hasChannel($channel)) {
            return $this->error(null, 'Der Konfigurator ist in diesem Verkaufskanal nicht verfügbar.', Response::HTTP_NOT_FOUND);
        }
        $channelCode = $channel->getCode();
        $currencyCode = $this->currencyContext->getCurrencyCode();
        if ($channelCode === null) {
            return $this->error(null, 'Der aktuelle Verkaufskanal ist nicht verfügbar.', Response::HTTP_SERVICE_UNAVAILABLE);
        }

        try {
            $result = $this->calculator->calculate(
                new ConfiguratorConfiguration($configurator->getCode(), $quantity, $currencyCode, $channelCode, $selections, [], $leadTimeCode),
                $channel,
                $currencyCode,
            );
        } catch (InvalidConfigurationException $exception) {
            return $this->validationError($exception);
        } catch (MissingPriceRuleException) {
            return $this->error('quantity', 'Für diese Menge ist kein Preis verfügbar.');
        } catch (\DomainException) {
            return $this->error(null, 'Die Konfiguration konnte nicht berechnet werden.');
        }

        return $this->json(['ok' => true, ...$result->jsonSerialize()]);
    }

    private function validationError(InvalidConfigurationException $exception): JsonResponse
    {
        $decoded = json_decode($exception->getMessage(), true);
        if (is_array($decoded) && is_array($decoded['errors'] ?? null)) {
            $errors = array_map(static fn (array $error): array => [
                'field' => is_string($error['fieldCode'] ?? null) ? $error['fieldCode'] : null,
                'message' => is_string($error['message'] ?? null) ? $error['message'] : 'Ungültige Auswahl.',
            ], array_filter($decoded['errors'], 'is_array'));

            return $this->json(['ok' => false, 'errors' => $errors], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $this->error(null, 'Die Konfiguration ist ungültig.');
    }

    private function error(?string $field, string $message, int $status = Response::HTTP_UNPROCESSABLE_ENTITY): JsonResponse
    {
        return $this->json(['ok' => false, 'errors' => [['field' => $field, 'message' => $message]]], $status);
    }
}
