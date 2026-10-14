<?php
declare(strict_types=1);
function geo_schema(): void {
    $end=db()->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci':'';
    db()->exec('CREATE TABLE IF NOT EXISTS geo_links (uid VARCHAR(30) PRIMARY KEY, city_id INTEGER NOT NULL)'.$end);
}
function geo_data(): array {
    static $data=null;if($data!==null)return $data;
    $data=['province'=>[],'district'=>[],'neighborhood'=>[]];
    foreach(['province'=>'provinces','district'=>'districts','neighborhood'=>'neighborhoods'] as $level=>$file)foreach(json_decode(file_get_contents(__DIR__.'/data/'.$file.'.json'),true,512,JSON_THROW_ON_ERROR) as $row){$row['level']=$level;$row['uid']=$level[0].$row['id'];$data[$level][(int)$row['id']]=$row;}
    return $data;
}
function geo_label(array $row): string {
    $data=geo_data();$parts=[];
    if($row['level']!=='province')$parts[]=$data['province'][(int)$row['provinceId']]['name'];
    if($row['level']==='neighborhood')$parts[]=$data['district'][(int)$row['districtId']]['name'];
    $parts[]=$row['name'];return implode(' / ',$parts);
}
function geo_filter(array $input): array {
    $level=is_string($input['level']??null)?$input['level']:'province';if(!in_array($level,['province','district','neighborhood'],true))$level='province';
    $province=(int)($input['province']??0);$district=(int)($input['district']??0);$search=mb_strtolower(mb_substr(is_string($input['q']??null)?$input['q']:'',0,100),'UTF-8');
    return array_values(array_filter(geo_data()[$level],function($r)use($province,$district,$search,$level){return (!$province||($level==='province'?(int)$r['id']===$province:(int)$r['provinceId']===$province))&&(!$district||$level!=='neighborhood'||(int)$r['districtId']===$district)&&(!$search||mb_stripos(geo_label($r),$search)!==false);}));
}
function geo_find(string $uid): ?array { $level=match($uid[0]??''){'p'=>'province','d'=>'district','n'=>'neighborhood',default=>''};return $level?(geo_data()[$level][(int)substr($uid,1)]??null):null; }
function geo_activate(array $row): int {
    $existing=(int)query('SELECT city_id FROM geo_links WHERE uid=?',[$row['uid']])->fetchColumn();if($existing&&record('cities',$existing))return $existing;
    $parent=0;$data=geo_data();
    if($row['level']==='district')$parent=geo_activate($data['province'][(int)$row['provinceId']]);
    if($row['level']==='neighborhood')$parent=geo_activate($data['district'][(int)$row['districtId']]);
    $label=geo_label($row);$slug=slugify(str_replace(' / ',' ',$label));if(strlen($slug)>110)$slug=substr($slug,0,100).'-'.$row['id'];
    $city=by_slug('cities',$slug);$id=(int)($city['id']??0);
    if($id && (meta('cities',$id)['geo_uid']??$row['uid'])!==$row['uid']){$slug.='-'.$row['id'];$id=(int)(by_slug('cities',$slug)['id']??0);}
    if(!$id){query('INSERT INTO cities (name,slug,context,sectors) VALUES (?,?,?,?)',[mb_substr(str_replace(' / ',' ',$label),0,100),$slug,'Bu lokasyon için hizmet kapsamı, hedef kitle ve doğrulanabilir yerel ihtiyaç bilgisi editör tarafından tamamlanmalıdır.','']);$id=(int)db()->lastInsertId();}
    $extra=meta('cities',$id);$extra['level']=$row['level'];$extra['parent_id']=$parent;$extra['geo_uid']=$row['uid'];meta_save('cities',$id,$extra);
    query('DELETE FROM geo_links WHERE uid=?',[$row['uid']]);query('INSERT INTO geo_links (uid,city_id) VALUES (?,?)',[$row['uid'],$id]);return $id;
}
function geo_create_page(int $serviceId,int $cityId): bool {
    if(query('SELECT id FROM pages WHERE service_id=? AND city_id=?',[$serviceId,$cityId])->fetchColumn())return false;
    $service=record('services',$serviceId);$city=record('cities',$cityId);if(!$service||!$city)return false;
    $row=generate_page($service,$city);$columns=implode(',',array_keys($row));$marks=implode(',',array_fill(0,count($row),'?'));query("INSERT INTO pages ($columns) VALUES ($marks)",array_values($row));return true;
}
function geo_action(): bool {
    $action=post('action');if(!in_array($action,['geo_activate','geo_batch'],true))return false;
    check_csrf();authorize_area('locations-import');
    if($action==='geo_activate'){
        $ids=$_POST['geo_ids']??[];if(!is_array($ids)||count($ids)>100)throw new InvalidArgumentException('En fazla 100 lokasyon seçin.');$count=0;
        db()->beginTransaction();try{foreach($ids as $uid){$r=is_string($uid)?geo_find($uid):null;if($r){geo_activate($r);$count++;}}db()->commit();}catch(Throwable $e){db()->rollBack();throw $e;}
        audit('Lokasyonlar etkinleştirildi',(string)$count);flash("$count lokasyon aktif listeye alındı. Üst lokasyonlar otomatik bağlandı.");return true;
    }
    $rows=geo_filter($_POST);$services=$_POST['services']??[];if(!is_array($services)||count($services)>50)throw new InvalidArgumentException('Hizmet seçimini kontrol edin.');$services=array_values(array_unique(array_map('intval',$services)));
    foreach($services as $id)if(!record('services',$id))throw new InvalidArgumentException('Hizmet bulunamadı.');
    $mode=post('mode');if(!in_array($mode,['activate','generate'],true)||($mode==='generate'&&!$services))throw new InvalidArgumentException('İşlem ve hizmet seçin.');
    $width=$mode==='generate'?count($services):1;$total=count($rows)*$width;$offset=max(0,(int)post('offset'));$end=min($total,$offset+40);$created=0;
    db()->beginTransaction();try{for($i=$offset;$i<$end;$i++){$city=geo_activate($rows[intdiv($i,$width)]);if($mode==='generate')$created+=(int)geo_create_page($services[$i%$width],$city);else $created++;}db()->commit();}catch(Throwable $e){db()->rollBack();throw $e;}
    if($offset===0||$end===$total)audit('Katalog toplu işlem',"$mode: $end / $total");
    header('Content-Type: application/json; charset=utf-8');echo json_encode(['next'=>$end,'total'=>$total,'created'=>$created,'done'=>$end>=$total]);exit;
}
function geo_admin(): void {
    $data=geo_data();$rows=geo_filter($_GET);$page=max(1,(int)($_GET['page']??1));$max=max(1,(int)ceil(count($rows)/50));$page=min($page,$max);$level=is_string($_GET['level']??null)?$_GET['level']:'province';$province=(int)($_GET['province']??0);$district=(int)($_GET['district']??0);
    $active=query('SELECT uid,city_id FROM geo_links')->fetchAll(PDO::FETCH_KEY_PAIR);
    ?><div class="stat-grid"><div><span>İl</span><strong><?=count($data['province'])?></strong><small>Türkiye kapsamı</small></div><div><span>İlçe</span><strong><?=count($data['district'])?></strong><small>Merkez ilçeler dahil</small></div><div><span>Mahalle</span><strong><?=number_format(count($data['neighborhood']),0,',','.')?></strong><small>Kaynak veri kapsamı</small></div><div><span>Etkin katalog kaydı</span><strong><?=count($active)?></strong><small>PSEO için seçilenler</small></div></div>
    <section class="panel"><h2>Hedef bölgelerinizi seçin</h2><p class="help">Katalog site dosyalarıyla birlikte gelir; dış API veya abonelik gerekmez. Semtler her yerde resmî bir idari seviye değildir; katalogda olmayan semtleri Aktif lokasyonlar bölümünden ekleyebilirsiniz. Kaynak: TurkiyeAPI açık coğrafi verisi. Yerel bilginizi yayın öncesinde doğrulayın.</p><form method="get" class="geo-filters"><input type="hidden" name="tab" value="locations-import"><?php ui_select('Seviye','level',['province'=>'İller','district'=>'İlçeler','neighborhood'=>'Mahalleler'],$level);$opts=[0=>'Tüm iller'];foreach($data['province'] as $p)$opts[$p['id']]=$p['name'];ui_select('İl','province',$opts,$province);$opts=[0=>'Tüm ilçeler'];foreach($data['district'] as $d)if(!$province||(int)$d['provinceId']===$province)$opts[$d['id']]=($province?'':$data['province'][$d['provinceId']]['name'].' / ').$d['name'];ui_select('İlçe (mahalle filtresi)','district',$opts,$district);ui_input('Adında ara','q',is_string($_GET['q']??null)?$_GET['q']:''); ?><button class="button dark">Filtrele</button></form></section>
    <section class="panel"><div class="panel-title"><h2><?=number_format(count($rows),0,',','.')?> lokasyon bulundu</h2><span>Sayfa <?=$page?> / <?=$max?></span></div><form method="post"><?php action_fields('geo_activate'); ?><div class="table-scroll"><table><thead><tr><th><input type="checkbox" data-geo-all aria-label="Bu sayfadakileri seç"></th><th>Lokasyon / hiyerarşi</th><th>Durum</th></tr></thead><tbody><?php foreach(array_slice($rows,($page-1)*50,50) as $r): ?><tr><td><input type="checkbox" name="geo_ids[]" value="<?=e($r['uid'])?>" aria-label="<?=e($r['name'])?> seç"></td><td><strong><?=e(geo_label($r))?></strong><small><?=e($r['uid'])?></small></td><td><?php if(isset($active[$r['uid']])): ?><a href="/yonetim?tab=cities&edit=<?=(int)$active[$r['uid']]?>">Etkin · düzenle ↗</a><?php else: ?>Katalogda<?php endif; ?></td></tr><?php endforeach; ?></tbody></table></div><div class="bulk-actions"><button class="button dark">Seçilenleri etkinleştir</button><span>Üst il ve ilçe de eklenir.</span></div></form><div class="table-pagination"><?php foreach(['Önceki'=>max(1,$page-1),'Sonraki'=>min($max,$page+1)] as $label=>$num): ?><a href="/yonetim?<?=e(http_build_query(array_merge($_GET,['tab'=>'locations-import','page'=>$num])))?>"><?=$label?></a><?php endforeach; ?></div></section>
    <section class="panel"><h2>Filtrenin tamamı için toplu işlem</h2><p class="help">Yukarıdaki <?=number_format(count($rows),0,',','.')?> lokasyona uygulanır. Her istekte en fazla 40 kombinasyon işlenir. Sekmeyi açık tutun; durdurulan işlemde tamamlanan kayıtlar korunur. Yeniden başlatırsanız mevcut sayfalar atlanır. Üretilen sayfalar taslaktır.</p><form method="post" data-geo-batch><?php action_fields('geo_batch',['level'=>$level,'province'=>$province,'district'=>$district,'q'=>is_string($_GET['q']??null)?$_GET['q']:'']);ui_select('İşlem','mode',['activate'=>'Yalnızca lokasyonları etkinleştir','generate'=>'Hizmetlerle eşleştir ve PSEO taslakları üret']); ?><div class="geo-services"><?php foreach(records('services') as $svc): ?><label class="check"><input type="checkbox" name="services[]" value="<?=$svc['id']?>"><span><?=e($svc['name'])?></span></label><?php endforeach; ?></div><button class="button dark">Toplu işlemi başlat</button><button type="button" class="button" data-geo-stop disabled>Sonraki partide durdur</button><progress aria-label="Toplu işlem ilerlemesi" data-geo-progress max="100" value="0"></progress><p role="status" data-geo-result>İşlem başlamadı.</p></form></section><?php
}
