<?php
/*
 * Project Oil - Vehicle and Facility Industrial Oil Tracking System
 * Copyright (C) 2026 Awosk
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/log.php';
require_once __DIR__ . '/../../classes/Kullanici.php';
adminKontrol();

$sayfa_basligi = 'Kullanıcı Yönetimi';
$ku = mevcutKullanici();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ekle'])) {
    csrfDogrula();
    $ad   = trim($_POST['ad_soyad']);
    $kadi = trim($_POST['kullanici_adi']);
    $sifre= $_POST['sifre'];
    $rol  = $_POST['rol'] === 'admin' ? 'admin' : 'kullanici';
    if ($ad && $kadi && strlen($sifre) >= 6) {
        $mevcut = Kullanici::bulKullaniciAdi($pdo, $kadi);

        if ($mevcut && $mevcut['aktif'] == 0) {
            Kullanici::reaktifEt($pdo, $mevcut['id'], $ad, password_hash($sifre, PASSWORD_DEFAULT), $rol);
            logYaz($pdo,'ekle','kullanici','Silinen kullanıcı reaktif edildi: '.$kadi.' ('.$rol.')', $mevcut['id'], null, ['ad_soyad'=>$ad,'kullanici_adi'=>$kadi,'rol'=>$rol], 'lite');
            flash('Daha önce silinmiş kullanıcı tekrar aktif edildi.');
        } elseif ($mevcut && $mevcut['aktif'] == 1) {
            flash('Bu kullanıcı adı zaten kullanımda.', 'danger');
        } else {
            try {
                $email_val = trim($_POST['email'] ?? '') ?: null;
                $yeni_id = Kullanici::ekle($pdo, $ad, $kadi, password_hash($sifre, PASSWORD_DEFAULT), $rol, $email_val);
                logYaz($pdo,'ekle','kullanici','Yeni kullanıcı eklendi: '.$kadi.' ('.$rol.')', $yeni_id, null, ['ad_soyad'=>$ad,'kullanici_adi'=>$kadi,'rol'=>$rol], 'lite');
                flash('Kullanıcı eklendi.');
            } catch (PDOException $e) { flash('Bir hata oluştu.', 'danger'); }
        }
    } else { flash('Tüm alanlar zorunludur, şifre min. 6 karakter.', 'danger'); }
    header('Location: users.php'); exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['rol_degistir'])) {
    csrfDogrula();
    $rol_id  = (int)$_POST['rol_id'];
    $yeni_rol = $_POST['yeni_rol'] === 'admin' ? 'admin' : 'kullanici';
    if ($rol_id) {
        $sr = Kullanici::bulAktif($pdo, $rol_id);
        if ($sr) {
            $eski_rol = $sr['rol'];
            Kullanici::rolGuncelle($pdo, $rol_id, $yeni_rol);
            logYaz($pdo,'guncelle','kullanici','Kullanıcı rolü değiştirildi: '.$sr['kullanici_adi'].' ('.$eski_rol.' → '.$yeni_rol.')', $rol_id, ['rol'=>$eski_rol], ['rol'=>$yeni_rol], 'lite');
            flash($sr['ad_soyad'] . ' kullanıcısının rolü ' . $yeni_rol . ' olarak güncellendi.');
        } else { flash('Kullanıcı bulunamadı.', 'danger'); }
    }
    header('Location: users.php'); exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sifre_reset'])) {
    csrfDogrula();
    $reset_id  = (int)$_POST['reset_id'];
    $yeni_sifre = $_POST['yeni_sifre'] ?? '';
    if ($reset_id && strlen($yeni_sifre) >= 6) {
        $sr = Kullanici::bulAktif($pdo, $reset_id);
        if ($sr) {
            Kullanici::sifreGuncelle($pdo, $reset_id, password_hash($yeni_sifre, PASSWORD_DEFAULT));
            logYaz($pdo,'guncelle','kullanici','Admin tarafından şifre sıfırlandı: '.$sr['kullanici_adi'], $reset_id, null, null, 'lite');
            flash($sr['ad_soyad'] . ' kullanıcısının şifresi güncellendi.');
        } else { flash('Kullanıcı bulunamadı.', 'danger'); }
    } else { flash('Şifre en az 6 karakter olmalıdır.', 'danger'); }
    header('Location: users.php'); exit;
}

if (isset($_GET['sil'])) {
    $sil_id = (int)$_GET['sil'];
    if ($sil_id === $ku['id']) { flash('Kendi hesabınızı silemezsiniz.', 'danger'); }
    else {
        $sr = Kullanici::bul($pdo, $sil_id);
        Kullanici::sil($pdo, $sil_id);
        if ($sr) logYaz($pdo,'sil','kullanici','Kullanıcı silindi: '.$sr['kullanici_adi'].' ('.$sr['rol'].')', $sil_id, ['kullanici_adi'=>$sr['kullanici_adi'],'ad_soyad'=>$sr['ad_soyad'],'rol'=>$sr['rol']], null, 'lite');
        flash('Kullanıcı silindi.');
    }
    header('Location: users.php'); exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['email_guncelle'])) {
    csrfDogrula();
    $eid   = (int)$_POST['email_id'];
    $email = trim($_POST['email_adres'] ?? '') ?: null;
    if ($eid) {
        if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash('Geçersiz e-posta adresi.', 'danger');
        } else {
            $sr = Kullanici::bul($pdo, $eid);
            Kullanici::emailGuncelle($pdo, $eid, $email);
            logYaz($pdo,'guncelle','kullanici','E-posta güncellendi: '.($sr['kullanici_adi']??''), $eid, ['email'=>$sr['email']], ['email'=>$email], 'lite');
            flash('E-posta güncellendi.');
        }
    }
    header('Location: users.php'); exit;
}

$users = Kullanici::listele($pdo);

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
    <h1><span>👥</span> Kullanıcı Yönetimi</h1>
</div>

<div class="card">
    <div class="card-title">➕ Yeni Kullanıcı</div>
    <form method="post">
        <?= csrfInput() ?>
        <div class="form-grid">
            <div class="form-group">
                <label>Ad Soyad *</label>
                <input type="text" name="ad_soyad" required>
            </div>
            <div class="form-group">
                <label>Kullanıcı Adı *</label>
                <input type="text" name="kullanici_adi" required>
            </div>
            <div class="form-group">
                <label>Şifre * (min. 6 karakter)</label>
                <input type="password" name="sifre" required minlength="6">
            </div>
            <div class="form-group">
                <label>E-posta <span style="font-weight:400;color:var(--muted);font-size:11px;">(opsiyonel, şifre sıfırlama için)</span></label>
                <input type="email" name="email" placeholder="ornek@eposta.com">
            </div>
            <div class="form-group">
                <label>Rol</label>
                <select name="rol">
                    <option value="kullanici">Kullanıcı</option>
                    <option value="admin">Admin</option>
                </select>
            </div>
        </div>
        <div style="margin-top:14px;">
            <button type="submit" name="ekle" class="btn btn-primary">💾 Ekle</button>
        </div>
    </form>
</div>

<div class="card">
    <div class="card-title">📋 Kullanıcılar (<?= count($users) ?>)</div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Ad Soyad</th><th>Kullanıcı Adı</th><th>E-posta</th><th>Rol</th><th>İşlem</th></tr></thead>
            <tbody>
            <?php foreach ($users as $u): ?>
            <tr>
                <td><?= htmlspecialchars($u['ad_soyad']) ?></td>
                <td><code><?= htmlspecialchars($u['kullanici_adi']) ?></code></td>
                <td style="font-size:12px;"><?= $u['email'] ? htmlspecialchars($u['email']) : '<span style="color:var(--muted)">—</span>' ?></td>
                <td><?= $u['rol']==='admin' ? '<span class="badge badge-warning">👑 Admin</span>' : '<span class="badge badge-info">👤 Kullanıcı</span>' ?></td>
                <td style="display:flex;gap:6px;flex-wrap:wrap;">
                    <button class="btn btn-sm btn-secondary" onclick="sifreModal(<?= $u['id'] ?>, '<?= htmlspecialchars($u['ad_soyad'], ENT_QUOTES) ?>')">🔑 Şifre</button>
                    <button class="btn btn-sm btn-secondary" onclick="rolModal(<?= $u['id'] ?>, '<?= htmlspecialchars($u['ad_soyad'], ENT_QUOTES) ?>', '<?= $u['rol'] ?>')">👤 Rol</button>
                    <button class="btn btn-sm btn-secondary" onclick="emailModal(<?= $u['id'] ?>, '<?= htmlspecialchars($u['email'] ?? '', ENT_QUOTES) ?>')">📧 E-posta</button>
                    <?php if ($u['id'] !== $ku['id']): ?>
                    <a href="?sil=<?= $u['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Silmek istediğinize emin misiniz?')">🗑️ Sil</a>
                    <?php else: ?>
                    <span class="badge badge-success">← Siz</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Şifre Reset Modal -->
<div id="sifreModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:1000;align-items:center;justify-content:center;">
    <div class="modal-box" style="max-width:360px;">
        <div style="font-weight:700;font-size:16px;margin-bottom:16px;">🔑 Şifre Sıfırla — <span id="modal_ad"></span></div>
        <form method="post">
            <?= csrfInput() ?>
            <input type="hidden" name="reset_id" id="modal_reset_id">
            <div class="form-group">
                <label>Yeni Şifre * <span style="font-weight:400;color:var(--muted);font-size:12px;">(min. 6 karakter)</span></label>
                <input type="password" name="yeni_sifre" id="modal_sifre" required minlength="6" placeholder="••••••••">
            </div>
            <div style="display:flex;gap:8px;margin-top:16px;">
                <button type="submit" name="sifre_reset" class="btn btn-primary" style="flex:1;">💾 Kaydet</button>
                <button type="button" class="btn btn-secondary" onclick="sifreModalKapat()">İptal</button>
            </div>
        </form>
    </div>
</div>

<!-- Rol Değiştir Modal -->
<div id="rolModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:1000;align-items:center;justify-content:center;">
    <div class="modal-box" style="max-width:360px;">
        <div style="font-weight:700;font-size:16px;margin-bottom:16px;">👤 Rol Değiştir — <span id="rol_modal_ad"></span></div>
        <form method="post">
            <?= csrfInput() ?>
            <input type="hidden" name="rol_id" id="rol_modal_id">
            <div class="form-group">
                <label>Yeni Rol</label>
                <select name="yeni_rol" id="rol_modal_select">
                    <option value="kullanici">👤 Kullanıcı</option>
                    <option value="admin">👑 Admin</option>
                </select>
            </div>
            <div style="display:flex;gap:8px;margin-top:16px;">
                <button type="submit" name="rol_degistir" class="btn btn-primary" style="flex:1;">💾 Kaydet</button>
                <button type="button" class="btn btn-secondary" onclick="rolModalKapat()">İptal</button>
            </div>
        </form>
    </div>
</div>

<!-- E-posta Düzenleme Modal -->
<div id="emailModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:1000;align-items:center;justify-content:center;">
    <div class="modal-box" style="max-width:360px;">
        <div style="font-weight:700;font-size:16px;margin-bottom:16px;">📧 E-posta Düzenle — <span id="email_modal_ad"></span></div>
        <form method="post">
            <?= csrfInput() ?>
            <input type="hidden" name="email_id" id="email_modal_id">
            <div class="form-group">
                <label>E-posta Adresi <span style="font-weight:400;color:var(--muted);font-size:11px;">(boş bırakılırsa silinir)</span></label>
                <input type="email" name="email_adres" id="email_modal_input" placeholder="ornek@eposta.com">
            </div>
            <div style="display:flex;gap:8px;margin-top:16px;">
                <button type="submit" name="email_guncelle" class="btn btn-primary" style="flex:1;">💾 Kaydet</button>
                <button type="button" class="btn btn-secondary" onclick="emailModalKapat()">İptal</button>
            </div>
        </form>
    </div>
</div>

<script>
function sifreModal(id, ad) {
    document.getElementById('modal_reset_id').value = id;
    document.getElementById('modal_ad').textContent = ad;
    document.getElementById('modal_sifre').value = '';
    var m = document.getElementById('sifreModal');
    m.style.display = 'flex';
    setTimeout(() => document.getElementById('modal_sifre').focus(), 50);
}
function sifreModalKapat() { document.getElementById('sifreModal').style.display = 'none'; }
document.getElementById('sifreModal').addEventListener('click', function(e) {
    if (e.target === this) sifreModalKapat();
});

function rolModal(id, ad, mevcutRol) {
    document.getElementById('rol_modal_id').value = id;
    document.getElementById('rol_modal_ad').textContent = ad;
    document.getElementById('rol_modal_select').value = mevcutRol;
    var m = document.getElementById('rolModal');
    m.style.display = 'flex';
}
function rolModalKapat() { document.getElementById('rolModal').style.display = 'none'; }
document.getElementById('rolModal').addEventListener('click', function(e) {
    if (e.target === this) rolModalKapat();
});
function emailModal(id, email) {
    document.getElementById('email_modal_id').value = id;
    document.getElementById('email_modal_input').value = email;
    document.getElementById('email_modal_ad').textContent = '';
    var m = document.getElementById('emailModal');
    m.style.display = 'flex';
    setTimeout(() => document.getElementById('email_modal_input').focus(), 50);
}
function emailModalKapat() { document.getElementById('emailModal').style.display = 'none'; }
document.getElementById('emailModal').addEventListener('click', function(e) {
    if (e.target === this) emailModalKapat();
});
</script>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
