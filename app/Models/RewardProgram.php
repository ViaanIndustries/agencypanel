<?php
/**
 * ModelName : RewardProgram.
 * Maintains a list of functions used for RewardProgram.
 *
 * @author      Shekhar <chandrashekhar.thalkar@bollyfame.com>
 * @since       2019-08-27
 * @link        http://bollyfame.com
 * @copyright   2019 BOLLYFAME
 * @license     http://bollyfame.com/license
 */

namespace App\Models;
use App\Models\Basemodel;

class RewardProgram extends  Basemodel {

    protected $connection   = 'arms_activities_jobs';

    protected $primaryKey   = '_id';

    protected $collection   = 'rewardprograms';

    protected $dates        = ['created_at', 'updated_at'];

    protected $priorities = [
        'high'  => 'High',
        'low'   => 'Low',
    ];

    protected $events = [
        'on_registration'       => 'On Registration',
        'on_package_purchase'   => 'On Package Purchase',
        'on_content_share'      => 'On Content Share',
    ];


    /**
     * Get the Priorities.
     *
     * @author  Shekhar <chandrashekhar.thalkar@bollyfame.com>
     * @since   2019-08-27
     */
    public function getPriorities() {
        return $this->priorities;
    }

    /**
     * Get the events.
     *
     * @author  Shekhar <chandrashekhar.thalkar@bollyfame.com>
     * @since   2019-08-27
     */
    public function getEvents() {
        return $this->events;
    }

    /**
     * Get the artist that whose reward program belongs to.
     *
     * @author  Shekhar <chandrashekhar.thalkar@bollyfame.com>
     * @since   2019-08-27
     */
    public function artist() {
        return $this->belongsTo('App\Models\Cmsuser');
    }


    /**
     * Set the RewardProgram's Referral XP.
     *
     * @param  string/integer  $value
     * @return void
     *
     * @author  Shekhar <chandrashekhar.thalkar@bollyfame.com>
     * @since   2019-08-27
     */
    public function setReferralXpAttribute($value)
    {
        $this->attributes['referral_xp'] = intval(trim($value));
    }


    /**
     * Set the RewardProgram's Referrer XP.
     *
     * @param  string/integer  $value
     * @return void
     *
     * @author  Shekhar <chandrashekhar.thalkar@bollyfame.com>
     * @since   2019-08-27
     */
    public function setReferrerXpAttribute($value)
    {
        $this->attributes['referrer_xp'] = intval(trim($value));
    }


    /**
     * Set the RewardProgram's Referral Coins.
     *
     * @param  string/integer  $value
     * @return void
     *
     * @author  Shekhar <chandrashekhar.thalkar@bollyfame.com>
     * @since   2019-08-27
     */
    public function setReferralCoinsAttribute($value)
    {
        $this->attributes['referral_coins'] = intval(trim($value));
    }


    /**
     * Set the RewardProgram's Referrer Coins.
     *
     * @param  string/integer  $value
     * @return void
     *
     * @author  Shekhar <chandrashekhar.thalkar@bollyfame.com>
     * @since   2019-08-27
     */
    public function setReferrerCoinsAttribute($value)
    {
        $this->attributes['referrer_coins'] = intval(trim($value));
    }


    /**
     * Set the RewardProgram's Referral Coins.
     *
     * @param  string/integer  $value
     * @return void
     *
     * @author  Shekhar <chandrashekhar.thalkar@bollyfame.com>
     * @since   2019-08-27
     */
    public function setReferralRewardLimitAttribute($value)
    {
        $this->attributes['referral_reward_limit'] = intval(trim($value));
    }


    /**
     * Set the RewardProgram's Referrer Coins.
     *
     * @param  string/integer  $value
     * @return void
     *
     * @author  Shekhar <chandrashekhar.thalkar@bollyfame.com>
     * @since   2019-08-27
     */
    public function setReferrerRewardLimitAttribute($value)
    {
        $this->attributes['referrer_reward_limit'] = intval(trim($value));
    }
}
