<?php

/**
 * This file is part of the Xi Filelib package.
 *
 * For copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Xi\Filelib\File;

use Symfony\Component\EventDispatcher\EventDispatcher;
use Xi\Filelib\Command\CommandDefinition;
use Xi\Filelib\Command\CommanderClient;
use Xi\Filelib\FileLibrary;
use Xi\Filelib\Folder\FolderRepository;
use Xi\Filelib\AbstractRepository;
use Xi\Filelib\FilelibException;
use Xi\Filelib\InvalidArgumentException;
use Xi\Filelib\File\File;
use Xi\Filelib\Folder\Folder;
use Xi\Filelib\File\Upload\FileUpload;
use Xi\Filelib\Plugin\VersionProvider\VersionProvider;
use Xi\Filelib\Event\FileProfileEvent;
use Xi\Filelib\Backend\Finder\FileFinder;
use ArrayIterator;
use Xi\Filelib\Events;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * File operator
 *
 * @author pekkis
 *
 */
class FileRepository extends AbstractRepository
{
    const COMMAND_UPLOAD = 'Xi\Filelib\File\Command\UploadFileCommand';
    const COMMAND_AFTERUPLOAD = 'Xi\Filelib\File\Command\AfterUploadFileCommand';
    const COMMAND_UPDATE = 'Xi\Filelib\File\Command\UpdateFileCommand';
    const COMMAND_DELETE = 'Xi\Filelib\File\Command\DeleteFileCommand';
    const COMMAND_COPY = 'Xi\Filelib\File\Command\CopyFileCommand';

    /**
     * @var array Profiles
     */
    private $profiles = array();

    /**
     * @var FolderRepository
     */
    private $folderRepository;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    public function attachTo(FileLibrary $filelib)
    {
        parent::attachTo($filelib);
        $this->folderRepository = $filelib->getFolderRepository();
        $this->eventDispatcher = $filelib->getEventDispatcher();
    }

    /**
     * @return array
     */
    public function getCommandDefinitions()
    {
        return array(
            new CommandDefinition(
                self::COMMAND_UPLOAD
            ),
            new CommandDefinition(
                self::COMMAND_AFTERUPLOAD
            ),
            new CommandDefinition(
                self::COMMAND_UPDATE
            ),
            new CommandDefinition(
                self::COMMAND_DELETE
            ),
            new CommandDefinition(
                self::COMMAND_COPY
            ),
        );
    }



    /**
     * Adds a file profile
     *
     * @param  FileProfile              $profile
     * @return FileLibrary
     * @throws InvalidArgumentException
     */
    public function addProfile(FileProfile $profile)
    {
        $identifier = $profile->getIdentifier();
        if (isset($this->profiles[$identifier])) {
            throw new InvalidArgumentException("Profile '{$identifier}' already exists");
        }
        $this->profiles[$identifier] = $profile;

        $this->eventDispatcher->addSubscriber($profile);

        $event = new FileProfileEvent($profile);
        $this->eventDispatcher->dispatch(Events::PROFILE_AFTER_ADD, $event);

        return $this;
    }

    /**
     * Returns a file profile
     *
     * @param  string                   $identifier File profile identifier
     * @throws InvalidArgumentException
     * @return FileProfile
     */
    public function getProfile($identifier)
    {
        if (!isset($this->profiles[$identifier])) {
            throw new InvalidArgumentException("File profile '{$identifier}' not found");
        }

        return $this->profiles[$identifier];
    }

    /**
     * Returns all file profiles
     *
     * @return FileProfile[] Array of file profiles
     */
    public function getProfiles()
    {
        return $this->profiles;
    }

    /**
     * Updates a file
     *
     * @param  File         $file
     * @return FileRepository
     */
    public function update(File $file)
    {
        return $this->commander
            ->createExecutable(self::COMMAND_UPDATE, array($file))
            ->execute();
    }

    /**
     * Finds a file
     *
     * @param  mixed      $id File id
     * @return File
     */
    public function find($id)
    {
        $file = $this->backend->findById($id, 'Xi\Filelib\File\File');

        if (!$file) {
            return false;
        }

        return $file;
    }

    /**
     * Finds file by filename in a folder
     *
     * @param Folder $folder
     * @param $filename
     * @return File
     */
    public function findByFilename(Folder $folder, $filename)
    {
        $file = $this->backend->findByFinder(
            new FileFinder(array('folder_id' => $folder->getId(), 'name' => $filename))
        )->current();

        if (!$file) {
            return false;
        }

        return $file;
    }

    /**
     * Finds and returns all files
     *
     * @return ArrayIterator
     */
    public function findAll()
    {
        $files = $this->backend->findByFinder(new FileFinder());

        return $files;
    }

    /**
     * Uploads a file
     *
     * @param  mixed            $upload Uploadable, path or object
     * @param  Folder           $folder
     * @return File
     * @throws FilelibException
     */
    public function upload($upload, Folder $folder = null, $profile = 'default')
    {
        if (!$upload instanceof FileUpload) {
            $upload = new FileUpload($upload);
        }

        if (!$folder) {
            $folder = $this->folderRepository->findRoot();
        }

        return $this->commander
            ->createExecutable(self::COMMAND_UPLOAD, array($upload, $folder, $profile))
            ->execute();
    }

    /**
     * Deletes a file
     *
     * @param File $file
     */
    public function delete(File $file)
    {
        return $this->commander
            ->createExecutable(self::COMMAND_DELETE, array($file))
            ->execute();
    }

    /**
     * Copies a file to folder
     *
     * @param File   $file
     * @param Folder $folder
     */
    public function copy(File $file, Folder $folder)
    {
        return $this->commander
            ->createExecutable(self::COMMAND_COPY, array($file, $folder))
            ->execute();
    }

    /**
     * Returns whether a file has a certain version
     *
     * @param  File    $file    File item
     * @param  string  $version Version
     * @return boolean
     */
    public function hasVersion(File $file, $version)
    {
        $profile = $this->getProfile($file->getProfile());
        return $profile->fileHasVersion($file, $version);
    }

    /**
     * Returns version provider for a file/version
     *
     * @param  File            $file    File item
     * @param  string          $version Version
     * @return VersionProvider Provider
     */
    public function getVersionProvider(File $file, $version)
    {
        $profile = $this->getProfile($file->getProfile());
        return $profile->getVersionProvider($file, $version);
    }
}
