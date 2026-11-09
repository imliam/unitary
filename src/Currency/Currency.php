<?php

declare(strict_types=1);

namespace Unitary\Currency;

use Unitary\Dimension;
use Unitary\Unit;

/**
 * ISO 4217 currencies. Converting between them requires an exchange rate
 * provider; see Unitary::useExchangeRates().
 *
 * Where a symbol is shared ("$", "¥", "kr") it resolves to the most widely
 * used currency (USD, JPY, SEK); use the ISO code or a qualified symbol
 * ("C$", "CN¥", "Nkr") for the others.
 */
enum Currency: string implements Unit
{
    case AED = 'AED';
    case AFN = 'AFN';
    case ALL = 'ALL';
    case AMD = 'AMD';
    case ANG = 'ANG';
    case AOA = 'AOA';
    case ARS = 'ARS';
    case AUD = 'AUD';
    case AWG = 'AWG';
    case AZN = 'AZN';
    case BAM = 'BAM';
    case BBD = 'BBD';
    case BDT = 'BDT';
    case BGN = 'BGN';
    case BHD = 'BHD';
    case BIF = 'BIF';
    case BMD = 'BMD';
    case BND = 'BND';
    case BOB = 'BOB';
    case BRL = 'BRL';
    case BSD = 'BSD';
    case BTN = 'BTN';
    case BWP = 'BWP';
    case BYN = 'BYN';
    case BZD = 'BZD';
    case CAD = 'CAD';
    case CDF = 'CDF';
    case CHF = 'CHF';
    case CLP = 'CLP';
    case CNY = 'CNY';
    case COP = 'COP';
    case CRC = 'CRC';
    case CUP = 'CUP';
    case CVE = 'CVE';
    case CZK = 'CZK';
    case DJF = 'DJF';
    case DKK = 'DKK';
    case DOP = 'DOP';
    case DZD = 'DZD';
    case EGP = 'EGP';
    case ERN = 'ERN';
    case ETB = 'ETB';
    case EUR = 'EUR';
    case FJD = 'FJD';
    case FKP = 'FKP';
    case GBP = 'GBP';
    case GEL = 'GEL';
    case GHS = 'GHS';
    case GIP = 'GIP';
    case GMD = 'GMD';
    case GNF = 'GNF';
    case GTQ = 'GTQ';
    case GYD = 'GYD';
    case HKD = 'HKD';
    case HNL = 'HNL';
    case HTG = 'HTG';
    case HUF = 'HUF';
    case IDR = 'IDR';
    case ILS = 'ILS';
    case INR = 'INR';
    case IQD = 'IQD';
    case IRR = 'IRR';
    case ISK = 'ISK';
    case JMD = 'JMD';
    case JOD = 'JOD';
    case JPY = 'JPY';
    case KES = 'KES';
    case KGS = 'KGS';
    case KHR = 'KHR';
    case KMF = 'KMF';
    case KPW = 'KPW';
    case KRW = 'KRW';
    case KWD = 'KWD';
    case KYD = 'KYD';
    case KZT = 'KZT';
    case LAK = 'LAK';
    case LBP = 'LBP';
    case LKR = 'LKR';
    case LRD = 'LRD';
    case LSL = 'LSL';
    case LYD = 'LYD';
    case MAD = 'MAD';
    case MDL = 'MDL';
    case MGA = 'MGA';
    case MKD = 'MKD';
    case MMK = 'MMK';
    case MNT = 'MNT';
    case MOP = 'MOP';
    case MRU = 'MRU';
    case MUR = 'MUR';
    case MVR = 'MVR';
    case MWK = 'MWK';
    case MXN = 'MXN';
    case MYR = 'MYR';
    case MZN = 'MZN';
    case NAD = 'NAD';
    case NGN = 'NGN';
    case NIO = 'NIO';
    case NOK = 'NOK';
    case NPR = 'NPR';
    case NZD = 'NZD';
    case OMR = 'OMR';
    case PAB = 'PAB';
    case PEN = 'PEN';
    case PGK = 'PGK';
    case PHP = 'PHP';
    case PKR = 'PKR';
    case PLN = 'PLN';
    case PYG = 'PYG';
    case QAR = 'QAR';
    case RON = 'RON';
    case RSD = 'RSD';
    case RUB = 'RUB';
    case RWF = 'RWF';
    case SAR = 'SAR';
    case SBD = 'SBD';
    case SCR = 'SCR';
    case SDG = 'SDG';
    case SEK = 'SEK';
    case SGD = 'SGD';
    case SHP = 'SHP';
    case SLE = 'SLE';
    case SOS = 'SOS';
    case SRD = 'SRD';
    case SSP = 'SSP';
    case STN = 'STN';
    case SVC = 'SVC';
    case SYP = 'SYP';
    case SZL = 'SZL';
    case THB = 'THB';
    case TJS = 'TJS';
    case TMT = 'TMT';
    case TND = 'TND';
    case TOP = 'TOP';
    case TRY = 'TRY';
    case TTD = 'TTD';
    case TWD = 'TWD';
    case TZS = 'TZS';
    case UAH = 'UAH';
    case UGX = 'UGX';
    case USD = 'USD';
    case UYU = 'UYU';
    case UZS = 'UZS';
    case VES = 'VES';
    case VND = 'VND';
    case VUV = 'VUV';
    case WST = 'WST';
    case XAF = 'XAF';
    case XCD = 'XCD';
    case XOF = 'XOF';
    case XPF = 'XPF';
    case YER = 'YER';
    case ZAR = 'ZAR';
    case ZMW = 'ZMW';
    case ZWG = 'ZWG';
    case XAU = 'XAU';
    case XAG = 'XAG';
    case XPT = 'XPT';
    case XPD = 'XPD';

