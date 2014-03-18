<?php
/**
 * \TechDivision\WebServer\Modules\PhpProcessThread
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 *
 * PHP version 5
 *
 * @category   Webserver
 * @package    TechDivision_WebServer
 * @subpackage Modules
 * @author     Johann Zelger <jz@techdivision.com>
 * @copyright  2014 TechDivision GmbH <info@techdivision.com>
 * @license    http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 * @link       https://github.com/techdivision/TechDivision_WebServer
 */

namespace TechDivision\WebServer\Modules;

/**
 * Class PhpProcessThread
 *
 * @category   Webserver
 * @package    TechDivision_WebServer
 * @subpackage Modules
 * @author     Johann Zelger <jz@techdivision.com>
 * @copyright  2014 TechDivision GmbH <info@techdivision.com>
 * @license    http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 * @link       https://github.com/techdivision/TechDivision_WebServer
 */
class PhpProcessThread extends \Thread
{
    /**
     * Hold's the headers as array
     *
     * @var array
     */
    public $headers;

    /**
     * Hold's the output buffer generated by process run
     *
     * @var string
     */
    public $outputBuffer;

    /**
     * Hold's last error information as array
     *
     * @var array
     */
    public $lastError;

    /**
     * Constructs the process
     *
     * @param string                                     $scriptFilename The script filename to execute
     * @param \TechDivision\WebServer\Modules\PhpGlobals $globals        The globals instance
     */
    public function __construct($scriptFilename, PhpGlobals $globals)
    {
        $this->globals = $globals;
        $this->scriptFilename = $scriptFilename;
    }

    /**
     * Run's the process
     *
     * @return void
     */
    public function run()
    {
        // init globals to local var
        $globals = $this->globals;
        // register shutdown handler
        register_shutdown_function(array(&$this, "shutdown"));
        // start output buffering
        ob_start();
        // set globals
        $_SERVER = $globals->server;
        $_REQUEST = $globals->request;
        $_POST = $globals->post;
        $_GET = $globals->get;
        $_COOKIE = $globals->cookie;
        //$_FILES = $globals->files;

        // change dir to be in real php process context
        chdir(dirname($this->scriptFilename));
        // reset headers sent
        appserver_set_headers_sent(false);
        // require script filename
        require $this->scriptFilename;
        // save last error
        $this->lastError = error_get_last();
    }

    /**
     * Implements shutdown logic
     *
     * @return void
     */
    public function shutdown()
    {
        // save last error
        $this->lastError = error_get_last();
        // get php output buffer
        if (strlen($outputBuffer = ob_get_clean()) === 0) {
            if ($this->lastError['type'] == 1) {
                $errorMessage = 'PHP Fatal error: ' . $this->lastError['message'] .
                    ' in ' . $this->lastError['file'] . ' on line ' . $this->lastError['line'];
            }
            $outputBuffer = $errorMessage;
        }
        // set headers set by script inclusion
        $this->headers = appserver_get_headers(true);
        // set output buffer set by script inclusion
        $this->outputBuffer = $outputBuffer;
    }

    /**
     * Return's the output buffer
     *
     * @return string
     */
    public function getOutputBuffer()
    {
        return $this->outputBuffer;
    }

    /**
     * Return's the headers array
     *
     * @return array
     */
    public function getHeaders()
    {
        return $this->headers;
    }

    /**
     * Return's last error informations as array got from function error_get_last()
     *
     * @return array
     * @see error_get_last()
     */
    public function getLastError()
    {
        return $this->lastError;
    }
}