<?php

declare(strict_types=1);

namespace App\Entity\Customer;

use App\Entity\Channel\Channel;
use Doctrine\ORM\Mapping as ORM;
use Sylius\Component\Core\Model\Customer as BaseCustomer;

#[ORM\Entity]
#[ORM\Table(name: 'sylius_customer')]
class Customer extends BaseCustomer
{
    #[ORM\ManyToOne(targetEntity: Channel::class)]
    #[ORM\JoinColumn(name: 'sales_channel_id', nullable: true, onDelete: 'SET NULL')]
    private ?Channel $salesChannel = null;

    #[ORM\OneToOne(
        mappedBy: 'customer',
        targetEntity: CustomerB2BProfile::class,
        cascade: ['persist', 'remove'],
        orphanRemoval: true,
    )]
    private ?CustomerB2BProfile $b2bProfile = null;

    public function getB2bProfile(): ?CustomerB2BProfile
    {
        return $this->b2bProfile;
    }

    public function setB2bProfile(?CustomerB2BProfile $b2bProfile): void
    {
        $this->b2bProfile = $b2bProfile;

        if ($b2bProfile !== null && $b2bProfile->getCustomer() !== $this) {
            $b2bProfile->setCustomer($this);
        }
    }

    public function isB2bCustomer(): bool
    {
        return $this->b2bProfile?->isEnabled() ?? false;
    }

    public function getSalesChannel(): ?Channel
    {
        return $this->salesChannel;
    }

    public function setSalesChannel(?Channel $salesChannel): void
    {
        $this->salesChannel = $salesChannel;
    }
}
