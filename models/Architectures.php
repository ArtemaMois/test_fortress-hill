<?php

declare(strict_types=1);

namespace app\models;

use yii\db\ActiveQuery;
use yii\db\ActiveRecord;

class Architectures extends ActiveRecord
{
    public static function tableName(): string
    {
        return 'architectures';
    }

    public function rules(): array
    {
        return [
            [['name'], 'required'],
            [['name'], 'string', 'max' => 255],
        ];
    }

    public function getLogs(): ActiveQuery
    {
        return $this->hasMany(Logs::class, ['architecture_id' => 'id']);
    }
}