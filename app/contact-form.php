<?php
declare(strict_types=1);
function contact_form(): void {
 $context=growth_context_service();$old=$_SESSION['contact_old']??[];$errors=$_SESSION['contact_errors']??[];unset($_SESSION['contact_old'],$_SESSION['contact_errors']);
 $attributes=fn($name)=>' id="contact-'.e($name).'"'.(isset($errors[$name])?' aria-invalid="true" aria-describedby="error-'.e($name).'"':'');
 $error=fn($name)=>isset($errors[$name])?'<small class="field-error" id="error-'.e($name).'">'.e($errors[$name]).'</small>':''; ?>
 <form action="/iletisim" method="post" class="contact-form"><?=csrf_field()?><?=flash_html()?><?php growth_contact_fields(); ?>
 <?php if($errors): ?><div class="form-error-summary" tabindex="-1" data-error-summary role="alert"><p>Göndermeden önce şu alanları kontrol edin:</p><ul><?php foreach($errors as $name=>$message): ?><li><a href="#contact-<?=e($name)?>"><?=e($message)?></a></li><?php endforeach; ?></ul></div><?php endif; ?>
 <p class="form-required">* işaretli alanlar gereklidir. Telefon veya e-posta bilgilerinden birini bırakmanız yeterli.</p><div class="form-grid">
 <label>Adınız / Şirketiniz *<input name="name"<?=$attributes('name')?> autocomplete="name" minlength="2" maxlength="120" required placeholder="Sizi nasıl tanıyalım?" value="<?=e($old['name']??'')?>"><?=$error('name')?></label>
 <label>E-posta adresiniz<input name="email"<?=$attributes('email')?> type="email" autocomplete="email" maxlength="190" placeholder="merhaba@sirketiniz.com" value="<?=e($old['email']??'')?>"><?=$error('email')?></label>
 <label>Telefon<input<?=$attributes('phone')?> name="phone" autocomplete="tel" type="tel" maxlength="40" placeholder="+90" value="<?=e($old['phone']??'')?>"><?=$error('phone')?></label>
 <label>Neye ihtiyacınız var?<select name="service"><option value="Birlikte belirleyelim">Birlikte belirleyelim</option><?php foreach(public_services() as $service): ?><option <?=($old['service']??($context['name']??''))===$service['name']?'selected':''?>><?=e($service['name'])?></option><?php endforeach; ?></select></label></div>
 <label>Biraz projenizden bahsedin (isteğe bağlı)<textarea name="message"<?=$attributes('message')?> rows="5" minlength="20" maxlength="5000" placeholder="Fikriniz, hedefleriniz, aklınızdaki sorular…"><?=e($old['message']??'')?></textarea><?=$error('message')?></label>
 <label class="honeypot" aria-hidden="true">Website<input name="website" tabindex="-1" autocomplete="off"></label>
 <label class="check"><input type="checkbox" name="consent"<?=$attributes('consent')?> required <?=!empty($old['consent'])?'checked':''?>><span><a href="/gizlilik" target="_blank" rel="noopener">Aydınlatma metnini <span class="sr-only">(yeni sekme)</span></a> okudum. Bilgilerimin talebime yanıt verilmesi amacıyla işlenmesini anladım. *</span></label><?=$error('consent')?>
 <button class="button dark" type="submit">Projemi konuşalım <?=arrow()?></button><p class="form-note"><?=e(settings()['contact_note']?:'Bilgilerinizi yalnızca talebiniz hakkında sizinle iletişime geçmek için kullanırız.')?></p></form>
<?php }
