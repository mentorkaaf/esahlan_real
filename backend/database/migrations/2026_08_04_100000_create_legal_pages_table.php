<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('legal_pages', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('title_en');
            $table->string('title_so');
            $table->longText('content_en');
            $table->longText('content_so');
            $table->boolean('is_published')->default(true);
            $table->timestamps();
        });

        // Seed default pages
        $now = now();

        DB::table('legal_pages')->insert([
            [
                'slug'       => 'privacy-policy',
                'title_en'   => 'Privacy Policy',
                'title_so'   => 'Siyaasadda Sirta',
                'content_en' => self::privacyEn(),
                'content_so' => self::privacySo(),
                'is_published' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'slug'       => 'terms',
                'title_en'   => 'Terms of Service',
                'title_so'   => 'Shuruudaha Adeegga',
                'content_en' => self::termsEn(),
                'content_so' => self::termsSo(),
                'is_published' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'slug'       => 'about',
                'title_en'   => 'About eSahlan',
                'title_so'   => 'Ku saabsan eSahlan',
                'content_en' => self::aboutEn(),
                'content_so' => self::aboutSo(),
                'is_published' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('legal_pages');
    }

    private static function privacyEn(): string
    {
        return '<h2>Privacy Policy</h2>
<p><strong>Last updated: August 2026</strong></p>
<p>eSahlan ("we", "our", or "us") is committed to protecting your privacy. This Privacy Policy explains how we collect, use, disclose, and safeguard your information when you use our mobile application and website.</p>

<h3>1. Information We Collect</h3>
<p>We collect the following types of information:</p>
<ul>
<li><strong>Personal Information:</strong> Name, phone number, email address, and profile photo when you register.</li>
<li><strong>Location Data:</strong> Your real-time location to enable delivery services and show nearby vendors. We only collect location when you grant permission.</li>
<li><strong>Payment Information:</strong> We process payments through WaafiPay. We do not store your full payment credentials — only transaction references.</li>
<li><strong>Usage Data:</strong> App interactions, pages viewed, features used, and device information (model, OS version, app version).</li>
<li><strong>Communications:</strong> Messages and posts you create within the eSahlan Community.</li>
</ul>

<h3>2. How We Use Your Information</h3>
<ul>
<li>To process and fulfill your orders (food, groceries, parcels, etc.)</li>
<li>To match you with available delivery drivers</li>
<li>To send order status updates and push notifications</li>
<li>To personalize your feed and recommendations</li>
<li>To improve our services through analytics</li>
<li>To prevent fraud and ensure account security</li>
<li>To respond to customer support inquiries</li>
</ul>

<h3>3. Information Sharing</h3>
<p>We share your information only in the following circumstances:</p>
<ul>
<li><strong>Delivery Partners:</strong> Drivers receive your name, phone number, and delivery address to complete your order.</li>
<li><strong>Vendors:</strong> Restaurants and shops receive your order details (not payment info).</li>
<li><strong>Payment Processor:</strong> WaafiPay processes payments. Their privacy policy applies to payment transactions.</li>
<li><strong>Service Providers:</strong> Firebase (notifications & analytics), Google Maps (location services). These providers are bound by confidentiality agreements.</li>
<li><strong>Legal Requirements:</strong> We may disclose information when required by Somali law or to protect our legal rights.</li>
</ul>
<p>We do not sell your personal data to third parties.</p>

<h3>4. Data Retention</h3>
<p>We retain your personal data as long as your account is active. You may request deletion of your account and associated data by contacting us. Order history may be retained for legal and financial compliance purposes.</p>

<h3>5. Data Security</h3>
<p>We use industry-standard security measures including encryption in transit (HTTPS/TLS) and at rest. However, no method of transmission over the internet is 100% secure.</p>

<h3>6. Children\'s Privacy</h3>
<p>Our services are not directed to children under the age of 13. We do not knowingly collect personal information from children.</p>

<h3>7. Your Rights</h3>
<p>You have the right to:</p>
<ul>
<li>Access and update your personal information through your profile settings</li>
<li>Request deletion of your account</li>
<li>Opt out of marketing notifications</li>
<li>Withdraw location permission at any time through your device settings</li>
</ul>

<h3>8. Changes to This Policy</h3>
<p>We may update this Privacy Policy from time to time. We will notify you of significant changes through the app or email.</p>

<h3>9. Contact Us</h3>
<p>If you have questions about this Privacy Policy, please contact us at:<br>
<strong>Email:</strong> support@esahlan.com<br>
<strong>Address:</strong> Mogadishu, Somalia</p>';
    }

    private static function privacySo(): string
    {
        return '<h2>Siyaasadda Sirta</h2>
<p><strong>Ugu dambeyntii la cusbooneysiiyay: Ogosto 2026</strong></p>
<p>eSahlan waxay u heellan tahay ilaalinta xurmadaada. Siyaasaddan waxay sharaxaysaa sida aan u aruurino, u isticmaalno, iyo u ilaalino macluumaadkaaga markaad isticmaalayso app-keenna iyo websaydkeenna.</p>

<h3>1. Macluumaadka Aan Aruurino</h3>
<ul>
<li><strong>Macluumaadka Shakhsiga:</strong> Magaca, lambarka telefoonka, iimaylka, iyo sawirka profile marka aad diiwaangeliso.</li>
<li><strong>Goobta:</strong> Goobta dhabta ah si adeegyada gaadhsiinta loogu fududeeyo. Goobta kaliya waxaan aruurinnaa marka aad ogolaato.</li>
<li><strong>Lacag-bixinta:</strong> Lacag-bixinta waxaa maareeyaa WaafiPay. Xogta lacag-bixintaada oo buuxda ma kaydinno — reference kaliya.</li>
<li><strong>Isticmaalka App-ka:</strong> Sida aad app-ka u isticmaalayso, aaladda (nooca, nidaamka), iyo version-ka app-ka.</li>
</ul>

<h3>2. Sida Aan Macluumaadka U Isticmaalno</h3>
<ul>
<li>Si amarka loo fuliyo (cunto, badeecad, baakad, iwm.)</li>
<li>Si driver-ka kula xidiidhsiino</li>
<li>Ogeysiisyada push notifications</li>
<li>Personalization iyo taageero macaamiil</li>
<li>Xaaladda amniga akownka</li>
</ul>

<h3>3. La Wadaajinta Macluumaadka</h3>
<ul>
<li><strong>Driver-yada:</strong> Magacaaga, telefoonka, iyo cinwaanka gaadhsiinta.</li>
<li><strong>Dukaamaha:</strong> Faahfaahinta amarka (lacag ma jirto).</li>
<li><strong>WaafiPay:</strong> Lacag-bixinta kaliya.</li>
<li><strong>Firebase & Google Maps:</strong> Adeegyada xogta.</li>
</ul>
<p>Xogta shakhsigaaga ma iibinno kooxo kale.</p>

<h3>4. Xuquuqdaada</h3>
<ul>
<li>Xogta profile-kaaga wax ka beddel</li>
<li>Akownkaaga tirtirasho</li>
<li>Ogeysiisyada kasoo bax</li>
</ul>

<h3>5. Nala Xiriir</h3>
<p><strong>Iimaylka:</strong> support@esahlan.com<br>
<strong>Cinwaanka:</strong> Muqdisho, Soomaaliya</p>';
    }

    private static function termsEn(): string
    {
        return '<h2>Terms of Service</h2>
<p><strong>Last updated: August 2026</strong></p>
<p>Welcome to eSahlan. By using our app or website, you agree to these Terms of Service. Please read them carefully.</p>

<h3>1. About eSahlan</h3>
<p>eSahlan is a multi-service platform operating in Somalia that connects customers with vendors, restaurants, service providers, and delivery drivers through a single application. Our services include food delivery (eFood), grocery delivery (eGrocery), online shopping (eShop), parcel delivery (eParcel), moving services (eMoving), learning (eLearning), currency exchange (eExchange), property rental (eRent), and a social community.</p>

<h3>2. Eligibility</h3>
<ul>
<li>You must be at least 18 years old to create an account and use eSahlan services.</li>
<li>You must provide accurate, complete, and current registration information.</li>
<li>You are responsible for maintaining the security of your account credentials.</li>
</ul>

<h3>3. Account Responsibilities</h3>
<ul>
<li>You are responsible for all activity that occurs under your account.</li>
<li>You must notify us immediately of any unauthorized use of your account.</li>
<li>One person may only maintain one account. Multiple accounts for the same person are not permitted.</li>
</ul>

<h3>4. Placing Orders</h3>
<ul>
<li>Orders placed through eSahlan are binding once confirmed.</li>
<li>Prices displayed include all applicable fees unless stated otherwise.</li>
<li>Delivery times are estimates and may vary due to traffic, weather, or vendor preparation time.</li>
<li>eSahlan acts as a marketplace connecting you with vendors — we are not responsible for the quality of third-party products.</li>
</ul>

<h3>5. Payments</h3>
<ul>
<li>Payments are processed through WaafiPay, Somalia\'s leading mobile payment provider.</li>
<li>All prices are in US Dollars (USD) unless otherwise specified.</li>
<li>You authorize us to charge the payment method on file for any orders you place.</li>
<li>Refunds are processed according to our Refund Policy on a case-by-case basis.</li>
</ul>

<h3>6. Cancellations and Refunds</h3>
<ul>
<li>You may cancel an order before the vendor accepts it.</li>
<li>Once a vendor starts preparing your order, cancellation may not be possible.</li>
<li>Refund requests for issues with your order must be submitted within 24 hours through the app.</li>
<li>Refunds are credited back to your WaafiPay account or eSahlan wallet within 3–5 business days.</li>
</ul>

<h3>7. Community Guidelines</h3>
<p>When using the eSahlan Community (social feed, posts, stories, and messages), you agree to:</p>
<ul>
<li>Not post content that is offensive, illegal, hateful, or violates the rights of others.</li>
<li>Not impersonate other people or organizations.</li>
<li>Not spam or send unsolicited messages.</li>
<li>Not share misinformation or false content.</li>
<li>Respect other users\' privacy.</li>
</ul>
<p>Violation of community guidelines may result in content removal or account suspension.</p>

<h3>8. Prohibited Activities</h3>
<ul>
<li>Fraud, deception, or manipulation of the platform</li>
<li>Creating fake orders or reviews</li>
<li>Attempting to hack or reverse-engineer the app</li>
<li>Using the app for any illegal activity under Somali law</li>
<li>Harassment of drivers, vendors, or other users</li>
</ul>

<h3>9. Limitation of Liability</h3>
<p>eSahlan shall not be liable for:</p>
<ul>
<li>Delays or failures in delivery caused by circumstances beyond our control</li>
<li>Loss or damage to parcels not reported within 24 hours of delivery</li>
<li>Actions of third-party vendors or delivery partners</li>
<li>Technical failures, interruptions, or errors in the app</li>
</ul>

<h3>10. Termination</h3>
<p>We reserve the right to suspend or terminate your account if you violate these Terms, engage in fraudulent activity, or for any other reason at our discretion. You may terminate your account at any time by contacting us.</p>

<h3>11. Changes to Terms</h3>
<p>We may update these Terms from time to time. Continued use of eSahlan after changes constitutes acceptance of the new Terms.</p>

<h3>12. Governing Law</h3>
<p>These Terms are governed by the laws of the Federal Republic of Somalia. Any disputes shall be resolved in the courts of Mogadishu, Somalia.</p>

<h3>13. Contact Us</h3>
<p><strong>Email:</strong> support@esahlan.com<br>
<strong>Address:</strong> Mogadishu, Somalia</p>';
    }

    private static function termsSo(): string
    {
        return '<h2>Shuruudaha Adeegga</h2>
<p><strong>Ugu dambeyntii la cusbooneysiiyay: Ogosto 2026</strong></p>
<p>Ku soo dhawow eSahlan. Markaad isticmaalayso app-keenna ama websaydkeenna, waxaad ogolaanaysaa Shuruudahan Adeegga.</p>

<h3>1. Ku saabsan eSahlan</h3>
<p>eSahlan waa platform multi-adeeg ah oo ka shaqeysa Soomaaliya. Adeegyadeena waxaa ka mid ah: gaadhsiinta cuntada (eFood), baaqliga (eGrocery), tuumaashada (eShop), boostada (eParcel), guurista (eMoving), waxbarashada (eLearning), is-beddelka lacagta (eExchange), kireysta hantida (eRent), iyo bulshada bulsheed.</p>

<h3>2. Xagga Da\'da</h3>
<ul>
<li>Waa in aad 18 jir tahay si aad akown u sameyso.</li>
<li>Waa in macluumaadka diiwaangelinta saxsan oo dhab ah bixisaa.</li>
<li>Adigu mas\'uul baad ka tahay amniga akownkaaga.</li>
</ul>

<h3>3. Amarka</h3>
<ul>
<li>Amarrada la xaqiijiyay waa kuwo xidid leh.</li>
<li>Waqtiga gaadhsiinta waa qiyaas — waxa kala duwanaanshaha keena: gaadiidka, cimilada, iyo u diyaar garoobaynta dukaamaha.</li>
</ul>

<h3>4. Lacag-bixinta</h3>
<ul>
<li>Lacag-bixinta waxaa maareeyaa WaafiPay.</li>
<li>Qiimaha oo dhan USD ayuu ku jiraa haddaan lagu shegin mid kale.</li>
</ul>

<h3>5. Joojinta & Lacag-celinta</h3>
<ul>
<li>Amarkii jooji kartaa haddaan dukaamahu weli garanaynin.</li>
<li>Arrimaha amarka ku jira 24 saac gudahood baad u soo dirsanaysaa.</li>
</ul>

<h3>6. Falalka Mamnuuca ah</h3>
<ul>
<li>Khiyaano, amarrada been ah, iyo maamulka platform-ka</li>
<li>Jeexjeexa app-ka ama reverse-engineering</li>
<li>Qalad kasta oo ka hor Sharciga Soomaaliya</li>
</ul>

<h3>7. Sharciga Xukuma</h3>
<p>Shuruudahan waxaa xukuma sharciyada Jamhuuriyadda Federaalka Soomaaliya.</p>

<h3>8. Nala Xiriir</h3>
<p><strong>Iimaylka:</strong> support@esahlan.com<br>
<strong>Cinwaanka:</strong> Muqdisho, Soomaaliya</p>';
    }

    private static function aboutEn(): string
    {
        return '<h2>About eSahlan</h2>
<p>eSahlan — Everything You Need, Simplified.</p>

<h3>Who We Are</h3>
<p>eSahlan is Somalia\'s first all-in-one Super App, built to make everyday life easier for Somalis at home and in the diaspora. From a single app, you can order food, shop online, send parcels, hire movers, learn new skills, exchange currency, rent property — and stay connected with your community.</p>

<h3>Our Mission</h3>
<p>We believe every Somali deserves access to modern, reliable digital services. Our mission is to build the infrastructure that powers Somalia\'s digital economy — connecting customers, vendors, drivers, and service providers through technology that works for everyone.</p>

<h3>Our Services</h3>
<ul>
<li><strong>eFood</strong> — Order from your favorite restaurants and have food delivered to your door.</li>
<li><strong>eGrocery</strong> — Fresh groceries and household essentials delivered fast.</li>
<li><strong>eShop</strong> — Browse and buy from local and online shops.</li>
<li><strong>eParcel</strong> — Send and receive packages anywhere in the city.</li>
<li><strong>eMoving</strong> — Professional moving services for homes and offices.</li>
<li><strong>eLearning</strong> — Access courses and educational content from top instructors.</li>
<li><strong>eExchange</strong> — Fast and secure currency exchange.</li>
<li><strong>eRent</strong> — Find and rent properties, equipment, and more.</li>
<li><strong>Community</strong> — A social space to connect, share, and discover.</li>
</ul>

<h3>Payments</h3>
<p>All payments on eSahlan are powered by <strong>WaafiPay</strong>, Somalia\'s leading mobile payment network. Transactions are fast, secure, and convenient — no cash required.</p>

<h3>Our Values</h3>
<ul>
<li><strong>Reliability</strong> — We deliver what we promise, on time.</li>
<li><strong>Trust</strong> — Your data and money are safe with us.</li>
<li><strong>Community</strong> — We build for Somalis, by Somalis.</li>
<li><strong>Innovation</strong> — We use technology to solve real problems.</li>
</ul>

<h3>Contact Us</h3>
<p><strong>Email:</strong> support@esahlan.com<br>
<strong>Website:</strong> esahlan.com<br>
<strong>Location:</strong> Mogadishu, Somalia</p>
<p>Follow us on social media for updates, promotions, and community news.</p>';
    }

    private static function aboutSo(): string
    {
        return '<h2>Ku saabsan eSahlan</h2>
<p>eSahlan — Wax Kastoo Aad U Baahato, Si Fudud.</p>

<h3>Cidda Aan Nahay</h3>
<p>eSahlan waa Super App-ka ugu horeeyay ee Soomaaliya, oo loogu talagalay in nolosha maalinlaha ah ay u fududaato Soomaalidda. App-ka hal ah oo ka bixi kartaa: cunto, badeecad, boostada, guurista, waxbarashada, is-beddelka lacagta, kireysta hantida — oo aad bulshada la xidiidhsan kartid.</p>

<h3>Hadafkeenna</h3>
<p>Waxaan rumaysanahay in Soomaaliyaan kasta uu xaq u leeyahay adeegyada dijitaalka ee casriga ah. Hadafkeenna waa in aan dhisno kaabayaasha dhaqaalaha dijitaalka Soomaaliya — iyadoo macaamiisha, dukaamaha, driver-yada, iyo bixiyeyaasha adeegyada la xidiidhsiinayo tignoolajiyadda u shaqeysa dhammaan.</p>

<h3>Adeegyadeenna</h3>
<ul>
<li><strong>eFood</strong> — Amarka cuntada xaafadaada laga keeno.</li>
<li><strong>eGrocery</strong> — Baaqliga iyo alaabta guriga degdeg laguugu keeno.</li>
<li><strong>eShop</strong> — Ka gado dukaamaha maxalliga ah iyo online-ka.</li>
<li><strong>eParcel</strong> — Dir oo hel baakadaha magaalada meel walba.</li>
<li><strong>eMoving</strong> — Adeegyada guurista oo xirfadlayaasha leh.</li>
<li><strong>eLearning</strong> — Koorsooyin iyo waxbarasho tayo leh.</li>
<li><strong>eExchange</strong> — Is-beddelka lacagta oo degdeg ah oo ammaan ah.</li>
<li><strong>eRent</strong> — Kireeyso guryaha, qalamiyada, iwm.</li>
<li><strong>Bulshada</strong> — Goob bulsheed oo aad ku xidiidhsan kartid oo wadaajin kartid.</li>
</ul>

<h3>Lacag-bixinta</h3>
<p>Dhammaan lacag-bixinaha eSahlan waxaa awood siinaya <strong>WaafiPay</strong>, shabakadda lacag-bixinta mobile-ka ee Soomaaliya ee ugu horeeysa.</p>

<h3>Nala Xiriir</h3>
<p><strong>Iimaylka:</strong> support@esahlan.com<br>
<strong>Websaydka:</strong> esahlan.com<br>
<strong>Goobta:</strong> Muqdisho, Soomaaliya</p>';
    }
};
