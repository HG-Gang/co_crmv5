<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\BaseModel;

/**
 * 交易品种价格模型 | Symbol Price Model
 * 
 * 存储交易品种的实时或历史价格数据。
 * Stores real-time or historical price data for trading symbols.
 */
class SymbolPrice extends BaseModel
{
    use HasFactory;
    protected $table = 'symbol_prices';
}
