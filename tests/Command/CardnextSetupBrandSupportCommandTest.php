<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Cms\CmsBlockRendererRegistry;
use App\Command\CardnextSetupBrandSupportCommand;
use App\Entity\Channel\Channel;
use App\Entity\Cms\CmsBlock;
use App\Entity\Cms\CmsLayout;
use App\Entity\Cms\CmsMenu;
use App\Entity\Cms\CmsMenuItem;
use App\Entity\Cms\CmsPage;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class CardnextSetupBrandSupportCommandTest extends TestCase
{
    public function testItCreatesChannelSeparatedPagesAndNavigationIdempotently(): void
    {
        $identible = $this->channel('IDENTIBLE_DE');
        $inplastor = $this->channel('INPLASTOR_AT');
        $foreignChannel = $this->channel('CARDNEXT_DE');
        $foreignPage = new CmsPage();
        $foreignPage->setCode('existing_cardnext_page');
        $foreignPage->addChannel($foreignChannel);
        $storage = [Channel::class => [$identible, $inplastor, $foreignChannel], CmsPage::class => [$foreignPage]];
        $command = $this->command($storage);

        $first = new CommandTester($command);
        self::assertSame(Command::SUCCESS, $first->execute([]));
        self::assertStringContainsString('Brand-Support und Downloads sind eingerichtet', $first->getDisplay());

        $pages = array_values(array_filter($storage[CmsPage::class], static fn (CmsPage $page): bool => $page !== $foreignPage));
        self::assertCount(4, $pages);
        self::assertSame('existing_cardnext_page', $foreignPage->getCode());
        self::assertTrue($foreignPage->getChannels()->contains($foreignChannel));

        $byCode = [];
        foreach ($pages as $page) {
            $byCode[$page->getCode()] = $page;
        }
        foreach (['identible_support', 'identible_downloads'] as $code) {
            self::assertCount(1, $byCode[$code]->getChannels());
            self::assertTrue($byCode[$code]->getChannels()->contains($identible));
            self::assertFalse($byCode[$code]->getChannels()->contains($inplastor));
        }
        foreach (['inplastor_support', 'inplastor_downloads'] as $code) {
            self::assertCount(1, $byCode[$code]->getChannels());
            self::assertTrue($byCode[$code]->getChannels()->contains($inplastor));
            self::assertFalse($byCode[$code]->getChannels()->contains($identible));
        }
        self::assertSame('support', $byCode['identible_support']->getTranslation('de_DE')?->getSlug());
        self::assertSame('support', $byCode['inplastor_support']->getTranslation('de_AT')?->getSlug());
        self::assertSame('downloads', $byCode['identible_downloads']->getTranslation('de_DE')?->getSlug());
        self::assertSame('downloads', $byCode['inplastor_downloads']->getTranslation('de_AT')?->getSlug());

        $registry = new CmsBlockRendererRegistry();
        foreach (['identible_support', 'inplastor_support'] as $code) {
            $linkCards = $this->block($byCode[$code], 'link_cards');
            self::assertSame([], $registry->validate('link_cards', $linkCards->getConfiguration()));
            self::assertCount(4, $linkCards->getConfiguration()['items']);
        }
        foreach (['identible_downloads', 'inplastor_downloads'] as $code) {
            $download = $this->block($byCode[$code], 'downloads');
            self::assertTrue($download->getConfiguration()['showFilters']);
            self::assertArrayNotHasKey('manufacturer', $download->getConfiguration());
            self::assertArrayNotHasKey('limit', $download->getConfiguration());
        }

        $byCode['identible_support']->getTranslation('de_DE')?->setTitle('Individuell bearbeitet');
        $blockCount = array_sum(array_map(static fn (CmsPage $page): int => $page->getBlocks()->count(), $pages));
        self::assertCount(6, $storage[CmsMenuItem::class]);
        $second = new CommandTester($command);
        self::assertSame(Command::SUCCESS, $second->execute([]));
        self::assertStringContainsString('Bereits vorhanden (unverändert)', $second->getDisplay());
        self::assertCount(5, $storage[CmsPage::class]);
        self::assertCount(6, $storage[CmsMenuItem::class]);
        self::assertCount(1, $storage[CmsLayout::class]);
        self::assertCount(1, $storage[CmsMenu::class]);
        self::assertSame('Individuell bearbeitet', $byCode['identible_support']->getTranslation('de_DE')?->getTitle());
        self::assertSame($blockCount, array_sum(array_map(static fn (CmsPage $page): int => $page->getBlocks()->count(), $pages)));
    }

    public function testItFailsClearlyBeforeWritingWhenAChannelIsMissing(): void
    {
        $storage = [Channel::class => [$this->channel('IDENTIBLE_DE')]];
        $tester = new CommandTester($this->command($storage));

        self::assertSame(Command::FAILURE, $tester->execute([]));
        self::assertStringContainsString('INPLASTOR_AT', $tester->getDisplay());
        self::assertArrayNotHasKey(CmsPage::class, $storage);
    }

    /** @param array<class-string, list<object>> $storage */
    private function command(array &$storage): CardnextSetupBrandSupportCommand
    {
        $repositories = [];
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getRepository')->willReturnCallback(function (string $class) use (&$repositories, &$storage): EntityRepository {
            if (!isset($repositories[$class])) {
                $repository = $this->createMock(EntityRepository::class);
                $repository->method('findOneBy')->willReturnCallback(static function (array $criteria) use ($class, &$storage): ?object {
                    foreach ($storage[$class] ?? [] as $entity) {
                        foreach ($criteria as $property => $expected) {
                            $getter = 'get' . ucfirst($property);
                            if ($entity->{$getter}() !== $expected) {
                                continue 2;
                            }
                        }

                        return $entity;
                    }

                    return null;
                });
                $repositories[$class] = $repository;
            }

            return $repositories[$class];
        });
        $entityManager->method('persist')->willReturnCallback(static function (object $entity) use (&$storage): void {
            $class = $entity::class;
            $storage[$class] ??= [];
            if (!in_array($entity, $storage[$class], true)) {
                $storage[$class][] = $entity;
            }
        });
        $connection = $this->createMock(Connection::class);

        return new CardnextSetupBrandSupportCommand($entityManager, $connection, new CmsBlockRendererRegistry());
    }

    private function channel(string $code): Channel
    {
        $channel = new Channel();
        $channel->setCode($code);

        return $channel;
    }

    private function block(CmsPage $page, string $type): CmsBlock
    {
        foreach ($page->getBlocks() as $block) {
            if ($block->getType() === $type) {
                return $block;
            }
        }

        self::fail(sprintf('Block "%s" missing.', $type));
    }
}
