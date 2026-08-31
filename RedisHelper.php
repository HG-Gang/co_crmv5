<?php
declare(strict_types = 1);

namespace haveyb\RedisHelper;

use Illuminate\Support\Facades\Redis;


/**
 * redis助手类
 * Redis使用了门面类，具体方法在 External Libraries\PHP Runtime\redis\RedisCluster.php
 * @author cyf
 */
class RedisHelper
{
    const AFTER = 'after';
    const BEFORE = 'before';

    /**
     * Confirm
     */
    const YES = 1;
    const NO = 0;

    // Direction
    const LEFT = 1;
    const RIGHT = 2;

    /**
     * Multi
     */
    const ATOMIC = 0;
    const MULTI = 1;
    const PIPELINE = 2;

    /**
     * 设置键值
     *
     * @param $key
     * @param $value
     * @param null $timeout 过期时间 | 默认值为null，即无过期时间
     * @param bool $timeUnit 过期时间单位 | 默认值为true，即以秒为单位，可填写false，则以毫秒为过期时间单位
     * @return bool
     *
     * RedisHelper::set('age', 18)
     * RedisHelper::set('age', 18, 60)
     * RedisHelper::set('age', 18, 60000, true)
     */
    public static function set($key, $value, $timeout = null, $isSecond = true)
    {
        return $timeout
            ? $isSecond ? Redis::setex($key, $timeout, $value) : Redis::psetex($key, $timeout, $value)
            : Redis::set($key, $value);
    }

    /**
     * 获取某个键的值
     *
     * @param $key
     * @return string|bool
     * RedisHelper::get('age')
     */
    public static function get($key)
    {
        return Redis::get($key);
    }

    /**
     * 同时设置多个键值对 （如果设置成功，则返回true；已有的键，它的值将被覆盖）
     *
     * @return bool
     * RedisHelper::mSet(['age' => 28, 'username' => 'haveyb', 'city' => 'hangzhou'])
     */
    public static function mSet(array $array)
    {
        return Redis::mset($array);
    }

    /**
     * 同时设置多个键值对（如果设置成功，则返回true；如果有键之前已经存在，则所有的都设置不成功，返回false）
     *
     * @param array $array
     * @return bool
     */
    public static function mSetNx(array $array)
    {
        return Redis::msetnx($array);
    }

    /**
     * 返回所有指定键的值（如果某个键不存在，则返回值中，该键的值为null）
     *
     * @param array $array
     * @return array
     * RedisHelper::mget(['age', 'username', 'sex'])
     */
    public static function mGet(array $array)
    {
        return Redis::mget($array);
    }

    /**
     * 删除指定的键
     *
     * @param $param string|array
     * @return int 被删除的键的数目（有不存在的键，不影响其他存在的键的删除）
     * RedisHelper::del('age')
     * RedisHelper::del(['city', 'username', 'sex'])
     */
    public static function del($param)
    {
        return Redis::del($param);
    }

    /**
     * 如果键不存在，就设置键值（如果键已经存在了，则不再设置，返回false）
     *
     * @param $key
     * @param $value
     * @return  bool
     */
    public static function setNx($key, $value)
    {
        return Redis::setnx($key, $value);
    }

    /**
     * 返回键的旧值，并设置为新值（如果没有该键，则返回false，并设置键值）
     *
     * @param $key
     * @param $value
     * @return string|bool
     */
    public static function getSet($key, $value)
    {
        return Redis::getset($key, $value);
    }

    /**
     * 检查指定的键是否存在。
     *
     * @param $key
     * @return bool
     */
    public static function exists($key)
    {
        return Redis::exists($key);
    }

    /**
     * 返回与特定模式匹配的键
     *
     * @param $pattern
     * @return  array
     * RedisHelper::keys('age*')
     * RedisHelper::keys('*')
     */
    public static function keys($pattern)
    {
        return Redis::keys($pattern);
    }

    /**
     * 返回给定键指向的数据类型
     *
     * 0 - REDIS_NOT_FOUND
     * 1 - string
     * 2 - set
     * 3 - list
     * 4 - zset
     * 5 - hash
     * @param $key
     * RedisHelper::type('admin')
     */
    public static function type($key)
    {
        return Redis::type($key);
    }

    /**
     * 获取key所储存的字符串值的长度（当key储存的不是字符串值时，返回false）
     *
     * @param $key
     * @return int
     * RedisHelper::strLen('age')
     */
    public static function strLen($key)
    {
        return Redis::strLen($key);
    }

    /**
     * 返回存储在指定key中字符串的子字符串。字符串的截取范围由start和end两个偏移量决定(包括start和end在内)
     *
     * @param string $key
     * @param int $start
     * @param int $end
     * RedisHelper::set('website', 'https://www.haveyb.com');
     * RedisHelper::getRange('website', 1, 5) // ttps:
     */
    public static function getRange($key, $start, $end)
    {
        return Redis::getRange($key, $start, $end);
    }

    /**
     * 设置key的过期时间
     *
     * @param $key
     * @param $ttl
     * @param bool $isSecond 时间单位 true-秒，false-毫秒
     * @return mixed
     */
    public static function expire($key, $ttl, $isSecond = true)
    {
        return true == $isSecond ? Redis::expire($key, $ttl) : Redis::pExpire($key, $ttl);
    }

    /**
     * 设置在到达某个时间戳时，key过期
     *
     * @param $key
     * @param $timestamp
     * @param bool $isSecond 时间单位 true-秒，false-毫秒
     * @return mixed
     */
    public static function expireAt($key, $timestamp, $isSecond = true)
    {
        return true == $isSecond ? Redis::expireAt($key, $timestamp) : Redis::pExpireAt($key, $timestamp);
    }

    /**
     * 返回key的剩余过期时间
     *
     * @param $key
     * @param int $isSecond 返回的单位为秒还是毫秒 RedisHelper::YES - 以秒为单位；RedisHelper::NO - 以毫秒为单位
     * @return mixed
     */
    public static function getTtl($key, $isSecond = RedisHelper::YES)
    {
        return RedisHelper::YES == $isSecond ? Redis::ttl($key) : Redis::pttl($key);
    }

    /**
     * 移除给定key的过期时间，使得key永不过期
     *
     * @param $key
     * @return bool
     * RedisHelper::persist('aa')
     */
    public static function persist($key)
    {
        return Redis::persist($key);
    }

    /**
     * 修改 key 的名称（如果新的key的名称已经存在，则将覆盖掉原来的内容）
     *
     * @param $srcKey
     * @param $dstKey
     * @return mixed
     * RedisHelper::rename('city', 'area') // 将键名city改为area
     */
    public static function rename($srcKey, $dstKey)
    {
        return Redis::rename($srcKey, $dstKey);
    }

