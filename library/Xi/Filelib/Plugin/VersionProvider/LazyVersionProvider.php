<?php

/**
 * This file is part of the Xi Filelib package.
 *
 * For copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Xi\Filelib\Plugin\VersionProvider;

use Xi\Filelib\Event\FileEvent;
use Xi\Filelib\Event\VersionProviderEvent;
use Xi\Filelib\File\File;
use Xi\Filelib\Version;

/**
 * Lazy version provider
 *
 * @author pekkis
 */
abstract class LazyVersionProvider extends VersionProvider
{
    /**
     * @var bool
     */
    private $lazyMode = false;

    /**
     * @param bool $enabled
     */
    public function enableLazyMode($enabled = true)
    {
        $this->lazyMode = $enabled;
    }

    /**
     * @return bool
     */
    public function lazyModeEnabled()
    {
        return $this->lazyMode;
    }

    /**
     * @param FileEvent $event
     */
    public function onAfterUpload(FileEvent $event)
    {
        if ($this->lazyModeEnabled()) {
            return;
        }
        parent::onAfterUpload($event);
    }

    public function provideVersion(File $file, Version $version)
    {
        $version = $this->ensureValidVersion($version);

        $versionable = $this->getApplicableVersionable($file);
        $versionable->addVersion($version);
        list(, $tmp) = $this->createTemporaryVersion($file, $version);
        $this->storage->storeVersion($versionable, $version, $tmp);

        unlink($tmp);

        $this->fileRepository->update($file);

        $event = new VersionProviderEvent($this, $file, array($version));
        $this->eventDispatcher->dispatch($event, Events::VERSIONS_PROVIDED);
    }

    public function createTemporaryVersion(File $file, Version $version)
    {
        return $this->doCreateTemporaryVersion($file, $version);
    }

    abstract protected function doCreateTemporaryVersion(File $file, Version $version);
}
