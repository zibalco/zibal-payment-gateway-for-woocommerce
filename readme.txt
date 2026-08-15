=== Gateway Zibal for WooCommerce ===
Contributors: mzamanzadah
Tags: woocommerce, zibal, gateway, payment, زیبال
Requires at least: 4.7
Requires PHP: 5.6
Tested up to: 6.9
Stable tag: 2.1.0
Donate link: https://zibal.ir
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.en.html

درگاه پرداخت زیبال برای ووکامرس با پشتیبانی از پرداخت کلاسیک، Checkout Blocks و HPOS.

== Description ==

این افزونه درگاه پرداخت زیبال را به ووکامرس اضافه می‌کند. درخواست و تأیید پرداخت در سمت سرور انجام می‌شود و مبلغ، شناسه تراکنش و کلید سفارش در callback کنترل می‌شوند.

سازگاری PHP 5.6 مربوط به نسخه‌های قدیمی و سازگار WordPress/WooCommerce است. نسخه‌های جدید WooCommerce حداقل PHP بالاتری نیاز دارند.

== Installation ==

1. فایل ZIP افزونه را از بخش افزونه‌ها بارگذاری کنید.
2. افزونه را فعال کنید.
3. از مسیر ووکامرس ← تنظیمات ← پرداخت‌ها، درگاه زیبال را تنظیم کنید.

== Frequently Asked Questions ==

= آیا افزونه روی PHP 5.6 کار می‌کند؟ =

کد افزونه با PHP 5.6 سازگار نگه داشته شده است، اما باید نسخه WordPress و WooCommerce نصب‌شده نیز از PHP 5.6 پشتیبانی کنند.

= آیا Checkout Blocks پشتیبانی می‌شود؟ =

بله، در نسخه‌های WooCommerce که زیرساخت Blocks را ارائه می‌کنند.

== Changelog ==

= 2.1.0 =
* سخت‌سازی callback و verify پرداخت.
* نگهداری چند trackId برای هر سفارش.
* اصلاح محاسبه مبلغ، ارز و شماره موبایل.
* افزودن سازگاری نحوی PHP 5.6 تا PHP 8.5.
* اصلاح نوتیس‌های مدیریت، کش فایل Blocks و اطلاعات افزونه.

= 2.0 =
* تغییر کلاس در پیشخوان ووکامرس.
* سوییچ بین سرورهای زیبال.

= 1.9 =
* رفع مشکل نمایش شماره سفارش در پنل زیبال.