    /**
     * 修改key的名称（如果新的key的名称已经存在，则不能修改成功）
     *
     * @param $srcKey
     * @param $dstKey
     * @return mixed
     */
    public static function renameNx($srcKey, $dstKey)
    {
        return Redis::renameNx($srcKey, $dstKey);
    }

    /**
     * 返回给定列表、集合、有序集合 key 中经过排序的元素
     * 排序默认以数字作为对象，值被解释为双精度浮点数，然后进行比较。
     *
     * @param string $key
     * @param array $option
    - 'by' => 'some_pattern_*',
    - 'limit' => array(0, 1),
    - 'get' => 'some_other_pattern_*' or an array of patterns,
    - 'sort' => 'asc' or 'desc',
    - 'alpha' => TRUE,
    - 'store' => 'external-key'
     * @return array
     */
    public static function sort($key, array $option = null)
    {
        return Redis::sort($key, $option);
    }

    /**
     * 描述由键指向的对象
     * @param string $sonCommand
    - refcount 返回给定 key 引用所储存的值的次数
    - encoding 返回给定 key 锁储存的值所使用的内部表示
    - idletime 返回给定 key 自储存以来的空转时间
     * @param string $key
     * @throws \Exception
     * @return string
     * RedisHelper::object('refcount', 'z-test');
     */
    public static function object($sonCommand, $key)
    {
        if (!in_array($sonCommand, ['encoding', 'refcount', 'idletime'])) {
            throw new \Exception('参数错误，核查后重试');
        }
        return Redis::object($sonCommand, $key);
    }

    /**
     * 返回被序列化的值
     *
     * @param $key
     * @return mixed
     *
     */
    public static function dump($key)
    {
        return Redis::dump($key);
    }

    /**
     * 反序列化给定的序列化值，并将它和给定的 key 关联
     *
     * @param $key
     * @param int $ttl key的有效期，如果为零，则为不对key设置expire
     * @param $value
     */
    public static function restore($key, $ttl, $value)
    {
        return Redis::restore($key, $ttl, $value);
    }

    /**
     * 用指定的字符串覆盖给定key所储存的字符串值，覆盖的位置从偏移量offset开始
     *
     * @param string $key
     * @param int $offset
     * @param string $value
     * @return string
     * RedisHelper::set('zz', 'hello world');
     * RedisHelper::setRange('zz', 6, 'redis');
     * $data = RedisHelper::get('zz'); // hello redis
     */
    public static function setRange($key, $offset, $value)
    {
        return Redis::setRange($key, $offset, $value);
    }

    /**
     * 将指定key存储的数字值加1，返回更改后的数字（如果key存储的类型不是数字，则返回false）
     *
     * @param $key
     * @return int|bool
     */
    public static function incr($key)
    {
        return Redis::incr($key);
    }

    /**
     * 将key中储存的数字加上指定的增量值，返回更改后的数字（被更改的key所存储的值必须是整数）
     *
     * @param $key
     * @param int $value
     * @return int
     * RedisHelper::incrBy('test', 10)
     */
    public static function incrBy($key, $value)
    {
        return Redis::incrBy($key, $value);
    }

    /**
     * 将指定key存储的数字减1，返回更改后的数字（如果key存储的类型不是整数，则返回false）
     *
     * @param string $key
     * @return int
     */
    public static function decr($key)
    {
        return Redis::decr($key);
    }

    /**
     * 将key中储存的数字减掉指定的增量值，返回更改后的数字（被更改的key所存储的值必须是整数）
     *
     * @param string $key
     * @param int $value
     * @return int
     */
    public static function decrBy($key, $value)
    {
        return Redis::decrBy($key, $value);
    }

    /**
     * 为key中所储存的值加上指定的浮点数增量值increment
     *
     * @param $key
     * @param float|int $increment（可以整数或负数）
     * @return float
     */
    public static function incrByFloat($key, $increment)
    {
        return Redis::incrByFloat($key, $increment);
    }

    /**
     * 对字符串值追加（如果key存储的不是字符串，则返回false）
     * Append string value (return false if the key does not store a string)
     *
     * @param $key
     * @param $value
     * @return int 返回追加后的字符串长度
     * RedisHelper::set('test-string', 'hello');
     * RedisHelper::append('test-string', ' world!'); // hello world!
     */
    public static function append($key, $value)
    {
        return Redis::append($key, $value);
    }

    /**
     * 订阅给定的一个或多个频道的信息
     *
     * @param $channels
     * @param $callback
     */
    public static function subscribe($channels, $callback)
    {
        return Redis::subscribe($channels, $callback);
    }

    /**
     * 订阅一个或多个符合给定模式的频道
     *
     * @param $patterns
     * @param $callback
     * @return mixed
     */
    public static function psubscribe($patterns, $callback)
    {
        return Redis::psubscribe($patterns, $callback);
    }

    /**
     * 退订给定的一个或多个频道的信息
     *
     * @param $channels
     * @param $callback
     * @return mixed
     */
    public static function unSubscribe($channels, $callback)
    {
        return Redis::unSubscribe($channels, $callback);
    }

    /**
     * 退订所有给定模式的频道
     *
     * @param $channels
     * @param $callback
     */
    public static function punSubscribe($channels, $callback)
    {
        return Redis::punSubscribe($channels, $callback);
    }

    /**
     * 根据给定的 sha1 校验码，执行缓存在服务器中的脚本（将脚本缓存到服务器的操作可以通过SCRIPT LOAD命令进行）
     *
     * @param $scriptSha
     * @param array $args
     * @param int $numKeys
     * @return mixed
     */
    public static function evalSha($scriptSha, $args = [], $numKeys = 0)
    {
        return Redis::evalSha($scriptSha, $args, $numKeys);
    }

    /**
     * 将信息发送到指定的频道
     *
     * @param $channel
     * @param $message
     * @return mixed
     */
    public static function publish($channel, $message)
    {
        return Redis::publish($channel, $message);
    }

    /**
     * 用于迭代当前数据库中的数据库键
     *
     * @param int $iterator
     * @param string $pattern
     * @param int $count
     * @return array
     */
    public static function scan(&$iterator, $pattern = null, $count = 0)
    {
        return Redis::scan($iterator, $pattern, $count);
    }

    /**
     * 迭代集合键中的元素
     *
     * @param $key
     * @param $iterator
     * @param null $pattern
     * @param int $count
     * @return mixed
     */
    public static function sScan($key, &$iterator, $pattern = null, $count = 0)
    {
        return Redis::sScan($key, $iterator, $pattern, $count);
    }

