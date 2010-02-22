<?php
/* vim: set expandtab tabstop=4 shiftwidth=4 foldmethod=marker: */
/**
 * PEAR2_Exception
 *
 * PHP versions 4 and 5
 *
 * @category   pear
 * @package    PEAR2_Exception
 * @author     Tomas V. V. Cox <cox@idecnet.com>
 * @author     Hans Lellelid <hans@velum.net>
 * @author     Bertrand Mansion <bmansion@mamasam.com>
 * @author     Greg Beaver <cellog@php.net>
 * @copyright  1997-2007 The PHP Group
 * @license    http://opensource.org/licenses/bsd-license.php New BSD License
 * @link       http://pear.php.net/package/PEAR
 * @since      File available since Release 0.1.0
 */


/**
 * Base PEAR2_Exception Class
 *
 * 1) Features:
 *
 * - Nestable exceptions (throw new PEAR2_Exception($msg, $prev_exception))
 * - Definable triggers, shot when exceptions occur
 * - Added more context info available (like class, method or cause)
 * - cause can be a PEAR2_Exception or an array of mixed
 *   PEAR2_Exceptions or a \pear2\MultiErrors
 * - callbacks for specific exception classes and their children
 *
 * 2) Usage example
 *
 * <code>
 * namespace pear2;
 * class PEAR2_MyPackage_Exception extends Exception {}
 *
 * class Test
 * {
 *     function foo()
 *     {
 *         throw new PEAR2_MyPackage_Exception('Error Message', 4);
 *     }
 * }
 *
 * function myLogger($exception)
 * {
 *     echo 'Logger: ' . $exception->getMessage() . "\n";
 * }
 *
 * // each time a exception is thrown the 'myLogger' will be called
 * // (its use is completely optional)
 * Exception::addObserver('\pear2\myLogger');
 * $test = new Test;
 * try {
 *     $test->foo();
 * } catch (\Exception $e) {
 *     print $e;
 * }
 * </code>
 *
 * @category   pear
 * @package    PEAR
 * @author     Tomas V.V.Cox <cox@idecnet.com>
 * @author     Hans Lellelid <hans@velum.net>
 * @author     Bertrand Mansion <bmansion@mamasam.com>
 * @author     Greg Beaver <cellog@php.net>
 * @copyright  1997-2007 The PHP Group
 * @license    http://opensource.org/licenses/bsd-license.php New BSD License
 * @version    Release: @package_version@
 * @link       http://pear.php.net/package/PEAR
 * @since      Class available since Release 0.1.0
 *
 */
namespace pear2;
abstract class Exception extends \Exception
{
    static $htmlError = false;
    private static $_observers = array();
    private $_trace;

    /**
     * Supported signatures:
     *  - PEAR2_Exception(string $message);
     *  - PEAR2_Exception(string $message, int $code);
     *  - PEAR2_Exception(string $message, Exception $cause);
     *  - PEAR2_Exception(string $message, Exception $cause, int $code);
     *  - PEAR2_Exception(string $message, pear2\MultiErrors $cause);
     *  - PEAR2_Exception(string $message, pear2\MultiErrors $cause, int $code);
     * @param string exception message
     * @param int|Exception|pear2\MultiErrors|null exception cause
     * @param int|null exception code or null
     */
    public function __construct($message, $p2 = null, $p3 = null)
    {
        $code = $cause = null;
        if (is_int($p2)) {
            $code = $p2;
        } elseif (is_object($p2)) {
            if (!($p2 instanceof \Exception)) {
                throw new \Exception('exception cause must be Exception, or pear2\MultiErrors');
            }

            $code  = $p3;
            $cause = $p2;
        }

        if (!is_string($message)) {
            throw new \Exception('exception message must be a string, was ' . gettype($message));
        }

        parent::__construct($message, $code, $cause);
        $this->signal();
    }

    /**
     * @param mixed $callback  - A valid php callback, see php func is_callable()
     *                         - A PEAR2_Exception::OBSERVER_* constant
     *                         - An array(const PEAR2_Exception::OBSERVER_*,
     *                           mixed $options)
     * @param string $label    The name of the observer. Use this if you want
     *                         to remove it later with removeObserver()
     */
    public static function addObserver($callback, $label = 'default')
    {
        self::$_observers[$label] = $callback;
    }

    public static function removeObserver($label = 'default')
    {
        unset(self::$_observers[$label]);
    }

    private function signal()
    {
        foreach (self::$_observers as $func) {
            if (is_callable($func)) {
                call_user_func($func, $this);
            }
        }
    }

