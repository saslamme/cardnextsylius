<?php

declare(strict_types=1); namespace App\Dto\Configurator;
final readonly class ConfiguratorConfiguration implements \JsonSerializable { /** @param array<string,mixed> $selections @param array<string,mixed> $metadata */ public function __construct(public string $configuratorCode,public int $quantity,public string $currencyCode,public string $channelCode,public array $selections=[],public array $metadata=[]){} public function jsonSerialize():array{return ['configuratorCode'=>$this->configuratorCode,'quantity'=>$this->quantity,'currencyCode'=>$this->currencyCode,'channelCode'=>$this->channelCode,'selections'=>$this->selections,'metadata'=>$this->metadata];} }
