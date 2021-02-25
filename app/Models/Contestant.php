<?php
/**
 * ModelName : Contestant.
 * Maintains a list of functions used for Contestant.
 *
 * @author Shekhar <chandrashekhar.thalkar@bollyfame.com>
 */

namespace App\Models;
use App\Models\Basemodel;

class Contestant extends  Basemodel {

    protected $connection = 'arms_customers';

    protected $primaryKey = '_id';

    protected $collection = 'contestants';

    protected $dates        = ['created_at', 'updated_at', 'approved_at'];

    /**
     * Get the contents for the contestant.
     */
    public function contents() {
        return $this->hasMany('App\Models\ContestantContent');
    }

    /**
     * Get the paid photos for the contestant.
     */
    public function paid_photos() {
        return $this->hasMany('App\Models\ContestantContent')->where('content_type', 'paid_content')->where('status', 'active');
    }

    /**
     * Get the KYC docs for the contestant.
     */
    public function kyc_documents() {
        return $this->hasMany('App\Models\ContestantContent')->where('content_type', 'kyc_doc')->where('status', 'active');
    }
}
