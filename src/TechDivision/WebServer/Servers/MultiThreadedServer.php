<?php
/**
 * \TechDivision\WebServer\Servers\MultiThreadedServer
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
 * @subpackage Servers
 * @author     Johann Zelger <jz@techdivision.com>
 * @copyright  2014 TechDivision GmbH <info@techdivision.com>
 * @license    http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 * @link       https://github.com/techdivision/TechDivision_WebServer
 */

namespace TechDivision\WebServer\Servers;

use TechDivision\WebServer\Interfaces\ServerConfigurationInterface;
use TechDivision\WebServer\Interfaces\ServerContextInterface;
use TechDivision\WebServer\Interfaces\ServerInterface;
use TechDivision\WebServer\Interfaces\ConfigInterface;
use TechDivision\WebServer\Exceptions\ModuleNotFoundException;
use TechDivision\WebServer\Exceptions\ConnectionHandlerNotFoundException;


/**
 * Class MultiThreadedServer
 *
 * @category   Webserver
 * @package    TechDivision_WebServer
 * @subpackage Servers
 * @author     Johann Zelger <jz@techdivision.com>
 * @copyright  2014 TechDivision GmbH <info@techdivision.com>
 * @license    http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 * @link       https://github.com/techdivision/TechDivision_WebServer
 */
class MultiThreadedServer extends \Thread implements ServerInterface
{

    /**
     * Hold's the modules pre initiated
     *
     * @var array
     */
    protected $modules;

    /**
     * Hold's the connection Handlers pre initiated
     *
     * @var array
     */
    protected $connectionHandlers;

    /**
     * Hold's the server context instance
     *
     * @var \TechDivision\WebServer\Interfaces\ServerContextInterface The server context instance
     */
    protected $serverContext;

    /**
     * Constructs the server instance
     *
     * @param \TechDivision\WebServer\Interfaces\ServerContextInterface $serverContext The server context instance
     */
    public function __construct(ServerContextInterface $serverContext)
    {
        // set context
        $this->serverContext = $serverContext;
        // start server thread
        $this->start();
    }

    /**
     * Return's the config instance
     *
     * @return \TechDivision\WebServer\Interfaces\ServerContextInterface
     */
    public function getServerContext()
    {
        return $this->serverContext;
    }

    /**
     * Return's the modules as array
     *
     * @return array
     */
    protected function getModules()
    {
        return $this->modules;
    }

    /**
     * Return's connection handlers as array
     *
     * @return array
     */
    protected function getConnectionHandlers()
    {
        return $this->connectionHandlers;
    }

    /**
     * Start's the server's worker as defined in configuration
     *
     * @return void
     *
     * @throws \TechDivision\WebServer\Exceptions\ModuleNotFoundException
     * @throws \TechDivision\WebServer\Exceptions\ConnectionHandlerNotFoundException
     */
    public function run()
    {
        // setup autoloader
        require WEBSERVER_AUTOLOADER;

        // init server context
        $serverContext = $this->getServerContext();

        // init config var for shorter calls
        $serverConfig = $serverContext->getServerConfig();

        // get class names
        $socketType = $serverConfig->getSocketType();
        $workerType = $serverConfig->getWorkerType();

        // init stream context for server connection
        $streamContext = stream_context_create();
        // check if ssl server config
        if ($serverConfig->getTransport() === 'ssl') {
            stream_context_set_option($streamContext, 'ssl', 'local_cert', WEBSERVER_BASEDIR . $serverConfig->getCertPath());
            stream_context_set_option($streamContext, 'ssl', 'passphrase', $serverConfig->getPassphrase());
            stream_context_set_option($streamContext, 'ssl', 'allow_self_signed', true);
            stream_context_set_option($streamContext, 'ssl', 'verify_peer', false);
        }

        // setup server bound on local adress
        $serverConnection = $socketType::getServerInstance(
            $serverConfig->getTransport() . '://' . $serverConfig->getAddress() . ':' . $serverConfig->getPort(),
            STREAM_SERVER_BIND | STREAM_SERVER_LISTEN,
            $streamContext
        );

        // setup and start workers
        for ($i=1; $i <= $serverConfig->getWorkerNumber(); ++$i) {
            $workers[$i] = new $workerType(
                $serverConnection->getConnectionResource(),
                $serverContext
            );
        }

        // wait until all workers finished
        foreach ($workers as $worker) {
            $worker->join();
        }
    }
}
