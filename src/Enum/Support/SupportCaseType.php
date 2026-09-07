<?php
declare(strict_types=1);
namespace App\Enum\Support;
enum SupportCaseType: string { case TechnicalSupport='technical_support'; case Defect='defect'; case Repair='repair'; case Maintenance='maintenance'; case OrderIssue='order_issue'; case ProductQuestion='product_question'; case Other='other'; }
