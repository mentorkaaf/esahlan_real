<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;

class GeoController extends Controller
{
    public function countries()
    {
        return response()->json(['status' => 'success', 'data' => self::$countries]);
    }

    public function cities(string $countryCode)
    {
        $cities = self::$cities[$countryCode] ?? [];
        return response()->json(['status' => 'success', 'data' => $cities]);
    }

    private static array $countries = [
        ['code' => 'SO', 'name' => 'Somalia', 'flag' => '🇸🇴'],
        ['code' => 'KE', 'name' => 'Kenya', 'flag' => '🇰🇪'],
        ['code' => 'ET', 'name' => 'Ethiopia', 'flag' => '🇪🇹'],
        ['code' => 'DJ', 'name' => 'Djibouti', 'flag' => '🇩🇯'],
        ['code' => 'UG', 'name' => 'Uganda', 'flag' => '🇺🇬'],
        ['code' => 'TZ', 'name' => 'Tanzania', 'flag' => '🇹🇿'],
        ['code' => 'SA', 'name' => 'Saudi Arabia', 'flag' => '🇸🇦'],
        ['code' => 'AE', 'name' => 'UAE', 'flag' => '🇦🇪'],
        ['code' => 'QA', 'name' => 'Qatar', 'flag' => '🇶🇦'],
        ['code' => 'KW', 'name' => 'Kuwait', 'flag' => '🇰🇼'],
        ['code' => 'BH', 'name' => 'Bahrain', 'flag' => '🇧🇭'],
        ['code' => 'OM', 'name' => 'Oman', 'flag' => '🇴🇲'],
        ['code' => 'TR', 'name' => 'Turkey', 'flag' => '🇹🇷'],
        ['code' => 'EG', 'name' => 'Egypt', 'flag' => '🇪🇬'],
        ['code' => 'SD', 'name' => 'Sudan', 'flag' => '🇸🇩'],
        ['code' => 'YE', 'name' => 'Yemen', 'flag' => '🇾🇪'],
        ['code' => 'MY', 'name' => 'Malaysia', 'flag' => '🇲🇾'],
        ['code' => 'GB', 'name' => 'United Kingdom', 'flag' => '🇬🇧'],
        ['code' => 'US', 'name' => 'United States', 'flag' => '🇺🇸'],
        ['code' => 'CA', 'name' => 'Canada', 'flag' => '🇨🇦'],
        ['code' => 'SE', 'name' => 'Sweden', 'flag' => '🇸🇪'],
        ['code' => 'NO', 'name' => 'Norway', 'flag' => '🇳🇴'],
        ['code' => 'FI', 'name' => 'Finland', 'flag' => '🇫🇮'],
        ['code' => 'DK', 'name' => 'Denmark', 'flag' => '🇩🇰'],
        ['code' => 'NL', 'name' => 'Netherlands', 'flag' => '🇳🇱'],
        ['code' => 'DE', 'name' => 'Germany', 'flag' => '🇩🇪'],
        ['code' => 'FR', 'name' => 'France', 'flag' => '🇫🇷'],
        ['code' => 'IT', 'name' => 'Italy', 'flag' => '🇮🇹'],
        ['code' => 'ES', 'name' => 'Spain', 'flag' => '🇪🇸'],
        ['code' => 'AU', 'name' => 'Australia', 'flag' => '🇦🇺'],
        ['code' => 'NZ', 'name' => 'New Zealand', 'flag' => '🇳🇿'],
        ['code' => 'IN', 'name' => 'India', 'flag' => '🇮🇳'],
        ['code' => 'PK', 'name' => 'Pakistan', 'flag' => '🇵🇰'],
        ['code' => 'BD', 'name' => 'Bangladesh', 'flag' => '🇧🇩'],
        ['code' => 'CN', 'name' => 'China', 'flag' => '🇨🇳'],
        ['code' => 'JP', 'name' => 'Japan', 'flag' => '🇯🇵'],
        ['code' => 'KR', 'name' => 'South Korea', 'flag' => '🇰🇷'],
        ['code' => 'ZA', 'name' => 'South Africa', 'flag' => '🇿🇦'],
        ['code' => 'NG', 'name' => 'Nigeria', 'flag' => '🇳🇬'],
        ['code' => 'GH', 'name' => 'Ghana', 'flag' => '🇬🇭'],
        ['code' => 'MA', 'name' => 'Morocco', 'flag' => '🇲🇦'],
        ['code' => 'TN', 'name' => 'Tunisia', 'flag' => '🇹🇳'],
        ['code' => 'LY', 'name' => 'Libya', 'flag' => '🇱🇾'],
        ['code' => 'IQ', 'name' => 'Iraq', 'flag' => '🇮🇶'],
        ['code' => 'JO', 'name' => 'Jordan', 'flag' => '🇯🇴'],
        ['code' => 'LB', 'name' => 'Lebanon', 'flag' => '🇱🇧'],
        ['code' => 'SY', 'name' => 'Syria', 'flag' => '🇸🇾'],
        ['code' => 'PS', 'name' => 'Palestine', 'flag' => '🇵🇸'],
        ['code' => 'BR', 'name' => 'Brazil', 'flag' => '🇧🇷'],
        ['code' => 'MX', 'name' => 'Mexico', 'flag' => '🇲🇽'],
        ['code' => 'RU', 'name' => 'Russia', 'flag' => '🇷🇺'],
        ['code' => 'ID', 'name' => 'Indonesia', 'flag' => '🇮🇩'],
        ['code' => 'PH', 'name' => 'Philippines', 'flag' => '🇵🇭'],
    ];

