<?php
declare(strict_types=1);

function responsive_media_generate(int $id): int {
    $media=cms_record('media',$id);if(!$media||!function_exists('imagewebp'))return 0;
    $path=bundled_url($media['filename']);if(!$path||!preg_match('/\.(webp|png|jpg)$/D',$path))return 0;
    $source=PUBLIC_ROOT.$path;if(!is_file($source))return 0;
    $size=@getimagesize($source);if(!$size||$size[0]*$size[1]>25000000)return 0;
    $extra=meta('media',$id);$signature=hash_file('sha256',$source);
    if(($extra['source_hash']??'')===$signature&&isset($extra['variants'])&&array_reduce($extra['variants'],fn($ok,$v)=>$ok&&is_file(PUBLIC_ROOT.$v['url']),true))return 0;
    $image=@imagecreatefromstring((string)file_get_contents($source));if(!$image)return 0;
    $variants=[];$count=0;$folder=str_starts_with($path,'/assets/')?'/assets/':'/uploads/';
    foreach([320,640,960,1280,1600] as $width){
        if($width>=$size[0])continue;
        $height=max(1,(int)round($size[1]*$width/$size[0]));
        $name=hash('sha256',$signature.'|'.$width.($folder==='/uploads/'?'|'.$media['filename']:'')).'.webp';$url=$folder.$name;
        if(is_file(PUBLIC_ROOT.$url)){$variants[]=['url'=>$url,'width'=>$width];continue;}
        $canvas=imagecreatetruecolor($width,$height);imagealphablending($canvas,false);imagesavealpha($canvas,true);
        imagecopyresampled($canvas,$image,0,0,0,0,$width,$height,$size[0],$size[1]);
        if(imagewebp($canvas,PUBLIC_ROOT.$url,82)){$variants[]=['url'=>$url,'width'=>$width];$count++;}imagedestroy($canvas);
    }
    imagedestroy($image);$extra['variants']=$variants;$extra['source_hash']=$signature;meta_save('media',$id,$extra);return $count;
}

function responsive_media_attributes(array $media,string $sizes='(max-width: 760px) 100vw, 70vw'): string {
    $variants=meta('media',(int)$media['id'])['variants']??[];
    // Bundled derivatives are also available after a fresh production install.
    if(!$variants&&str_starts_with($media['filename'],'asset:')){
        $source=PUBLIC_ROOT.bundled_url($media['filename']);
        if(is_file($source)&&preg_match('/\.(webp|png|jpg)$/D',$source)){
            $hash=hash_file('sha256',$source);
            foreach([320,640,960,1280,1600] as $width){$url='/assets/'.hash('sha256',$hash.'|'.$width).'.webp';if(is_file(PUBLIC_ROOT.$url))$variants[]=['url'=>$url,'width'=>$width];}
        }
    }
    if(!$variants)return '';
    $set=[];foreach($variants as $v)if(preg_match('~^/(assets|uploads)/[a-f0-9]+\.webp$~D',$v['url'])&&is_file(PUBLIC_ROOT.$v['url']))$set[]=$v['url'].' '.(int)$v['width'].'w';
    if(!$set)return '';$set[]=bundled_url($media['filename']).' '.(int)$media['width'].'w';
    return ' srcset="'.e(implode(', ',$set)).'" sizes="'.e($sizes).'"';
}

function responsive_media_delete(int $id): void {
    // Bundled derivatives belong to the release; only delete user-owned files here.
    foreach(meta('media',$id)['variants']??[] as $v)if(preg_match('~^/uploads/[a-f0-9]+\.webp$~D',$v['url'])&&is_file(PUBLIC_ROOT.$v['url']))unlink(PUBLIC_ROOT.$v['url']);
    query("DELETE FROM content_meta WHERE entity='media' AND record_id=?",[$id]);
}

function growth_media_referenced(int $id): bool {
    if(growth_ready()&&query('SELECT id FROM authors WHERE image_id=? LIMIT 1',[$id])->fetchColumn())return true;
    foreach(query('SELECT payload FROM content_meta')->fetchAll() as $row){$data=json_decode($row['payload'],true)?:[];if(in_array($id,array_map('intval',$data['gallery_ids']??[]),true))return true;}
    foreach(growth_seo_catalog() as $key=>$unused)if((int)(growth_page_seo($key,settings())['image_id']??0)===$id)return true;
    return false;
}
