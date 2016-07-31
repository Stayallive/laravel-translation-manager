<?php

namespace Barryvdh\TranslationManager\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Translation model
 *
 * @property integer        $id
 * @property integer        $status
 * @property string         $locale
 * @property string         $group
 * @property string         $key
 * @property string         $value
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class Translation extends Model
{
    const STATUS_SAVED   = 0;
    const STATUS_CHANGED = 1;

    protected $table      = 'ltm_translations';
    protected $guarded    = ['id', 'created_at', 'updated_at'];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->setConnection(config('translation-manager.db_connection'));
    }
}
