<?php

namespace App\Support;

use MongoDB\BSON\ObjectId;
use MongoDB\Laravel\Eloquent\Model;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class MongoModelFinder
{
    public static function findOrFail(string $modelClass, string $id): Model
    {
        try {
            $model = $modelClass::find(new ObjectId($id));
        } catch (\Throwable $e) {
            $model = null;
        }

        if (! $model) {
            throw new NotFoundHttpException();
        }

        return $model;
    }
}
