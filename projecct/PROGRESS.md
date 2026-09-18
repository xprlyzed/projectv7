# İyileştirme İlerleme Takibi (v3 — Yeni Repo + Özellikler + Yayın Öncesi Analiz)

> Faz 1/Faz 2 (önceki repoda tamamlanmıştı) referans alınarak bu proje yeni
> bir GitHub reposuna taşınıyor. Bu dosya, "Yeni Repo Kurulumu + Özellik/
> Cilalama Talimatı" belgesindeki görevleri takip eder. Bu belgede HER
> görünür/yapısal değişiklik önce plana dökülüp onay bekliyor.
> Her madde: [ ] yapılmadı · [~] devam ediyor · [x] tamam · [B] bloke

## Bölüm A — Yeni Repo Kurulumu
- [x] Mevcut durum analiz edildi (A.1)
- [x] Kurulum planı sunuldu, onaylandı (A.2)
- [x] Repo kuruldu: sarmalayıcı klasör yok, Laravel kökü = repo kökü (A.3)
- [ ] Push için onay alındı, push yapıldı (kullanıcı "Save to Github" ile yapacak)
- [x] Son doğrulama temiz (submodule/nested yok) (A.4)

## Bölüm B — Dosya/Klasör Yapısı Denetimi
- [x] CSS-in-Vue-klasörü + diğer düzensizlikler denetlendi (B.1)
- [x] CSS konumu kararı (public/assets vs resources/css/pages) soruldu, netleşti (B.2 → Seçenek 2)
- [x] Diğer bulgular için onay alındı (B.3 → 1,2,3,4 onaylandı)
- [x] Onaylananlar uygulandı (B.4): 40 CSS resources/css/{pages,components,layouts}'a taşındı; Error→Errors/Error; LiveKit*→LiveKit/, Seo→Front/; SweetAlert inline style→sınıf. build+35 test yeşil.

## Bölüm C — Login/Register Sağ Panel Yenileme
- [ ] Mevcut durum incelendi (C.1)
- [ ] 2-3 tasarım yönü önerildi, biri onaylandı (C.3)
- [ ] Uygulandı, doğrulandı (C.4)

## Bölüm D — Mesajlar: Görüldü Bilgisi
- [ ] Mevcut yapı incelendi (D.1)
- [ ] Plan onaylandı (D.2)
- [ ] Uygulandı, test eklendi (D.3)

## Bölüm E — Admin: EFT/Havale Yönetim Sayfaları
- [ ] Mevcut akış incelendi (E.1)
- [ ] Şema+route+sayfa planı onaylandı (E.2)
- [ ] Uygulandı, test eklendi, doğrulandı (E.3)

## Bölüm F — Yayına Alma Öncesi Analiz
- [ ] Production readiness denetimi yapıldı, rapor sunuldu

## Bölüm G — KURULUM.md Düzeltmesi
- [ ] KURULUM.md gerçek kurulumla karşılaştırıldı, farklar raporlandı
- [ ] Onaylanan düzeltmeler uygulandı

## Kapsam Dışı / Ertelenen
(Bloke olan veya kullanıcı kararı gereken maddeler buraya yazılacak)