    /**
     * 迭代有序集合中的元素（包括元素成员和元素分值）
     *
     * @param $key
     * @param $iterator
     * @param null $pattern
     * @param int $count
     * @return mixed
     */
    public static function zScan($key, &$iterator, $pattern = null, $count = 0)
    {
        return Redis::zScan($key, $iterator, $pattern, $count);
    }

    /**
     * 迭代哈希键中的键值对
     *
     * @param $key
     * @param $iterator
     * @param null $pattern
     * @param int $count
     * @return mixed
     */
    public static function hScan($key, &$iterator, $pattern = null, $count = 0)
    {
        return Redis:: hScan($key, $iterator, $pattern, $count);
    }

    /**
     * 检测我们是否处于 ATOMIC/MULTI/管道模式
     */
    public static function getMode()
    {
        return Redis::getMode();
    }

    /**
     * 返回最后一条错误信息（如果有）
     */
    public static function getLastError()
    {
        return Redis::getLastError();
    }

    /**
     * 清除最后一条错误消息
     */
    public static function clearLastError()
    {
        return Redis::clearLastError();
    }

    /**
     * 设置客户端选项
     *
     * @param $name
     * @param $value
     */
    public static function setOption($name, $value)
    {
        return Redis::setOption($name, $value);
    }

    /**
     *  获取客户端选项
     *
     * @param $name
     */
    public static function getOption($name)
    {
        return Redis::getOption($name);
    }

    /**
     * A utility method to prefix the value with the prefix setting for phpredis
     *
     * @param $value
     */
    public static function _prefix($value)
    {
        return Redis::_prefix($value);
    }

    /**
     * 手动序列化值的实用程序方法
     *
     * @param $value
     */
    public static function _serialize($value)
    {
        return Redis::_serialize($value);
    }

    /**
     * 使用设置的序列化程序取消序列化数据的实用方法
     *
     * @param $value
     */
    public static function _unserialize($value)
    {
        return Redis::_unserialize($value);
    }




    #----------------------------- 列表 List -----------------------------------

    /**
     * 将字符串值单个或批量添加到列表的左侧（头部）或右侧（尾部）
     * 如果key不存在，则创建列表
     *
     * @param string $key
     * @param array $values
     * @param string $direction 1-头部，2-尾部
     * @return int|bool 成功时返回列表的新长度，失败时返回false
     * @throws \Exception
     * RedisHelper::push('city', ['hangzhou', 'shanghai', 'wuhan'])
     */
    public static function push($key, array $values, $direction = RedisHelper::LEFT)
    {
        if (!in_array($direction, [RedisHelper::LEFT, RedisHelper::RIGHT])) {
            throw new \Exception('redis-push方法，direction参数值错误');
        }
        return RedisHelper::LEFT == $direction ? Redis::lPush($key, ...$values) : Redis::rPush($key, ...$values);
    }

    /**
     * 将字符串值单个插入到已存在的列表头部或尾部。
     * 插入成功，返回列表的新长度；如果列表不存在，则不创建列表，操作无效。返回0
     *
     * @param string $key
     * @param string $value
     * @param string $direction 1-头部，2-尾部
     * @return int
     * @throws \Exception
     */
    public static function pushX($key, $value, $direction = RedisHelper::LEFT)
    {
        if (!in_array($direction, [RedisHelper::LEFT, RedisHelper::RIGHT])) {
            throw new \Exception('redis-push方法，direction参数值错误');
        }
        return RedisHelper::LEFT == $direction ? Redis::lPushx($key, $value) : Redis::rPushx($key, $value);
    }

    /**
     * 返回并删除列表的第一个元素
     *
     * @param $key
     * @param string $direction 1-头部，2-尾部，random-随机
     * @return string
     * @throws \Exception
     * RedisHelper::pop('city')
     * RedisHelper::pop('city', RedisHelper::LEFT)
     */
    public static function pop($key, $direction = RedisHelper::LEFT)
    {
        switch ($direction) {
            case RedisHelper::LEFT :
                return Redis::lPop($key);
                break;
            case RedisHelper::RIGHT :
                return Redis::rPop($key);
                break;
            default :
                throw new \Exception('redis-pop方法，direction参数值错误');
        }
    }

    /**
     * 当给定多个 key 参数时，按参数 key 的先后顺序依次检查各个列表，弹出第一个非空列表的头元素或尾元素
     * 当给定列表内没有任何元素可供弹出的时候，连接将被阻塞，直到等待超时或发现可弹出元素为止
     *
     * @param array $keys
     * @param $timeout
     * @param string $direction
     * @throws \Exception
     *
     * RedisHelper::push('city', ['hangzhou', 'shenzhen', 'shanghai']);
     * RedisHelper::push('province', ['zhejiang', 'heilongjiang', 'liaoning']);
     * RedisHelper::bPop(['city', 'province'], 20, RedisHelper::LEFT)
     */
    public static function bPop(array $keys, $timeout, $direction = RedisHelper::LEFT)
    {
        switch ($direction) {
            case RedisHelper::LEFT :
                return Redis::blPop($keys, $timeout);
                break;
            case RedisHelper::RIGHT :
                return Redis::brPop($keys, $timeout);
                break;
            default :
                throw new \Exception('redis-pop方法，direction参数值错误');
        }
    }

    /**
     * 对列表的指定key设置新值（如果key不存在 或者 对一个空列表执行该命令，则会返回false）
     *
     * @param $key
     * @param $index
     * @param $value
     * @return bool
     * RedisHelper::lSet('province', 1, 'jilin');
     */
    public static function lSet($key, $index, $value)
    {
        return Redis::lSet($key, $index, $value);
    }

    /**
     * 返回列表指定键的值【已被废弃，改为使用lIndex命令】
     *
     * @param string $key
     * @param int $index
     * @throws \Exception
     * @return mixed
     */
    public static function lGet()
    {
        throw new \Exception('方法已启用，改为使用lIndex');
    }

    /**
     * 返回列表指定键的值（存在这个键，返回值，否则返回false）
     *
     * @param string $key
     * @param int $index
     * @return string|bool
     * RedisHelper::lIndex('province', 0)
     */
    public static function lIndex($key, $index)
    {
        return Redis::lIndex($key, $index);
    }

