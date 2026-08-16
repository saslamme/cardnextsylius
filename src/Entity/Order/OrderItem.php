<?php

declare(strict_types=1);

namespace App\Entity\Order;

use App\Entity\Product\ProductVariant;
use Doctrine\ORM\Mapping as ORM;
use Sylius\Component\Core\Model\OrderItem as BaseOrderItem;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ORM\Entity]
#[ORM\Table(name: 'sylius_order_item')]
class OrderItem extends BaseOrderItem
{
    #[Assert\Callback]
    public function validateCardnextOrderQuantity(ExecutionContextInterface $context): void
    {
        $variant = $this->getVariant();
        if (!$variant instanceof ProductVariant || $variant->isValidOrderQuantity($this->getQuantity())) {
            return;
        }

        if ($this->getQuantity() < $variant->getMinimumOrderQuantity()) {
            $context->buildViolation('Die Mindestbestellmenge für diesen Artikel beträgt {{ minimum }}.')
                ->setParameter('{{ minimum }}', (string) $variant->getMinimumOrderQuantity())
                ->atPath('quantity')
                ->addViolation();

            return;
        }

        $context->buildViolation('Die Menge muss ab {{ minimum }} in Schritten von {{ increment }} bestellt werden.')
            ->setParameter('{{ minimum }}', (string) $variant->getMinimumOrderQuantity())
            ->setParameter('{{ increment }}', (string) $variant->getOrderIncrement())
            ->atPath('quantity')
            ->addViolation();
    }
}
