# 🛢️ Project Oil

![GitHub repo size](https://img.shields.io/github/repo-size/Awosk/project-oil?style=for-the-badge)
![GitHub stars](https://img.shields.io/github/stars/Awosk/project-oil?style=for-the-badge)
![PHP Version](https://img.shields.io/badge/PHP-%E2%89%A5%208.0-777bb4?style=for-the-badge&logo=php)
![License](https://img.shields.io/github/license/Awosk/project-oil?color=blue&style=for-the-badge)

---

## 📌 Proje Hakkında

`Project Oil`, endüstriyel tesislerde veya araç filolarında yağ tüketimini izlemek için geliştirilmiş hafif (lightweight) ve güvenli bir web uygulamasıdır. Karmaşık stok giriş-çıkış süreçleriyle uğraşmak yerine, sisteme tanımlı her araç/tesis birer **görsel kart** olarak listelenir. Personeller bu kartlar üzerinden saniyeler içinde yağ çıkışı işleyebilir, yöneticiler ise geçmiş tüm çıkış kayıtlarını, sistem loglarını ve veritabanı yedeklerini denetleyebilir.

---

## ✨ Temel Özellikler

### 🪪 Görsel Kart Arayüzü & Takip
- **Yağ Çıkış Kaydı:** Araç ve tesisler ekran üzerinde görsel kartlar halinde listelenir. Kartlara tıklanarak hızlıca yağ çıkış kaydı eklenebilir, düzenlenebilir veya silinebilir.
- **Kart İçi Geçmiş:** Hangi araca/tesise, ne zaman, hangi ürünün, ne kadar ve kim tarafından verildiği doğrudan ilgili kartın içinden görüntülenebilir.

### 📋 Operasyon ve Yönetim
- **Ürün Yönetimi:** Sistemde kullanılan endüstriyel yağların (ürünlerin) dinamik olarak eklenmesi, düzenlenmesi ve silinmesi.
- **Araç & Tesis Yönetimi:** Takipten sorumlu olunan araç ve tesislerin yönetimi.
- **Araç Türleri Kategorizasyonu:** Araçların türlerine göre gruplandırılmasını ve daha düzenli listelenmesini sağlar.

### 🔍 İşlemler ve Depo Entegrasyonu
- **İşlemler Sayfası:** Yapılan tüm işlemleri varsayılan olarak listeler. Gelişmiş filtreleme yeteneğine sahiptir.
- **İşlendi / İşlenmedi Durumu:** Depo personelinin takibini kolaylaştırmak için tasarlanmıştır. Çıkışı yapılan yağ ana depoya/stok sistemine işlendiğinde, personel tarafından **"İşlendi"** olarak işaretlenir.

### 📊 Raporlama ve Çıktı Alma
- **Gelişmiş Filtreleme:** Günlük, haftalık, aylık veya özel tarih aralıklarına göre dinamik raporlar.
- **PDF Çıktısı:** Hazırlanan raporlar tek tıkla PDF formatında dışa aktarılabilir.

### 🔐 Güvenlik ve Profil
- Kullanıcılar kendi şifrelerini profil ayarlarından güncelleyebilirler.
- Sistemdeki hiçbir silme işlemi veriyi fiziksel olarak yok etmez (**Soft Delete / Yazılımsal Silme** kullanılır). Bu sayede veri kayıplarının önüne geçilir ve geçmişe dönük denetim sağlanır.

---

## 🛠️ Yönetim Paneli (Sadece Yönetici / Admin)

Admin rolündeki kullanıcılar sistemin kalbi olan yönetim araçlarına tam erişim sağlarlar:

- **Kullanıcı Yönetimi:** Kullanıcı ekleme, düzenleme, silme ve yetki seviyelerini (Kullanıcı / Admin) belirleme.
- **Sistem Kayıtları (Loglar):** Sistemde gerçekleştirilen tüm operasyonların şeffaf bir şekilde loglanması ve izlenmesi.
- **Seçmeli Veritabanı Yedekleme:** İster tabloları tek tek seçerek, ister tüm veritabanını tek tıkla yedekleme ve alınan yedeği anında geri yükleme (Restore).
- **Güvenli Sistem Güncelleme:** GitHub üzerinden yayınlanan son kararlı sürümü (Release) otomatik çeker. Güncelleme esnasında `.env` gibi özel yapılandırma dosyaları korunur, veritabanı şeması güncellenir ve **veri kaybı yaşanmaz**.

---

## ⚙️ Sistem Gereksinimleri

Uygulamanın çalışabilmesi için sunucunuzda aşağıdaki yapılandırmaların bulunması gerekir:

### 🖥️ Sunucu Yazılımları
- **Apache Web Server**
- **MySQL** veya **MariaDB** veritabanı sunucusu

### 🔧 PHP Yapılandırması (PHP ≥ 8.0)
- `PDO` & `PDO MySQL` (Veritabanı bağlantısı için)
- `ZipArchive` (Yedekleme ve güncelleme işlemleri için)
- `file_get_contents` fonksiyonu aktif olmalı
- `allow_url_fopen` ayarı açık olmalı (GitHub güncellemeleri için)
- Proje dosyalarının bulunduğu klasörlerde **Dizin okuma/yazma izinleri** verilmiş olmalı

---

## 🚀 Kurulum Adımları

⚠️ **Önemli:** Projenin kararlı çalışabilmesi için doğrudan klonlanarak (Git clone) kurulması önerilmez. Lütfen yayınlanmış resmi paketleri kullanın.

1. GitHub üzerindeki **[Latest Releases](https://github.com/Awosk/project-oil/releases)** sekmesine gidin.
2. En son kararlı sürümün `.zip` veya `.tar.gz` arşivini indirin.
3. İndirdiğiniz dosyayı Apache web sunucunuzun kök dizinine çıkartın.
4. Tarayıcınızdan web sitenizi açarak `/install` dizinine gidin (Örn: `http://localhost/install`).
5. Web tabanlı kurulum sihirbazındaki adımları (Veritabanı bilgileri vb.) takip ederek kurulumu tamamlayın.

---

## 🤝 Katkıda Bulunma (Contributing)

Geliştirmeleri ve geri bildirimleri her zaman memnuniyetle karşılıyoruz! Katkı sağlamak isterseniz:

1. Bu depoyu çatallayın (Fork).
2. Yeni bir özellik dalı oluşturun: `git checkout -b ozellik/yeni-tasarim`
3. Değişikliklerinizi kaydedin: `git commit -m 'Eklendi: Log filtreleme'`
4. Dalınızı sunucuya gönderin: `git push origin ozellik/yeni-tasarim`
5. Bir **Pull Request (PR)** açın.

---

## 📄 Lisans

Bu proje **GNU GPL v3** Lisansı ile lisanslanmıştır. Kodun kopyalanması, değiştirilmesi veya dağıtılması durumunda türetilen projenin de açık kaynak kodlu ve GPL v3 lisanslı olması zorunludur. Daha fazla bilgi için projedeki `LICENSE` dosyasına göz atabilirsiniz.

---

## ✉️ İletişim

Geliştirici: **Awosk** - **GitHub:** [@Awosk](https://github.com/Awosk)  

---
<p align="center">Geliştirmeyi desteklemek için projeye bir ⭐ bırakmayı unutmayın!</p>
