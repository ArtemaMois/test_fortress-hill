<?php

declare(strict_types=1);

namespace app\models;

use yii\db\ActiveQuery;
use yii\db\ActiveRecord;

class Logs extends ActiveRecord
{
    public static function tableName(): string
    {
        return 'logs';
    }

    public function rules(): array
    {
        return [
            [['ip', 'url', 'user_agent'], 'required'],
            [['ip', 'url', 'user_agent'], 'string', 'max' => 255],
            [['requested_at'], 'datetime', 'format' => 'php:Y-m-d H:i:s'],
            [['operating_system_id', 'architecture_id', 'browser_id'], 'integer'],
            [['operating_system_id'], 'exist', 'skipOnError' => true, 'targetClass' => OperatingSystems::class, 'targetAttribute' => ['operating_system_id' => 'id']],
            [['architecture_id'], 'exist', 'skipOnError' => true, 'targetClass' => Architectures::class, 'targetAttribute' => ['architecture_id' => 'id']],
            [['browser_id'], 'exist', 'skipOnError' => true, 'targetClass' => Browsers::class, 'targetAttribute' => ['browser_id' => 'id']],
        ];
    }

    public function getOperatingSystem(): ActiveQuery
    {
        return $this->hasOne(OperatingSystems::class, ['id' => 'operating_system_id']);
    }

    public function getArchitecture(): ActiveQuery
    {
        return $this->hasOne(Architectures::class, ['id' => 'architecture_id']);
    }

    public function getBrowser(): ActiveQuery
    {
        return $this->hasOne(Browsers::class, ['id' => 'browser_id']);
    }
}