    /**
     * 在列表的元素前或者后插入元素（当指定元素不存在于列表中时，不执行任何操作）
     *
     * @param string $key 列表名
     * @param int $position int  RedisHelper::BEFORE | RedisHelper::AFTER 指定前后
     * @param string $pivot 被指定的元素
     * @param string $value
     * @return int 返回新列表的元素数（如果没有执行成功，则返回-1）
     * RedisHelper::lInsert('province', RedisHelper::AFTER, 'liaoning', 'jilin');
     */
    public static function lInsert($key, $position, $pivot, $value)
    {
        return Redis::lInsert($key, $position, $pivot, $value);
    }

    /**
     * 根据参数 COUNT 的值，移除列表中与参数 VALUE 相同的元素
     *
     * @param $key
     * @param $value
     * @param int $count
     * count > 0 : 从表头开始向表尾搜索，移除与 VALUE 相等的元素，数量为 COUNT
     * count < 0 : 从表尾开始向表头搜索，移除与 VALUE 相等的元素，数量为 COUNT 的绝对值
     * count = 0 : 移除表中所有与 VALUE 相等的值
     */
    public static function lRem($key, $value, $count)
    {
        return Redis::lRem($key, $value, $count);
    }

    /**
     * 返回列表中指定区间内的所有元素
     *
     * 区间用 START 和 END 指定。
     * 0表示列表的第一个元素， 1表示列表的第二个元素，以此类推
     * -1表示列表的最后一个元素， -2表示列表的倒数第二个元素
     *
     * @param string $key
     * @param int $start
     * @param int $end
     * RedisHelper::lRange('province', 0, -1)
     */
    public static function lRange($key, $start, $end)
    {
        return Redis::lRange($key, $start, $end);
    }

    /**
     * 修剪现有列表，使其仅包含指定范围的元素
     *
     * @param string $key
     * @param int $start
     * @param int $stop
     * @return bool
     * RedisHelper::push('list-test', ['A', 'B', 'C', 'D', 'E'], RedisHelper::RIGHT);
     * RedisHelper::lTrim('list-test', 1, 3);
     * RedisHelper::lRange('list-test', 0, -1); // ['B', 'C', 'D']
     */
    public static function lTrim($key, $start, $stop)
    {
        return Redis::lTrim($key, $start, $stop);
    }

    /**
     * 从列表尾部弹出一个值，并将其推到另一个列表的前面
     *
     * @param $srcKey
     * @param $dstKey
     * RedisHelper::rPopLPush('will_second_kill_0008', 'have_second_killed_0008')
     */
    public static function rPopLPush($srcKey, $dstKey)
    {
        return Redis::rpoplpush($srcKey, $dstKey);
    }

    /**
     * rPopLPush 的阻塞版本，如果列表没有元素会阻塞列表直到等待超时或发现可弹出元素为止
     *
     * @param $srcKey
     * @param $dstKey
     * @return mixed
     */
    public static function brPopLPush($srcKey, $dstKey, $timeout)
    {
        return Redis::brpoplpush($srcKey, $dstKey, $timeout);
    }

    /**
     * 返回列表的长度
     * 如果列表key不存在，则key被解释为一个空列表，返回0。 如果key不是列表类型，返回一个错误
     *
     * @param $key
     * @return mixed
     * RedisHelper::lLen('province')
     */
    public static function lLen($key)
    {
        return Redis::lLen($key);
    }

    #---------------------------------------  集合 Set  ---------------------------------------------

    /**
     * 添加一个或多个元素到指定集合中，集合不存在则创建（如果key存在，但类型不是集合，则返回false）
     *
     * @param $key
     * @param array $valueArray
     * @return mixed
     * RedisHelper::sAdd('dongBeiSanSheng', ['heiLongJiang', 'JiLin', 'liaoNing'])
     */
    public static function sAdd($key, array $valueArray)
    {
        return Redis::sAdd($key, ...$valueArray);
    }

    /**
     * 和sAdd功能相同
     *
     * @param $key
     * @param array $valueArray
     * @return mixed
     */
    public static function sAddArray($key, array $valueArray)
    {
        return Redis::sAddArray($key, $valueArray);
    }

    /**
     * 将指定成员member元素从$srcKey集合移动到$dstKey集合
     *
     * @param $srcKey
     * @param $dstKey
     * @param $member
     * @return mixed
     */
    public static function sMove($srcKey, $dstKey, $member)
    {
        return Redis::sMove($srcKey, $dstKey, $member);
    }

    /**
     * 返回集合中元素的数量
     *
     * @param $key
     * @return int
     * RedisHelper::sCard('province')
     */
    public static function sCard($key)
    {
        return Redis::sCard($key);
    }

    /**
     * 返回集合的所有的成员元素
     *
     * @param $key
     * RedisHelper::sMembers('dongBeiSanSheng')
     */
    public static function sMembers($key)
    {
        return Redis::sMembers($key);
    }

    /**
     * 判断成员元素是否是集合的成员
     *
     * @param $key
     * @param $value
     * @return bool
     * RedisHelper::sIsMember('dongBeiSanSheng', 'jilin')
     */
    public static function sIsMember($key, $value)
    {
        return Redis::sIsMember($key, $value);
    }

    /**
     * 移除集合中的一个或多个成员元素，不存在的成员元素会被忽略
     *
     * @param $key
     * @param array $valueArray
     * RedisHelper::sRem('dongBeiSanSheng', ['liaoning', 'jilin', 'guangdong'])
     */
    public static function sRem($key, array $valueArray)
    {
        return Redis::sRem($key, ...$valueArray);
    }

    /**
     * 返回给定集合的并集
     *
     * @param array $setArray
     * @return mixed
     */
    public static function sUnion(array $setArray)
    {
        return Redis::sUnion(...$setArray);
    }

    /**
     * 将指定集合的并集，存储到指定的集合$dstKey中（如果集合$dstKey已经存在，则将其覆盖）
     *
     * @param string $dstKey 指定存储得到哪个集合中
     * @param array $setArray
     * @return int
     * RedisHelper::sUnionStore('china', ['dongBei', 'huaDong'])
     */
    public static function sUnionStore($dstKey, array $setArray)
    {
        return Redis::sUnionStore($dstKey, ...$setArray);
    }

    /**
     * 返回给定所有给定集合的交集（不存在的集合key被视为空集，返回结果也将为空集）
     *
     * @param array $setArray
     * @return array
     * RedisHelper::sInterStore(['aa', 'bb'])
     */
    public static function sInter(array $setArray)
    {
        return Redis::sInter($setArray);
    }

