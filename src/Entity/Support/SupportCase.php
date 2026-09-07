<?php
declare(strict_types=1);
namespace App\Entity\Support;

use App\Entity\Customer\Customer;
use App\Entity\Maintenance\MaintenanceContract;
use App\Entity\Order\Order;
use App\Entity\Product\Product;
use App\Enum\Support\SupportCaseType;
use App\Repository\Support\SupportCaseRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SupportCaseRepository::class)]
#[ORM\Table(name: 'cardnext_support_case')]
#[ORM\UniqueConstraint(name:'UNIQ_SUPPORT_FRESHDESK_TICKET',columns:['freshdesk_ticket_id'])]
#[ORM\Index(columns:['customer_id'],name:'IDX_SUPPORT_CUSTOMER')]
#[ORM\Index(columns:['channel_code'],name:'IDX_SUPPORT_CHANNEL')]
#[ORM\Index(columns:['service_type'],name:'IDX_SUPPORT_TYPE')]
#[ORM\Index(columns:['serial_number'],name:'IDX_SUPPORT_SERIAL')]
class SupportCase
{
 #[ORM\Id, ORM\GeneratedValue, ORM\Column(type:'integer')] private ?int $id=null;
 #[ORM\ManyToOne(targetEntity:Customer::class), ORM\JoinColumn(name:'customer_id',referencedColumnName:'id',nullable:false,onDelete:'RESTRICT')] private Customer $customer;
 #[ORM\Column(name:'freshdesk_ticket_id',type:'bigint')] private int $freshdeskTicketId;
 #[ORM\Column(name:'freshdesk_requester_id',type:'bigint')] private int $freshdeskRequesterId;
 #[ORM\Column(name:'channel_code',length:64)] private string $channelCode;
 #[ORM\Column(name:'service_type',length:64,enumType:SupportCaseType::class)] private SupportCaseType $serviceType;
 #[ORM\ManyToOne(targetEntity:Order::class), ORM\JoinColumn(name:'order_id',referencedColumnName:'id',nullable:true,onDelete:'SET NULL')] private ?Order $order=null;
 #[ORM\ManyToOne(targetEntity:Product::class), ORM\JoinColumn(name:'product_id',referencedColumnName:'id',nullable:true,onDelete:'SET NULL')] private ?Product $product=null;
 #[ORM\ManyToOne(targetEntity: MaintenanceContract::class)]
 #[ORM\JoinColumn(
     name: 'maintenance_contract_id',
     referencedColumnName: 'id',
     nullable: true,
     onDelete: 'SET NULL'
 )]
 private ?MaintenanceContract $maintenanceContract=null;
 #[ORM\Column(name:'serial_number',length:255,nullable:true)] private ?string $serialNumber=null;
 #[ORM\Column(name:'created_at',type:'datetime_immutable')] private \DateTimeImmutable $createdAt;
 #[ORM\Column(name:'updated_at',type:'datetime_immutable')] private \DateTimeImmutable $updatedAt;
 public function __construct(Customer $customer,int $ticketId,int $requesterId,string $channelCode,SupportCaseType $type,\DateTimeImmutable $createdAt){if($ticketId<1||$requesterId<1)throw new \InvalidArgumentException('Freshdesk IDs must be positive.');$channelCode=trim($channelCode);if($channelCode==='')throw new \InvalidArgumentException('Channel code is required.');$this->customer=$customer;$this->freshdeskTicketId=$ticketId;$this->freshdeskRequesterId=$requesterId;$this->channelCode=$channelCode;$this->serviceType=$type;$this->createdAt=$this->updatedAt=$createdAt;}
 public function getId():?int{return $this->id;} public function getCustomer():Customer{return $this->customer;} public function getFreshdeskTicketId():int{return $this->freshdeskTicketId;} public function getFreshdeskRequesterId():int{return $this->freshdeskRequesterId;} public function getChannelCode():string{return $this->channelCode;} public function getServiceType():SupportCaseType{return $this->serviceType;} public function getOrder():?Order{return $this->order;} public function getProduct():?Product{return $this->product;} public function getMaintenanceContract():?MaintenanceContract{return $this->maintenanceContract;} public function getSerialNumber():?string{return $this->serialNumber;} public function getCreatedAt():\DateTimeImmutable{return $this->createdAt;} public function getUpdatedAt():\DateTimeImmutable{return $this->updatedAt;}
 public function setOrder(?Order $v):void{$this->order=$v;$this->touch();} public function setProduct(?Product $v):void{$this->product=$v;$this->touch();} public function setMaintenanceContract(?MaintenanceContract $v):void{$this->maintenanceContract=$v;$this->touch();} public function setSerialNumber(?string $v):void{$v=$v===null?'':trim($v);$this->serialNumber=$v===''?null:$v;$this->touch();} private function touch():void{$this->updatedAt=new \DateTimeImmutable();}
}
