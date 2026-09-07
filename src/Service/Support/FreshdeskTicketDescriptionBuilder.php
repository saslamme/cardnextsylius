<?php
declare(strict_types=1);
namespace App\Service\Support;
use App\Entity\Customer\Customer;
final class FreshdeskTicketDescriptionBuilder
{
 public function build(Customer $customer,SupportCaseCreateData $data):string { $lines=['Art der Anfrage'=>$data->type->value]; $erp=$customer->getB2bProfile()?->getErpCustomerNumber(); if($erp) $lines['Kundennummer']=$erp; if($data->order) $lines['Bestellung']=(string)$data->order->getNumber(); if($data->product){$lines['Produkt']=(string)$data->product->getName();$lines['Artikelcode']=$data->product->getCode();} if(trim((string)$data->serialNumber)!=='')$lines['Seriennummer']=trim((string)$data->serialNumber); if($data->maintenanceContract)$lines['Wartungsvertrag']=$data->maintenanceContract->getContractReference()??$data->maintenanceContract->getErpContractId(); $context=''; foreach($lines as $label=>$value){if(trim((string)$value)!=='')$context.='<strong>'.$this->e($label).':</strong><br>'.$this->e((string)$value).'<br>';} return nl2br($this->e($data->description),false).'<br><br>------------------------------<br><br><strong>Cardnext-Kontext</strong><br><br>'.$context; }
 private function e(string $v):string{return htmlspecialchars($v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
}
