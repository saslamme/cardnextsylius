<?php
declare(strict_types=1);
namespace App\Integration\Freshdesk;

use App\Integration\Freshdesk\Dto\{FreshdeskConversationData, FreshdeskCreateTicketData, FreshdeskTicketData};
use App\Integration\Freshdesk\Exception\{FreshdeskApiException, FreshdeskAuthenticationException, FreshdeskNotConfiguredException, FreshdeskNotFoundException, FreshdeskProtocolException, FreshdeskRateLimitException, FreshdeskUnavailableException};
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class HttpFreshdeskClient implements FreshdeskClientInterface
{
    public function __construct(private HttpClientInterface $httpClient, private string $baseUri, private string $apiKey) {}
    public function checkConnection(): void { $this->request('GET', '/api/v2/account', 'check connection'); }
    public function createTicket(FreshdeskCreateTicketData $data): FreshdeskTicketData
    {
        return $this->ticket($this->request('POST', '/api/v2/tickets', 'create ticket', ['json' => ['email'=>$data->email,'name'=>$data->name,'subject'=>$data->subject,'description'=>$data->description,'status'=>2,'priority'=>1,'source'=>2]]), 'create ticket');
    }
    public function getTicket(int $ticketId): FreshdeskTicketData
    {
        if ($ticketId < 1) throw new \InvalidArgumentException('Ticket ID must be positive.');
        return $this->ticket($this->request('GET', '/api/v2/tickets/'.$ticketId, 'get ticket'), 'get ticket');
    }
    public function getConversations(int $ticketId): array
    {
        if ($ticketId < 1) throw new \InvalidArgumentException('Ticket ID must be positive.');
        $rows=$this->request('GET', '/api/v2/tickets/'.$ticketId.'/conversations', 'get conversations');
        if (!array_is_list($rows)) throw new FreshdeskProtocolException('get conversations');
        return array_map(fn(array $r): FreshdeskConversationData => new FreshdeskConversationData($this->positive($r,'id','get conversations'),$this->positive($r,'ticket_id','get conversations'),$this->nullablePositive($r,'user_id','get conversations'),$this->boolean($r,'incoming','get conversations'),$this->boolean($r,'private','get conversations'),$this->string($r,'body_text','get conversations'),$this->nullableString($r,'body'),$this->date($r,'created_at','get conversations'),$this->date($r,'updated_at','get conversations')), $rows);
    }
    /** @return array<mixed> */
    private function request(string $method,string $endpoint,string $operation,array $options=[]): array
    {
        $base=trim($this->baseUri); $key=trim($this->apiKey);
        if ($base==='' || $key==='') throw new FreshdeskNotConfiguredException($operation);
        $base=rtrim($base,'/'); $parts=parse_url($base);
        if ($parts===false || ($parts['scheme']??null)!=='https' || !isset($parts['host']) || isset($parts['query'],$parts['fragment'])) throw new FreshdeskNotConfiguredException($operation);
        $options += ['auth_basic'=>[$key,'X'],'headers'=>['Accept'=>'application/json'],'timeout'=>15,'max_duration'=>20];
        try { $response=$this->httpClient->request($method,$base.$endpoint,$options); $status=$response->getStatusCode(); $body=$response->getContent(false); }
        catch (TransportExceptionInterface $e) { throw new FreshdeskUnavailableException($operation, null, null, null, $e); }
        $decoded=json_decode($body,true);
        if ($status>=400) { $code=is_array($decoded)&&is_string($decoded['code']??null)?$decoded['code']:null; $retry=$status===429?$this->retryAfter($response->getHeaders(false)['retry-after'][0]??null):null; throw match(true) { in_array($status,[401,403],true)=>new FreshdeskAuthenticationException($operation,$status,$code), $status===404=>new FreshdeskNotFoundException($operation,$status,$code), $status===429=>new FreshdeskRateLimitException($operation,$status,$code,$retry), $status>=500=>new FreshdeskUnavailableException($operation,$status,$code), default=>new FreshdeskApiException($operation,$status,$code) }; }
        if (!is_array($decoded)) throw new FreshdeskProtocolException($operation,$status);
        return $decoded;
    }
    private function ticket(array $r,string $op): FreshdeskTicketData { return new FreshdeskTicketData($this->positive($r,'id',$op),$this->positive($r,'requester_id',$op),$this->string($r,'subject',$op),$this->string($r,'description_text',$op),$this->nullableString($r,'description'),$this->integer($r,'status',$op),$this->integer($r,'priority',$op),$this->nullablePositive($r,'responder_id',$op),$this->date($r,'created_at',$op),$this->date($r,'updated_at',$op)); }
    private function positive(array $r,string $k,string $op): int { $v=$this->integer($r,$k,$op); if($v<1) throw new FreshdeskProtocolException($op); return $v; }
    private function integer(array $r,string $k,string $op): int { if(!isset($r[$k])||!is_int($r[$k])) throw new FreshdeskProtocolException($op); return $r[$k]; }
    private function nullablePositive(array $r,string $k,string $op): ?int { if(!array_key_exists($k,$r)||$r[$k]===null)return null; return $this->positive($r,$k,$op); }
    private function string(array $r,string $k,string $op): string { if(!isset($r[$k])||!is_string($r[$k]))throw new FreshdeskProtocolException($op); return $r[$k]; }
    private function nullableString(array $r,string $k): ?string { return !array_key_exists($k,$r)||$r[$k]===null?null:(is_string($r[$k])?$r[$k]:null); }
    private function boolean(array $r,string $k,string $op): bool { if(!isset($r[$k])||!is_bool($r[$k]))throw new FreshdeskProtocolException($op); return $r[$k]; }
    private function date(array $r,string $k,string $op): \DateTimeImmutable { try{return new \DateTimeImmutable($this->string($r,$k,$op));}catch(\Exception){throw new FreshdeskProtocolException($op);} }
    private function retryAfter(mixed $value): ?int { return is_string($value)&&ctype_digit($value)?(int)$value:null; }
}
