<?php
declare(strict_types=1);
namespace App\Enum\Leasing;
enum LeasingInquiryStatus: string
{
    case New='NEW'; case EnteredInLeaseSeven='ENTERED_IN_LEASE_SEVEN'; case UnderReview='UNDER_REVIEW';
    case Approved='APPROVED'; case Rejected='REJECTED'; case ContractSent='CONTRACT_SENT';
    case ContractCompleted='CONTRACT_COMPLETED'; case Cancelled='CANCELLED';
}
