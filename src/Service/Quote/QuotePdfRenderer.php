<?php

declare(strict_types=1);

namespace App\Service\Quote;

use App\Entity\Quote\Quote;
use Dompdf\Dompdf;
use Dompdf\Options;
use Twig\Environment;

final class QuotePdfRenderer
{
    public function __construct(private Environment $twig, private QuoteIssuerProfileRegistry $issuers)
    {
    }

    public function render(Quote $quote): string
    {
        $rates = [];
        foreach ($quote->getItems() as $item) {
            $rate = $item->getTaxRate() ?? $quote->getDefaultTaxRate();
            $rates[$rate] = ($rates[$rate] ?? 0) + $item->getLineTotal();
        }
        $rates[$quote->getDefaultTaxRate()] = ($rates[$quote->getDefaultTaxRate()] ?? 0) + $quote->getShippingTotal() + $quote->getServiceTotal();
        $taxes = [];
        foreach ($rates as $rate => $base) {
            $taxes[(int) $rate] = QuoteCalculator::taxFor($base, (int) $rate);
        } ksort($taxes);
        $html = $this->twig->render('pdf/quote.html.twig', [
            'quote' => $quote,
            'issuer' => $this->issuers->get($quote->getChannelCode()),
            'taxes' => $taxes,
            'hasZeroTax' => array_key_exists(0, $taxes),
        ]);
        $options = new Options();
        $options->setIsRemoteEnabled(false);
        $options->setIsPhpEnabled(true);
        $options->setDefaultFont('DejaVu Sans');
        $pdf = new Dompdf($options);
        $pdf->loadHtml($html, 'UTF-8');
        $pdf->setPaper('A4', 'portrait');
        $pdf->render();
        $canvas = $pdf->getCanvas();
        $canvas->page_text(500, 810, 'Seite {PAGE_NUM} / {PAGE_COUNT}', 'DejaVu Sans', 8, [0.35, 0.35, 0.35]);

        return $pdf->output();
    }
}
