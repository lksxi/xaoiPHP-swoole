<?php
namespace xaoi;

class container{

	protected static $instance;

	protected $instances = [];

	protected $bind = [];

	//获取当前容器的实例（单例）
	static function init()
    {
		static::$instance = new static;
    }

	//获取当前容器的实例（单例）
	static function getInstance()
    {
        return static::$instance;
    }

	//设置当前容器的实例
	static function setInstance($instance): void
    {
        static::$instance = $instance;
    }

	function has(string $abstract): bool
    {

        return isset($this->bind[$abstract]) || isset($this->instances[$abstract]);
    }

	function exists(string $abstract): bool
    {

        return isset($this->instances[$abstract]);
    }

	public function make(string $abstract, array $vars = [], bool $newInstance = false)
    {
		if (isset($this->instances[$abstract]) && !$newInstance) {
			return $this->instances[$abstract];
		}

		if (isset($this->bind[$abstract]) && $this->bind[$abstract] instanceof \Closure) {
			$object = call_user_func_array($this->bind[$abstract], $vars);
		} elseif(isset($this->bind[$abstract])) {
			$object = $this->invokeClass($this->bind[$abstract], $vars);
		}else{
			$_k = str_replace('/','\\',url_path(str_replace(['\\','::'],['/','/'],$abstract)));
			if($_k[0] !== '\\')$_k = '\\'.$_k;
			$object = $this->invokeClass($_k, $vars);
		}

		if (!$newInstance) {
			$this->instances[$abstract] = $object;
		}

		return $object;
    }

    public function bind($abstract, $concrete = null)
    {
        if (is_array($abstract)) {
            foreach ($abstract as $key => $val) {
                $this->bind($key, $val);
            }
        } elseif ($concrete instanceof \Closure) {
            $this->bind[$abstract] = $concrete;
        } elseif (is_object($concrete) || is_array($concrete)) {
			$this->instances[$abstract] = $concrete;
        } elseif (is_null($concrete)) {
            if(isset($this->instances[$abstract]))unset($this->instances[$abstract]);
        } else {
            $this->bind[$abstract] = $concrete;
        }

        return $this;
    }

    function invokeClass(string $class, array $vars = [])
    {
        try {
            $reflect = new \ReflectionClass($class);
        } catch (\ReflectionException $e) {
			/*$d = debug_backtrace();
			foreach($d as $v){
				if(empty($v['class']))
				_echo([$v['file'],$v['function'],$v['line']]);
				else
				_echo([$v['class'],$v['function']]);
			}*/
			_exit($class.' does not exist');
        }

		$constructor = $reflect->getConstructor();
		$args = $constructor ? $this->bindParams($constructor, $vars) : [];

        return $reflect->newInstanceArgs($args);
    }

    protected function bindParams(\ReflectionFunctionAbstract $reflect, array $vars = []): array
    {
        if ($reflect->getNumberOfParameters() == 0) {
            return [];
        }

        // 判断数组类型 数字数组时按顺序绑定参数
        reset($vars);
        $type   = key($vars) === 0 ? 1 : 0;
        $params = $reflect->getParameters();
        $args   = [];

        foreach ($params as $param) {
            $name      = $param->getName();
            $class     = $param->getClass();

            if ($class) {
                $args[] = $this->getObjectParam($class->getName(), $vars);
            } elseif (1 == $type && !empty($vars)) {
                $args[] = array_shift($vars);
            } elseif (0 == $type && isset($vars[$name])) {
                $args[] = $vars[$name];
            } elseif ($param->isDefaultValueAvailable()) {
                $args[] = $param->getDefaultValue();
            } else {
                _exit('method param miss:' . $name);
            }
        }

        return $args;
    }

    protected function getObjectParam(string $className, array &$vars)
    {
        $array = $vars;
        $value = array_shift($array);

        if ($value instanceof $className) {
            $result = $value;
            array_shift($vars);
        } else {
            $result = $this->make($className);
        }

        return $result;
    }
}