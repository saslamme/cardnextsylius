<?php

declare(strict_types=1);

namespace App\Validator;

use Doctrine\Persistence\ManagerRegistry;
use Sylius\Component\Product\Model\ProductTranslationInterface;
use Sylius\Component\Taxonomy\Model\TaxonTranslationInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

final class PublicSlugUniqueValidator extends ConstraintValidator
{
    public function __construct(private readonly ManagerRegistry $doctrine)
    {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof PublicSlugUnique) {
            throw new UnexpectedTypeException($constraint, PublicSlugUnique::class);
        }

        if (!$value instanceof ProductTranslationInterface && !$value instanceof TaxonTranslationInterface) {
            throw new UnexpectedTypeException($value, ProductTranslationInterface::class . ' or ' . TaxonTranslationInterface::class);
        }

        $slug = $value->getSlug();
        $locale = $value->getLocale();
        if ($slug === null || $slug === '' || $locale === null || $locale === '') {
            return;
        }

        $counterpart = $value instanceof ProductTranslationInterface
            ? 'App\\Entity\\Taxonomy\\TaxonTranslation'
            : 'App\\Entity\\Product\\ProductTranslation';
        $collision = $this->doctrine->getRepository($counterpart)->findOneBy(['slug' => $slug, 'locale' => $locale]);

        if ($collision === null) {
            return;
        }

        $this->context->buildViolation($constraint->message)
            ->atPath('slug')
            ->setParameter('{{ slug }}', $slug)
            ->setParameter('{{ locale }}', $locale)
            ->setParameter('{{ type }}', $value instanceof ProductTranslationInterface ? 'taxon' : 'product')
            ->addViolation();
    }
}
