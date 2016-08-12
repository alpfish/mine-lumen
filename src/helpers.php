<?php

use Illuminate\Support\Str;
use Illuminate\Support\Arr;
use Illuminate\Container\Container;

if (! function_exists('app')) {
    /**
     * 应用容器
     *
     * @param  string $make
     * @param  array  $parameters
     *
     * @return mixed|\Mine\Application
     */
    function app($make = null, $parameters = [])
    {
        if (is_null($make)) {
            return Container::getInstance();
        }

        return Container::getInstance()->make($make, $parameters);
    }
}

if (! function_exists('api')) {
    /**
     * 应用容器
     *
     * @param  string $make
     * @param  array  $parameters
     *
     * @return mixed|\Mine\Application
     */
    function api($make = null, $parameters = [])
    {
        if (is_null($make)) {
            return Container::getInstance();
        }

        return Container::getInstance()->make($make, $parameters);
    }
}

if (! function_exists('request')) {
    /**
     * 获取HTTP客户端请求数据
     *
     * @param  string [$key]  要获取的参数键
     * @param  string [$default]  键不存在时返回的值
     *
     * @return mixed
     *
     * @e   .g
     * request('id');  ===  request()->id;  //返回同一个数据的两种用法
     * request('name.firstname');  //使用点语法
     * request()->only(['id', 'name']);   //返回多个数据
     * request()->has('pic');  //数据判断
     * request()->all();  //所有数据
     * request()->file('img');  //文件数据
     * request()->isPost();  //请求方式判断
     * ...
     *
     * @auth: AlpFish 2016/7/25 9:26
     */
    function request($key = null, $default = null)
    {
        if (is_null($key)) {
            return Me\Http\Request::getInstance();
        }
        return Me\Http\Request::getInstance()->input($key, $default);
    }
}

if (! function_exists('validate')) {
    /**
     * 数据验证器
     *
     * 1. 支持的验证规则有 required|mobile|email|min:|max:|numeric|integer|date|ip|url|activeUrl
     *
     * @param array $data  要验证的数据，$rules 有的数据必须有，$rules 没有的数据也可以有
     * @param array $rules 待验证的规则，规则名和参数必须正确
     * @param       array  [$msg]    验证失败信息，使用 ： 号声明对应规则验证失败信息，不使用 ：号则是对该条数据设置相同的失败信息
     *
     * @return Me\Validation\Validator
     *
     * @author AlpFish 2016/7/28 23:52
     */
    function validate($data, $rules, $msg = null)
    {
        return new Me\Validation\Validator($data, $rules, $msg);
    }
}

if (! function_exists('ab_path')) {
    /*--------------------------------------------------------------------------
     * 将目录或文件路径转化为项目绝对路径
     *--------------------------------------------------------------------------
     *
     * 1. 目录路径末尾自动添加 / ， 文件不会加
     * 2. $path 参数为空默认返回项目根目录路径
     * 3. 项目根目录路径默认服务器设置，可用配置文件 root_path 项重新设置
     *
     * @param string [$path] 路径
     *
     * @return string 格式化后的路径
     *
     * @author AlpFish 2016/7/25 10:50
     */
    function ab_path($path = null)
    {
        //获取根目录 & 统一分隔符
        $root = str_replace('\\', '/', realpath(__DIR__ . '/../../../../')) . '/';

        //返回项目根路径
        if (is_null($path))
            return $root;

        //统一分隔符
        $path = trim(str_replace('\\', '/', $path));

        //如果不是文件, 末尾加 /
        $array = explode('/', $path);
        $last = $array[ count($array) - 1 ];
        if (! empty($last))
            if (strpos($last, '.') === false)
                $path = $path[ strlen($path) - 1 ] === '/' ? $path : $path . '/';

        //子目录包含根目录
        if (strpos($path, $root) !== false) {
            return $path;
        }

        return $path[ 0 ] === '/' ? $root . substr($path, 1) : $root . $path;
    }
}

if (! function_exists('base_path')) {
    /**
     * 项目基本目录
     *
     * @param  string $path
     *
     * @return string
     */
    function base_path($path = '')
    {
        return app()->basePath() . ($path ? '/' . $path : $path);
    }
}

