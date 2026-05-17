<?php

declare(strict_types=1);

namespace app\models;

use yii\db\ActiveQuery;
use yii\db\ActiveRecord;

/**
 * @property int $id
 * @property string $name
 *
 * @property Log[] $logs
 */
class OperatingSystem extends ActiveRecord
{
    public static function tableName(): string
    {
        return 'operating_systems';
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
        return $this->hasMany(Log::class, ['operating_system_id' => 'id']);
    }
}
