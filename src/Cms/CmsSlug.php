<?php
declare(strict_types=1);
namespace App\Cms;
final class CmsSlug { public const RESERVED=['admin','api','account','cart','checkout','login','register','search','configurator','quote','offer','payment','webhook','sitemap.xml']; public static function normalize(string $slug):string { $slug=strtolower(trim($slug," \t\n\r\0\x0B/")); $slug=(string)preg_replace('#/{2,}#','/',$slug); return $slug; } public static function isSafe(string $slug):bool { $slug=self::normalize($slug); if($slug===''||str_contains($slug,'..')||str_contains($slug,'%')||!preg_match('#^[\pL\pN][\pL\pN/_-]*$#u',$slug))return false; return !in_array(explode('/',$slug)[0],self::RESERVED,true); } }
