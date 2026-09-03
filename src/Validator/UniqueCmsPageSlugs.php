<?php
declare(strict_types=1);
namespace App\Validator; use Symfony\Component\Validator\Constraint;
#[\Attribute(\Attribute::TARGET_CLASS)] final class UniqueCmsPageSlugs extends Constraint { public string $message='This CMS slug is already used by a published page in the selected channel and locale.'; public function getTargets():string{return self::CLASS_CONSTRAINT;} }
