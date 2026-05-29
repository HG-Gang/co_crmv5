<?php
/**
 * 后台基类控制器 | Admin Base Controller
 * 
 * 所有后台控制器继承此类，统一使用ApiResponse | All admin controllers extend this class
 */
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Traits\ApiResponse;

class AdminBaseController extends Controller
{
    use ApiResponse;
}
