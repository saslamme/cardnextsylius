<?php

declare(strict_types=1);

namespace App\Tests\Quote;

use PHPUnit\Framework\TestCase;

/**
 * Lightweight regression coverage for the controller flow. The project kernel cannot be
 * booted in every supported CI environment, so these assertions protect the critical
 * ordering and guards without replacing the form and service unit tests.
 */
final class QuoteCartSubmissionFlowTest extends TestCase
{
    private string $controller;
    private string $submitter;

    protected function setUp(): void
    {
        $root = dirname(__DIR__, 2);
        $controller = file_get_contents($root . '/src/Controller/Shop/QuoteCartController.php');
        $submitter = file_get_contents($root . '/src/Service/Quote/QuoteRequestSubmitter.php');
        self::assertIsString($controller);
        self::assertIsString($submitter);
        $this->controller = $controller;
        $this->submitter = $submitter;
    }

    public function testSubmissionTokenIsCreatedBeforeFormHandling(): void
    {
        self::assertTrue($this->position("get('cardnext.quote_submission')") < $this->position('Uuid::v4()->toRfc4122()'));
        self::assertTrue($this->position("set('cardnext.quote_submission', \$storedToken)") < $this->position("add('_submission'"));
        self::assertTrue($this->position("add('_submission'") < $this->position('handleRequest($request)'));
    }

    public function testNormalizedEmptyHoneypotValuesReachSubmissionButSpamDoesNot(): void
    {
        self::assertStringContainsString("\$isHoneypotEmpty = \$honeypot === null || \$honeypot === '';", $this->controller);
        self::assertStringContainsString('if ($isHoneypotEmpty && $items !== [])', $this->controller);
        self::assertStringNotContainsString('empty($honeypot)', $this->controller);
    }

    public function testSuccessfulPostUsesSameResolvedSnapshotAndThenCleansSession(): void
    {
        self::assertSame(1, substr_count($this->controller, '$this->cart->resolvedItems($channel)'));
        self::assertStringContainsString('$submitter->submit($quote, $channel, $request->getLocale(), $items)', $this->controller);
        self::assertStringNotContainsString('resolvedItems(', $this->submitter);
        self::assertTrue($this->position('$submitter->submit(') < $this->position("remove('cardnext.quote_submission')"));
        self::assertTrue($this->position("remove('cardnext.quote_submission')") < $this->position('$this->cart->clear()'));
        self::assertTrue($this->position('$this->cart->clear()') < $this->position('$mailer->send($quote)'));
        self::assertStringContainsString("redirectToRoute('cardnext_shop_quote_confirmation'", $this->controller);
    }

    public function testSubmitterPersistsItemsFromSnapshotAndRejectsEmptySnapshot(): void
    {
        self::assertStringContainsString("if (\$items === [])", $this->submitter);
        self::assertStringContainsString("throw new \\DomainException('Quote cart is empty.')", $this->submitter);
        self::assertStringContainsString('foreach ($items as $position => $row)', $this->submitter);
        self::assertStringContainsString('$quote->addItem($item)', $this->submitter);
        self::assertStringContainsString('$this->em->persist($quote)', $this->submitter);
    }

    public function testInvalidFormRenders422WithoutEnteringSubmitter(): void
    {
        self::assertStringContainsString('$showValidationError = $form->isSubmitted() && !$form->isValid()', $this->controller);
        self::assertStringContainsString('Response::HTTP_UNPROCESSABLE_ENTITY', $this->controller);
        self::assertTrue($this->position('if ($form->isSubmitted() && $form->isValid())') < $this->position('$submitter->submit('));
    }

    public function testDomainRejectionIsLoggedWithoutPersonalDataAndUnexpectedCausesEscape(): void
    {
        self::assertStringContainsString("warning('quote submission rejected'", $this->controller);
        foreach (["'reason'", "'channel'", "'locale'", "'item_count'"] as $contextKey) {
            self::assertStringContainsString($contextKey, $this->controller);
        }
        self::assertStringContainsString("if (\$exception->getMessage() !== 'Quote cart is empty.')", $this->controller);
        self::assertStringContainsString('throw $exception;', $this->controller);
        self::assertStringNotContainsString('catch (\\Throwable', $this->controller);
    }

    private function position(string $needle): int
    {
        $position = strpos($this->controller, $needle);
        self::assertNotFalse($position, sprintf('Expected controller to contain %s', $needle));

        return $position;
    }
}
