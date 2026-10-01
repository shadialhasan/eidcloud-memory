[🇸🇦 العربية](README.ar.md) | [🇬🇧 English](README.md)

# eidcloud-memory | طبقة الذاكرة الهرمية الذكية لعملاء الذكاء الاصطناعي

[![الإصدار](https://img.shields.io/badge/version-v1.0.0-blue.svg)](https://github.com/shadialhasan/eidcloud-memory/releases)
[![بيئة التشغيل](https://img.shields.io/badge/php-%3E%3D8.2-8892BF.svg)](https://www.php.net)
[![الترخيص: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](LICENSE)
[![فتح في كولاب](https://colab.research.google.com/assets/colab-badge.svg)](https://colab.research.google.com/github/shadialhasan/eidcloud-memory/blob/main/notebooks/quickstart.ipynb)
[![المنظومة](https://img.shields.io/badge/EIDCloud-Ecosystem-green.svg)](https://github.com/shadialhasan/eidcloud-cli)

محرك ذاكرة متعدد المستويات (ذاكرة قصيرة، ذاكرة عاملة، واسترجاع دلالي طويل الأمد) مع تقييم أهمية الذكريات وتصفية التفاصيل غير المهمة.


> **☁️ إشعار المنظومة:** هذه الأداة هي جزء مستقل من منظومة **[EIDCloud](https://github.com/shadialhasan/eidcloud-cli)**.  
> يمكنك استخدامها بمفردها كأداة متخصصة، أو إدارتها مع كافة أدوات المنظومة الـ 49 عبر الماستر CLI المركزي: `eidcloud`.


---

## 📑 التصنيف والقطاع
**الذكاء الاصطناعي والعملاء المستقلين (AI & Agents)**

---

## ✨ المميزات الرئيسية

- **ذاكرة ثلاثية المستويات: الذاكرة اللحظية، الذاكرة العاملة، والأرشيف الدلالي**
- **نظام تقييم الأهمية (Importance Scoring) لنسيان التفاصيل التافهة والاحتفاظ بالحقائق**
- **تخزين خفيف على SQLite مع سرعة استرجاع مذهلة**
- **توليد ملخصات تلقائية لسياق الجلسات الطويلة**

---

## 🚀 التثبيت والتشغيل السريع

### 1. الاستخدام المستقل (Standalone)
يمكنك استنساخ وتشغيل هذه الأداة بشكل منفصل تماماً:

```bash
git clone https://github.com/shadialhasan/eidcloud-memory.git
cd eidcloud-memory
php tests/run_tests.php
```

### 2. التثبيت كجزء من الماستر CLI الموحد
إذا كان لديك أداة `eidcloud-cli` مثبتة، يمكنك تسجيل هذه الأداة أو تثبيتها بأمر واحد:

```bash
eidcloud plugin add https://github.com/shadialhasan/eidcloud-memory.git
```

---

## 💻 أمثلة الاستخدام عبر سطر الأوامر

```bash
# تشغيل الأداة مباشرة
php bin/eidcloud-memory store "المستخدم يفضل الردود باللغة العربية دائماً" --importance=9
php bin/eidcloud-memory recall "تفضيلات المستخدم"
```

```bash
# عرض المساعدة والدليل الشامل
php bin/eidcloud-memory --help
```

---

## 🧪 الاختبارات الآلية

تم تجهيز هذا المستودع بمجموعة اختبارات ذاتية متكاملة تعمل بدون أي مكتبات خارجية:

```bash
php tests/run_tests.php
```

---

## 🌐 تجربة فورية عبر المتصفح (Google Colab)

يمكنك تجربة الأداة مباشرة على سحابة Google بدون الحاجة لتثبيت أي متطلبات محلياً:  
[![Open In Colab](https://colab.research.google.com/assets/colab-badge.svg)](https://colab.research.google.com/github/shadialhasan/eidcloud-memory/blob/main/notebooks/quickstart.ipynb)

---

## 👤 المؤلف والمشرف

**م. محمد شادي الحسن**  
- **الدور:** المدير التقني التنفيذي ومهندس الحلول المؤسسية  
- **البريد الإلكتروني:** [mhd.shadi.alhasan@gmail.com](mailto:mhd.shadi.alhasan@gmail.com)  
- **الهاتف / واتساب:** [+963934005922](tel:+963934005922)  
- **الموقع:** دمشق، سوريا  
- **GitHub:** [shadialhasan](https://github.com/shadialhasan)  

---

## 📄 الرخصة

هذا المشروع مرخص بموجب رخصة MIT - انظر ملف [LICENSE](LICENSE) للتفاصيل.  
حقوق النشر (c) 2026 **م. محمد شادي الحسن**. جميع الحقوق محفوظة.
