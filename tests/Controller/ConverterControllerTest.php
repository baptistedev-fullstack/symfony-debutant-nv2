<?php

namespace App\Tests\Controller;

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class ConverterControllerTest extends WebTestCase
{
    public function testEmptyFormIsDisplayed(): void
    {
        $client = static::createClient();
        $client->request('GET', '/');

        self::assertResponseStatusCodeSame(200);
        self::assertSelectorTextContains('h1', 'Convertisseur');
        self::assertSelectorCount(3, 'select[name="de"] optgroup');
        self::assertSelectorExists('form[method="get"]');
        self::assertSelectorExists('label[for="valeur"]');
        self::assertSelectorNotExists('output.result');
        self::assertSelectorNotExists('[role="alert"]');
    }

    public function testConvertsAndKeepsTheInput(): void
    {
        $client = static::createClient();
        $client->request('GET', '/?valeur=1&de=km&vers=m');

        self::assertResponseStatusCodeSame(200);
        self::assertSelectorTextContains('output.result', '1 km = 1000 m');
        self::assertSelectorExists('input[name="valeur"][value="1"]');
        self::assertSelectorExists('select[name="de"] option[value="km"][selected]');
        self::assertSelectorExists('select[name="vers"] option[value="m"][selected]');
    }

    public function testConvertsTemperatures(): void
    {
        $client = static::createClient();
        $client->request('GET', '/?valeur=100&de=C&vers=F');

        self::assertResponseStatusCodeSame(200);
        self::assertSelectorTextContains('output.result', '100 C = 212 F');
    }

    public function testNegativeZeroIsDisplayedAsZero(): void
    {
        $client = static::createClient();
        $client->request('GET', '/?valeur=-0.00001&de=m&vers=km');

        self::assertResponseStatusCodeSame(200);
        self::assertSelectorTextContains('output.result', '-0.00001 m = 0 km');
    }

    public function testPostIsNotAllowed(): void
    {
        $client = static::createClient();
        $client->request('POST', '/');

        self::assertResponseStatusCodeSame(405);
    }

    #[DataProvider('invalidNumbers')]
    public function testInvalidNumberShowsAMessage(string $query): void
    {
        $client = static::createClient();
        $client->request('GET', '/?'.$query);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorExists('[role="alert"]');
        self::assertSelectorNotExists('output.result');
        self::assertSelectorNotExists('input[name="valeur"][value]');
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidNumbers(): iterable
    {
        yield 'texte' => ['valeur=abc&de=km&vers=m'];
        yield 'vide' => ['valeur=&de=km&vers=m'];
        yield 'virgule' => ['valeur=12,5&de=km&vers=m'];
        yield 'débordement' => ['valeur=1e999&de=km&vers=m'];
    }

    public function testMissingUnitShowsAMessage(): void
    {
        $client = static::createClient();
        $client->request('GET', '/?valeur=1&de=km');

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorExists('[role="alert"]');
        self::assertSelectorExists('input[name="valeur"][value="1"]');
        self::assertSelectorExists('select[name="de"] option[value="km"][selected]');
    }

    public function testIncompatibleUnitsShowAMessage(): void
    {
        $client = static::createClient();
        $client->request('GET', '/?valeur=1&de=km&vers=kg');

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorExists('[role="alert"]');
    }

    public function testBelowAbsoluteZeroShowsAMessage(): void
    {
        $client = static::createClient();
        $client->request('GET', '/?valeur=-300&de=C&vers=F');

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorExists('[role="alert"]');
    }

    public function testInfiniteResultShowsAMessage(): void
    {
        $client = static::createClient();
        $client->request('GET', '/?valeur=1e308&de=km&vers=mm');

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorExists('[role="alert"]');
        self::assertStringNotContainsString('INF', $client->getResponse()->getContent());
    }

    public function testUnknownUnitIsNotReflected(): void
    {
        $client = static::createClient();
        $client->request('GET', '/?valeur=1&de=%3Cscript%3E&vers=m');

        self::assertResponseStatusCodeSame(422);
        self::assertStringNotContainsString('<script>', $client->getResponse()->getContent());
        self::assertSelectorNotExists('option[selected][value="<script>"]');
    }

    public function testArrayParameterIsNotFound(): void
    {
        $client = static::createClient();
        $client->request('GET', '/?valeur[]=1');

        self::assertResponseStatusCodeSame(404);
    }
}
