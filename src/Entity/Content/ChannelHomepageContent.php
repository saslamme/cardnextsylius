<?php

declare(strict_types=1);

namespace App\Entity\Content;

use App\Entity\Channel\Channel;
use App\Repository\Content\ChannelHomepageContentRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ChannelHomepageContentRepository::class)]
#[ORM\Table(name: 'cardnext_channel_homepage_content', uniqueConstraints: [new ORM\UniqueConstraint(name: 'uniq_homepage_channel_locale', columns: ['channel_id', 'locale_code'])])]
#[ORM\HasLifecycleCallbacks]
#[UniqueEntity(fields: ['channel', 'localeCode'], message: 'Für diesen Verkaufskanal und diese Sprache existieren bereits Homepage-Inhalte.', errorPath: 'localeCode')]
class ChannelHomepageContent
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private int $id;

    #[ORM\ManyToOne(targetEntity: Channel::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull(message: 'Bitte wählen Sie einen Verkaufskanal aus.')]
    private Channel $channel;

    #[ORM\Column(name: 'locale_code', length: 16)]
    #[Assert\NotBlank(message: 'Bitte wählen Sie eine Sprache aus.')]
    private string $localeCode = '';

    #[ORM\Column(name: 'meta_title', length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    private ?string $metaTitle = null;

    #[ORM\Column(name: 'meta_description', length: 320, nullable: true)]
    #[Assert\Length(max: 320)]
    private ?string $metaDescription = null;

    #[ORM\Column(name: 'hero_kicker', length: 255, nullable: true)]
    private ?string $heroKicker = null;

    #[ORM\Column(name: 'hero_title', length: 255, nullable: true)]
    private ?string $heroTitle = null;

    #[ORM\Column(name: 'hero_text', type: Types::TEXT, nullable: true)]
    private ?string $heroText = null;

    #[ORM\Column(name: 'hero_image_path', length: 255, nullable: true)]
    private ?string $heroImagePath = null;

    private ?UploadedFile $heroImageFile = null;

    private bool $removeHeroImage = false;

    #[ORM\Column(name: 'intro_kicker', length: 255, nullable: true)]
    private ?string $introKicker = null;

    #[ORM\Column(name: 'intro_title', length: 255, nullable: true)]
    private ?string $introTitle = null;

    #[ORM\Column(name: 'intro_text', type: Types::TEXT, nullable: true)]
    private ?string $introText = null;

    #[ORM\Column(name: 'intro_image_path', length: 255, nullable: true)]
    private ?string $introImagePath = null;

    private ?UploadedFile $introImageFile = null;

    private bool $removeIntroImage = false;

    #[ORM\Column(name: 'why_kicker', length: 255, nullable: true)]
    private ?string $whyKicker = null;

    #[ORM\Column(name: 'why_title', length: 255, nullable: true)]
    private ?string $whyTitle = null;

    #[ORM\Column(name: 'why_text', type: Types::TEXT, nullable: true)]
    private ?string $whyText = null;

    #[ORM\Column(name: 'cta_kicker', length: 255, nullable: true)]
    private ?string $ctaKicker = null;

    #[ORM\Column(name: 'cta_title', length: 255, nullable: true)]
    private ?string $ctaTitle = null;

    #[ORM\Column(name: 'cta_text', type: Types::TEXT, nullable: true)]
    private ?string $ctaText = null;

    #[ORM\Column(name: 'cta_image_path', length: 255, nullable: true)]
    private ?string $ctaImagePath = null;

    private ?UploadedFile $ctaImageFile = null;

    private bool $removeCtaImage = false;

    #[ORM\Column(name: 'printer_guide_enabled', options: ['default' => false])]
    private bool $printerGuideEnabled = false;

    #[ORM\Column(name: 'printer_guide_eyebrow', length: 255, nullable: true)]
    private ?string $printerGuideEyebrow = null;

    #[ORM\Column(name: 'printer_guide_headline', length: 255, nullable: true)]
    private ?string $printerGuideHeadline = null;

    #[ORM\Column(name: 'printer_guide_text', type: Types::TEXT, nullable: true)]
    private ?string $printerGuideText = null;

    #[ORM\Column(name: 'printer_guide_button_label', length: 255, nullable: true)]
    private ?string $printerGuideButtonLabel = null;

    #[ORM\Column(name: 'printer_guide_url', length: 2048, nullable: true)]
    #[Assert\Regex(pattern: '#^(?:/|https?://)#i', message: 'Bitte geben Sie eine relative URL oder eine HTTP(S)-URL ein.')]
    private ?string $printerGuideUrl = '/kartendrucker-berater';

    #[ORM\Column(name: 'printer_guide_image_path', length: 255, nullable: true)]
    private ?string $printerGuideImagePath = null;

    private ?UploadedFile $printerGuideImageFile = null;

    private bool $removePrinterGuideImage = false;

    #[ORM\Column(name: 'printer_guide_image_alt', length: 255, nullable: true)]
    private ?string $printerGuideImageAlt = null;

    #[ORM\Column(name: 'printer_guide_badge', length: 255, nullable: true)]
    private ?string $printerGuideBadge = null;

    #[ORM\Column(name: 'configurator_enabled', options: ['default' => false])]
    private bool $configuratorEnabled = false;

    #[ORM\Column(name: 'configurator_eyebrow', length: 255, nullable: true)]
    private ?string $configuratorEyebrow = null;

    #[ORM\Column(name: 'configurator_headline', length: 255, nullable: true)]
    private ?string $configuratorHeadline = null;

    #[ORM\Column(name: 'configurator_text', type: Types::TEXT, nullable: true)]
    private ?string $configuratorText = null;

    #[ORM\Column(name: 'configurator_button_label', length: 255, nullable: true)]
    private ?string $configuratorButtonLabel = null;

    #[ORM\Column(name: 'configurator_url', length: 2048, nullable: true)]
    #[Assert\Regex(pattern: '#^(?:/|https?://)#i', message: 'Bitte geben Sie eine relative URL oder eine HTTP(S)-URL ein.')]
    private ?string $configuratorUrl = '/kartenkonfigurator';

    #[ORM\Column(name: 'configurator_image_path', length: 255, nullable: true)]
    private ?string $configuratorImagePath = null;

    private ?UploadedFile $configuratorImageFile = null;

    private bool $removeConfiguratorImage = false;

    #[ORM\Column(name: 'configurator_image_alt', length: 255, nullable: true)]
    private ?string $configuratorImageAlt = null;

    #[ORM\Column(name: 'configurator_badge', length: 255, nullable: true)]
    private ?string $configuratorBadge = null;

    #[ORM\Column(name: 'footer_text', type: Types::TEXT, nullable: true)]
    private ?string $footerText = null;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->createdAt = $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id ?? null;
    }

    public function getChannel(): ?Channel
    {
        return $this->channel ?? null;
    }

    public function setChannel(Channel $channel): void
    {
        $this->channel = $channel;
    }

    public function getLocaleCode(): string
    {
        return $this->localeCode;
    }

    public function setLocaleCode(string $localeCode): void
    {
        $this->localeCode = $localeCode;
    }

    public function getMetaTitle(): ?string
    {
        return $this->metaTitle;
    }

    public function setMetaTitle(?string $metaTitle): void
    {
        $this->metaTitle = self::optional($metaTitle);
    }

    public function getMetaDescription(): ?string
    {
        return $this->metaDescription;
    }

    public function setMetaDescription(?string $metaDescription): void
    {
        $this->metaDescription = self::optional($metaDescription);
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    #[ORM\PreUpdate]
    public function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    private static function optional(?string $value): ?string
    {
        $value = $value === null ? null : trim($value);

        return $value === '' ? null : $value;
    }

    public function getHeroKicker(): ?string
    {
        return $this->heroKicker;
    }

    public function setHeroKicker(?string $v): void
    {
        $this->heroKicker = self::optional($v);
    }

    public function getHeroTitle(): ?string
    {
        return $this->heroTitle;
    }

    public function setHeroTitle(?string $v): void
    {
        $this->heroTitle = self::optional($v);
    }

    public function getHeroText(): ?string
    {
        return $this->heroText;
    }

    public function setHeroText(?string $v): void
    {
        $this->heroText = self::optional($v);
    }

    public function getHeroImagePath(): ?string
    {
        return $this->heroImagePath;
    }

    public function setHeroImagePath(?string $path): void
    {
        $this->heroImagePath = self::optional($path);
    }

    public function getHeroImageFile(): ?UploadedFile
    {
        return $this->heroImageFile;
    }

    public function setHeroImageFile(?UploadedFile $file): void
    {
        $this->heroImageFile = $file;
    }

    public function isRemoveHeroImage(): bool
    {
        return $this->removeHeroImage;
    }

    public function setRemoveHeroImage(bool $remove): void
    {
        $this->removeHeroImage = $remove;
    }

    public function getIntroKicker(): ?string
    {
        return $this->introKicker;
    }

    public function setIntroKicker(?string $v): void
    {
        $this->introKicker = self::optional($v);
    }

    public function getIntroTitle(): ?string
    {
        return $this->introTitle;
    }

    public function setIntroTitle(?string $v): void
    {
        $this->introTitle = self::optional($v);
    }

    public function getIntroText(): ?string
    {
        return $this->introText;
    }

    public function setIntroText(?string $v): void
    {
        $this->introText = self::optional($v);
    }

    public function getIntroImagePath(): ?string
    {
        return $this->introImagePath;
    }

    public function setIntroImagePath(?string $path): void
    {
        $this->introImagePath = self::optional($path);
    }

    public function getIntroImageFile(): ?UploadedFile
    {
        return $this->introImageFile;
    }

    public function setIntroImageFile(?UploadedFile $file): void
    {
        $this->introImageFile = $file;
    }

    public function isRemoveIntroImage(): bool
    {
        return $this->removeIntroImage;
    }

    public function setRemoveIntroImage(bool $remove): void
    {
        $this->removeIntroImage = $remove;
    }

    public function getWhyKicker(): ?string
    {
        return $this->whyKicker;
    }

    public function setWhyKicker(?string $v): void
    {
        $this->whyKicker = self::optional($v);
    }

    public function getWhyTitle(): ?string
    {
        return $this->whyTitle;
    }

    public function setWhyTitle(?string $v): void
    {
        $this->whyTitle = self::optional($v);
    }

    public function getWhyText(): ?string
    {
        return $this->whyText;
    }

    public function setWhyText(?string $v): void
    {
        $this->whyText = self::optional($v);
    }

    public function getCtaKicker(): ?string
    {
        return $this->ctaKicker;
    }

    public function setCtaKicker(?string $v): void
    {
        $this->ctaKicker = self::optional($v);
    }

    public function getCtaTitle(): ?string
    {
        return $this->ctaTitle;
    }

    public function setCtaTitle(?string $v): void
    {
        $this->ctaTitle = self::optional($v);
    }

    public function getCtaText(): ?string
    {
        return $this->ctaText;
    }

    public function setCtaText(?string $v): void
    {
        $this->ctaText = self::optional($v);
    }

    public function getCtaImagePath(): ?string
    {
        return $this->ctaImagePath;
    }

    public function setCtaImagePath(?string $path): void
    {
        $this->ctaImagePath = self::optional($path);
    }

    public function getCtaImageFile(): ?UploadedFile
    {
        return $this->ctaImageFile;
    }

    public function setCtaImageFile(?UploadedFile $file): void
    {
        $this->ctaImageFile = $file;
    }

    public function isRemoveCtaImage(): bool
    {
        return $this->removeCtaImage;
    }

    public function setRemoveCtaImage(bool $remove): void
    {
        $this->removeCtaImage = $remove;
    }

    public function getFooterText(): ?string
    {
        return $this->footerText;
    }

    public function setFooterText(?string $v): void
    {
        $this->footerText = self::optional($v);
    }

    public function isPrinterGuideEnabled(): bool
    {
        return $this->printerGuideEnabled;
    }

    public function setPrinterGuideEnabled(bool $value): void
    {
        $this->printerGuideEnabled = $value;
    }

    public function getPrinterGuideEyebrow(): ?string
    {
        return $this->printerGuideEyebrow;
    }

    public function setPrinterGuideEyebrow(?string $value): void
    {
        $this->printerGuideEyebrow = self::optional($value);
    }

    public function getPrinterGuideHeadline(): ?string
    {
        return $this->printerGuideHeadline;
    }

    public function setPrinterGuideHeadline(?string $value): void
    {
        $this->printerGuideHeadline = self::optional($value);
    }

    public function getPrinterGuideText(): ?string
    {
        return $this->printerGuideText;
    }

    public function setPrinterGuideText(?string $value): void
    {
        $this->printerGuideText = self::optional($value);
    }

    public function getPrinterGuideButtonLabel(): ?string
    {
        return $this->printerGuideButtonLabel;
    }

    public function setPrinterGuideButtonLabel(?string $value): void
    {
        $this->printerGuideButtonLabel = self::optional($value);
    }

    public function getPrinterGuideUrl(): ?string
    {
        return $this->printerGuideUrl;
    }

    public function setPrinterGuideUrl(?string $value): void
    {
        $this->printerGuideUrl = self::optional($value);
    }

    public function getPrinterGuideImagePath(): ?string
    {
        return $this->printerGuideImagePath;
    }

    public function setPrinterGuideImagePath(?string $value): void
    {
        $this->printerGuideImagePath = self::optional($value);
    }

    public function getPrinterGuideImageFile(): ?UploadedFile
    {
        return $this->printerGuideImageFile;
    }

    public function setPrinterGuideImageFile(?UploadedFile $value): void
    {
        $this->printerGuideImageFile = $value;
    }

    public function isRemovePrinterGuideImage(): bool
    {
        return $this->removePrinterGuideImage;
    }

    public function setRemovePrinterGuideImage(bool $value): void
    {
        $this->removePrinterGuideImage = $value;
    }

    public function getPrinterGuideImageAlt(): ?string
    {
        return $this->printerGuideImageAlt;
    }

    public function setPrinterGuideImageAlt(?string $value): void
    {
        $this->printerGuideImageAlt = self::optional($value);
    }

    public function getPrinterGuideBadge(): ?string
    {
        return $this->printerGuideBadge;
    }

    public function setPrinterGuideBadge(?string $value): void
    {
        $this->printerGuideBadge = self::optional($value);
    }

    public function isConfiguratorEnabled(): bool
    {
        return $this->configuratorEnabled;
    }

    public function setConfiguratorEnabled(bool $value): void
    {
        $this->configuratorEnabled = $value;
    }

    public function getConfiguratorEyebrow(): ?string
    {
        return $this->configuratorEyebrow;
    }

    public function setConfiguratorEyebrow(?string $value): void
    {
        $this->configuratorEyebrow = self::optional($value);
    }

    public function getConfiguratorHeadline(): ?string
    {
        return $this->configuratorHeadline;
    }

    public function setConfiguratorHeadline(?string $value): void
    {
        $this->configuratorHeadline = self::optional($value);
    }

    public function getConfiguratorText(): ?string
    {
        return $this->configuratorText;
    }

    public function setConfiguratorText(?string $value): void
    {
        $this->configuratorText = self::optional($value);
    }

    public function getConfiguratorButtonLabel(): ?string
    {
        return $this->configuratorButtonLabel;
    }

    public function setConfiguratorButtonLabel(?string $value): void
    {
        $this->configuratorButtonLabel = self::optional($value);
    }

    public function getConfiguratorUrl(): ?string
    {
        return $this->configuratorUrl;
    }

    public function setConfiguratorUrl(?string $value): void
    {
        $this->configuratorUrl = self::optional($value);
    }

    public function getConfiguratorImagePath(): ?string
    {
        return $this->configuratorImagePath;
    }

    public function setConfiguratorImagePath(?string $value): void
    {
        $this->configuratorImagePath = self::optional($value);
    }

    public function getConfiguratorImageFile(): ?UploadedFile
    {
        return $this->configuratorImageFile;
    }

    public function setConfiguratorImageFile(?UploadedFile $value): void
    {
        $this->configuratorImageFile = $value;
    }

    public function isRemoveConfiguratorImage(): bool
    {
        return $this->removeConfiguratorImage;
    }

    public function setRemoveConfiguratorImage(bool $value): void
    {
        $this->removeConfiguratorImage = $value;
    }

    public function getConfiguratorImageAlt(): ?string
    {
        return $this->configuratorImageAlt;
    }

    public function setConfiguratorImageAlt(?string $value): void
    {
        $this->configuratorImageAlt = self::optional($value);
    }

    public function getConfiguratorBadge(): ?string
    {
        return $this->configuratorBadge;
    }

    public function setConfiguratorBadge(?string $value): void
    {
        $this->configuratorBadge = self::optional($value);
    }
}
