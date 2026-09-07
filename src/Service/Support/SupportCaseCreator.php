<?php
declare(strict_types=1);
namespace App\Service\Support;

use App\Entity\Customer\Customer;
use App\Entity\Support\SupportCase;
use App\Integration\Freshdesk\Dto\FreshdeskCreateTicketData;
use App\Integration\Freshdesk\FreshdeskClientInterface;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Sylius\Component\Channel\Context\ChannelContextInterface;

final readonly class SupportCaseCreator
{
 public function __construct(private FreshdeskClientInterface $freshdesk,private FreshdeskTicketDescriptionBuilder $descriptionBuilder,private ChannelContextInterface $channelContext,private EntityManagerInterface $entityManager,private LoggerInterface $logger){}
 public function create(Customer $customer,SupportCaseCreateData $data):SupportCase
 {
  $email=trim((string)$customer->getEmail()); if(!filter_var($email,FILTER_VALIDATE_EMAIL))throw new \DomainException('Customer requires a valid email address.');
  if($data->order!==null && $data->order->getCustomer()!==$customer)throw new SupportCaseAccessDeniedException('Order does not belong to customer.');
  if($data->maintenanceContract!==null && $data->maintenanceContract->getCustomer()!==$customer)throw new SupportCaseAccessDeniedException('Maintenance contract does not belong to customer.');
  $serial=trim((string)$data->serialNumber); if($data->maintenanceContract!==null && $serial!=='' && !$data->maintenanceContract->hasSerialNumber($serial))throw new SupportCaseAccessDeniedException('Serial number does not belong to maintenance contract.');
  $name=trim((string)$customer->getFirstName().' '.(string)$customer->getLastName()); if($name==='')$name=$email;
  $channelCode=trim((string)$this->channelContext->getChannel()->getCode()); if($channelCode==='')throw new \DomainException('Current channel has no code.');
  $ticket=$this->freshdesk->createTicket(new FreshdeskCreateTicketData($email,$name,$data->subject,$this->descriptionBuilder->build($customer,$data)));
  $case=new SupportCase($customer,$ticket->id,$ticket->requesterId,$channelCode,$data->type,new \DateTimeImmutable()); $case->setOrder($data->order);$case->setProduct($data->product);$case->setMaintenanceContract($data->maintenanceContract);$case->setSerialNumber($data->serialNumber);
  try{$this->entityManager->persist($case);$this->entityManager->flush();}catch(\Throwable $e){$this->logger->error('Local persistence failed after Freshdesk ticket creation.',['operation'=>'persist support case','ticket_id'=>$ticket->id,'exception'=>$e::class]);throw $e;}
  return $case;
 }
}