    /**
     * 将给定集合的交集存储到指定的集合中（如果指定的集合已经存在，则将其覆盖，不存在则创建）
     *
     * @param string $dstKey 指定存储得到哪个集合中
     * @param array $setArray
     * @return int
     * RedisHelper::sInterStore('test', ['aa', 'bb'])
     */
    public static function sInterStore($dstKey, array $setArray)
    {
        return Redis::sInterStore($dstKey, ...$setArray);
    }

    /**
     * 返回多个集合的差集
     *
     * @param array $setArray
     * @return array
     */
    public static function sDiff(array $setArray)
    {
        return Redis::sDiff(...$setArray);
    }

    /**
     * 返回多个集合的差集的元素个数，并将差集元素存储到集合$dstKey
     *
     * @param $dstKey
     * @param array $setArray
     * @return int
     */
    public static function sDiffStore($dstKey, array $setArray)
    {
        return Redis::sDiffStore($dstKey, ...$setArray);
    }

    /**
     * 返回集合中的一个随机元素
     *
     * @param $key
     * @param int $count
     * @return mixed
     * 如果count为正数，将返回一个包含count个元素的数组，数组中的元素各不相同，如果大于集合元素数目，则返回集合全部元素
     * 如果count为负数，将返回一个数组，但是数组中的元素可能会重复出现多次
     * RedisHelper::sRandMember('province', 2)
     */
    public static function sRandMember($key, $count = 1)
    {
        return Redis::sRandMember($key, $count);
    }

    /**
     * 移除集合中的的一个或多个随机元素，并返回被移除的元素
     *
     */
    public static function sPop($key, $count = 1)
    {
        return Redis::sPop($key, $count);
    }


    # ---------------------------- 有序集合 ZSet ---------------------------------

    /**
     * 将一个或多个成员的分数值及元素值依次加入到有序集合
     *
     * @param $key
     * @param array $scoreAndValueArray
     * @return int
     * @throws \Exception
     * 添加1个元素 RedisHelper::zAdd('zset-test', [1, 'Abe'])
     * 添加2个元素 RedisHelper::zAdd('zset-test', [1, 'Maria', 2, 'Jone'])
     * 第一个参数填写zSet集合的key，第二个参数是一个数组，里面的值要遵循一个score之后一个值
     */
    public static function zAdd($key, array $scoreAndValueArray)
    {
        if (true == sizeof($scoreAndValueArray) % 2) {
            throw new \Exception('第二个参数scoreAndValueArray必须一个score之后跟一个值，传参有缺失');
        }
        return Redis::zAdd($key, ...$scoreAndValueArray);
    }

    /**
     * 返回有序集合中元素个数（如果类型不是有序集合，则返回false）
     *
     * @param $key
     * @return int|bool
     * RedisHelper::zCard('china')
     */
    public static function zCard($key)
    {
        return Redis::zCard($key);
    }

    /**
     * 返回有序集合中指定分数区间的成员数量
     *
     * @param $key
     * @param $start
     * @param $end
     * @return int
     * RedisHelper::zCount('zSet-test', 2, 3)
     */
    public static function zCount($key, $start, $end)
    {
        return Redis::zCount($key, $start, $end);
    }

    /**
     * 返回有序集中，指定区间内的成员（按分数值递增(从小到大)来排序）
     *
     * @param string $key
     * @param int $start
     * @param int $end
     * @param bool $withScores
     * @return array
     */
    public static function zRange($key, $start, $end, $withScores = null)
    {
        return Redis::zRange($key, $start, $end, $withScores);
    }

    /**
     * 返回有序集中，指定区间内的成员（按分数值递减(从大到小)来排列）
     *
     * @param $key
     * @param $start
     * @param $end
     * @param bool $withScores
     * @return array
     */
    public static function zRevRange($key, $start, $end, $withScores = null)
    {
        return Redis::zRevRange($key, $start, $end, $withScores);
    }

    /**
     * 返回有序集合中指定分数区间的成员列表。有序集成员按分数值递增(从小到大)次序排列
     *
     * @param $key
     * @param $start
     * @param $end
     * @param array $options
     * @return mixed
     */
    public static function zRangeByScore($key, $start, $end, array $options = [])
    {
        return Redis::zRangeByScore($key, $start, $end, $options);
    }

    /**
     * 返回有序集中指定分数区间内的所有的成员。有序集成员按分数值递减(从大到小)的次序排列
     *
     * @param $key
     * @param $start
     * @param $end
     * @param array $options
     * @return mixed
     */
    public static function zRevRangeByScore($key, $start, $end, array $options = array())
    {
        return Redis::zRevRangeByScore($key, $start, $end, $options);
    }

    /**
     * 通过字典区间返回有序集合的成员
     *
     * @param string $key
     * @param int $min
     * @param int $max
     * @param int $offset
     * @param int $limit
     * @return mixed
     */
    public function zRangeByLex($key, $min, $max, $offset = null, $limit = null)
    {
        return Redis::zRangeByLex($key, $min, $max, $offset, $limit);
    }

    /**
     * 计算有序集合中指定字典区间内成员数量
     *
     * @param string $key
     * @param int $min
     * @param int $max
     * @return int
     */
    public static function zLexCount($key, $min, $max)
    {
        return Redis::zLexCount($key, $min, $max);
    }

    /**
     * 移除有序集中的一个或多个成员，不存在的成员将被忽略
     *
     * @param string $key
     * @param array $memberArray
     * @return int
     */
    public function zRem($key, array $memberArray)
    {
        return Redis::zRem($key, ...$memberArray);
    }

    /**
     * 移除有序集合中给定的字典区间的所有成员
     *
     * @param string $key
     * @param int $min
     * @param int $max
     * @return array mixed
     */
    public function zRemRangeByLex($key, $min, $max)
    {
        return Redis::zRemRangeByLex($key, $min, $max);
    }

    /**
     * 返回一个或多个有序集的并集，并将该结果集储存到 Output 中
     *
     * @param string $Output
     * @param array $ZSetKeys
     * @param array $Weights
     * @param string $aggregateFunction SUM|MIN|MAX
     * @return int
     */
    public function zUnionStore($Output, $ZSetKeys, array $Weights = null, $aggregateFunction = 'SUM')
    {
        return Redis::zUnionStore($Output, $ZSetKeys, $Weights, $aggregateFunction);
    }

    /**
     * 返回一个或多个有序集的交集，并将该交集(结果集)储存到 Output
     *
     * @param string $Output
     * @param array $ZSetKeys
     * @param array $Weights
     * @param string $aggregateFunction
     * @return int
     */
    public function zInterStore($Output, $ZSetKeys, array $Weights = null, $aggregateFunction = 'SUM')
    {
        return Redis::zInterStore($Output, $ZSetKeys, $Weights, $aggregateFunction);
    }



