<?php

declare(strict_types=1);

namespace App\Validator;

use App\Entity\Content\LegalPage;
use App\Repository\Content\LegalPageRepository;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

final class UniqueLegalPageChannelsValidator extends ConstraintValidator
{
    public function __construct(private readonly LegalPageRepository $repository)
    {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$value instanceof LegalPage || !$constraint instanceof UniqueLegalPageChannels || $value->getChannels()->isEmpty()) {
            return;
        }

        $conflictingChannels = [];
        foreach ($this->repository->findConflicts($value) as $conflict) {
            foreach ($conflict->getChannels() as $channel) {
                if ($value->getChannels()->contains($channel)) {
                    $conflictingChannels[$channel->getCode() ?? (string) $channel->getId()] = $channel->getName() ?? $channel->getCode();
                }
            }
        }

        foreach ($conflictingChannels as $channelName) {
            $this->context->buildViolation($constraint->message)
                ->atPath('channels')
                ->setParameter('{{ channel }}', (string) $channelName)
                ->addViolation();
        }
    }
}
