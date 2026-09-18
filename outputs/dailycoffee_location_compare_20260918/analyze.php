<?php
$configPath = 'C:/xampp/htdocs/github/instalari_offline_ecogest/dailycoffee_cu_mini_admin/config_offline_dailycoffee.json';
$config = json_decode(file_get_contents($configPath), true);
$url = $config['online_products_sync']['api_url'];
$key = $config['sync_api_key'] ?? $config['online_products_sync']['sync_api_key'] ?? '';
$params = http_build_query(['api_key'=>$key,'cod_client'=>2,'cod_locatie'=>2,'include_all_locations'=>1]);
$ch = curl_init($url.'?'.$params);
curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>60,CURLOPT_SSL_VERIFYPEER=>false]);
$raw=curl_exec($ch);
if($raw===false){fwrite(STDERR,curl_error($ch)); exit(2);}
curl_close($ch);
$j=json_decode($raw,true);
if(!is_array($j) || ($j['status']??'')!=='success'){fwrite(STDERR,$raw); exit(3);}
$products=$j['data']??[];
$maps=$j['produse_servicii_locatii']??[];
$loc2=[];
foreach($maps as $m){
    if((int)($m['cod_locatie']??0)===2 && (int)($m['activ']??0)===1){
        $loc2[(string)$m['cod_produs']]=1;
    }
}
function norm_name($s){
    $s=trim((string)$s);
    $s=strtr($s,['ă'=>'a','Ă'=>'A','â'=>'a','Â'=>'A','î'=>'i','Î'=>'I','ș'=>'s','Ș'=>'S','ş'=>'s','Ş'=>'S','ț'=>'t','Ț'=>'T','ţ'=>'t','Ţ'=>'T']);
    $s=mb_strtoupper($s,'UTF-8');
    return preg_replace('/[^A-Z0-9]+/u','',$s);
}
function product_code($p){ return (string)($p['cod_produs']??$p['cod']??''); }
function product_name($p){ return $p['nume_produs']??$p['nume']??''; }
function product_price($p){ return (float)($p['pret_vanzare']??$p['pret']??$p['pret_cu_tva']??0); }
$groups=[];
foreach($products as $p){
    if((int)($p['activ']??0)!==1){ continue; }
    $code=product_code($p);
    if($code===''){ continue; }
    $p['_code']=(int)$code;
    $p['_loc1']=1;
    $p['_loc2']=isset($loc2[$code])?1:0;
    $p['_norm']=norm_name(product_name($p));
    if($p['_norm']===''){ continue; }
    $groups[$p['_norm']][]=$p;
}
$rows=[];
$groupCount=0;
foreach($groups as $norm=>$items){
    $codes=[];
    foreach($items as $p){ $codes[$p['_code']]=true; }
    if(count($codes)<2){ continue; }
    usort($items,function($a,$b){return $a['_code']<=>$b['_code'];});
    $groupCount++;
    $prices=array_map('product_price',$items);
    foreach($items as $p){
        $p['_group_count']=count($items);
        $p['_group_min_code']=$items[0]['_code'];
        $p['_group_max_code']=$items[count($items)-1]['_code'];
        $p['_group_min_price']=min($prices);
        $p['_group_max_price']=max($prices);
        $p['_price']=product_price($p);
        $rows[]=$p;
    }
}
$example=[];
foreach($products as $p){
    $code=product_code($p);
    if(in_array($code,['250','925'],true)){
        $example[]=['code'=>$code,'name'=>product_name($p),'price'=>product_price($p),'activ'=>$p['activ']??null,'loc2'=>isset($loc2[$code])?1:0,'category'=>$p['categorie']??$p['nume_categorie']??null,'raw_keys'=>array_keys($p)];
    }
}
$offlinePath='C:/xampp/htdocs/github/instalari_offline_ecogest/dailycoffee_cu_mini_admin/api_offline_ecogest_dailycoffee/db_local/pos.db';
$offlineDb=new PDO('sqlite:'.$offlinePath);
$offlineProducts=$offlineDb->query('SELECT * FROM `produse_servicii`')->fetchAll(PDO::FETCH_ASSOC);
$offlineMaps=$offlineDb->query('SELECT * FROM `produse_servicii_locatii`')->fetchAll(PDO::FETCH_ASSOC);
$offlineCategories=$offlineDb->query('SELECT * FROM `categorii`')->fetchAll(PDO::FETCH_ASSOC);
$offlineCategoryLocations=$offlineDb->query('SELECT * FROM `categorii_locatii`')->fetchAll(PDO::FETCH_ASSOC);
$out=[
    'products_total'=>count($products),
    'maps_total'=>count($maps),
    'active_products'=>count(array_filter($products,fn($p)=>(int)($p['activ']??0)===1)),
    'loc2_active'=>count($loc2),
    'duplicate_name_groups'=>$groupCount,
    'duplicate_rows'=>count($rows),
    'example'=>$example,
    'online_products'=>$products,
    'rows'=>$rows,
    'online_categories'=>$j['categorii']??[],
    'online_category_locations'=>$j['categorii_locatii']??[],
    'online_product_locations'=>$maps,
    'offline_products'=>$offlineProducts,
    'offline_product_locations'=>$offlineMaps,
    'offline_categories'=>$offlineCategories,
    'offline_category_locations'=>$offlineCategoryLocations
];
echo json_encode($out,JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
