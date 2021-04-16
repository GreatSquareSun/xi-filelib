<?php

namespace Xi\Filelib\Tests;

use Pekkis\TemporaryFileManager\TemporaryFileManager;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Xi\Filelib\Authorization\AuthorizationPlugin;
use Xi\Filelib\Backend\Adapter\BackendAdapter;
use Xi\Filelib\Backend\Cache\Cache;
use Xi\Filelib\Backend\Finder\FileFinder;
use Xi\Filelib\File\File;
use Xi\Filelib\File\Upload\FileUpload;
use Xi\Filelib\FileLibrary;
use Xi\Filelib\Plugin\RandomizeNamePlugin;
use Xi\Filelib\Profile\FileProfile;
use Xi\Filelib\Events;
use Xi\Filelib\Storage\Adapter\StorageAdapter;

class FileLibraryTest extends TestCase
{
    /**
     * @test
     */
    public function correctVersion()
    {
        $this->assertEquals('0.14.0-dev', FileLibrary::VERSION);
    }

    /**
     * @test
     */
    public function classShouldExist()
    {
        $this->assertTrue(class_exists('Xi\Filelib\FileLibrary'));
    }

    /**
     * @test
     */
    public function storageGetterShouldWork()
    {
        $filelib = new FileLibrary($this->getMockedStorageAdapter(), $this->getMockedBackendAdapter());
        $this->assertInstanceOf('Xi\Filelib\Storage\Storage', $filelib->getStorage());
    }

    /**
     * @test
     */
    public function platformGetterShouldWork()
    {
        $filelib = new FileLibrary($this->getMockedStorageAdapter(), $this->getMockedBackendAdapter());
        $this->assertInstanceOf('Xi\Filelib\Backend\Adapter\BackendAdapter', $filelib->getBackendAdapter());
    }

    /**
     * @test
     */
    public function backendGetterShouldWork()
    {
        $filelib = new FileLibrary($this->getMockedStorageAdapter(), $this->getMockedBackendAdapter());
        $this->assertInstanceOf('Xi\Filelib\Backend\Backend', $filelib->getBackend());
    }

    /**
     * @test
     */
    public function getsResourceRepository()
    {
        $filelib = new FileLibrary($this->getMockedStorageAdapter(), $this->getMockedBackendAdapter());
        $rere = $filelib->getResourceRepository();
        $this->assertInstanceOf('Xi\Filelib\Resource\ResourceRepository', $rere);
    }

    /**
     * @test
     */
    public function getFileRepositoryShouldWork()
    {
        $filelib = new FileLibrary($this->getMockedStorageAdapter(), $this->getMockedBackendAdapter());
        $fop = $filelib->getFileRepository();

        $this->assertInstanceOf('Xi\Filelib\File\FileRepository', $fop);
    }

    /**
     * @test
     */
    public function getFolderRepositoryShouldWork()
    {
        $filelib = new FileLibrary($this->getMockedStorageAdapter(), $this->getMockedBackendAdapter());
        $fop = $filelib->getFolderRepository();

        $this->assertInstanceOf('Xi\Filelib\Folder\FolderRepository', $fop);
    }

    /**
     * @test
     */
    public function addedProfileShouldBeReturned()
    {
        $filelib = new FileLibrary($this->getMockedStorageAdapter(), $this->getMockedBackendAdapter());

        $this->assertCount(1, $filelib->getProfiles());

        try {
            $profile = $filelib->getProfile('tussi');
            $this->fail('should have thrown exception');
        } catch (\InvalidArgumentException $e) {

            $p = new FileProfile('tussi', $this->getMockedLinker());

            $filelib->addProfile(
                $p
            );

            $this->assertSame($p, $filelib->getProfile('tussi'));
            $this->assertCount(2, $filelib->getProfiles());
        }
    }

    /**
     * @test
     */
    public function addPluginShouldFirePluginAddEventAndAddPluginAsSubscriber()
    {
        $ed = $this->getMockedEventDispatcher();
        $filelib = new FileLibrary($this->getMockedStorageAdapter(), $this->getMockedBackendAdapter(), $ed);

        $plugin = $this->getMockForAbstractClass('Xi\Filelib\Plugin\Plugin');

        $ed
            ->expects($this->once())
            ->method('dispatch')
            ->with(
                $this->isInstanceOf('Xi\Filelib\Event\PluginEvent'),
                Events::PLUGIN_AFTER_ADD,
            );

        $ed
            ->expects($this->once())
            ->method('addSubscriber')
            ->with($this->equalTo($plugin));

        $filelib->addPlugin($plugin);
    }

    /**
     * @test
     */
    public function addPluginDelegates()
    {
        $filelib = new FileLibrary($this->getMockedStorageAdapter(), $this->getMockedBackendAdapter());
        $plugin = new RandomizeNamePlugin();

        $this->assertSame($filelib, $filelib->addPlugin($plugin, array(), 'lusso'));

        $this->assertCount(1, $filelib->getPluginManager()->getPlugins());
    }


    /**
     * @test
     */
    public function getEventDispatcherShouldWork()
    {
        $ed = $this->getMockedEventDispatcher();
        $filelib = new FileLibrary($this->getMockedStorageAdapter(), $this->getMockedBackendAdapter(), $ed);
        $this->assertSame($ed, $filelib->getEventDispatcher());
    }

    /**
     * @test
     */
    public function uploadFileDelegates()
    {
        $filelib = $this->getMockedFilelib(array('getFileRepository'));
        $fop = $this->getMockedFileRepository();
        $filelib->expects($this->any())->method('getFileRepository')->will($this->returnValue($fop));

        $folder = $this->getMockedFolder();

        $fop
            ->expects($this->once())
            ->method('upload')
            ->with('lussutus', $folder, 'tussi')
            ->will($this->returnValue(File::create()));

        $ret = $filelib->uploadFile('lussutus', $folder, 'tussi');
        $this->assertEquals(File::create(), $ret);
    }