    /**
     * 将有序集合中指定成员的分数加上增量increment（如果不是有序集合，则返回false）
     *
     * @param $key
     * @param int|float $increment 分数增量
     * @param $member
     * @return float|bool 返回成员member改变后的分数值
     * RedisHelper::zIncrBy('zset-test', 2.5, 'haveyb')
     *
     * 如果指定的member存在，则会改变该member的分数值，增量为$increment
     * 如果指定的member不存在，则会在有序集合中添加此member，分数值是设置的$increment
     */
    public static function zIncrBy($key, $increment, $member)
    {
        return Redis::zIncrBy($key, $increment, $member);
    }

    /**
     * 返回有序集合指定成员的排名（可正向排序或反向排序，排序结果是从0开始）
     *
     * @param $key
     * @param $member
     * @param bool $isRec 排序规则 是否反向排序，默认否，即默认按score正向从小到大排序
     * @return mixed
     * RedisHelper::zRank('zSet-test', 'Maria', true)
     */
    public static function zRank($key, $member, $isRec = false)
    {
        return $isRec ? Redis::zRevRank($key, $member) : Redis::zRank($key, $member);
    }

    /**
     * 移除有序集中，指定排名(rank)区间内的所有成员（行记录）
     *
     * @param string $key
     * @param int $start
     * @param int $end
     * @return  int
     */
    public static function zRemRangeByRank($key, $start, $end)
    {
        return Redis::zRemRangeByRank($key, $start, $end);
    }


    #------------------------------------ 哈希 hash  ---------------------------------------------

    /**
     * 为哈希表中的字段赋值
     *
     * @param string $hashTableName 哈希表名
     * @param string $hashKey 哈希字段名
     * @param string|int $value 哈希值
     * @param bool $isCover 指定如果字段已经存在，是否覆盖，默认覆盖
     *
     * @return int
     * RedisHelper::hSet('hash-test', 'manager', 'haveyb');
     * RedisHelper::hSet('hash-test', 'domain', 'https://www.haveyb.com');
     */
    public static function hSet($hashTableName, $hashKey, $value, $isCover = true)
    {
        return $isCover
            ? Redis::hSet($hashTableName, $hashKey, $value)
            : Redis::hSetNx($hashTableName, $hashKey, $value)
            ;
    }

    /**
     * 批量将 多个field-value 设置到哈希表中
     *
     * @param $hashTableName
     * @param $keyAndValueArray
     * @return mixed
     * RedisHelper::hMSet('hash-test', ['tel' => 1311234567, 'email' => 'haveyb@163.com'])
     */
    public function hMSet($hashTableName, $keyAndValueArray)
    {
        return Redis::hMSet($hashTableName, $keyAndValueArray);
    }

    /**
     * 返回哈希表中指定字段的值
     *
     * @param $hashTableName
     * @param $hashKey
     * @return string
     * RedisHelper::hGet('hash-test', 'domain')
     */
    public static function hGet($hashTableName, $hashKey)
    {
        return Redis::hGet($hashTableName, $hashKey);
    }

    /**
     * 返回哈希表中多个给定字段的值
     *
     * @param $hashTableName
     * @param array $hashKeyArray
     * @return array
     *
     */
    public static function hMGet($hashTableName, array $hashKeyArray)
    {
        return Redis::hMGet($hashTableName, $hashKeyArray);
    }

    /**
     * 返回整个哈希表中（字段为键，值为值的数组）
     *
     * @param $hashTableName
     * @return array
     *
     */
    public static function hGetAll($hashTableName)
    {
        return Redis::hGetAll($hashTableName);
    }


    /**
     * 返回哈希表中元素的数量
     *
     * @param $hashTableName
     * @return mixed
     * RedisHelper::hLen('hash-test')
     */
    public static function hLen($hashTableName)
    {
        return Redis::hLen($hashTableName);
    }

    /**
     * 检测哈希表是否存在指定字段
     *
     * @param string $hashTableName 哈希表名
     * @param string $hashKey 字段名
     * @return bool
     */
    public static function hExists($hashTableName, $hashKey)
    {
        return Redis::hExists($hashTableName, $hashKey);
    }

    /**
     * 删除哈希表 key 中的一个或多个指定字段 （不存在的字段将被忽略）
     *
     * @param $hashTableName
     * @param $hashKeyArray
     * @return int
     * RedisHelper::hDel('hash-test', ['email', 'position', 'tel'])
     */
    public static function hDel($hashTableName, array $hashKeyArray)
    {
        return Redis::hDel($hashTableName, ...$hashKeyArray);
    }

    /**
     * 返回指定hash表中的所有hash键
     *
     * @param $key
     * @return array
     * RedisHelper::hSet('hash-test', 'manager', 'haveyb');
     * RedisHelper::hSet('hash-test', 'domain', 'https://www.haveyb.com');
     * RedisHelper::hKeys('hash-test'); // return array(2) { [0]=> string(6) "domain" [1]=> string(7) "manager" }
     */
    public static function hKeys($hashTableName)
    {
        return Redis::hKeys($hashTableName);
    }

    /**
     * 返回指定哈希表中的所有值
     *
     * @param $hashTableName
     * @return array
     * RedisHelper::hVals('hash-test')
     */
    public static function hVals($hashTableName)
    {
        return Redis::hVals($hashTableName);
    }

    /**
     * 为哈希表中的字段值加或减掉指定数值（被增量的值必须是int，否则返回false）
     * 如果被增量的值不是int，则返回false
     * 自动取整，如果增量值5.5，则增量5；如果增量值是-5.5，则增量为-5
     *
     * @param $hashTableName
     * @param $hashKey
     * @param $increment
     * @return int|bool
     * RedisHelper::hSet('hash-test', 'ranking', 10)
     * RedisHelper::hIncrBy('hash-test', 'ranking', 2)
     * RedisHelper::hIncrBy('hash-test', 'ranking', -5)
     */
    public static function hIncrBy($hashTableName, $hashKey, $increment)
    {
        return Redis::hIncrBy($hashTableName, $hashKey, $increment);
    }

    /**
     * 为哈希表中的字段值加上指定浮点数增量值 (功能与hIncrBy类似，区别在于该命令可递增或递减小数，并且不会自动取整)
     *
     * @param $hashTableName
     * @param $hashKey
     * @param $increment
     * @return mixed
     */
    public static function hIncrByFloat($hashTableName, $hashKey, $increment)
    {
        return Redis::hIncrByFloat($hashTableName, $hashKey, $increment);
    }


    #------------------------------------ 位 bit ---------------------------------

