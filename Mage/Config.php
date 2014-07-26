<?php
/*
 * This file is part of the Magallanes package.
*
* (c) Andrés Montañez <andres@andresmontanez.com>
*
* For the full copyright and license information, please view the LICENSE
* file that was distributed with this source code.
*/

namespace Mage;

use Mage\Config\ConfigNotFoundException;
use Mage\Config\RequiredConfigNotFoundException;
use Mage\Console;
use Mage\Yaml\Exception\RuntimeException;
use Mage\Yaml\Yaml;
use Exception;

/**
 * Magallanes Configuration
 *
 * @author Andrés Montañez <andres@andresmontanez.com>
 */
class Config
{
	/**
	 * Arguments loaded
	 * @var array
	 */
    private $arguments = array();

    /**
     * Parameters loaded
     * @var array
     */
    private $parameters = array();

    /**
     * Environment
     * @var string|boolean
     */
    private $environment = false;

    /**
     * The current Host
     * @var string
     */
    private $host = null;

    /**
     * Custom Configuration for the current Host
     * @var array
     */
    private $hostConfig = array();

    /**
     * The Release ID
     * @var integer
     */
    private $releaseId = null;

    /**
     * Magallanes Global and Environment configuration
     */
    private $generalConfig = array();
    private $environmentConfig = array();

    /**
     * Parse the Command Line options
     * @param $arguments
     */
    protected function parse($arguments)
    {
    	foreach ($arguments as $argument) {
    		if (preg_match('/to:[\w]+/i', $argument)) {
    			$this->environment = str_replace('to:', '', $argument);

    		} else if (preg_match('/--[\w]+/i', $argument)) {
    			$optionValue = explode('=', substr($argument, 2));
    			if (count($optionValue) == 1) {
    				$this->parameters[$optionValue[0]] = true;
    			} else if (count($optionValue) == 2) {
    				if (strtolower($optionValue[1]) == 'true') {
    					$this->parameters[$optionValue[0]] = true;
    				} else if (strtolower($optionValue[1]) == 'false') {
    					$this->parameters[$optionValue[0]] = false;
    				} else {
    					$this->parameters[$optionValue[0]] = $optionValue[1];
    				}
    			}
    		} else {
    			$this->arguments[] = $argument;
    		}
    	}
    }

    /**
     * Initializes the General Configuration
     */
    protected function initGeneral()
    {
        try {
            $this->generalConfig =  $this->loadGeneral(getcwd() . '/.mage/config/general.yml');
        } catch (ConfigNotFoundException $e) {
            // normal situation
        }
    }

    /**
     * Load general config from the given file
     *
     * @param $filePath
     *
     * @return array
     * @throws Config\ConfigNotFoundException
     */
    protected function loadGeneral($filePath){
        return $this->parseConfigFile($filePath);
    }


    /**
     * Obviously this method is a HACK.  It was refactored from ::loadEnvironment()
     * TODO Please put it to SCM functionality.
     *
     * @param array $settings
     *
     * @return array
     */
    protected function updateSCMTempDir(array $settings)
    {
        // Create temporal directory for clone
        if (isset($settings['deployment']['source']) && is_array($settings['deployment']['source'])) {
            if (trim($settings['deployment']['source']['temporal']) == '') {
                $settings['deployment']['source']['temporal'] = sys_get_temp_dir();
            }
            $settings['deployment']['source']['temporal']
                = rtrim($settings['deployment']['source']['temporal'], '/') . '/' . md5(microtime()) . '/';
        }

        return $settings;
    }
    /**
     * Loads the Environment configuration
     * @param $filePath string
     *
     * @throws Exception
     * @return boolean
     */
    protected function loadEnvironment($filePath)
    {

        $settings = $this->parseConfigFile($filePath);

        //this is a HACK in the old code - no time to remove it now, so I factored it out in own method
        $settings = $this->updateSCMTempDir($settings);

        return $settings;

    }

    /**
     * Initializes the Environment configuration
     *
     * @throws Exception
     * @return boolean
     */
    protected function initEnvironment()
    {
    	$environment = $this->getEnvironment();

        $configFilePath = getcwd() . '/.mage/config/environment/' . $environment . '.yml';

        $parameters = $this->getParameters();

        if (empty($environment) && (!empty($parameters) || !$this->isRunInSpecialMode($parameters))) {
        		throw new RuntimeException('Environment is not set');
        }

        try {
            $this->environmentConfig =  $this->loadEnvironment($configFilePath);
        } catch (ConfigNotFoundException $e) {
            throw new RequiredConfigNotFoundException("Not found required config $configFilePath for environment $environment", 0 , $e);
        }

    }