    /**
     * @test
     */
    public function findFileDelegates()
    {
        $filelib = $this->getMockedFilelib(array('getFileRepository'));
        $fop = $this->getMockedFileRepository();
        $filelib->expects($this->any())->method('getFileRepository')->will($this->returnValue($fop));

        $id = 'lussendorf';

        $fop
            ->expects($this->once())
            ->method('find')
            ->with($id)
            ->will($this->returnValue('xooxer'));

        $ret = $filelib->findFile($id);
        $this->assertSame('xooxer', $ret);
    }

    /**
     * @test
     */
    public function findFilesDelegates()
    {
        $filelib = $this->getMockedFilelib(array('getFileRepository'));
        $fop = $this->getMockedFileRepository();
        $filelib->expects($this->any())->method('getFileRepository')->will($this->returnValue($fop));

        $ids = array('lussendorf', 'lussenford');

        $fop
            ->expects($this->once())
            ->method('findMany')
            ->with($ids)
            ->will($this->returnValue('xooxer'));

        $ret = $filelib->findFiles($ids);
        $this->assertSame('xooxer', $ret);
    }

    /**
     * @test
     */
    public function findFilesByDelegates()
    {
        $filelib = $this->getMockedFilelib(array('getFileRepository'));
        $fop = $this->getMockedFileRepository();
        $filelib->expects($this->any())->method('getFileRepository')->will($this->returnValue($fop));

        $finder = new FileFinder();

        $fop
            ->expects($this->once())
            ->method('findBy')
            ->with($finder)
            ->will($this->returnValue('xooxer'));

        $ret = $filelib->findFilesBy($finder);
        $this->assertSame('xooxer', $ret);
    }

    /**
     * @test
     */
    public function createFolderByUrlDelegates()
    {
        $filelib = $this->getMockedFilelib(array('getFolderRepository', 'getFileRepository'));
        $fop = $this->getMockedFolderRepository();
        $filelib->expects($this->any())->method('getFolderRepository')->will($this->returnValue($fop));

        $url = '/tenhunen/imaiseppa/tappion/karvas/kalkki';

        $fop
            ->expects($this->once())
            ->method('createByUrl')
            ->with($url)
            ->will($this->returnValue('xooxer'));

        $ret = $filelib->createFolderByUrl($url);
        $this->assertEquals('xooxer', $ret);
    }


    /**
     * @test
     */
    public function cacheCanBeSet()
    {
        $filelib = new FileLibrary($this->getMockedStorageAdapter(), $this->getMockedBackendAdapter());
        $adapter = $this->getMockedCacheAdapter();
        $this->assertSame($filelib, $filelib->createCacheFromAdapter($adapter));
        $this->assertInstanceOf('Xi\Filelib\Backend\Cache\Cache', $filelib->getCache());
    }

    /**
     * @test
     */
    public function fileRepositoryCanBeSet()
    {
        $repo = $this->prophesize('Xi\Filelib\File\FileRepositoryInterface')->reveal();
        $filelib = new FileLibrary($this->getMockedStorageAdapter(), $this->getMockedBackendAdapter());

        $filelib->setFileRepository($repo);
        $this->assertSame($repo, $filelib->getFileRepository());

    }

    /**
     * @test
     */
    public function folderRepositoryCanBeSet()
    {
        $repo = $this->prophesize('Xi\Filelib\Folder\FolderRepositoryInterface')->reveal();
        $filelib = new FileLibrary($this->getMockedStorageAdapter(), $this->getMockedBackendAdapter());

        $filelib->setFolderRepository($repo);
        $this->assertSame($repo, $filelib->getFolderRepository());

    }

    /**
     * @test
     */
    public function resourceRepositoryCanBeSet()
    {
        $repo = $this->prophesize('Xi\Filelib\Resource\ResourceRepositoryInterface')->reveal();
        $filelib = new FileLibrary($this->getMockedStorageAdapter(), $this->getMockedBackendAdapter());

        $filelib->setResourceRepository($repo);
        $this->assertSame($repo, $filelib->getResourceRepository());
    }

    /**
     * @test
     */
    public function adaptersCanBeGivenLazily()
    {
        $storageAdapter = function() {
            return $this->getMockedStorageAdapter();
        };

        $backendAdapter = function() {
            return $this->getMockedBackendAdapter();
        };

        $filelib = new FileLibrary(
            $storageAdapter,
            $backendAdapter
        );

        $this->assertInstanceOf('Xi\Filelib\Storage\Storage', $filelib->getStorage());
        $this->assertInstanceOf('Xi\Filelib\Backend\Backend', $filelib->getBackend());
    }

    /**
     * @test
     */
    public function tempDirAcceptsDirectory()
    {
        $filelib = new FileLibrary(
            $this->prophesize(StorageAdapter::class)->reveal(),
            $this->prophesize(BackendAdapter::class)->reveal(),
            $this->prophesize(EventDispatcherInterface::class)->reveal(),
            sys_get_temp_dir()
        );

        $this->assertInstanceOf(TemporaryFileManager::class, $filelib->getTemporaryFileManager());
    }

    /**
     * @test
     */
    public function tempDirAcceptsObject()
    {
        $tfm = new TemporaryFileManager(sys_get_temp_dir());

        $filelib = new FileLibrary(
            $this->prophesize(StorageAdapter::class)->reveal(),
            $this->prophesize(BackendAdapter::class)->reveal(),
            $this->prophesize(EventDispatcherInterface::class)->reveal(),
            $tfm
        );

        $this->assertSame($tfm, $filelib->getTemporaryFileManager());
    }
}
