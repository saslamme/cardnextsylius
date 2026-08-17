<?php

declare(strict_types=1); namespace App\Dto\Configurator; final readonly class ValidationResult implements \JsonSerializable { /** @param list<ValidationError> $errors */ public function __construct(public array $errors=[]){} public function isValid():bool{return $this->errors===[];} public function jsonSerialize():array{return ['valid'=>$this->isValid(),'errors'=>$this->errors];} }
