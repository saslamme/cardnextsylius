<?php

declare(strict_types=1);

namespace App\Controller\Shop;

use App\PrinterAdvisor\PrinterAdvisorAnswers;
use App\PrinterAdvisor\PrinterAdvisorCandidateProvider;
use App\PrinterAdvisor\PrinterAdvisorRecommendation;
use App\PrinterAdvisor\PrinterAdvisorRecommendationService;
use App\Service\ProductPublicUrlGenerator;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class PrinterAdvisorController extends AbstractController
{
    #[Route('/{_locale}/kartendrucker-berater', name: 'cardnext_shop_printer_advisor', requirements: ['_locale' => '^[A-Za-z]{2,4}(?:_[A-Za-z]{2})?$'], methods: ['GET', 'POST'], priority: 100)]
    public function __invoke(
        Request $request,
        ChannelContextInterface $channelContext,
        PrinterAdvisorCandidateProvider $provider,
        PrinterAdvisorRecommendationService $service,
        ProductPublicUrlGenerator $urlGenerator,
    ): Response {
        $recommendations = null;
        $error = null;
        $answers = null;
        if ($request->isMethod('POST')) {
            try {
                $token = $request->request->get('_token');
                if (!is_string($token) || !$this->isCsrfTokenValid('printer_advisor', $token)) {
                    throw new \InvalidArgumentException('Die Anfrage ist abgelaufen. Bitte laden Sie die Seite neu.');
                }
                $answers = PrinterAdvisorAnswers::fromArray($request->request->all('advisor'));
                $locale = $request->attributes->get('_locale');
                if (!is_string($locale)) {
                    throw new \InvalidArgumentException('Die Sprache konnte nicht aufgelöst werden.');
                }
                $channel = $channelContext->getChannel();
                if (!$channel instanceof ChannelInterface) {
                    throw new \InvalidArgumentException('Der Verkaufskanal konnte nicht aufgelöst werden.');
                }
                $recommendations = array_map(
                    static fn (PrinterAdvisorRecommendation $r): PrinterAdvisorRecommendation => new PrinterAdvisorRecommendation($r->product, $r->price, $r->score, $r->reasons, $r->label, $urlGenerator->generate($r->product, $locale)),
                    $service->recommend($answers, $provider->forChannel($channel)),
                );
            } catch (\InvalidArgumentException $exception) {
                $error = $exception->getMessage();
            }
        }

        $response = $this->render('shop/printer_advisor/index.html.twig', [
            'recommendations' => $recommendations, 'answers' => $answers, 'error' => $error,
            'channel' => $channelContext->getChannel(),
        ]);
        if ($error !== null) {
            $response->setStatusCode(Response::HTTP_BAD_REQUEST);
        }

        return $response;
    }
}
