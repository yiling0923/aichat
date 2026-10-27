<?php

namespace App\Models\Scopes;

use App\Support\CurrentBusiness;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class BusinessScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $id = app(CurrentBusiness::class)->id();

        if ($id === null) {
            // No current business: show nothing rather than everything.
            $builder->whereRaw('1 = 0');

            return;
        }

        $builder->where($model->getTable().'.business_id', $id);
    }
}
