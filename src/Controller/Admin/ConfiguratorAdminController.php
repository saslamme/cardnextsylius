<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Channel\Channel;
use App\Entity\Configurator\Configurator;
use App\Entity\Configurator\ConfiguratorField;
use App\Entity\Configurator\ConfiguratorPriceRule;
use App\Entity\Configurator\ConfiguratorSection;
use App\Entity\Configurator\ConfiguratorTranslation;
use App\Entity\Configurator\ConfiguratorValue;
use App\Enum\Configurator\FieldType;
use App\Enum\Configurator\MultiplierType;
use App\Enum\Configurator\PercentageBase;
use App\Enum\Configurator\PriceType;
use App\Service\Configurator\Admin\DecimalAmountTransformer;
use App\Service\Configurator\ConfiguratorAggregateDeleter;
use App\Service\Configurator\PriceRuleOverlapValidator;
use Doctrine\ORM\EntityManagerInterface;
use Sylius\Component\Core\Model\AdminUserInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/configurators', name: 'cardnext_admin_configurator_')]
#[IsGranted(AdminUserInterface::DEFAULT_ADMIN_ROLE)]
final class ConfiguratorAdminController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request, EntityManagerInterface $em): Response
    {
        $page = max(1, $request->query->getInt('page', 1));
        $search = trim((string) $request->query->get('q'));
        $qb = $em->createQueryBuilder()->select('c', 'COUNT(DISTINCT s.id) AS sectionCount', 'COUNT(DISTINCT f.id) AS fieldCount', 'COUNT(DISTINCT v.id) AS valueCount', 'COUNT(DISTINCT r.id) AS priceRuleCount')->from(Configurator::class, 'c')->leftJoin('c.sections', 's')->leftJoin('s.fields', 'f')->leftJoin('f.values', 'v')->leftJoin(ConfiguratorPriceRule::class, 'r', 'WITH', 'r.configurator = c')->groupBy('c.id')->orderBy('c.code', 'ASC');
        if ($search !== '') {
            $qb->andWhere('LOWER(c.internalName) LIKE :q OR LOWER(c.code) LIKE :q')->setParameter('q', '%' . mb_strtolower($search) . '%');
        }
        if ($request->query->has('enabled') && $request->query->get('enabled') !== '') {
            $qb->andWhere('c.enabled = :enabled')->setParameter('enabled', $request->query->getBoolean('enabled'));
        }
        $countQb = clone $qb;
        $total = count($countQb->setFirstResult(null)->setMaxResults(null)->getQuery()->getResult());
        $rows = $qb->setFirstResult(($page - 1) * 25)->setMaxResults(25)->getQuery()->getResult();

        return $this->render('admin/cardnext/configurator/index.html.twig', compact('rows', 'page', 'total', 'search'));
    }

    #[Route('/new', name: 'create', methods: ['GET', 'POST'])]
    public function create(Request $request, EntityManagerInterface $em): Response
    {
        if ($request->isMethod('POST') && $this->validToken($request, 'configurator-create')) {
            try {
                $configurator = new Configurator($this->required($request, 'code'), $this->required($request, 'name'));
                $configurator->setEnabled($request->request->getBoolean('enabled'));
                $this->applyCatalogData($configurator, $request, $em);
                $em->persist($configurator);
                $em->flush();

                return $this->redirectToRoute('cardnext_admin_configurator_update', ['id' => $configurator->getId()]);
            } catch (\Throwable $exception) {
                $this->addFlash('error', $exception->getMessage());
            }
        }

        return $this->render('admin/cardnext/configurator/form.html.twig', ['configurator' => null, 'channels' => $em->getRepository(Channel::class)->findAll()]);
    }

    #[Route('/{id}/edit', name: 'update', requirements: ['id' => '\\d+'], methods: ['GET', 'POST'])]
    public function update(Configurator $configurator, Request $request, EntityManagerInterface $em): Response
    {
        if ($request->isMethod('POST') && $this->validToken($request, 'configurator-' . $configurator->getId())) {
            try {
                $configurator->setName($this->required($request, 'name'));
                $configurator->setEnabled($request->request->getBoolean('enabled'));
                $this->applyCatalogData($configurator, $request, $em);
                $em->flush();
                $this->addFlash('success', 'cardnext.configurator.flash.updated');
            } catch (\Throwable $exception) {
                $this->addFlash('error', $exception->getMessage());
            }
        }

        return $this->render('admin/cardnext/configurator/form.html.twig', ['configurator' => $configurator, 'channels' => $em->getRepository(Channel::class)->findAll()]);
    }

    private function applyCatalogData(Configurator $configurator, Request $request, EntityManagerInterface $em): void
    {
        $locale = trim((string) $request->request->get('locale'));
        $path = trim((string) $request->request->get('path'));
        $publicName = trim((string) $request->request->get('translation_name'));
        if ($locale !== '' || $path !== '' || $publicName !== '') {
            if ($locale === '' || $path === '' || $publicName === '') {
                throw new \InvalidArgumentException('Locale, öffentlicher Name und Pfad müssen gemeinsam angegeben werden.');
            }
            $translation = $configurator->getTranslation($locale) ?? new ConfiguratorTranslation($locale, $publicName, $path);
            $translation->setName($publicName);
            $translation->setPath($path);
            $translation->setShortDescription($request->request->get('short_description'));
            $translation->setDescription($request->request->get('description'));
            $translation->setMetaTitle($request->request->get('meta_title'));
            $translation->setMetaDescription($request->request->get('meta_description'));
            $configurator->addTranslation($translation);
        }
        foreach ($configurator->getChannels()->toArray() as $channel) {
            $configurator->removeChannel($channel);
        }
        foreach ((array) $request->request->all('channels') as $id) {
            $channel = $em->find(Channel::class, (int) $id);
            if ($channel instanceof Channel) {
                $configurator->addChannel($channel);
            }
        }
    }

    #[Route('/{id}/structure', name: 'structure', requirements: ['id' => '\\d+'], methods: ['GET'])]
    public function structure(Configurator $configurator): Response
    {
        return $this->render('admin/cardnext/configurator/structure.html.twig', compact('configurator'));
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'])]
    public function delete(Configurator $configurator, Request $request, ConfiguratorAggregateDeleter $aggregateDeleter): Response
    {
        if (!$this->validToken($request, 'configurator-delete-' . $configurator->getId())) {
            throw $this->createAccessDeniedException();
        }

        try {
            $aggregateDeleter->delete($configurator);
            $this->addFlash('success', 'cardnext.configurator.flash.deleted');
        } catch (\Throwable) {
            $this->addFlash('error', 'cardnext.configurator.flash.delete_blocked');
        }

        return $this->redirectToRoute('cardnext_admin_configurator_index');
    }

    #[Route('/{id}/prices', name: 'prices', requirements: ['id' => '\\d+'], methods: ['GET'])]
    public function prices(Configurator $configurator, EntityManagerInterface $em, DecimalAmountTransformer $amounts): Response
    {
        $rules = $em->createQueryBuilder()->select('r', 'v', 'f', 's', 'ch', 'mf', 'mfs')->from(ConfiguratorPriceRule::class, 'r')->leftJoin('r.value', 'v')->leftJoin('v.field', 'f')->leftJoin('f.section', 's')->leftJoin('r.channel', 'ch')->leftJoin('r.multiplierField', 'mf')->leftJoin('mf.section', 'mfs')->where('r.configurator = :configurator')->setParameter('configurator', $configurator)->orderBy('s.position', 'ASC')->addOrderBy('f.position', 'ASC')->addOrderBy('v.position', 'ASC')->addOrderBy('r.chargeCode', 'ASC')->addOrderBy('r.minimumQuantity', 'ASC')->getQuery()->getResult();

        return $this->render('admin/cardnext/configurator/prices.html.twig', compact('configurator', 'rules', 'amounts'));
    }

    #[Route('/{id}/sections/new', name: 'section_create', methods: ['GET', 'POST'])]
    public function sectionCreate(Configurator $configurator, Request $request, EntityManagerInterface $em): Response
    {
        if ($request->isMethod('POST') && $this->validToken($request, 'section-create-' . $configurator->getId())) {
            try {
                $section = new ConfiguratorSection($this->required($request, 'code'), $this->required($request, 'name'));
                $this->applySection($section, $request);
                $configurator->addSection($section);
                $em->persist($section);
                $em->flush();

                return $this->redirectToRoute('cardnext_admin_configurator_structure', ['id' => $configurator->getId()]);
            } catch (\Throwable $exception) {
                $this->addFlash('error', $exception->getMessage());
            }
        }

        return $this->render('admin/cardnext/configurator/section_form.html.twig', ['configurator' => $configurator, 'section' => null]);
    }

    #[Route('/{id}/sections/{section}/edit', name: 'section_update', methods: ['GET', 'POST'])]
    public function sectionUpdate(Configurator $configurator, ConfiguratorSection $section, Request $request, EntityManagerInterface $em): Response
    {
        $this->assertSame($configurator, $section->getConfigurator());
        if ($request->isMethod('POST') && $this->validToken($request, 'section-' . $section->getId())) {
            try {
                $section->setName($this->required($request, 'name'));
                $this->applySection($section, $request);
                $em->flush();

                return $this->redirectToRoute('cardnext_admin_configurator_structure', ['id' => $configurator->getId()]);
            } catch (\Throwable $e) {
                $this->addFlash('error', $e->getMessage());
            }
        }

        return $this->render('admin/cardnext/configurator/section_form.html.twig', compact('configurator', 'section'));
    }

    #[Route('/{id}/sections/{section}/delete', name: 'section_delete', methods: ['POST'])]
    public function sectionDelete(Configurator $configurator, ConfiguratorSection $section, Request $request, EntityManagerInterface $em): Response
    {
        $this->assertSame($configurator, $section->getConfigurator());
        if (!$this->validToken($request, 'section-delete-' . $section->getId())) {
            throw $this->createAccessDeniedException();
        }
        if (!$section->getFields()->isEmpty()) {
            $this->addFlash('error', 'Dieser Bereich enthält noch Felder.');
        } else {
            $em->remove($section);
            $em->flush();
        }

        return $this->redirectToRoute('cardnext_admin_configurator_structure', ['id' => $configurator->getId()]);
    }

    #[Route('/{id}/sections/{section}/fields/new', name: 'field_create', methods: ['GET', 'POST'])]
    public function fieldCreate(Configurator $configurator, ConfiguratorSection $section, Request $request, EntityManagerInterface $em): Response
    {
        $this->assertSame($configurator, $section->getConfigurator());
        if ($request->isMethod('POST') && $this->validToken($request, 'field-create-' . $section->getId())) {
            try {
                $field = new ConfiguratorField($this->required($request, 'code'), $this->required($request, 'name'), FieldType::from($this->required($request, 'type')));
                $this->applyField($field, $request);
                $section->addField($field);
                $em->persist($field);
                $em->flush();

                return $this->redirectToRoute('cardnext_admin_configurator_structure', ['id' => $configurator->getId()]);
            } catch (\Throwable $e) {
                $this->addFlash('error', $e->getMessage());
            }
        }

        return $this->render('admin/cardnext/configurator/field_form.html.twig', ['configurator' => $configurator, 'section' => $section, 'field' => null, 'field_types' => FieldType::cases()]);
    }

    #[Route('/{id}/fields/{field}/edit', name: 'field_update', methods: ['GET', 'POST'])]
    public function fieldUpdate(Configurator $configurator, ConfiguratorField $field, Request $request, EntityManagerInterface $em): Response
    {
        $this->assertSame($configurator, $field->getConfigurator());
        if ($request->isMethod('POST') && $this->validToken($request, 'field-' . $field->getId())) {
            try {
                $field->setName($this->required($request, 'name'));
                if ($field->getValues()->isEmpty()) {
                    $field->setType(FieldType::from($this->required($request, 'type')));
                } elseif ($field->getType()->value !== $request->request->get('type')) {
                    throw new \DomainException('Der Feldtyp kann nicht geändert werden, solange Werte vorhanden sind.');
                } $this->applyField($field, $request);
                $em->flush();

                return $this->redirectToRoute('cardnext_admin_configurator_structure', ['id' => $configurator->getId()]);
            } catch (\Throwable $e) {
                $this->addFlash('error', $e->getMessage());
            }
        }

        return $this->render('admin/cardnext/configurator/field_form.html.twig', ['configurator' => $configurator, 'section' => $field->getSection(), 'field' => $field, 'field_types' => FieldType::cases()]);
    }

    #[Route('/{id}/fields/{field}/delete', name: 'field_delete', methods: ['POST'])]
    public function fieldDelete(Configurator $configurator, ConfiguratorField $field, Request $request, EntityManagerInterface $em): Response
    {
        $this->assertSame($configurator, $field->getConfigurator());
        if (!$this->validToken($request, 'field-delete-' . $field->getId())) {
            throw $this->createAccessDeniedException();
        }
        $references = $em->createQueryBuilder()->select('COUNT(r.id)')->from(ConfiguratorPriceRule::class, 'r')->where('r.multiplierField = :field')->setParameter('field', $field)->getQuery()->getSingleScalarResult();
        if (!$field->getValues()->isEmpty() || (int) $references > 0) {
            $this->addFlash('error', sprintf('Dieses Feld besitzt noch Werte oder wird von %d Preisregeln verwendet.', $references));
        } else {
            $em->remove($field);
            $em->flush();
        }

        return $this->redirectToRoute('cardnext_admin_configurator_structure', ['id' => $configurator->getId()]);
    }

    #[Route('/{id}/fields/{field}/values', name: 'values', methods: ['GET'])]
    public function values(Configurator $configurator, ConfiguratorField $field): Response
    {
        $this->assertChoiceField($configurator, $field);

        return $this->render('admin/cardnext/configurator/values.html.twig', compact('configurator', 'field'));
    }

    #[Route('/{id}/fields/{field}/values/new', name: 'value_create', methods: ['GET', 'POST'])]
    public function valueCreate(Configurator $configurator, ConfiguratorField $field, Request $request, EntityManagerInterface $em): Response
    {
        $this->assertChoiceField($configurator, $field);
        if ($request->isMethod('POST') && $this->validToken($request, 'value-create-' . $field->getId())) {
            try {
                $value = new ConfiguratorValue($this->required($request, 'code'), $this->required($request, 'name'));
                $this->applyValue($value, $request);
                $field->addValue($value);
                $em->persist($value);
                $em->flush();

                return $this->redirectToRoute('cardnext_admin_configurator_values', ['id' => $configurator->getId(), 'field' => $field->getId()]);
            } catch (\Throwable $e) {
                $this->addFlash('error', $e->getMessage());
            }
        }

        return $this->render('admin/cardnext/configurator/value_form.html.twig', ['configurator' => $configurator, 'field' => $field, 'value' => null]);
    }

    #[Route('/{id}/fields/{field}/values/{value}/edit', name: 'value_update', methods: ['GET', 'POST'])]
    public function valueUpdate(Configurator $configurator, ConfiguratorField $field, ConfiguratorValue $value, Request $request, EntityManagerInterface $em): Response
    {
        $this->assertChoiceField($configurator, $field);
        $this->assertSame($field, $value->getField());
        if ($request->isMethod('POST') && $this->validToken($request, 'value-' . $value->getId())) {
            try {
                $value->setName($this->required($request, 'name'));
                $this->applyValue($value, $request);
                $em->flush();

                return $this->redirectToRoute('cardnext_admin_configurator_values', ['id' => $configurator->getId(), 'field' => $field->getId()]);
            } catch (\Throwable $e) {
                $this->addFlash('error', $e->getMessage());
            }
        }

        return $this->render('admin/cardnext/configurator/value_form.html.twig', compact('configurator', 'field', 'value'));
    }

    #[Route('/{id}/fields/{field}/values/{value}/delete', name: 'value_delete', methods: ['POST'])]
    public function valueDelete(Configurator $configurator, ConfiguratorField $field, ConfiguratorValue $value, Request $request, EntityManagerInterface $em): Response
    {
        $this->assertChoiceField($configurator, $field);
        $this->assertSame($field, $value->getField());
        if (!$this->validToken($request, 'value-delete-' . $value->getId())) {
            throw $this->createAccessDeniedException();
        }
        $references = $em->getRepository(ConfiguratorPriceRule::class)->count(['value' => $value]);
        if ($references > 0) {
            $this->addFlash('error', sprintf('Dieser Wert besitzt noch %d Preisstaffeln.', $references));
        } else {
            $em->remove($value);
            $em->flush();
        }

        return $this->redirectToRoute('cardnext_admin_configurator_values', ['id' => $configurator->getId(), 'field' => $field->getId()]);
    }

    #[Route('/{id}/prices/new', name: 'price_create', methods: ['GET', 'POST'])]
    public function priceCreate(Configurator $configurator, Request $request, EntityManagerInterface $em, DecimalAmountTransformer $amounts, PriceRuleOverlapValidator $overlaps): Response
    {
        if ($request->isMethod('POST') && $this->validToken($request, 'price-create-' . $configurator->getId())) {
            try {
                $type = PriceType::from($this->required($request, 'price_type'));
                $currency = strtoupper($this->required($request, 'currency_code'));
                $raw = $this->required($request, 'amount');
                $rule = new ConfiguratorPriceRule($configurator, $currency, $this->required($request, 'charge_code'), $type, $type === PriceType::PERCENT ? $amounts->percentageToBasisPoints($raw) : $amounts->toMinorUnits($raw, $currency));
                $this->applyPriceRule($rule, $request, $em);
                $em->persist($rule);
                $all = $em->getRepository(ConfiguratorPriceRule::class)->findBy(['configurator' => $configurator]);
                $all[] = $rule;
                foreach ($overlaps->findOverlaps($all) as $overlap) {
                    if ($overlap['second'] === $rule || $overlap['first'] === $rule) {
                        throw new \DomainException(sprintf('Diese Mengenstaffel überschneidet sich mit einer bestehenden Preisregel (%d–%s).', $overlap['first']->getMinimumQuantity(), $overlap['first']->getMaximumQuantity() ?? '∞'));
                    }
                }
                $em->flush();

                return $this->redirectToRoute('cardnext_admin_configurator_prices', ['id' => $configurator->getId()]);
            } catch (\Throwable $e) {
                $this->addFlash('error', $e->getMessage());
            }
        }

        return $this->priceForm($configurator, $request, $em, $amounts);
    }

    #[Route('/{id}/prices/{rule}/delete', name: 'price_delete', methods: ['POST'])]
    public function priceDelete(Configurator $configurator, ConfiguratorPriceRule $rule, Request $request, EntityManagerInterface $em): Response
    {
        $this->assertSame($configurator, $rule->getConfigurator());
        if (!$this->validToken($request, 'price-delete-' . $rule->getId())) {
            throw $this->createAccessDeniedException();
        }
        $em->remove($rule);
        $em->flush();

        return $this->redirectToRoute('cardnext_admin_configurator_prices', ['id' => $configurator->getId()]);
    }

    private function priceForm(Configurator $configurator, Request $request, EntityManagerInterface $em, DecimalAmountTransformer $amounts): Response
    {
        $values = $em->createQueryBuilder()->select('v', 'f', 's')->from(ConfiguratorValue::class, 'v')->join('v.field', 'f')->join('f.section', 's')->where('s.configurator = :c')->setParameter('c', $configurator)->orderBy('s.position', 'ASC')->addOrderBy('f.position', 'ASC')->addOrderBy('v.position', 'ASC')->getQuery()->getResult();
        $fields = $em->createQueryBuilder()->select('f', 's')->from(ConfiguratorField::class, 'f')->join('f.section', 's')->where('s.configurator = :c')->andWhere('f.type IN (:types)')->setParameter('c', $configurator)->setParameter('types', [FieldType::INTEGER, FieldType::QUANTITY])->orderBy('s.position', 'ASC')->addOrderBy('f.position', 'ASC')->getQuery()->getResult();
        $channels = $em->getRepository(\App\Entity\Channel\Channel::class)->findBy([], ['code' => 'ASC']);
        $currencies = ['EUR'];
        foreach ($channels as $channel) {
            foreach ($channel->getCurrencies() as $currency) {
                $currencies[] = $currency->getCode();
            }
        } $currencies = array_values(array_unique($currencies));
        sort($currencies);

        return $this->render('admin/cardnext/configurator/price_form.html.twig', ['configurator' => $configurator, 'values' => $values, 'fields' => $fields, 'channels' => $channels, 'currencies' => $currencies, 'price_types' => PriceType::cases(), 'multipliers' => MultiplierType::cases(), 'percentage_bases' => PercentageBase::cases(), 'amounts' => $amounts]);
    }

    private function applySection(ConfiguratorSection $s, Request $r): void
    {
        $s->setDescription($this->nullable($r, 'description'));
        $s->setPosition($this->nonNegativeInt($r, 'position', 0, 'Die Position muss eine nicht negative ganze Zahl sein.'));
        $s->setEnabled($r->request->getBoolean('enabled'));
    }

    private function applyField(ConfiguratorField $f, Request $r): void
    {
        $f->setDescription($this->nullable($r, 'description'));
        $f->setHelpText($this->nullable($r, 'help_text'));
        $f->setRequired($r->request->getBoolean('required'));
        $f->setPosition($this->nonNegativeInt($r, 'position', 0, 'Die Position muss eine nicht negative ganze Zahl sein.'));
        $f->setEnabled($r->request->getBoolean('enabled'));
        $f->setMinimumValue($this->nullable($r, 'minimum_value'));
        $f->setMaximumValue($this->nullable($r, 'maximum_value'));
        $f->setStep($this->nullable($r, 'step'));
    }

    private function applyValue(ConfiguratorValue $v, Request $r): void
    {
        $v->setDescription($this->nullable($r, 'description'));
        $v->setPosition($this->nonNegativeInt($r, 'position', 0, 'Die Position muss eine nicht negative ganze Zahl sein.'));
        $v->setEnabled($r->request->getBoolean('enabled'));
        $color = $this->nullable($r, 'color_hex');
        if ($color !== null && preg_match('/^#[0-9A-Fa-f]{6}$/D', $color) !== 1) {
            throw new \DomainException('Die Farbe muss im Format #RRGGBB angegeben werden.');
        } $v->setColorHex($color);
        $v->setImagePath($this->nullable($r, 'image_path'));
        $v->setIcon($this->nullable($r, 'icon'));
    }

    private function applyPriceRule(ConfiguratorPriceRule $rule, Request $r, EntityManagerInterface $em): void
    {
        $valueId = $this->optionalPositiveInt($r, 'value_id', 'Die Wert-ID ist ungültig.');
        if ($valueId !== null) {
            $value = $em->find(ConfiguratorValue::class, $valueId) ?? throw new \DomainException('Der gewählte Wert existiert nicht.');
            $rule->setValue($value);
        } else {
            $rule->setValue(null);
        }
        $channelId = $this->optionalPositiveInt($r, 'channel_id', 'Die Channel-ID ist ungültig.');
        if ($channelId !== null) {
            $channel = $em->find(\App\Entity\Channel\Channel::class, $channelId) ?? throw new \DomainException('Der gewählte Channel existiert nicht.');
            $rule->setChannel($channel);
        } else {
            $rule->setChannel(null);
        }
        $rule->setLabel($this->nullable($r, 'label'));
        $minimum = $this->requiredPositiveInt($r, 'minimum_quantity', 'Die Mindestmenge muss eine ganze Zahl größer oder gleich 1 sein.');
        $maximum = $this->optionalPositiveInt($r, 'maximum_quantity', 'Die Höchstmenge muss eine ganze Zahl größer oder gleich 1 sein.');
        $rule->setQuantityRange($minimum, $maximum);
        $multiplier = MultiplierType::from($this->required($r, 'multiplier_type'));
        $fieldId = $this->optionalPositiveInt($r, 'multiplier_field_id', 'Die Multiplikatorfeld-ID ist ungültig.');
        if ($multiplier !== MultiplierType::FIELD_VALUE && $fieldId !== null) {
            throw new \DomainException('Ein Multiplikatorfeld ist nur für Feldwert-Multiplikatoren zulässig.');
        }
        $field = $fieldId !== null ? ($em->find(ConfiguratorField::class, $fieldId) ?? throw new \DomainException('Das Multiplikatorfeld existiert nicht.')) : null;
        $rule->setMultiplier($multiplier, $field);
        if ($rule->getPriceType() === PriceType::PERCENT) {
            $rule->setPercentageBase(PercentageBase::from($this->required($r, 'percentage_base')));
        } elseif ($r->request->get('percentage_base')) {
            throw new \DomainException('Eine Prozentbasis ist nur für prozentuale Regeln zulässig.');
        } $rule->setPriority($this->nonNegativeInt($r, 'priority', 0, 'Die Priorität muss eine nicht negative ganze Zahl sein.'));
        $rule->setEnabled($r->request->getBoolean('enabled'));
    }

    private function optionalPositiveInt(Request $request, string $key, string $error): ?int
    {
        $parameters = $request->request->all();
        if (!array_key_exists($key, $parameters) || $parameters[$key] === null || $parameters[$key] === '') {
            return null;
        }

        $value = $parameters[$key];
        if ((!is_int($value) && (!is_string($value) || preg_match('/^[1-9][0-9]*$/D', $value) !== 1)) || (is_int($value) && $value < 1)) {
            throw new \DomainException($error);
        }

        if (is_string($value) && filter_var($value, \FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false) {
            throw new \DomainException($error);
        }

        return (int) $value;
    }

    private function requiredPositiveInt(Request $request, string $key, string $error): int
    {
        return $this->optionalPositiveInt($request, $key, $error) ?? throw new \DomainException($error);
    }

    private function nonNegativeInt(Request $request, string $key, int $default, string $error): int
    {
        $parameters = $request->request->all();
        if (!array_key_exists($key, $parameters) || $parameters[$key] === null || $parameters[$key] === '') {
            return $default;
        }

        $value = $parameters[$key];
        if ((!is_int($value) && (!is_string($value) || preg_match('/^(0|[1-9][0-9]*)$/D', $value) !== 1)) || (is_int($value) && $value < 0)) {
            throw new \DomainException($error);
        }

        if (is_string($value) && filter_var($value, \FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]) === false) {
            throw new \DomainException($error);
        }

        return (int) $value;
    }

    private function required(Request $r, string $key): string
    {
        $value = trim((string) $r->request->get($key));
        if ($value === '') {
            throw new \DomainException(sprintf('%s ist erforderlich.', $key));
        }

        return $value;
    }

    private function nullable(Request $r, string $key): ?string
    {
        $value = trim((string) $r->request->get($key));

        return $value === '' ? null : $value;
    }

    private function validToken(Request $r, string $id): bool
    {
        if (!$this->isCsrfTokenValid($id, (string) $r->request->get('_token'))) {
            throw $this->createAccessDeniedException('Ungültiger CSRF-Token.');
        }

        return true;
    }

    private function assertSame(object $expected, object $actual): void
    {
        if ($expected !== $actual) {
            throw $this->createNotFoundException();
        }
    }

    private function assertChoiceField(Configurator $c, ConfiguratorField $f): void
    {
        $this->assertSame($c, $f->getConfigurator());
        if (!in_array($f->getType(), [FieldType::SINGLE_CHOICE, FieldType::MULTIPLE_CHOICE], true)) {
            throw $this->createNotFoundException();
        }
    }
}
