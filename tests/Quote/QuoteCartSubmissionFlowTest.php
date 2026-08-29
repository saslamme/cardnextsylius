<?php

declare(strict_types=1);

namespace App\Tests\Quote;

use PHPUnit\Framework\TestCase;

final class QuoteCartSubmissionFlowTest extends TestCase
{
    private string $controller;

    protected function setUp(): void
    {
        $controller = file_get_contents(dirname(__DIR__, 2) . '/src/Controller/Shop/QuoteCartController.php');
        self::assertIsString($controller);
        $this->controller = $controller;
    }

    public function testGetCreatesAndStoresTokenBeforeAddingHiddenField(): void
    {
        $read = $this->position("get('cardnext.quote_submission')");
        $create = $this->position('Uuid::v4()->toRfc4122()');
        $store = $this->position("set('cardnext.quote_submission',\$storedToken)");
        $add = $this->position("add('_submission'");
        $handle = $this->position('handleRequest($request)');

        self::assertTrue($read < $create && $create < $store && $store < $add && $add < $handle);
        self::assertStringContainsString("'mapped'=>false,'data'=>\$storedToken", $this->controller);
    }

    public function testValidPostReadsAndChecksTheSubmittedFormField(): void
    {
        self::assertStringContainsString("\$submittedToken=\$form->get('_submission')->getData()", $this->controller);
        self::assertStringContainsString("is_string(\$submittedToken)&&\$submittedToken!==''&&hash_equals(\$storedToken,\$submittedToken)", $this->controller);
        self::assertStringNotContainsString("request->all('quote_request')", $this->controller);
        self::assertTrue($this->position('handleRequest($request)') < $this->position('$submitter->submit('));
        self::assertTrue($this->position('$submitter->submit(') < $this->position("remove('cardnext.quote_submission')"));
        self::assertTrue($this->position("remove('cardnext.quote_submission')") < $this->position('$this->cart->clear()'));
        self::assertStringContainsString("redirectToRoute('cardnext_shop_quote_confirmation'", $this->controller);
    }

    public function testInvalidPostRendersWithoutMutatingSubmittedFormOrRemovingToken(): void
    {
        self::assertSame(1, substr_count($this->controller, "add('_submission'"));
        self::assertTrue($this->position("add('_submission'") < $this->position('handleRequest($request)'));
        self::assertTrue($this->position("remove('cardnext.quote_submission')") > $this->position('hash_equals($storedToken,$submittedToken)'));
        self::assertStringContainsString("return \$this->render('shop/quote/cart.html.twig'", $this->controller);
    }

    public function testWrongOrReplayedTokenCannotSubmitAQuote(): void
    {
        $check = $this->position('hash_equals($storedToken,$submittedToken)');
        $submit = $this->position('$submitter->submit(');

        self::assertTrue($check < $submit);
        self::assertStringContainsString("\$submittedToken!==''", $this->controller);
        self::assertStringContainsString("remove('cardnext.quote_submission')", $this->controller);
    }

    private function position(string $needle): int
    {
        $position = strpos($this->controller, $needle);
        self::assertNotFalse($position, sprintf('Expected controller to contain %s', $needle));

        return $position;
    }
}
