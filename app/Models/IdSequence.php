<?php

namespace App\Models;

use App\Models\BaseModel;
use Illuminate\Support\Facades\DB;

/**
 * ID序列模型 | ID Sequence Model
 * 
 * 用于生成自定义格式的唯一ID序列。
 * Used for generating unique ID sequences in custom formats.
 */
class IdSequence extends BaseModel
{
    /**
     * 数据库表名 | Table Name
     * @var string
     */
    protected $table = 'id_sequences';

    /**
     * 获取序列类型的下一个ID（原子操作） | Get next ID for a sequence type (atomic operation)
     * 
     * @param string $type 'agent' 或 'customer'
     * @return int
     * @throws \RuntimeException
     */
    public static function nextId(string $type): int
    {
        return DB::transaction(function () use ($type) {
            $seq = self::where('type', $type)->lockForUpdate()->first();
            if (!$seq) {
                // Initialize if not exists
                $startValue = ($type === 'agent') ? 1000 : 600000;
                $seq = self::create([
                    'type'          => $type,
                    'current_value' => $startValue,
                    'step'          => 1
                ]);
            }
            $nextVal = $seq->current_value + $seq->step;
            $seq->update(['current_value' => $nextVal]);
            return $nextVal;
        });
    }
}
