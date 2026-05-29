<?php
namespace App\Facades;

use Illuminate\Support\Facades\Facade;

class Mt4ManagerApi extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'mt4.manager';
    }
}
