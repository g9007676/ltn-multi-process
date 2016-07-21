<?php
class Loader
{
    protected static function autoload($class)
    {
        if (class_exists($class, false) or interface_exists($class, false)) {
            return false;
        }
		$class = strtolower($class . '.php');
		$class = str_replace('\\', '/', $class);
        $paths = explode(PATH_SEPARATOR, get_include_path());
        foreach ($paths as $path) {
            $path = rtrim($path, '/');
            if (file_exists($path . '/' . $class)) {
                require $class;
                return true;
            }
        }
        return false;
    }

    public static function registerAutoload()
    {
        spl_autoload_register(array('Loader', 'autoload'));
    }
}

call_user_func(function () {
    set_include_path(implode(PATH_SEPARATOR, array(
        get_include_path(),
		__DIR__ . "/pns",
		__DIR__ . "/threads"
    )));
});

Loader::registerAutoload();