    /**
     * Function must be public to call on caused exceptions
     * @param array
     */
    public function getCauseMessage(array &$causes)
    {
        $trace = $this->getTraceSafe();
        $cause = array(
            'class'   => get_class($this),
            'message' => $this->message,
            'file'    => 'unknown',
            'line'    => 'unknown'
        );

        if (isset($trace[0]) && isset($trace[0]['file'])) {
            $cause['file'] = $trace[0]['file'];
            $cause['line'] = $trace[0]['line'];
        }

        $causes[] = $cause;
        if ($this->getPrevious() instanceof self) {
            $this->getPrevious()->getCauseMessage($causes);
        } elseif ($this->getPrevious() instanceof \pear2\MultiErrors) {
            foreach ($this->getPrevious() as $cause) {
                if ($cause instanceof self) {
                    $cause->getCauseMessage($causes);
                } elseif ($cause instanceof \Exception) {
                    $causes[] = array(
                        'class'   => get_class($cause),
                        'message' => $cause->getMessage(),
                        'file'    => $cause->getFile(),
                        'line'    => $cause->getLine()
                    );
                }
            }
        } elseif ($this->getPrevious() instanceof \Exception) {
            $causes[] = array(
                'class'   => get_class($this->getPrevious()),
                'message' => $this->getPrevious()->getMessage(),
                'file'    => $this->getPrevious()->getFile(),
                'line'    => $this->getPrevious()->getLine()
            );
        }
    }

    public function getTraceSafe()
    {
        if (!isset($this->_trace)) {
            $this->_trace = $this->getTrace();
            if (empty($this->_trace)) {
                $backtrace = debug_backtrace();
                $this->_trace = array($backtrace[count($backtrace)-1]);
            }
        }

        return $this->_trace;
    }

    public function getErrorClass()
    {
        $trace = $this->getTraceSafe();
        return $trace[0]['class'];
    }

    public function getErrorMethod()
    {
        $trace = $this->getTraceSafe();
        return $trace[0]['function'];
    }

    public function __toString()
    {
        if (self::$htmlError) {
            return $this->toHtml();
        }

        return $this->toText();
    }

    public function toHtml()
    {
        $trace = $this->getTraceSafe();
        $causes = array();
        $this->getCauseMessage($causes);
        $html =  '<table border="1" cellspacing="0">' . "\n";
        foreach ($causes as $i => $cause) {
            $html .= '<tr><td colspan="3" bgcolor="#ff9999">'
               . str_repeat('-', $i) . ' <b>' . $cause['class'] . '</b>: '
               . htmlspecialchars($cause['message']) . ' in <b>' . $cause['file'] . '</b> '
               . 'on line <b>' . $cause['line'] . '</b>'
               . "</td></tr>\n";
        }
        $html .= '<tr><td colspan="3" bgcolor="#aaaaaa" align="center"><b>Exception trace</b></td></tr>' . "\n"
               . '<tr><td align="center" bgcolor="#cccccc" width="20"><b>#</b></td>'
               . '<td align="center" bgcolor="#cccccc"><b>Function</b></td>'
               . '<td align="center" bgcolor="#cccccc"><b>Location</b></td></tr>' . "\n";

        foreach ($trace as $k => $v) {
            $html .= '<tr><td align="center">' . $k . '</td>'
                   . '<td>';
            if (!empty($v['class'])) {
                $html .= $v['class'] . $v['type'];
            }

            $html .= $v['function'];
            $args = array();
            if (!empty($v['args'])) {
                foreach ($v['args'] as $arg) {
                    if (is_null($arg)) $args[] = 'null';
                    elseif (is_array($arg)) $args[] = 'Array';
                    elseif (is_object($arg)) $args[] = 'Object('.get_class($arg).')';
                    elseif (is_bool($arg)) $args[] = $arg ? 'true' : 'false';
                    elseif (is_int($arg) || is_double($arg)) $args[] = $arg;
                    else {
                        $arg = (string)$arg;
                        $str = htmlspecialchars(substr($arg, 0, 16));
                        if (strlen($arg) > 16) $str .= '&hellip;';
                        $args[] = "'" . $str . "'";
                    }
                }
            }

            $html .= '(' . implode(', ',$args) . ')'
                   . '</td>'
                   . '<td>' . (isset($v['file']) ? $v['file'] : 'unknown')
                   . ':' . (isset($v['line']) ? $v['line'] : 'unknown')
                   . '</td></tr>' . "\n";
        }

        $html .= '<tr><td align="center">' . ($k+1) . '</td>'
               . '<td>{main}</td>'
               . '<td>&nbsp;</td></tr>' . "\n"
               . '</table>';
        return $html;
    }

    public function toText()
    {
        $causes = array();
        $this->getCauseMessage($causes);
        $causeMsg = '';
        foreach ($causes as $i => $cause) {
            $causeMsg .= str_repeat(' ', $i) . $cause['class'] . ': '
                   . $cause['message'] . ' in ' . $cause['file']
                   . ' on line ' . $cause['line'] . "\n";
        }

        return $causeMsg . $this->getTraceAsString();
    }
}