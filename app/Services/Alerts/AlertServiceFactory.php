<?php

namespace App\Services\Alerts;

use App\Models\Setting;

class AlertServiceFactory
{
    /**
     * Get the appropriate alert service based on configuration
     */
    public static function make(): AlertServiceInterface
    {
        $source = Setting::getValue('alerts.source', 'europe');
        
        return match($source) {
            'europe' => app(MeteoalarmService::class),
            'usa' => app(NWSAlertService::class),
            'canada' => app(CanadaAlertService::class),
            'uk' => app(UKMetOfficeService::class),
            'australia' => app(AustraliaAlertService::class),
            default => app(MeteoalarmService::class),
        };
    }

    /**
     * Get available alert sources
     */
    public static function getSources(): array
    {
        return [
            'europe' => [
                'name' => 'Meteoalarm (Europe)',
                'description' => '35 European countries',
                'regions' => self::getEuropeanCountries(),
            ],
            'usa' => [
                'name' => 'NWS (USA)',
                'description' => 'National Weather Service',
                'regions' => self::getUSStates(),
            ],
            'canada' => [
                'name' => 'Environment Canada',
                'description' => 'Canadian weather alerts',
                'regions' => self::getCanadianProvinces(),
            ],
            'uk' => [
                'name' => 'Met Office (UK)',
                'description' => 'UK weather warnings',
                'regions' => self::getUKRegions(),
            ],
            'australia' => [
                'name' => 'BOM (Australia)',
                'description' => 'Bureau of Meteorology',
                'regions' => self::getAustralianStates(),
            ],
        ];
    }

    private static function getEuropeanCountries(): array
    {
        return [
            'AT' => 'Austria', 'BA' => 'Bosnia-Herzegovina', 'BE' => 'Belgium', 
            'BG' => 'Bulgaria', 'CH' => 'Switzerland', 'CY' => 'Cyprus', 
            'CZ' => 'Czechia', 'DE' => 'Germany', 'DK' => 'Denmark', 
            'EE' => 'Estonia', 'ES' => 'Spain', 'FI' => 'Finland', 
            'FR' => 'France', 'GR' => 'Greece', 'HR' => 'Croatia', 
            'HU' => 'Hungary', 'IE' => 'Ireland', 'IS' => 'Iceland', 
            'IT' => 'Italy', 'LT' => 'Lithuania', 'LU' => 'Luxembourg', 
            'LV' => 'Latvia', 'MD' => 'Moldova', 'ME' => 'Montenegro', 
            'MK' => 'North Macedonia', 'MT' => 'Malta', 'NL' => 'Netherlands', 
            'NO' => 'Norway', 'PL' => 'Poland', 'PT' => 'Portugal', 
            'RO' => 'Romania', 'RS' => 'Serbia', 'SE' => 'Sweden', 
            'SI' => 'Slovenia', 'SK' => 'Slovakia', 'UK' => 'United Kingdom',
        ];
    }

    private static function getUSStates(): array
    {
        return [
            'AL' => 'Alabama', 'AK' => 'Alaska', 'AZ' => 'Arizona', 
            'AR' => 'Arkansas', 'CA' => 'California', 'CO' => 'Colorado',
            'CT' => 'Connecticut', 'DE' => 'Delaware', 'FL' => 'Florida',
            'GA' => 'Georgia', 'HI' => 'Hawaii', 'ID' => 'Idaho',
            'IL' => 'Illinois', 'IN' => 'Indiana', 'IA' => 'Iowa',
            'KS' => 'Kansas', 'KY' => 'Kentucky', 'LA' => 'Louisiana',
            'ME' => 'Maine', 'MD' => 'Maryland', 'MA' => 'Massachusetts',
            'MI' => 'Michigan', 'MN' => 'Minnesota', 'MS' => 'Mississippi',
            'MO' => 'Missouri', 'MT' => 'Montana', 'NE' => 'Nebraska',
            'NV' => 'Nevada', 'NH' => 'New Hampshire', 'NJ' => 'New Jersey',
            'NM' => 'New Mexico', 'NY' => 'New York', 'NC' => 'North Carolina',
            'ND' => 'North Dakota', 'OH' => 'Ohio', 'OK' => 'Oklahoma',
            'OR' => 'Oregon', 'PA' => 'Pennsylvania', 'RI' => 'Rhode Island',
            'SC' => 'South Carolina', 'SD' => 'South Dakota', 'TN' => 'Tennessee',
            'TX' => 'Texas', 'UT' => 'Utah', 'VT' => 'Vermont',
            'VA' => 'Virginia', 'WA' => 'Washington', 'WV' => 'West Virginia',
            'WI' => 'Wisconsin', 'WY' => 'Wyoming',
        ];
    }

    private static function getCanadianProvinces(): array
    {
        return [
            'AB' => 'Alberta', 'BC' => 'British Columbia', 'MB' => 'Manitoba',
            'NB' => 'New Brunswick', 'NL' => 'Newfoundland and Labrador',
            'NS' => 'Nova Scotia', 'NT' => 'Northwest Territories',
            'NU' => 'Nunavut', 'ON' => 'Ontario', 'PE' => 'Prince Edward Island',
            'QC' => 'Quebec', 'SK' => 'Saskatchewan', 'YT' => 'Yukon',
        ];
    }

    private static function getUKRegions(): array
    {
        return [
            'dg' => 'Dumfries, Galloway, Lothian & Borders',
            'ee' => 'East of England',
            'em' => 'East Midlands',
            'gc' => 'Grampian',
            'he' => 'Highlands & Eilean Siar',
            'hm' => 'Hampshire & Isle of Wight',
            'ke' => 'Kent, Surrey & Sussex',
            'ln' => 'London & Greater London',
            'nw' => 'North West England',
            'ni' => 'Northern Ireland',
            'or' => 'Orkney & Shetland',
            'se' => 'South West England',
            'sh' => 'Strathclyde',
            'ss' => 'Central, Tayside & Fife',
            'sw' => 'South Wales',
            'ta' => 'Tayside',
            'uk' => 'UK Overview',
            'wl' => 'Wales',
            'wm' => 'West Midlands',
            'yh' => 'Yorkshire & Humber',
        ];
    }

    private static function getAustralianStates(): array
    {
        return [
            'nsw' => 'New South Wales',
            'vic' => 'Victoria',
            'qld' => 'Queensland',
            'wa' => 'Western Australia',
            'sa' => 'South Australia',
            'tas' => 'Tasmania',
            'nt' => 'Northern Territory',
            'act' => 'Australian Capital Territory',
        ];
    }
}