if (! function_exists('api_path')) {
    /**
     * 项目基本目录
     *
     * @param  string $path
     *
     * @return string
     */
    function api_path($path = '')
    {
        return app()->apiPath() . ($path ? '/' . $path : $path);
    }
}

if (! function_exists('data_get')) {
    /**
     * 从数组/对象中获取数据, 支持点语法
     * Get an item from an array or object using "dot" notation.
     *
     * @param  mixed        $target
     * @param  string|array $key
     * @param  mixed        $default
     *
     * @return mixed
     */
    function data_get($target, $key, $default = null)
    {
        if (is_null($key)) {
            return $target;
        }

        $key = is_array($key) ? $key : explode('.', $key);

        while (($segment = array_shift($key)) !== null){
            if ($segment === '*') {
                if ($target instanceof Illuminate\Support\Collection) {
                    $target = $target->all();
                } elseif (! is_array($target)){
                    return value($default);
                }

                $result = Arr::pluck($target, $key);

                return in_array('*', $key) ? Arr::collapse($result) : $result;
            }

            if (Arr::accessible($target) && Arr::exists($target, $segment)) {
                $target = $target[ $segment ];
            } elseif (is_object($target) && isset($target->{$segment})){
                $target = $target->{$segment};
            } else{
                return value($default);
            }
        }

        return $target;
    }
}

if (! function_exists('data_set')) {
    /**
     * （以覆盖的方式）为数组或对象填充数据, 支持点语法
     * Set an item on an array or object using dot notation.
     *
     * @param  mixed  $target
     * @param  string $key
     * @param  mixed  $value
     * @param  bool   $overwrite
     *
     * @return mixed
     */
    function data_set(&$target, $key, $value, $overwrite = true)
    {
        $segments = is_array($key) ? $key : explode('.', $key);

        if (($segment = array_shift($segments)) === '*') {
            if (! Arr::accessible($target)) {
                $target = [];
            }

            if ($segments) {
                foreach ($target as &$inner){
                    data_set($inner, $segments, $value, $overwrite);
                }
            } elseif ($overwrite){
                foreach ($target as &$inner){
                    $inner = $value;
                }
            }
        } elseif (Arr::accessible($target)){
            if ($segments) {
                if (! Arr::exists($target, $segment)) {
                    $target[ $segment ] = [];
                }

                data_set($target[ $segment ], $segments, $value, $overwrite);
            } elseif ($overwrite || ! Arr::exists($target, $segment)){
                $target[ $segment ] = $value;
            }
        } elseif (is_object($target)){
            if ($segments) {
                if (! isset($target->{$segment})) {
                    $target->{$segment} = [];
                }

                data_set($target->{$segment}, $segments, $value, $overwrite);
            } elseif ($overwrite || ! isset($target->{$segment})){
                $target->{$segment} = $value;
            }
        } else{
            $target = [];

            if ($segments) {
                data_set($target[ $segment ], $segments, $value, $overwrite);
            } elseif ($overwrite){
                $target[ $segment ] = $value;
            }
        }

        return $target;
    }
}

if (! function_exists('encrypt')) {
    /**
     * 加密
     *
     * @param  string $value
     *
     * @return string
     */
    function encrypt($value)
    {
        return app('encrypter')->encrypt($value);
    }
}

if (! function_exists('decrypt')) {
    /**
     * 解密
     *
     * @param  string $value
     *
     * @return string
     */
    function decrypt($value)
    {
        return app('encrypter')->decrypt($value);
    }
}

if (! function_exists('redirect')) {
    /**
     * Get an instance of the redirector.
     *
     * @param  string|null $to
     * @param  int         $status
     * @param  array       $headers
     * @param  bool        $secure
     *
     * @return \Mine\Http\Redirector|\Illuminate\Http\RedirectResponse
     */
    function redirect($to = null, $status = 302, $headers = [], $secure = null)
    {
        $redirector = new Mine\Http\Redirector(app());

        if (is_null($to)) {
            return $redirector;
        }

        return $redirector->to($to, $status, $headers, $secure);
    }
}

if (! function_exists('e')) {
    /**
     * 转换 HTML entities 特殊字符
     *
     * @param  string $value
     *
     * @return string
     */
    function e($value)
    {
        return htmlentities($value, ENT_QUOTES, 'UTF-8', false);
    }
}