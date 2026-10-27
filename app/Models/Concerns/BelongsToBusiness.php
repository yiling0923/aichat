<?php

namespace App\Models\Concerns;

use App\Models\Business;
use App\Models\Scopes\BusinessScope;
use App\Support\CurrentBusiness;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Put this on every model whose table has a business_id column.
 * Reads are limited to the current business and new rows get its id.
 */
trait BelongsToBusiness
{
    public static function bootBelongsToBusiness(): void
    {
        static::addGlobalScope(new BusinessScope);

        static::creating(function (Model $model): void {
            if (! empty($model->business_id)) {
                return;
            }

            $id = app(CurrentBusiness::class)->id();

            if ($id === null) {
                throw new LogicException(class_basename($model).' cannot be created without a current business.');
            }

            $model->business_id = $id;
        });
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class)->withoutGlobalScopes();
    }
}
