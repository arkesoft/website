<?php
declare(strict_types=1);
function page_design_catalog(): array {
 return [
 'services'=>['label'=>'Hizmetler','url'=>'/hizmetler','layouts'=>['index'=>'Editoryal hizmet dizini','cards'=>'Görselli uzmanlık kartları'],'title'=>'Birlikte neler yapabiliriz?','intro'=>'Bir web sitesi, bir marka, işinizi kolaylaştıran bir yazılım. Başlangıç noktamız sizin ihtiyacınız.','cover'=>'flow'],
 'projects'=>['label'=>'Projeler','url'=>'/projeler','layouts'=>['gallery'=>'Asimetrik portföy','grid'=>'Dengeli proje ızgarası'],'title'=>'Fikirden görünen işe.','intro'=>'Marka, arayüz ve yazılım üzerine konsept seçkimiz. Her çalışmanın kendi ihtiyacı, kendi hikâyesi var.','cover'=>'coffee'],
 'about'=>['label'=>'Hakkımızda','url'=>'/hakkimizda','layouts'=>['manifesto'=>'Stüdyo manifestosu','panorama'=>'Panoramik görsel anlatımı'],'title'=>'Aynı masada, iyi fikirler.','intro'=>'Tasarımcı gibi merak ediyor, geliştirici gibi düşünüyoruz. İyi bir işin önce iyi bir konuşmayla başladığına inanıyoruz.','cover'=>'studio'],
 'contact'=>['label'=>'İletişim','url'=>'/iletisim','layouts'=>['split-form'=>'Yan yana iletişim ve form','form-first'=>'Formu öne çıkaran düzen'],'title'=>'Anlatın. Birlikte başlayalım.','intro'=>'Bir fikriniz, bir sorunuz ya da çözmek istediğiniz bir iş olabilir. İlk adımı buradan atalım.','cover'=>'studio'],
 'blog'=>['label'=>'Blog / İçgörüler','url'=>'/blog','layouts'=>['magazine'=>'Öne çıkan yazılı dergi düzeni','journal'=>'Sade editoryal liste'],'title'=>'Masamızdan notlar.','intro'=>'Projenin başında, ortasında ve sonrasında işinize yarayacak düşünceler.','cover'=>'architecture'],
 'solutions'=>['label'=>'Yerel çözümler','url'=>'/cozumler','layouts'=>['directory'=>'Arama odaklı lokasyon dizini','compact'=>'Kompakt hizmet rehberi'],'title'=>'İhtiyacınıza yakın çözümler.','intro'=>'Şehrinizi ve hizmeti seçin, çalışma kapsamını inceleyin. Proje görüşmelerini çevrim içi planlayabiliriz.','cover'=>'flow'],
 'privacy'=>['label'=>'Gizlilik','url'=>'/gizlilik','layouts'=>['document'=>'Okuma odaklı belge'],'title'=>'Gizlilik ve aydınlatma.','intro'=>'İletişim formu ve paylaştığınız bilgiler hakkında.','cover'=>''],
 '404'=>['label'=>'Bulunamayan sayfa','url'=>'/ornek-bulunamayan-sayfa','layouts'=>['wayfinding'=>'Yönlendirme ekranı'],'title'=>'Burada bir sayfa yok.','intro'=>'Bağlantı değişmiş veya içerik kaldırılmış olabilir. Birlikte doğru yere dönelim.','cover'=>'']
 ];
}
function page_design(string $key): array {
 $defaults=page_design_catalog()[$key]??[];if(!$defaults)return [];
 $saved=json_decode(settings()['page_design_'.$key]??'{}',true);if(!is_array($saved))$saved=[];
 $result=array_merge(['layout'=>array_key_first($defaults['layouts']),'image_id'=>0],$defaults,$saved);
 if(!isset($defaults['layouts'][$result['layout']]))$result['layout']=array_key_first($defaults['layouts']);
 return $result;
}
function service_layouts(): array {return ['auto'=>'Hizmete uygun otomatik seçim','web'=>'Web · tarayıcı sahnesi','software'=>'Yazılım · ürün ve akış','mobile'=>'Mobil · uygulama vitrini','brand'=>'Marka · editoryal kompozisyon','commerce'=>'E-ticaret · ürün odağı','growth'=>'Büyüme · strateji ve içerik'];}
function service_layout(array $service,array $extra): string {
 $chosen=$extra['page_layout']??'auto';if($chosen!=='auto'&&isset(service_layouts()[$chosen]))return $chosen;
 return match($service['slug']){'web-tasarim','kurumsal-web-sitesi'=>'web','mobil-uygulama'=>'mobile','marka-tasarim'=>'brand','e-ticaret'=>'commerce','seo','sosyal-medya-yonetimi','dijital-strateji'=>'growth',default=>'software'};
}
function design_action(): bool {
 if(post('action')!=='page_design_save')return false;
 check_csrf();authorize_area('designs');$key=post('page_key');$catalog=page_design_catalog();
 if(!isset($catalog[$key]))throw new InvalidArgumentException('Geçerli bir sayfa seçin.');
 $layout=post('layout');$image=(int)post('image_id');$title=post('title',190);$intro=post('intro',1500);
 if(!isset($catalog[$key]['layouts'][$layout]))throw new InvalidArgumentException('Bu sayfaya uygun bir şablon seçin.');
 if(!$title||mb_strlen($title)<3)throw new InvalidArgumentException('En az 3 karakterlik başlık yazın.');
 if($image&&!cms_record('media',$image))throw new InvalidArgumentException('Görsel bulunamadı.');
 setting_write('page_design_'.$key,json_encode(['layout'=>$layout,'title'=>$title,'intro'=>$intro,'image_id'=>$image],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));
 audit('Sayfa tasarımı güncellendi',$key.' / '.$layout);flash('Sayfanın tasarımı ve açılış içeriği kaydedildi.');redirect('/yonetim?tab=designs&page_key='.$key);
}
function design_admin(): void {
 $catalog=page_design_catalog();$key=is_string($_GET['page_key']??null)?$_GET['page_key']:'services';if(!isset($catalog[$key]))$key='services';$design=page_design($key); ?>
 <nav class="design-page-tabs" aria-label="Tasarımını düzenleyeceğiniz sayfa"><?php foreach($catalog as $id=>$page): ?><a href="/yonetim?tab=designs&page_key=<?=$id?>" <?=$key===(string)$id?'aria-current="page"':''?>><?=e($page['label'])?></a><?php endforeach; ?></nav>
 <form method="post" class="panel admin-form design-editor" data-content-editor><?php action_fields('page_design_save',['page_key'=>$key]); ?><div class="panel-title"><h2><?=e($design['label'])?> sayfası</h2><a href="<?=e($design['url'])?>" target="_blank" rel="noopener">Sayfayı görüntüle ↗ <span class="sr-only">(yeni sekme)</span></a></div><p class="help">Bu seçim yalnızca bu sayfayı değiştirir. Hizmetlerin kendi şablonlarını Hizmetler → Düzenle ekranından seçebilirsiniz. Blog yazıları okuma düzenini, PSEO sayfaları yerel kapsam düzenini kullanır.</p><fieldset class="layout-options"><legend>Sayfa yerleşimi</legend><?php foreach($design['layouts'] as $layout=>$label): ?><label class="layout-choice"><input type="radio" name="layout" value="<?=e($layout)?>" <?=$design['layout']===$layout?'checked':''?>><span class="layout-thumb thumb-<?=e($layout)?>" aria-hidden="true"><i></i><i></i><i></i></span><strong><?=e($label)?></strong></label><?php endforeach; ?></fieldset><?php ui_input('Sayfa açılış başlığı','title',$design['title'],'text',true);ui_input('Kısa giriş','intro',$design['intro'],'textarea');if(!in_array($key,['privacy','404','solutions'],true)):media_select('image_id',(int)$design['image_id']);else: ?><input type="hidden" name="image_id" value="0"><?php endif; ?><p class="help">Görsel seçmezseniz sayfaya uygun başlangıç görseli kullanılır. Hizmet, proje ve yazı görselleri kendi içerik ekranlarından yönetilir.</p><div class="editor-savebar"><p data-save-status role="status" aria-live="polite">Değişikliklerinizi kaydedin ve sayfayı kontrol edin.</p><button class="button dark">Sayfa tasarımını kaydet</button></div></form>
<?php }
