<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:28
 */

/**
 * 文件系统磁盘配置。
 *
 * 配置用途：
 * - 定义默认文件存储磁盘与各磁盘根目录（local/public/s3）。
 * - 用户头像、身份证照片、银行卡照片等上传文件通过 Storage 门面按磁盘读写。
 *
 * 注意：
 * - public 磁盘需执行 `php artisan storage:link` 建立软链后才能通过 URL 访问；
 * - 切换 S3 前需确认凭证与 Bucket 权限，存量本地文件不会自动迁移。
 */

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application. Just store away!
    |
    */

    // 默认磁盘：local（storage/app 目录）。
    'default' => env('FILESYSTEM_DRIVER', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Here you may configure as many filesystem "disks" as you wish, and you
    | may even configure multiple disks of the same driver. Defaults have
    | been setup for each driver as an example of the required options.
    |
    | Supported Drivers: "local", "ftp", "sftp", "s3"
    |
    */

    // 磁盘实例配置总表：每个键是一个磁盘名，供 Storage::disk() 按名调用。
    'disks' => [

        // 本地私有磁盘：根目录 storage/app，不对外提供 URL 访问。
        'local' => [
            'driver' => 'local', // 驱动类型：本地文件系统。
            'root' => storage_path('app'), // 磁盘根目录（绝对路径）。
        ],

        // 本地公开磁盘：根目录 storage/app/public，经 /storage 软链公开访问。
        'public' => [
            'driver' => 'local', // 驱动类型：本地文件系统。
            'root' => storage_path('app/public'), // 磁盘根目录（软链目标）。
            'url' => env('APP_URL').'/storage', // 公开访问 URL 前缀。
            'visibility' => 'public', // 文件默认可见性：public 可公开读取。
        ],

        // AWS S3 对象存储磁盘。
        's3' => [
            'driver' => 's3', // 驱动类型：AWS S3 对象存储。
            'key' => env('AWS_ACCESS_KEY_ID'), // AWS 访问密钥 ID。
            'secret' => env('AWS_SECRET_ACCESS_KEY'), // AWS 密钥（生产必须注入）。
            'region' => env('AWS_DEFAULT_REGION'), // Bucket 所在区域。
            'bucket' => env('AWS_BUCKET'), // Bucket 名称。
            'url' => env('AWS_URL'), // 自定义访问 URL（可选）。
            'endpoint' => env('AWS_ENDPOINT'), // 自定义端点（MinIO 等兼容服务用）。
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false), // 是否使用路径式端点。
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    |
    | Here you may configure the symbolic links that will be created when the
    | `storage:link` Artisan command is executed. The array keys should be
    | the locations of the links and the values should be their targets.
    |
    */

    // storage:link 创建的软链映射：public/storage -> storage/app/public。
    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