    /**
     * 对key所储存的字符串值，设置或清除指定偏移量上的位(bit)
     *
     * @param $key
     * @param $offset
     * @param $value
     * @return mixed
     */
    public static function setBit($key, $offset, $value)
    {
        return Redis::setBit($key, $offset, $value);
    }

    /**
     * 多个键的按位操作
     *
     * @param string $operation
     * @param string $retKey
     * @param array $keyArray
     * @return int
     *
     * RedisHelper::set('bit1', '1'); // 11 0001
     * RedisHelper::set('bit2', '2'); // 11 0010
     *
     * RedisHelper::bitOp('AND', 'bit', 'bit1', 'bit2'); // bit = 110000
     * RedisHelper::bitOp('OR',  'bit', 'bit1', 'bit2'); // bit = 110011
     * RedisHelper::bitOp('NOT', 'bit', 'bit1', 'bit2'); // bit = 110011
     * RedisHelper::bitOp('XOR', 'bit', 'bit1', 'bit2'); // bit = 11
     */
    public static function bitOp($operation, $retKey, $keyArray)
    {
        return Redis::bitOp($operation, $retKey, ...$keyArray);
    }

    /**
     * 返回字符串里面第一个被设置为1或者0的bit位
     *
     * @param string $key
     * @param int $bit
     * @param int $start
     * @param int $end
     * @return int
     */
    public function bitpos($key, $bit, $start = 0, $end = null)
    {
        return Redis::bitOp($key, $bit, $start, $end);
    }

    /**
     * 返回字符串中的位数
     *
     * @param $key
     * @author cyf
     */
    public function bitCount($key)
    {
        return Redis::bitCount($key);
    }

    #--------------------------------- 系统及主从相关 ----------------------------------------

    /**
     * 返回所有redis主节点
     *
     * @return mixed
     */
    public static function _masters()
    {
        return Redis::_masters();
    }

    /**
     * 进入和退出事务模式
     *
     * @param int $mode
     */
    public static function multi($mode = RedisHelper::MULTI)
    {
        return Redis::multi($mode);
    }

    /**
     * 执行
     *
     * @return mixed
     */
    public static function exec()
    {
        return Redis::exec();
    }

    /**
     * 用于监视一个(或多个) key ，如果在事务执行之前这个(或这些) key 被其他命令所改动，那么事务将被打断
     *
     * @param $key
     */
    public static function watch($key)
    {
        return Redis::watch($key);
    }

    /**
     * 取消 WATCH 命令对所有 key 的监视
     */
    public static function unwatch()
    {
        return Redis::unwatch();
    }

    /**
     * 执行一个同步保存操作，将当前Redis实例的所有数据快照以文件的形式保存到硬盘
     *
     * @return bool
     */
    public static function save()
    {
        return Redis::save();
    }

    /**
     * 在后台异步保存当前数据库的数据到磁盘
     *
     * @return bool
     */
    public static function bgsave()
    {
        return Redis::bgsave();
    }

    /**
     * 清空当前数据库中的所有key
     *
     * @return bool
     */
    public static function flushDB()
    {
        return Redis::flushDB();
    }

    /**
     * 清空整个 Redis 服务器的数据(删除所有数据库的所有key)
     *
     * @return bool
     */
    public static function flushAll()
    {
        return Redis::flushAll();
    }

    /**
     * 返回当前数据库的key的数量
     *
     * @return int
     */
    public static function dbSize()
    {
        return Redis::dbSize();
    }

    /**
     * 异步执行一个 AOF（AppendOnly File） 文件重写操作。重写会创建一个当前 AOF 文件的体积优化版本
     *
     */
    public static function bgrewriteaof()
    {
        return Redis::bgrewriteaof();
    }

    /**
     * 返回最近一次 Redis 成功将数据保存到磁盘上的时间，以 UNIX 时间戳格式表示
     *
     * @param $nodeParams
     * @return int timestamp
     * RedisHelper::lastSave()
     */
    public static function lastSave()
    {
        return Redis::lastSave();
    }

    /**
     * 以一种易于理解和阅读的格式，返回关于 Redis 服务器的各种信息和统计数值
     *
     * @return array
     * RedisHelper::info();
     */
    public static function info()
    {
        return Redis::info();
    }

    /**
     * 查看主从实例所属的角色，角色有 master, slave, sentinel
     *
     */
    public static function role()
    {
        return Redis::role();
    }

    /**
     * 从当前数据库中随机返回一个 key
     *
     * @return string
     */
    public static function randomKey()
    {
        return Redis::randomKey();
    }

    /**
     * 返回当前服务器时间
     *
     * @return array
     * 返回值是一个数组，其中有两个元素，第一个表示当前时间戳，第二个表示当前这一秒钟已经逝去的微秒数
     */
    public static function time()
    {
        return Redis::time();
    }

    /**
     * 使用客户端向Redis 服务器发送一个PING，如果服务器运作正常的话，会返回一个PONG
     * 通常用于测试与服务器的连接是否仍然生效，或者用于测量延迟值
     *
     */
    public static function ping()
    {
        return Redis::ping();
    }

    /**
     * 打印给定的字符串
     *
     * @param $nodeParams
     * @param $msg
     */
    public static function echo($message)
    {
        return Redis::echo($message);
    }

    /**
     * 返回所有的Redis命令的详细信息，以数组形式展示
     *
     */
    public static function command()
    {
        // 因为Laravel框架对这个命令进行了重写，所以我们换一种方式，不用laravel自带的门面类redis，而改为自己实例化
        $redis = new \Redis();
        $redis->connect('localhost',6379);
        // $redis->auth('');
        return $redis->command();
    }

    /**
     * 执行任意命令
     *
     * @param array $explodedCommand 将要执行的命令分割开，比如要执行 set age 20，则传过来参数 ['set', 'age', 20]
     *
     * RedisHelper::rawCommand(['set', 'age', 30]);
     * RedisHelper::rawCommand(['get', 'age']); // return 30
     */
    public static function rawCommand(array $explodedCommand)
    {
        return Redis::rawCommand(...$explodedCommand);
    }

    /**
     * Executes cluster command
     *
     * @param string|array $nodeParams 节点key 或 [host,port]
     * @param string $command Required command to send to the server
     * @param mixed $arguments Optional variable amount of arguments to send to the server
     * @return mixed
     * @author cyf
     */
    public static function cluster($nodeParams, $command, $arguments)
    {
        return Redis::cluster($nodeParams, $command, $arguments);
    }

