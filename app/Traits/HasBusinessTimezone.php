<?php

namespace App\Traits;

use Carbon\CarbonInterface;

/**
 * Trait ensuring all Eloquent model datetime attributes:
 * 1. Are returned in the business viewport timezone (Asia/Dhaka) when retrieved,
 *    so all Blade views show local BD time automatically without modifying template code.
 * 2. Are converted back to UTC when saved, strictly preserving UTC database normalization.
 */
trait HasBusinessTimezone
{
    /**
     * Convert retrieved database timestamps into the business operating timezone (Asia/Dhaka).
     *
     * @param mixed $value
     * @return \Illuminate\Support\Carbon
     */
    protected function asDateTime($value)
    {
        $date = parent::asDateTime($value);

        if ($date instanceof CarbonInterface) {
            $tz = config('helpdesk.business_hours.timezone', 'Asia/Dhaka');
            return $date->setTimezone($tz);
        }

        return $date;
    }

    /**
     * Convert DateTime to UTC string before persisting to the database.
     *
     * @param mixed $value
     * @return string|null
     */
    public function fromDateTime($value)
    {
        if (empty($value)) {
            return $value;
        }

        $date = parent::asDateTime($value);

        return $date->copy()->setTimezone('UTC')->format($this->getDateFormat());
    }
}
