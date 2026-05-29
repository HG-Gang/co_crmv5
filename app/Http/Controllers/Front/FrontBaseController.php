<?php
/**
 * 前台基类控制器 | Front Base Controller
 * 
 * 所有前台控制器继承此类，统一使用ApiResponse | All front controllers extend this class
 */
namespace App\Http\Controllers\Front;

use App\Http\Controllers\Controller;
use App\Traits\ApiResponse;

class FrontBaseController extends Controller
{
    use ApiResponse;
}
