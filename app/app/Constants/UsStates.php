<?php

declare(strict_types=1);

namespace App\Constants;

class UsStates
{
    /**
     * Location codes for therapist/student/school/lead records.
     *
     * Historically US state codes (2-letter USPS). International therapists are
     * represented by 3-letter city codes (e.g. KHI = Karachi, LDN = London),
     * which is why the column is varchar(3) and not constrained to US states.
     * The "state" naming is retained for backwards compatibility.
     */

    public const STATES = [
        'AL' => 'Alabama',
        'AK' => 'Alaska',
        'AZ' => 'Arizona',
        'AR' => 'Arkansas',
        'CA' => 'California',
        'CO' => 'Colorado',
        'CT' => 'Connecticut',
        'DE' => 'Delaware',
        'FL' => 'Florida',
        'GA' => 'Georgia',
        'HI' => 'Hawaii',
        'ID' => 'Idaho',
        'IL' => 'Illinois',
        'IN' => 'Indiana',
        'IA' => 'Iowa',
        'KS' => 'Kansas',
        'KY' => 'Kentucky',
        'LA' => 'Louisiana',
        'ME' => 'Maine',
        'MD' => 'Maryland',
        'MA' => 'Massachusetts',
        'MI' => 'Michigan',
        'MN' => 'Minnesota',
        'MS' => 'Mississippi',
        'MO' => 'Missouri',
        'MT' => 'Montana',
        'NE' => 'Nebraska',
        'NV' => 'Nevada',
        'NH' => 'New Hampshire',
        'NJ' => 'New Jersey',
        'NM' => 'New Mexico',
        'NY' => 'New York',
        'NC' => 'North Carolina',
        'ND' => 'North Dakota',
        'OH' => 'Ohio',
        'OK' => 'Oklahoma',
        'OR' => 'Oregon',
        'PA' => 'Pennsylvania',
        'RI' => 'Rhode Island',
        'SC' => 'South Carolina',
        'SD' => 'South Dakota',
        'TN' => 'Tennessee',
        'TX' => 'Texas',
        'UT' => 'Utah',
        'VT' => 'Vermont',
        'VA' => 'Virginia',
        'WA' => 'Washington',
        'WV' => 'West Virginia',
        'WI' => 'Wisconsin',
        'WY' => 'Wyoming',
        'DC' => 'District of Columbia',
        'KHI' => 'Karachi',
        'ISB' => 'Islamabad',
        'IST' => 'Istanbul',
        'LDN' => 'London',
        'EDI' => 'Edinburgh',
        'DUB' => 'Dublin',
        'LIS' => 'Lisbon',
    ];

    /**
     * @return array<string, string>
     */
    public static function getStates(): array
    {
        return self::STATES;
    }

    public static function getStateName(string $stateCode): string
    {
        return self::STATES[$stateCode] ?? $stateCode;
    }
}