    /**
     * Allows you to get information of the cluster client
     *
     * @param string|array $nodeParams 节点key 或 [host,port]
     * @param $subCmd String  which can be: 'LIST', 'KILL', 'GETNAME', or 'SETNAME'
     * @param $args String optional arguments
     * @return mixed
     */
    public static function client($nodeParams, $subCmd, $args)
    {
        return Redis::client($nodeParams, $subCmd, $args);
    }

    /**
     * Get or Set the redis config keys【】
     *
     * @param string|array $nodeParams
     * @param string $operation GET` or `SET`
     * @param string $key for `SET`, glob-pattern for `GET`. See http://redis.io/commands/config-get for examples
     * @param string $value optional string (only for `SET`)
     * @return array
     */
    public static function config($nodeParams, $operation, $key, $value)
    {
        return Redis::config($nodeParams, $operation, $key, $value);
    }

    /**
     *查看订阅与发布系统状态，它由数个不同格式的子命令组成
     *
     * @param string|array $nodeParams
     * @param string $keyword
     * @param string|array $argument
     * @return  array|int
     */
    public static function pubsub($nodeParams, $keyword, $argument)
    {
        return Redis::pubsub($nodeParams, $keyword, $argument);
    }

    /**
     * 执行Redis SCRIPT命令在脚本子系统上执行各种操作
     * 相当于 SCRIPT LOAD、SCRIPT FLUSH、SCRIPT KILL、SCRIPT EXISTS 命令
     *
     * @param string|array $nodeParams
     * @param string $command   load | flush | kill | exists
     * @param string $script
     *
     * RedisHelper::script(['127.0.0.1',6379], 'load', $script);
     * RedisHelper::script(['127.0.0.1',6379], 'flush');
     * RedisHelper::script(['127.0.0.1',6379], 'kill');
     * RedisHelper::script(['127.0.0.1',6379], 'exists', $script1, [$script2, $script3, ...]);
     *
     * SCRIPT LOAD will return the SHA1 hash of the passed script on success, and FALSE on failure.
     * SCRIPT FLUSH should always return TRUE
     * SCRIPT KILL will return true if a script was able to be killed and false if not
     * SCRIPT EXISTS will return an array with TRUE or FALSE for each passed script
     */
    public static function script($nodeParams, $command, $script)
    {
        return Redis::script($nodeParams, $command, $script);
    }

    /**
     * This function is used in order to read and reset the Redis slow queries log.
     *
     * @param   string | array $nodeParams key or [host,port]
     * @param   string         $command
     * @param   mixed          $argument
     *
     * @link  https://redis.io/commands/slowlog
     */
    public static function slowLog($nodeParams, $command, $argument)
    {
        return Redis::slowLog($nodeParams, $command, $argument);
    }



    #-------------------------------- pf HyperLogLog ----------------------------------------

    /**
     * 将所有元素参数添加到 HyperLogLog 数据结构中
     *
     * @param string $key
     * @param array $elements
     * @return bool
     * RedisHelper::pfAdd('yy', [1 , 2, 3])
     */
    public static function pfAdd($key, array $elements)
    {
        return Redis::pfAdd($key, $elements);
    }

    /**
     * 返回给定 HyperLogLog 的基数估算值
     *
     * @param string|array $key
     * @return int
     * RedisHelper::pfAdd('yy', [4, 5, 69]);
     * RedisHelper::pfAdd('xx', ['hello', 'love', 'young', 666]);
     * RedisHelper::pfCount('xx');
     * RedisHelper::pfCount(['xx', 'yy']);
     */
    public static function pfCount($key)
    {
        return Redis::pfCount($key);
    }

    /**
     * 将多个 HyperLogLog 合并为一个 HyperLogLog
     * 合并后的 HyperLogLog 的基数估算值是通过对所有 给定 HyperLogLog 进行并集计算得出的
     *
     * @param string $destKey
     * @param array $sourceKeys
     * @return bool
     * RedisHelper::pfMerge('zz', ['xx', 'yy']);
     */
    public static function pfMerge($destKey, array $sourceKeys)
    {
        return Redis::pfMerge($destKey, $sourceKeys);
    }

    #-------------------------- 地理位置 GEO 模块 --------------------------

    /**
     * 将指定的地理空间位置（纬度、经度、名称）添加到指定的key中
     *
     * @param string $key
     * @param float  $longitude
     * @param float  $latitude
     * @param string $member
     *
     * @link  https://redis.io/commands/geoadd
     */
    public static function geoAdd($key, $longitude, $latitude, $member)
    {
        return Redis::geoAdd($key, $longitude, $latitude, $member);
    }

    /**
     * 将地理空间索引的成员作为标准geohash字符串返回
     *
     * @param string $key
     * @param array $memberArray
     */
    public static function geohash($key, array $memberArray)
    {
        return Redis::geohash($key, ...$memberArray);
    }

    /**
     * 返回地理空间索引成员的经度和纬度
     *
     * @param string $key
     * @param array $memberArray
     */
    public static function geopos($key, array $memberArray)
    {
        return Redis::geopos($key, ...$memberArray);
    }

    /**
     *
     * 返回地理空间索引的两个成员之间的距离
     *
     * @param    string $key
     * @param    string $member1
     * @param    string $member2
     * @param    string $unit The unit must be one of the following, and defaults to meters:
     *                        m for meters.
     *                        km for kilometers.
     *                        mi for miles.
     *                        ft for feet.
     *
     * @link https://redis.io/commands/geoadd
     */
    public static function geoDist($key, $member1, $member2, $unit = 'm') { }

    /**
     * 查询表示地理空间索引的排序集，以获取与点的给定最大距离匹配的成员
     *
     * @param    string $key
     * @param    float  $longitude
     * @param    float  $latitude
     * @param    float  $radius
     * @param    string $radiusUnit String can be: "m" for meters; "km" for kilometers , "mi" for miles, or "ft" for feet.
     * @param    array  $options
     *
     * @link  https://redis.io/commands/georadius
     */
    public static function geoRadius($key, $longitude, $latitude, $radius, $radiusUnit, array $options)
    {
        return Redis::geoRadius($key, $longitude, $latitude, $radius, $radiusUnit, $options);
    }

    /**
     * 查询表示地理空间索引的排序集，以获取与给定的成员最大距离匹配的成员
     *
     * @see geoRadius
     *
     * @param string $key
     * @param string $member
     * @param float  $radius
     * @param string $radiusUnit
     * @param array  $options
     */
    public function geoRadiusByMember($key, $member, $radius, $radiusUnit, array $options)
    {
        return Redis::geoRadiusByMember($key, $member, $radius, $radiusUnit, $options);
    }




}