<?php
declare(strict_types=1);
namespace App\Service\Support;
use App\Entity\Maintenance\MaintenanceContract; use App\Entity\Order\Order; use App\Entity\Product\Product; use App\Enum\Support\SupportCaseType;
final readonly class SupportCaseCreateData { public function __construct(public SupportCaseType $type,public string $subject,public string $description,public ?Order $order=null,public ?Product $product=null,public ?MaintenanceContract $maintenanceContract=null,public ?string $serialNumber=null){} }
