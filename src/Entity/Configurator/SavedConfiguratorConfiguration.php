<?php

declare(strict_types=1);

namespace App\Entity\Configurator;

use App\Entity\Channel\Channel;
use App\Repository\Configurator\SavedConfiguratorConfigurationRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SavedConfiguratorConfigurationRepository::class)]
#[ORM\Table(name: 'cardnext_saved_configurator_configuration')]
#[ORM\Index(name: 'IDX_CN_SAVED_CONFIGURATOR', columns: ['configurator_id'])]
#[ORM\Index(name: 'IDX_CN_SAVED_CHANNEL', columns: ['channel_id'])]
#[ORM\Index(name: 'IDX_CN_SAVED_CREATED_AT', columns: ['created_at'])]
final class SavedConfiguratorConfiguration
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column(name: 'id')]
    private ?int $id = null;

    /** The random token is the only public identifier; snapshots have no mutators. */
    #[ORM\Column(name: 'token', length: 64, unique: true)]
    private string $token;

    #[ORM\ManyToOne(targetEntity: Configurator::class)]
    #[ORM\JoinColumn(name: 'configurator_id', nullable: false, onDelete: 'CASCADE')]
    private Configurator $configurator;

    #[ORM\ManyToOne(targetEntity: Channel::class)]
    #[ORM\JoinColumn(name: 'channel_id', nullable: false, onDelete: 'CASCADE')]
    private Channel $channel;

    #[ORM\Column(name: 'locale_code', length: 16)]
    private string $localeCode;

    #[ORM\Column(name: 'quantity')]
    private int $quantity;

    /** @var array<string, mixed> */
    #[ORM\Column(name: 'selections', type: Types::JSON)]
    private array $selections;

    #[ORM\Column(name: 'lead_time_code', length: 100, nullable: true)]
    private ?string $leadTimeCode;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    /** @param array<string, mixed> $selections */
    public function __construct(string $token, Configurator $configurator, Channel $channel, string $localeCode, int $quantity, array $selections, ?string $leadTimeCode)
    {
        $this->token = $token;
        $this->configurator = $configurator;
        $this->channel = $channel;
        $this->localeCode = $localeCode;
        $this->quantity = $quantity;
        $this->selections = $selections;
        $this->leadTimeCode = $leadTimeCode;
        $this->createdAt = new \DateTimeImmutable();
    }

    public static function generateToken(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(16)), '+/', '-_'), '=');
    }

    public function getId(): ?int { return $this->id; }
    public function getToken(): string { return $this->token; }
    public function getConfigurator(): Configurator { return $this->configurator; }
    public function getChannel(): Channel { return $this->channel; }
    public function getLocaleCode(): string { return $this->localeCode; }
    public function getQuantity(): int { return $this->quantity; }
    /** @return array<string, mixed> */
    public function getSelections(): array { return $this->selections; }
    public function getLeadTimeCode(): ?string { return $this->leadTimeCode; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }

    /** @return array{quantity: int, selections: array<string, mixed>, leadTimeCode: ?string} */
    public function configuration(): array
    {
        return ['quantity' => $this->quantity, 'selections' => $this->selections, 'leadTimeCode' => $this->leadTimeCode];
    }
}