    /**
     *
     * @param array $parameters
     * @return boolean
     */
    protected function isRunInSpecialMode(array $parameters)
    {
        if(empty($parameters))
            return true;
        foreach($parameters as $parameter)
        {
            if(isset(Console::$paramsNotRequiringEnvironment[$parameter]))
            {
                return true;
            }
        }

        return false;
    }
    /**
     * Load the Configuration and parses the Arguments
     *
     * @param array $arguments
     */
    public function load($arguments)
    {
        $this->parse($arguments);
        $this->initGeneral();
        $this->initEnvironment();
    }

    /**
     * Reloads the configuration
     */
    public function reload()
    {
    	$this->initGeneral();
    	$this->initEnvironment();
    }

    /**
     * Return the invocation argument based on a position
     * 0 = Invoked Command Name
     *
     * @param integer $position
     * @return mixed
     */
    public function getArgument($position = 0)
    {
        if (isset($this->arguments[$position])) {
            return $this->arguments[$position];
        } else {
            return false;
        }
    }

    /**
     * Returns all the invocation arguments
     *
     * @return array
     */
    public function getArguments()
    {
        return $this->arguments;
    }

    /**
     * Return the a parameter
     *
     * @param string $name
     * @param mixed $default
     * @param array $extraParameters
     * @return mixed
     */
    public function getParameter($name, $default = null, $extraParameters = array())
    {
        if (isset($this->parameters[$name])) {
            return $this->parameters[$name];
        } else if (isset($extraParameters[$name])) {
            return $extraParameters[$name];
        } else {
            return $default;
        }
    }

    /**
     * Returns all the invocation arguments
     *
     * @return array
     */
    public function getParameters()
    {
        return $this->parameters;
    }

    /**
     * Adds (or replaces) a parameter
     * @param string $name
     * @param mixed $value
     */
    public function addParameter($name, $value = true)
    {
    	$this->parameters[$name] = $value;
    }

    /**
     * Returns the Current environment
     *
     * @return mixed
     */
    public function getEnvironment()
    {
        return $this->environment;
    }

    /**
     * Get the Tasks to execute
     *
     * @param string $stage
     * @return array
     */
    public function getTasks($stage = 'deploy')
    {
    	if ($stage == 'deploy') {
    		$configStage = 'on-deploy';
    	} else {
    		$configStage = $stage;
    	}

        $tasks = array();
        $config = $this->getEnvironmentOption('tasks', array());

        // Host Config
        if (is_array($this->hostConfig) && isset($this->hostConfig['tasks'])) {
        	if (isset($this->hostConfig['tasks'][$configStage])) {
        		$config[$configStage] = $this->hostConfig['tasks'][$configStage];
        	}
        }

        if (isset($config[$configStage])) {
            $tasksData = ($config[$configStage] ? (array) $config[$configStage] : array());
            foreach ($tasksData as $taskData) {
                if (is_array($taskData)) {
                    $tasks[] = array(
                        'name' => key($taskData),
                        'parameters' => current($taskData),
                    );
                } else {
                    $tasks[] = $taskData;
                }
            }
        }

        return $tasks;
    }

    /**
     * Get the current Hosts to deploy
     *
     * @return array
     */
    public function getHosts()
    {
        $hosts = array();

        $envConfig = $this->getEnvironmentConfig();
        if (isset($envConfig['hosts'])) {
            if (is_array($envConfig['hosts'])) {
                $hosts = (array) $envConfig['hosts'];
            } else if (is_string($envConfig['hosts']) && file_exists($envConfig['hosts']) && is_readable($envConfig['hosts'])) {
                $fileContent = fopen($envConfig['hosts'], 'r');
                while (($host = fgets($fileContent)) == true) {
                    $host = trim($host);
                    if ($host != '') {
                        $hosts[] = $host;
                    }
                }
            }
        }

        return $hosts;
    }

    /**
     * Set the current host
     *
     * @param string $host
     * @return \Mage\Config
     */
    public function setHost($host)
    {
        $this->host = $host;
        return $this;
    }