    public function dimension(): Dimension
    {
        return Dimension::Currency;
    }

    public function symbol(): string
    {
        return $this->value;
    }

    public function code(): string
    {
        return $this->value;
    }

    public function name(): string
    {
        return $this->data()[0];
    }

    /**
     * Number of digits after the decimal point in the minor unit (2 for GBP, 0 for JPY, 3 for KWD).
     *
     * @return non-negative-int
     */
    public function minorUnits(): int
    {
        return $this->data()[1];
    }

    /** The preferred display symbol, falling back to the ISO code. */
    public function displaySymbol(): string
    {
        return $this->data()[2][0] ?? $this->value;
    }

    /**
     * Symbols that may be written directly before or after an amount, e.g. "£", "US$".
     *
     * @return list<string>
     */
    public function symbols(): array
    {
        return $this->data()[2];
    }

    public function aliases(): array
    {
        [$name, , $symbols, $names] = $this->data();

        return [...$symbols, strtolower($name), $name.'s', ...$names];
    }

    /**
     * @return array{string, non-negative-int, list<string>, list<string>}
     */
    private function data(): array
    {
        return match ($this) {
            self::AED => ['UAE dirham', 2, ['د.إ', 'Dh', 'Dhs'], ['dirham', 'dirhams']],
            self::AFN => ['Afghan afghani', 2, ['؋', 'Af'], ['afghani']],
            self::ALL => ['Albanian lek', 2, [], ['lek']],
            self::AMD => ['Armenian dram', 2, ['֏'], []],
            self::ANG => ['Netherlands Antillean guilder', 2, ['ƒ'], []],
            self::AOA => ['Angolan kwanza', 2, ['Kz'], ['kwanza']],
            self::ARS => ['Argentine peso', 2, ['AR$'], []],
            self::AUD => ['Australian dollar', 2, ['A$', 'AU$'], ['aussie dollar']],
            self::AWG => ['Aruban florin', 2, [], []],
            self::AZN => ['Azerbaijani manat', 2, ['₼'], ['manat']],
            self::BAM => ['Bosnia and Herzegovina convertible mark', 2, ['KM'], []],
            self::BBD => ['Barbadian dollar', 2, ['Bds$'], []],
            self::BDT => ['Bangladeshi taka', 2, ['৳', 'Tk'], ['taka']],
            self::BGN => ['Bulgarian lev', 2, ['лв'], ['lev', 'leva']],
            self::BHD => ['Bahraini dinar', 3, ['BD'], []],
            self::BIF => ['Burundian franc', 0, ['FBu'], []],
            self::BMD => ['Bermudian dollar', 2, [], []],
            self::BND => ['Brunei dollar', 2, ['B$'], []],
            self::BOB => ['Bolivian boliviano', 2, ['Bs'], ['boliviano']],
            self::BRL => ['Brazilian real', 2, ['R$'], ['real', 'reais']],
            self::BSD => ['Bahamian dollar', 2, [], []],
            self::BTN => ['Bhutanese ngultrum', 2, ['Nu.'], ['ngultrum']],
            self::BWP => ['Botswana pula', 2, ['P'], ['pula']],
            self::BYN => ['Belarusian ruble', 2, ['Br'], []],
            self::BZD => ['Belize dollar', 2, ['BZ$'], []],
            self::CAD => ['Canadian dollar', 2, ['C$', 'CA$', 'Can$'], ['loonie']],
            self::CDF => ['Congolese franc', 2, ['FC'], []],
            self::CHF => ['Swiss franc', 2, ['Fr.', 'SFr', 'CHf'], ['franc', 'francs']],
            self::CLP => ['Chilean peso', 0, ['CL$'], []],
            self::CNY => ['Chinese yuan', 2, ['CN¥', '元', 'RMB'], ['yuan', 'renminbi']],
            self::COP => ['Colombian peso', 2, ['COL$'], []],
            self::CRC => ['Costa Rican colón', 2, ['₡'], ['colon', 'colón']],
            self::CUP => ['Cuban peso', 2, [], []],
            self::CVE => ['Cape Verdean escudo', 2, [], ['escudo']],
            self::CZK => ['Czech koruna', 2, ['Kč'], ['koruna', 'korun']],
            self::DJF => ['Djiboutian franc', 0, ['Fdj'], []],
            self::DKK => ['Danish krone', 2, ['Dkr', 'kr.'], []],
            self::DOP => ['Dominican peso', 2, ['RD$'], []],
            self::DZD => ['Algerian dinar', 2, ['DA'], []],
            self::EGP => ['Egyptian pound', 2, ['E£', 'LE'], []],
            self::ERN => ['Eritrean nakfa', 2, ['Nfk'], ['nakfa']],
            self::ETB => ['Ethiopian birr', 2, [], ['birr']],
            self::EUR => ['Euro', 2, ['€'], ['euro', 'euros']],
            self::FJD => ['Fijian dollar', 2, ['FJ$'], []],
            self::FKP => ['Falkland Islands pound', 2, [], []],
            self::GBP => ['Pound sterling', 2, ['£'], ['pound', 'pounds', 'sterling', 'pounds sterling', 'british pound', 'british pounds', 'quid', 'GB£']],
            self::GEL => ['Georgian lari', 2, ['₾'], ['lari']],
            self::GHS => ['Ghanaian cedi', 2, ['₵', 'GH₵'], ['cedi', 'cedis']],
            self::GIP => ['Gibraltar pound', 2, [], []],
            self::GMD => ['Gambian dalasi', 2, [], ['dalasi']],
            self::GNF => ['Guinean franc', 0, ['FG'], []],
            self::GTQ => ['Guatemalan quetzal', 2, ['Q'], ['quetzal']],
            self::GYD => ['Guyanese dollar', 2, ['GY$'], []],
            self::HKD => ['Hong Kong dollar', 2, ['HK$'], []],
            self::HNL => ['Honduran lempira', 2, [], ['lempira']],
            self::HTG => ['Haitian gourde', 2, [], ['gourde']],
            self::HUF => ['Hungarian forint', 2, ['Ft'], ['forint', 'forints']],
            self::IDR => ['Indonesian rupiah', 2, ['Rp'], ['rupiah']],
            self::ILS => ['Israeli new shekel', 2, ['₪'], ['shekel', 'shekels', 'sheqel']],
            self::INR => ['Indian rupee', 2, ['₹', 'Rs', 'Rs.'], ['rupee', 'rupees']],
            self::IQD => ['Iraqi dinar', 3, ['ع.د'], []],
            self::IRR => ['Iranian rial', 2, ['﷼'], []],
            self::ISK => ['Icelandic króna', 0, ['Íkr'], []],
            self::JMD => ['Jamaican dollar', 2, ['J$'], []],
            self::JOD => ['Jordanian dinar', 3, ['JD'], []],
            self::JPY => ['Japanese yen', 0, ['¥', '円', 'JP¥'], ['yen']],
            self::KES => ['Kenyan shilling', 2, ['KSh'], []],
            self::KGS => ['Kyrgyzstani som', 2, ['сом'], ['som']],
            self::KHR => ['Cambodian riel', 2, ['៛'], ['riel']],
            self::KMF => ['Comorian franc', 0, ['CF'], []],
            self::KPW => ['North Korean won', 2, [], []],
            self::KRW => ['South Korean won', 0, ['₩'], ['won']],
            self::KWD => ['Kuwaiti dinar', 3, ['KD'], []],
            self::KYD => ['Cayman Islands dollar', 2, ['CI$'], []],
            self::KZT => ['Kazakhstani tenge', 2, ['₸'], ['tenge']],
            self::LAK => ['Lao kip', 2, ['₭'], []],
            self::LBP => ['Lebanese pound', 2, ['LL'], []],
            self::LKR => ['Sri Lankan rupee', 2, ['SLRs'], []],
            self::LRD => ['Liberian dollar', 2, ['L$'], []],
            self::LSL => ['Lesotho loti', 2, [], ['loti', 'maloti']],
            self::LYD => ['Libyan dinar', 3, ['LD'], []],
            self::MAD => ['Moroccan dirham', 2, ['DH'], []],
            self::MDL => ['Moldovan leu', 2, [], []],
            self::MGA => ['Malagasy ariary', 2, ['Ar'], ['ariary']],
            self::MKD => ['Macedonian denar', 2, ['ден'], ['denar']],
            self::MMK => ['Myanmar kyat', 2, ['Ks'], ['kyat']],
            self::MNT => ['Mongolian tögrög', 2, ['₮'], ['tugrik', 'tögrög']],
            self::MOP => ['Macanese pataca', 2, ['MOP$'], ['pataca']],
            self::MRU => ['Mauritanian ouguiya', 2, ['UM'], ['ouguiya']],
            self::MUR => ['Mauritian rupee', 2, [], []],
            self::MVR => ['Maldivian rufiyaa', 2, ['Rf'], ['rufiyaa']],
            self::MWK => ['Malawian kwacha', 2, ['MK'], []],
            self::MXN => ['Mexican peso', 2, ['MX$', 'Mex$'], ['peso', 'pesos']],
            self::MYR => ['Malaysian ringgit', 2, ['RM'], ['ringgit']],
            self::MZN => ['Mozambican metical', 2, ['MT'], ['metical']],
            self::NAD => ['Namibian dollar', 2, ['N$'], []],
            self::NGN => ['Nigerian naira', 2, ['₦'], ['naira']],
            self::NIO => ['Nicaraguan córdoba', 2, [], ['cordoba', 'córdoba']],
            self::NOK => ['Norwegian krone', 2, ['Nkr'], []],
            self::NPR => ['Nepalese rupee', 2, ['NRs'], []],
            self::NZD => ['New Zealand dollar', 2, ['NZ$'], ['kiwi dollar']],
            self::OMR => ['Omani rial', 3, ['RO'], []],
            self::PAB => ['Panamanian balboa', 2, ['B/.'], ['balboa']],
            self::PEN => ['Peruvian sol', 2, ['S/', 'S/.'], ['sol', 'soles']],
            self::PGK => ['Papua New Guinean kina', 2, [], ['kina']],
            self::PHP => ['Philippine peso', 2, ['₱'], []],
            self::PKR => ['Pakistani rupee', 2, ['PKRs'], []],
            self::PLN => ['Polish złoty', 2, ['zł'], ['zloty', 'złoty', 'zlotys']],
            self::PYG => ['Paraguayan guaraní', 0, ['₲'], ['guarani', 'guaraní']],
            self::QAR => ['Qatari riyal', 2, ['QR'], []],
            self::RON => ['Romanian leu', 2, ['lei'], ['leu']],
            self::RSD => ['Serbian dinar', 2, ['дин'], []],
            self::RUB => ['Russian ruble', 2, ['₽'], ['ruble', 'rubles', 'rouble', 'roubles']],
            self::RWF => ['Rwandan franc', 0, ['FRw', 'RF'], []],
            self::SAR => ['Saudi riyal', 2, ['SR', 'ر.س'], ['riyal', 'riyals']],
            self::SBD => ['Solomon Islands dollar', 2, ['SI$'], []],
            self::SCR => ['Seychellois rupee', 2, ['SRe'], []],
            self::SDG => ['Sudanese pound', 2, [], []],
            self::SEK => ['Swedish krona', 2, ['kr', 'Skr'], ['krona', 'kronor']],
            self::SGD => ['Singapore dollar', 2, ['S$'], []],
            self::SHP => ['Saint Helena pound', 2, [], []],
            self::SLE => ['Sierra Leonean leone', 2, ['Le'], ['leone']],
            self::SOS => ['Somali shilling', 2, ['Sh.So.'], []],
            self::SRD => ['Surinamese dollar', 2, ['Sr$'], []],
            self::SSP => ['South Sudanese pound', 2, [], []],
            self::STN => ['São Tomé and Príncipe dobra', 2, ['Db'], ['dobra']],
            self::SVC => ['Salvadoran colón', 2, [], []],
            self::SYP => ['Syrian pound', 2, ['LS'], []],
            self::SZL => ['Swazi lilangeni', 2, ['E'], ['lilangeni', 'emalangeni']],
            self::THB => ['Thai baht', 2, ['฿'], ['baht']],
            self::TJS => ['Tajikistani somoni', 2, ['SM'], ['somoni']],
            self::TMT => ['Turkmenistan manat', 2, [], []],
            self::TND => ['Tunisian dinar', 3, ['DT'], []],
            self::TOP => ['Tongan paʻanga', 2, ['T$'], ['paanga', 'paʻanga']],
            self::TRY => ['Turkish lira', 2, ['₺', 'TL'], ['lira', 'liras']],
            self::TTD => ['Trinidad and Tobago dollar', 2, ['TT$'], []],
            self::TWD => ['New Taiwan dollar', 2, ['NT$'], []],
            self::TZS => ['Tanzanian shilling', 2, ['TSh'], []],
            self::UAH => ['Ukrainian hryvnia', 2, ['₴'], ['hryvnia', 'hryvnias']],
            self::UGX => ['Ugandan shilling', 0, ['USh'], []],
            self::USD => ['United States dollar', 2, ['$', 'US$'], ['dollar', 'dollars', 'US dollar', 'US dollars', 'buck', 'bucks']],
            self::UYU => ['Uruguayan peso', 2, ['\\$U', 'UY$'], []],
            self::UZS => ['Uzbekistani sum', 2, [], []],
            self::VES => ['Venezuelan bolívar', 2, ['Bs.S'], ['bolivar', 'bolívar']],
            self::VND => ['Vietnamese đồng', 0, ['₫'], ['dong', 'đồng']],
            self::VUV => ['Vanuatu vatu', 0, ['VT'], ['vatu']],
            self::WST => ['Samoan tālā', 2, ['WS$'], ['tala']],
            self::XAF => ['Central African CFA franc', 0, ['FCFA'], []],
            self::XCD => ['East Caribbean dollar', 2, ['EC$'], []],
            self::XOF => ['West African CFA franc', 0, ['CFA'], []],
            self::XPF => ['CFP franc', 0, ['₣'], []],
            self::YER => ['Yemeni rial', 2, [], []],
            self::ZAR => ['South African rand', 2, ['R'], ['rand']],
            self::ZMW => ['Zambian kwacha', 2, ['ZK'], []],
            self::ZWG => ['Zimbabwe gold', 2, ['ZiG'], []],
            self::XAU => ['Gold (troy ounce)', 0, [], ['gold']],
            self::XAG => ['Silver (troy ounce)', 0, [], ['silver']],
            self::XPT => ['Platinum (troy ounce)', 0, [], ['platinum']],
            self::XPD => ['Palladium (troy ounce)', 0, [], ['palladium']],
        };
    }
}
