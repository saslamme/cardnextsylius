<?php

declare(strict_types=1);

namespace App\LegacyImport;

final readonly class CardnextLegacySourceParser
{
    public function __construct(private LegacyPriceParser $prices, private LegacyCategoryMapper $categories, private ?LegacyAttributeMapper $attributeMapper = null) {}

    public function parse(string $zipPath): LegacyImportPlan
    {
        $zip = new \ZipArchive();
        if ($zip->open($zipPath) !== true) throw new \RuntimeException(sprintf('ZIP "%s" is not readable.', $zipPath));
        foreach (['products/shop.artindex','products/shop.vendor','products/shop.attrib'] as $required) {
            if ($zip->locateName($required) === false) throw new \RuntimeException("Central source file $required is missing.");
        }
        $vendors = $this->lines($zip, 'products/shop.vendor');
        $vendorVocabulary = [];
        foreach ($vendors as $vendor) $vendorVocabulary[self::normalize($vendor)] = $vendor;
        $artIndex = [];
        foreach ($this->lines($zip, 'products/shop.artindex') as $line) {
            [$id, $mpn] = array_pad(explode('|', $line), 2, '');
            if (trim($id) !== '' && trim($mpn) !== '') $artIndex[trim($id)] = trim($mpn);
        }
        $attributeVocabulary = [];
        foreach ($this->lines($zip, 'products/shop.attrib') as $line) {
            $name = trim(explode('|', $line)[0]);
            if ($name !== '') $attributeVocabulary[LegacyAttributeMapper::fold($name)] = true;
        }

        $raw=[]; $ignored=0; $sourceFiles=0;
        for ($i=0; $i<$zip->numFiles; ++$i) {
            $name=$zip->getNameIndex($i);
            if ($name === false || str_contains($name,'__MACOSX/') || str_contains($name,'/._') || str_ends_with($name,'.DS_Store')) { ++$ignored; continue; }
            if (!str_ends_with($name,'.dat')) continue;
            ++$sourceFiles; $content=$zip->getFromIndex($i);
            if ($content === false) throw new \RuntimeException("Cannot read $name.");
            foreach (preg_split('/\R/', $content) ?: [] as $line) {
                if (trim($line)==='') continue;
                $fields=explode('|',$this->utf8($line));
                if (count($fields)!==62) throw new \RuntimeException(sprintf('%s has an unsupported field count.', $name));
                $raw[]=$this->record($name,$fields,$vendorVocabulary,$artIndex,$attributeVocabulary);
            }
        }
        $zipFiles=$zip->numFiles; $zip->close();
        return $this->deduplicate($raw,$zipFiles,$sourceFiles,$ignored,count($vendors),count($artIndex),count($attributeVocabulary));
    }

    /**
     * @param list<string> $f
     * @param array<string,string> $vendors
     * @param array<string,string> $artIndex
     * @param array<string,bool> $attributeVocabulary
     */
    private function record(string $file,array $f,array $vendors,array $artIndex,array $attributeVocabulary): LegacyProductRecord
    {
        $legacy=[];
        foreach (explode(':#',$f[25]) as $item) {
            $parts=explode(':',$item);
            if (count($parts)>=2 && trim($parts[0])!=='') $legacy[trim($parts[0])]=trim($parts[1]);
        }
        $mapped=($this->attributeMapper ?? new LegacyAttributeMapper())->map($legacy,$attributeVocabulary);
        $id=trim($f[0]); $datMpn=trim($f[3]); $indexedMpn=$artIndex[$id] ?? '';
        $reasons=[];
        if ($indexedMpn!=='' && $datMpn!=='' && self::normalize($indexedMpn)!==self::normalize($datMpn)) $reasons[]='artindex_mpn_mismatch';
        $mpn=$indexedMpn !== '' ? $indexedMpn : $datMpn;
        // Field 17 is the actual manufacturer. Field 2 is deliberately never a fallback.
        $manufacturer=trim($f[17]);
        if ($manufacturer==='' || !isset($vendors[self::normalize($manufacturer)])) $reasons[]=$manufacturer===''?'missing_manufacturer':'unknown_manufacturer';
        if ($mapped['unknown']!==[]) $reasons[]='unknown_attribute';
        $gtin=preg_replace('/\D/','',$f[35]) ?: null;
        return new LegacyProductRecord($id,basename($file),$manufacturer,$mpn,trim($f[4]),$this->prices->parse($f[5]),$this->sanitizeHtml($f[6]),$gtin,$this->categories->map(basename($file)),$mapped['attributes'],array_values(array_filter(array_map('trim',preg_split('/[,;]+/',$f[9]) ?: []))),str_contains(mb_strtolower($f[22]),'archiv') || str_starts_with(basename($file),'Archiv'),false,$f,$mapped['model'],$reasons);
    }

    /** @param list<LegacyProductRecord> $raw */
    private function deduplicate(array $raw,int $zipFiles,int $sourceFiles,int $ignored,int $vendorCount,int $artIndexCount,int $attributeVocabularyCount): LegacyImportPlan
    {
        $manufacturersByMpn=[];
        foreach ($raw as $r) if ($r->manufacturer!=='') $manufacturersByMpn[self::normalize($r->manufacturerPartNumber)][self::normalize($r->manufacturer)]=$r->manufacturer;
        foreach ($raw as $i=>$r) {
            $candidates=array_values($manufacturersByMpn[self::normalize($r->manufacturerPartNumber)] ?? []);
            if ($r->manufacturer==='' && count($candidates)===1) {
                $raw[$i]=new LegacyProductRecord($r->legacyId,$r->sourceFile,$candidates[0],$r->manufacturerPartNumber,$r->name,$r->price,$r->description,$r->gtin,$r->taxonCodes,$r->attributes,$r->compatibilityReferences,$r->archived,$r->hasImage,$r->rawData,$r->model,array_values(array_diff($r->reviewReasons,['missing_manufacturer'])));
            }
        }
        $byKey=[]; $conflicts=[]; $conflictDetails=[];
        foreach ($raw as $record) {
            $mpn=self::normalize($record->manufacturerPartNumber); $manufacturer=self::normalize($record->manufacturer);
            $key=$mpn!=='' ? 'sku:'.$manufacturer.':'.$mpn : ($record->gtin ? 'gtin:'.$record->gtin : 'legacy:'.$record->legacyId);
            if (!isset($byKey[$key])) { $byKey[$key]=$record; continue; }
            $old=$byKey[$key]; $reasons=array_values(array_unique([...$old->reviewReasons,...$record->reviewReasons]));
            if ($old->price!==null && $record->price!==null && $old->price!==$record->price) { $conflicts[$key]='price_conflict'; $reasons[]='price_conflict'; $conflictDetails[$key]=['type'=>'price_conflict','manufacturer'=>$old->manufacturer,'mpn'=>$old->manufacturerPartNumber,'legacy_ids'=>[$old->legacyId,$record->legacyId],'prices'=>[$old->price,$record->price]]; }
            if ($old->gtin!==null && $record->gtin!==null && $old->gtin!==$record->gtin) { $conflicts[$key]='gtin_conflict'; $reasons[]='gtin_conflict'; }
            $price=isset($conflicts[$key]) ? null : ($old->price??$record->price);
            $byKey[$key]=new LegacyProductRecord($old->legacyId,$old->sourceFile,$old->manufacturer,$old->manufacturerPartNumber,strlen($old->name)>=strlen($record->name)?$old->name:$record->name,$price,strlen($old->description)>=strlen($record->description)?$old->description:$record->description,$old->gtin??$record->gtin,array_values(array_unique([...$old->taxonCodes,...$record->taxonCodes])),$old->attributes+$record->attributes,array_values(array_unique([...$old->compatibilityReferences,...$record->compatibilityReferences])),$old->archived&&$record->archived,false,$old->rawData,$old->model?:$record->model,array_values(array_unique($reasons)));
        }
        $records=array_values($byKey);
        // Resolve field 9 only as a general product relation. It is not evidence for a device model.
        $mpnToCode=[];
        foreach ($records as $r) $mpnToCode[self::normalize($r->manufacturerPartNumber)][]=self::productCode($r);
        foreach ($records as $i=>$r) {
            $related=[]; $reasons=$r->reviewReasons;
            foreach ($r->compatibilityReferences as $ref) {
                $targets=array_values(array_unique($mpnToCode[self::normalize($ref)] ?? []));
                if (count($targets)===1 && $targets[0]!==self::productCode($r)) $related[]=$targets[0];
                elseif ($targets===[]) $reasons[]='unresolved_relation';
            }
            $records[$i]=new LegacyProductRecord($r->legacyId,$r->sourceFile,$r->manufacturer,$r->manufacturerPartNumber,$r->name,$r->price,$r->description,$r->gtin,$r->taxonCodes,$r->attributes,$r->compatibilityReferences,$r->archived,$r->hasImage,$r->rawData,$r->model,array_values(array_unique($reasons)),array_values(array_unique($related)));
        }
        $manufacturers=[]; foreach($records as $r) if($r->manufacturer!=='') $manufacturers[self::normalize($r->manufacturer)]=true;
        $missing=fn(callable $test):int=>count(array_filter($records,$test));
        $requiresReview=static fn(LegacyProductRecord $r):bool => $r->manufacturerPartNumber==='' || $r->taxonCodes===[] || array_diff($r->reviewReasons,['unresolved_relation'])!==[];
        $needsReview=$missing($requiresReview);
        $relations=array_sum(array_map(fn($r)=>count($r->relatedProductCodes),$records));
        $unknownCategoryProducts=array_map(static fn($r)=>['legacy_id'=>$r->legacyId,'mpn'=>$r->manufacturerPartNumber,'source_file'=>$r->sourceFile,'archived'=>$r->archived],array_values(array_filter($records,fn($r)=>$r->taxonCodes===[])));
        $report=['zip_files_total'=>$zipFiles,'relevant_source_files'=>$sourceFiles,'ignored_macos_files'=>$ignored,'source_records_total'=>count($raw),'processed_legacy_records'=>count($raw),'deduplicated_records'=>count($raw)-count($records),'unique_skus'=>count($records),'products'=>count($records),'product_variants'=>count($records),'manufacturers'=>count($manufacturers),'vendor_vocabulary_entries'=>$vendorCount,'artindex_entries'=>$artIndexCount,'attribute_vocabulary_entries'=>$attributeVocabularyCount,'taxon_assignments'=>array_sum(array_map(fn($r)=>count($r->taxonCodes),$records)),'attribute_values'=>array_sum(array_map(fn($r)=>count($r->attributes),$records)),'device_models'=>0,'compatibilities'=>$relations,'normal_prices'=>$missing(fn($r)=>$r->price!==null),'tier_prices'=>0,'products_imported'=>$missing(fn($r)=>$r->taxonCodes!==[]),'products_needs_review'=>$needsReview,'missing_mpn'=>$missing(fn($r)=>$r->manufacturerPartNumber===''),'missing_gtin'=>$missing(fn($r)=>$r->gtin===null),'missing_image'=>count($records),'missing_price'=>$missing(fn($r)=>$r->price===null),'unknown_manufacturers'=>$missing(fn($r)=>in_array('unknown_manufacturer',$r->reviewReasons,true)||in_array('missing_manufacturer',$r->reviewReasons,true)),'unknown_categories'=>$missing(fn($r)=>$r->taxonCodes===[]),'unknown_attributes'=>$missing(fn($r)=>in_array('unknown_attribute',$r->reviewReasons,true)),'unresolved_relations'=>$missing(fn($r)=>in_array('unresolved_relation',$r->reviewReasons,true)),'real_conflicts'=>count($conflicts),'conflicts'=>count($conflicts),'conflict_details'=>array_values($conflictDetails),'unknown_category_products'=>$unknownCategoryProducts];
        return new LegacyImportPlan($records,$report,array_keys($conflicts));
    }

    public static function productCode(LegacyProductRecord $r): string { return 'LEGACY_'.self::normalize($r->manufacturer).'_'.self::normalize($r->manufacturerPartNumber ?: $r->legacyId); }
    public static function normalize(string $value): string { return mb_strtoupper(preg_replace('/[^A-Z0-9]+/i','',trim($value)) ?? ''); }
    /** @return list<string> */ private function lines(\ZipArchive $zip,string $name):array { $s=$zip->getFromName($name); if($s===false) throw new \RuntimeException("Cannot read $name."); return array_values(array_filter(array_map(fn($v)=>trim($this->utf8($v)),preg_split('/\R/',$s) ?: []),fn($v)=>$v!=='')); }
    private function utf8(string $value):string { return mb_check_encoding($value,'UTF-8')?$value:mb_convert_encoding($value,'UTF-8','Windows-1252'); }
    private function sanitizeHtml(string $html):string { $html=preg_replace('#<(script|iframe)\b[^>]*>.*?</\1>#is','',$html)??''; return preg_replace('/\s(on\w+|style)=("[^"]*"|\'[^\']*\')/i','',$html)??''; }
}