    private static array $cities = [
        'SO' => ['Mogadishu','Hargeisa','Kismayo','Marka','Baidoa','Beledweyne','Bosaso','Galkayo','Garowe','Burao','Berbera','Jowhar','Afgooye','Wanlaweyn','Belet Hawa','Dhusamareb','Erigavo','Laascaanood','Qardho','Ceerigaabo','Hobyo','Adado','Barawe','Buurhakaba','Dinsor','Hudur','Luuq','Doolow','Garbahaarey','Jilib','Jamaame','Bu\'aale','Sablale','Wajid','Tayeeglow','Cabudwaaq','Goldogob','Jariban','Bandiiradley','Xudun','Taleex','Ceel Afweyn','Zeylac','Lughaye','Baki','Borama','Gebiley','Wajaale','Tog Wajaale','Oodweyne'],
        'KE' => ['Nairobi','Mombasa','Kisumu','Nakuru','Eldoret','Thika','Malindi','Kitale','Garissa','Nyeri','Machakos','Meru','Lamu','Nanyuki','Naivasha','Eastleigh','Wajir','Mandera','Isiolo','Marsabit'],
        'ET' => ['Addis Ababa','Dire Dawa','Mekelle','Gondar','Hawassa','Bahir Dar','Adama','Jimma','Jijiga','Harar','Dessie','Debre Berhan','Arba Minch','Sodo','Nekemte'],
        'DJ' => ['Djibouti City','Ali Sabieh','Tadjoura','Obock','Dikhil','Arta'],
        'UG' => ['Kampala','Gulu','Lira','Mbarara','Jinja','Mbale','Mukono','Entebbe','Masaka','Fort Portal'],
        'TZ' => ['Dar es Salaam','Dodoma','Mwanza','Arusha','Mbeya','Morogoro','Tanga','Zanzibar City','Kigoma','Tabora'],
        'SA' => ['Riyadh','Jeddah','Mecca','Medina','Dammam','Khobar','Tabuk','Abha','Taif','Buraidah','Hail','Najran','Jazan','Al Ahsa','Yanbu','Dhahran','Jubail','Khamis Mushait'],
        'AE' => ['Dubai','Abu Dhabi','Sharjah','Ajman','Ras Al Khaimah','Fujairah','Al Ain','Umm Al Quwain'],
        'QA' => ['Doha','Al Wakrah','Al Khor','Dukhan','Mesaieed','Al Rayyan'],
        'KW' => ['Kuwait City','Hawalli','Salmiya','Jahra','Farwaniya','Ahmadi'],
        'TR' => ['Istanbul','Ankara','Izmir','Bursa','Antalya','Adana','Konya','Gaziantep','Mersin','Kayseri','Trabzon','Samsun','Diyarbakir','Eskisehir','Malatya'],
        'EG' => ['Cairo','Alexandria','Giza','Shubra El Kheima','Port Said','Suez','Luxor','Aswan','Mansoura','Tanta','Ismailia','Hurghada','Sharm El Sheikh'],
        'GB' => ['London','Birmingham','Manchester','Leeds','Glasgow','Liverpool','Edinburgh','Bristol','Sheffield','Leicester','Cardiff','Belfast','Nottingham','Newcastle','Southampton','Oxford','Cambridge'],
        'US' => ['New York','Los Angeles','Chicago','Houston','Phoenix','Philadelphia','San Antonio','San Diego','Dallas','San Jose','Austin','Jacksonville','San Francisco','Columbus','Indianapolis','Seattle','Denver','Washington DC','Nashville','Minneapolis'],
        'CA' => ['Toronto','Montreal','Vancouver','Calgary','Edmonton','Ottawa','Winnipeg','Quebec City','Hamilton','Kitchener'],
        'SE' => ['Stockholm','Gothenburg','Malmo','Uppsala','Vasteras','Orebro','Linkoping','Helsingborg','Jonkoping','Norrkoping'],
        'NO' => ['Oslo','Bergen','Trondheim','Stavanger','Drammen','Fredrikstad','Kristiansand','Sandnes','Tromso','Sarpsborg'],
        'FI' => ['Helsinki','Espoo','Tampere','Vantaa','Oulu','Turku','Jyvaskyla','Lahti','Kuopio','Pori'],
        'DE' => ['Berlin','Hamburg','Munich','Cologne','Frankfurt','Stuttgart','Dusseldorf','Leipzig','Dortmund','Essen','Bremen','Dresden','Hanover','Nuremberg'],
        'FR' => ['Paris','Marseille','Lyon','Toulouse','Nice','Nantes','Strasbourg','Montpellier','Bordeaux','Lille','Rennes'],
        'NL' => ['Amsterdam','Rotterdam','The Hague','Utrecht','Eindhoven','Tilburg','Groningen','Almere','Breda'],
        'IN' => ['Mumbai','Delhi','Bangalore','Hyderabad','Ahmedabad','Chennai','Kolkata','Pune','Jaipur','Lucknow','Surat','Kanpur'],
        'PK' => ['Karachi','Lahore','Islamabad','Rawalpindi','Faisalabad','Multan','Peshawar','Quetta','Sialkot','Gujranwala'],
        'SD' => ['Khartoum','Omdurman','Port Sudan','Kassala','El Obeid','Wad Madani','Nyala','El Fasher','Gedaref','Atbara'],
        'YE' => ['Sanaa','Aden','Taiz','Al Hudaydah','Ibb','Dhamar','Mukalla','Sayyan','Zabid'],
        'AU' => ['Sydney','Melbourne','Brisbane','Perth','Adelaide','Gold Coast','Canberra','Newcastle','Hobart','Darwin'],
        'ZA' => ['Johannesburg','Cape Town','Durban','Pretoria','Port Elizabeth','Bloemfontein','East London','Polokwane'],
        'NG' => ['Lagos','Abuja','Kano','Ibadan','Port Harcourt','Benin City','Kaduna','Maiduguri','Enugu','Calabar'],
        'BR' => ['Sao Paulo','Rio de Janeiro','Brasilia','Salvador','Fortaleza','Belo Horizonte','Manaus','Curitiba','Recife'],
        'RU' => ['Moscow','Saint Petersburg','Novosibirsk','Yekaterinburg','Kazan','Nizhny Novgorod','Chelyabinsk','Samara','Omsk','Rostov'],
        'JP' => ['Tokyo','Yokohama','Osaka','Nagoya','Sapporo','Fukuoka','Kobe','Kyoto','Kawasaki','Saitama'],
        'CN' => ['Shanghai','Beijing','Guangzhou','Shenzhen','Chengdu','Hangzhou','Wuhan','Xian','Nanjing','Chongqing'],
        'ID' => ['Jakarta','Surabaya','Bandung','Medan','Semarang','Makassar','Palembang','Tangerang','Depok','Yogyakarta'],
    ];
}
