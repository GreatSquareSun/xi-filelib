<?php

/**
 * This file is part of the Xi Filelib package.
 *
 * For copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Xi\Filelib\Plugin;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Xi\Collections\Collection\ArrayCollection;
use Xi\Filelib\Event\PluginEvent;
use Xi\Filelib\Events;
use Xi\Filelib\FileLibrary;
use Xi\Filelib\InvalidArgumentException;

class PluginManager
{
    /**
     * @var FileLibrary
     */
    private $filelib;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var Plugin[]
     */
    private $plugins = array();

    public function __construct(FileLibrary $filelib)
    {
        $this->filelib = $filelib;
        $this->eventDispatcher = $filelib->getEventDispatcher();
    }

    /**
     * @return ArrayCollection
     */
    public function getPlugins()
    {
        return ArrayCollection::create($this->plugins);
    }

    /**
     * @param string $name
     * @return Plugin
     * @throws InvalidArgumentException
     */
    public function getPlugin($name)
    {
        if (!isset($this->plugins[$name])) {
            throw new InvalidArgumentException(
                sprintf("Plugin with the name '%s' does not exist", $name)
            );
        }

        return $this->plugins[$name];
    }


    /**
     * @param Plugin $plugin
     * @param array $profiles Profiles to add to, empty array to add to all profiles
     * @param string $name
     * @return PluginManager
     */
    public function addPlugin(Plugin $plugin, $profiles = array(), $name = null)
    {
        if (!$name) {
            $name = $this->generatePluginName($plugin);
        }

        if (isset($this->plugins[$name])) {
            throw new InvalidArgumentException(
                sprintf("Plugin with the name '%s' already exists", $name)
            );
        }

        $this->plugins[$name] = $plugin;
        $this->setResolverFunction($plugin, $profiles);

        $this->eventDispatcher->addSubscriber($plugin);
        $event = new PluginEvent($plugin, $this->filelib);
        $this->eventDispatcher->dispatch($event, Events::PLUGIN_AFTER_ADD);

        return $this;
    }

    /**
     * @param Plugin $plugin
     * @param array $profiles
     */
    private function setResolverFunction(Plugin $plugin, $profiles)
    {
        if (!$profiles) {
            $resolverFunc = function ($profile) {
                return true;
            };
        } else {
            $resolverFunc = function ($profile) use ($profiles) {
                return (bool)in_array($profile, $profiles);
            };
        }

        $plugin->setBelongsToProfileResolver($resolverFunc);
    }

    /**
     * @param Plugin $plugin
     * @return string
     */
    private function generatePluginName(Plugin $plugin)
    {
        $generatedName = get_class($plugin) . '___' . (count($this->plugins) + 1);
        $generatedName = str_replace('\\', '___', $generatedName);
        return $generatedName;
    }
}