    /**
     * Set the host specific configuration
     *
     * @param array $hostConfig
     * @return \Mage\Config
     */
    public function setHostConfig($hostConfig = null)
    {
    	$this->hostConfig = $hostConfig;
    	return $this;
    }

    /**
     * Get the current host name
     *
     * @return string
     */
    public function getHostName()
    {
        $info = explode(':', $this->host);
        return $info[0];
    }

    /**
     * Get the current Host Port
     *
     * @return integer
     */
    public function getHostPort()
    {
        $info = explode(':', $this->host);
        $info[] = $this->deployment('port', '22');
        return $info[1];
    }

    /**
     * Get the general Host Identity File Option
     *
     * @return string
     */
    public function getHostIdentityFileOption()
    {
        return $this->deployment('identity-file') ? ('-i ' . $this->deployment('identity-file') . ' ') : '';
    }

    /**
     * Get the current Host
     *
     * @return string
     */
    public function getHost()
    {
        return $this->host;
    }

    /**
     * Gets General Configuration
     *
     * @param string $option
     * @param string $default
     * @return mixed
     */
    public function general($option, $default = false)
    {
        $config = $this->getGeneralConfig();
        if (isset($config[$option])) {
            if (is_array($default) && ($config[$option] == '')) {
                return $default;
            } else {
                return $config[$option];
            }
        } else {
            return $default;
        }
    }

    /**
     * Gets Environments Full Configuration
     *
     * @param string $option
     * @param string $default
     * @return mixed
     */
    public function environmentConfig($option, $default = false)
    {
    	return $this->getEnvironmentOption($option, $default);
    }

    /**
     * Get deployment configuration
     *
     * @param string $option
     * @param string $default
     * @return string
     */
    public function deployment($option, $default = false)
    {
    	// Host Config
    	if (is_array($this->hostConfig) && isset($this->hostConfig['deployment'])) {
    		if (isset($this->hostConfig['deployment'][$option])) {
    			return $this->hostConfig['deployment'][$option];
    		}
    	}

    	// Global Config
        $config = $this->getEnvironmentOption('deployment', array());
        if (isset($config[$option])) {
            if (is_array($default) && ($config[$option] == '')) {
                return $default;
            } else {
                return $config[$option];
            }
        } else {
            return $default;
        }
    }

    /**
     * Returns Releasing Options
     *
     * @param string $option
     * @param string $default
     * @return mixed
     */
    public function release($option, $default = false)
    {
    	// Host Config
    	if (is_array($this->hostConfig) && isset($this->hostConfig['releases'])) {
    		if (isset($this->hostConfig['releases'][$option])) {
    			return $this->hostConfig['releases'][$option];
    		}
    	}

        $config = $this->getEnvironmentOption('releases', array());
        if (isset($config[$option])) {
            if (is_array($default) && ($config[$option] == '')) {
                return $default;
            } else {
                return $config[$option];
            }
        } else {
            return $default;
        }
    }

    /**
     * Set From Deployment Path
     *
     * @param string $from
     * @return \Mage\Config
     */
    public function setFrom($from)
    {
        $envConfig = $this->getEnvironmentConfig();
        $envConfig['deployment']['from'] = $from;
        return $this;
    }

    /**
     * Sets the Current Release ID
     *
     * @param integer $id
     * @return \Mage\Config
     */
    public function setReleaseId($id)
    {
        $this->releaseId = $id;
        return $this;
    }

    /**
     * Gets the Current Release ID
     *
     * @return integer
     */
    public function getReleaseId()
    {
        return $this->releaseId;
    }

    /**
     * Get Environment root option
     *
     * @param string $option
     * @param mixed $default
     * @return mixed
     */
    protected function getEnvironmentOption($option, $default = array())
    {
        $config = $this->getEnvironmentConfig();
        if (isset($config[$option])) {
            return $config[$option];
        } else {
            return $default;
        }
    }

    /**
     * Utility methods. TODO To be extracted into own Class
     */
    public function parseConfigFile($filePath)
    {
        if(!file_exists($filePath))
        {
            throw new ConfigNotFoundException("Cannot find the file at path $filePath");
        }

        return $this->parseConfigText(file_get_contents($filePath));
    }
    public function parseConfigText($input)
    {
        return Yaml::parse($input);
    }

    /**
     * @return array
     */
    protected function getGeneralConfig()
    {
        return $this->generalConfig;
    }

    /**
     * @return array
     */
    protected function getEnvironmentConfig()
    {
        return $this->environmentConfig;
    }

}
